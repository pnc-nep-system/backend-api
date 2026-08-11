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

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Permissions assigned directly to this individual user.
     * When present, they are authoritative over role-derived permissions
     * (see hasPermission).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName || $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return in_array($this->role, $roleNames, true) || $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->role === self::ROLE_NEP_ADMIN) {
            return true;
        }

        // Resolve all permission names once per request and cache in-memory.
        // This prevents repeated DB queries when multiple middleware/gates
        // check permissions on the same request.
        $allPermissions = $this->resolvePermissions();

        return $allPermissions->contains($permissionName);
    }

    /**
     * Resolve the full set of permission names for this user, cached for the
     * lifetime of the current request to avoid repeated DB round-trips.
     */
    protected function resolvePermissions(): \Illuminate\Support\Collection
    {
        $cacheKey = 'user_permissions_' . $this->id;

        if (\Illuminate\Support\Facades\Cache::store('array')->has($cacheKey)) {
            return \Illuminate\Support\Facades\Cache::store('array')->get($cacheKey);
        }

        // Individually-assigned permissions are authoritative.
        $direct = $this->permissions()->pluck('name');
        if ($direct->isNotEmpty()) {
            \Illuminate\Support\Facades\Cache::store('array')->put($cacheKey, $direct);
            return $direct;
        }

        // Fall back to role-derived permissions (assigned roles + primary role).
        $fromRoles = Role::query()
            ->whereIn('name', $this->roles()->pluck('name')->push($this->role)->unique())
            ->with('permissions:name')
            ->get()
            ->flatMap(fn($r) => $r->permissions->pluck('name'))
            ->unique()
            ->values();

        \Illuminate\Support\Facades\Cache::store('array')->put($cacheKey, $fromRoles);

        return $fromRoles;
    }

    public function syncLegacyRole(): void
    {
        $role = Role::query()->where('name', $this->role)->first();

        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }
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

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
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
            'role' => [$update ? 'sometimes' : 'required', Rule::in([
                self::ROLE_NEP_ADMIN,
                self::ROLE_NEP_COORDINATOR,
                self::ROLE_MEMBER_ORG,
            ])],
            'status' => ['sometimes', Rule::in([self::STATUS_ACTIVE, self::STATUS_INACTIVE])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
