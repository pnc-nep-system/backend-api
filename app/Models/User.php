<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\Rule;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    public const ROLE_NEP_ADMIN = 'nep_admin';
    public const ROLE_NEP_COORDINATOR = 'nep_coordinator';
    public const ROLE_MEMBER_ORG = 'member_org';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Keep the granular roles() pivot in sync with the `role` column so that
     * hasPermission()/hasRole() work for every user, not just ones that were
     * manually assigned a Role via the admin Role Management screen. `role`
     * can be any role name that exists in the `roles` table — not just the
     * three original system roles — since Role Management supports creating
     * custom roles and the Create/Edit User form lets admins pick any of them.
     *
     * Only the specific role the `role` column previously pointed to is
     * detached (not "all legacy roles"), so switching between two custom
     * roles correctly swaps permissions instead of accumulating both. Any
     * additional role an admin has granted through RoleManagementController
     * (i.e. one that was never the `role` column's value) is left untouched.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (! $user->wasRecentlyCreated && ! $user->wasChanged('role')) {
                return;
            }

            $currentRole = Role::where('name', $user->role)->first();

            // getOriginal() reflects the pre-save value here — finishSave()
            // fires the 'saved' event *before* syncOriginal() runs. On a
            // fresh INSERT this is naturally null (nothing to detach). Don't
            // gate this on wasRecentlyCreated: that flag is set to true on
            // insert and never reset back to false for the lifetime of the
            // in-memory instance, so a second update() on a just-created
            // object (common in the same request/test) would wrongly skip
            // the detach step and leak the old role's permissions.
            $previousRoleName = $user->getOriginal('role');
            if ($previousRoleName && $previousRoleName !== $user->role) {
                $previousRole = Role::where('name', $previousRoleName)->first();
                if ($previousRole && (! $currentRole || $previousRole->id !== $currentRole->id)) {
                    $user->roles()->detach($previousRole->id);
                }
            }

            if ($currentRole) {
                $user->roles()->syncWithoutDetaching([$currentRole->id]);
            }
        });
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isNepAdmin(): bool
    {
        return $this->role === self::ROLE_NEP_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * True when this user is an active nep_admin and no other active nep_admin
     * exists. Used to stop the last super admin from being demoted, deactivated,
     * or stripped of the nep_admin role — which would lock everyone out.
     */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->isNepAdmin() || ! $this->isActive()) {
            return false;
        }

        return ! static::query()
            ->where('role', self::ROLE_NEP_ADMIN)
            ->where('status', self::STATUS_ACTIVE)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    /**
     * All permission names granted to this user through any of its assigned
     * roles (legacy role-synced + any custom roles), de-duplicated.
     */
    public function effectivePermissions(): \Illuminate\Support\Collection
    {
        return $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Privilege-escalation guard: an actor may only grant a role/permission set
     * that is a subset of their own effective permissions. This is fully
     * dynamic — no role name is ever compared. nep_admin (or any future role
     * holding every permission) automatically satisfies this for any target,
     * since the diff against "every permission" is always empty.
     */
    public function canGrantRole(Role $role): bool
    {
        return $this->canGrantPermissionIds($role->permissions()->pluck('permissions.id')->all());
    }

    /**
     * Same guard, for the raw permission IDs being assigned directly to a role
     * (used when an actor creates/edits a role's permission set).
     *
     * @param array<int> $permissionIds
     */
    public function canGrantPermissionIds(array $permissionIds): bool
    {
        if (empty($permissionIds)) {
            return true;
        }

        $requestedNames = Permission::whereIn('id', $permissionIds)->pluck('name');

        return $requestedNames->diff($this->effectivePermissions())->isEmpty();
    }

    /**
     * @param bool $update Pass true when validating an update, so the
     *                     email uniqueness rule ignores the current user.
     * @param int|null $userId The id of the user being updated (for the email rule).
     */
    public static function validationRules(bool $update = false, ?int $userId = null): array
    {
        return [
            'organisation_id' => [$update ? 'sometimes' : 'nullable', 'exists:organisations,id'],
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [
                $update ? 'sometimes' : 'required',
                'email',
                'max:255',
                $update ? Rule::unique('users', 'email')->ignore($userId) : 'unique:users,email',
            ],
            'password' => [$update ? 'sometimes' : 'nullable', 'string', 'min:8'],
            // Dynamic: any role that exists in the `roles` table is a valid
            // assignment, not just the three original system roles — matches
            // whatever Role Management currently has defined.
            'role' => [$update ? 'sometimes' : 'required', 'string', Rule::exists('roles', 'name')],
            'status' => ['sometimes', Rule::in([self::STATUS_ACTIVE, self::STATUS_INACTIVE])],
        ];
    }
}