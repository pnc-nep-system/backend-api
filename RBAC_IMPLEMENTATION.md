# User Role Management and Permissions (RBAC) Implementation

## Overview

This document describes the Role-Based Access Control (RBAC) system implemented for the NEP (National Education Platform) system. Access is dynamic: the backend computes a user's **effective permissions** from all of their assigned roles, every protected route (across the whole app, not just the admin screens) is gated on a permission — not a hard-coded role name — and the frontend renders navigation/routes/buttons from that same permission list. Creating a new role, or changing what an existing role grants, changes what users can do immediately, with no code changes and no hard-coded `if (role === 'admin')` anywhere in the request path.

## Architecture

### Database Structure

The RBAC system uses four main database tables:

1. **roles** - Stores role definitions
   - `id` - Primary key
   - `name` - Unique role identifier (e.g., 'nep_admin')
   - `display_name` - Human-readable name
   - `description` - Role description
   - `is_system` - Boolean flag for system roles (cannot be deleted)

2. **permissions** - Stores permission definitions
   - `id` - Primary key
   - `name` - Unique permission identifier (e.g., 'users.view')
   - `display_name` - Human-readable name
   - `group` - Permission group (e.g., 'Users', 'Organisations')
   - `description` - Permission description

3. **permission_role** - Pivot table linking permissions to roles
   - `role_id` - Foreign key to roles
   - `permission_id` - Foreign key to permissions

4. **role_user** - Pivot table linking roles to users
   - `user_id` - Foreign key to users
   - `role_id` - Foreign key to roles

### Models

#### Role Model (`app/Models/Role.php`)
- `users()` - Many-to-many relationship with User model
- `permissions()` - Many-to-many relationship with Permission model
- `hasPermission(string $permissionName)` - Check if role has specific permission

#### Permission Model (`app/Models/Permission.php`)
- `roles()` - Many-to-many relationship with Role model

#### User Model (`app/Models/User.php`)
- `roles()` - Many-to-many relationship with Role model
- `hasRole(string $roleName)` - Check if user has specific role
- `hasAnyRole(array $roleNames)` - Check if user has any of the specified roles
- `hasPermission(string $permissionName)` - Check if user has specific permission through their roles
- `effectivePermissions()` - All permission names granted through any assigned role, de-duplicated — this is what `/user`, `/session`, and login return to the frontend
- `isLastActiveAdmin()` - True if this is the sole remaining active `nep_admin` (used to block self-lockout)
- `canGrantRole(Role $role)` / `canGrantPermissionIds(array $ids)` - Privilege-escalation guard: true only if every permission the role/set carries is already in the actor's own `effectivePermissions()`. Fully dynamic — never compares role names, and `nep_admin` (or any future role holding every permission) automatically passes since the diff against "everything" is always empty.

### Middleware

#### RoleMiddleware (`app/Http/Middleware/RoleMiddleware.php`)
- Checks if authenticated user has one of the specified roles
- Usage: `->middleware('role:nep_admin,nep_coordinator')`
- Returns 403 Forbidden if user doesn't have required role

#### PermissionMiddleware (`app/Http/Middleware/PermissionMiddleware.php`)
- Checks if authenticated user has one of the specified permissions
- Usage: `->middleware('permission:users.view,users.create')`
- Returns 403 Forbidden if user doesn't have required permission

## Default Roles and Permissions

### System Roles

1. **nep_admin** (NEP Administrator)
   - Full system access
   - All permissions assigned (`$admin->permissions()->sync(Permission::all())` — any permission added later is automatically included)
   - Cannot be deleted or modified

2. **nep_coordinator** (NEP Coordinator)
   - Organisation-level, staff-facing access
   - Permissions: `users.view`, `organisations.view`, `programmes.view`, `programmes.create`, `programmes.update`, `advisory.view`, `advisory.manage`, `advisory.deliver`, `dashboard.view`, `reports.view`, `reports.export`, `taxonomy.view`, `policy.view`, `policy.create`, `policy.update`, `map.view`, `map.export`

3. **member_org** (Member Organisation)
   - Basic access to assigned programmes
   - Permissions: `programmes.view`, `programmes.create`, `programmes.update`, `advisory.view`, `taxonomy.view`, `policy.view`

### Permission Groups

- **Users** - User management permissions
- **Organisations** - Organisation management permissions
- **Programmes** - Programme entry permissions
- **Advisory** - Advisory submission permissions (see the `advisory.view` vs `advisory.manage` split below — they are deliberately not interchangeable)
- **Policy** - Policy document library permissions
- **Map** - Map view/export permissions
- **Roles** - Role management permissions
- **Permissions** - Permission management permissions
- **Taxonomy** - Taxonomy management permissions
- **Dashboard** - Dashboard/oversight access permissions
- **Reports** - Report viewing and export permissions

### Why `advisory.view` and `advisory.manage` are separate permissions

`advisory.view` is narrow: viewing the one delivered advisory note for a
programme entry you already have access to (this is what `member_org` holds).
`advisory.manage` is the broad staff-only "Adviser workspace" — list every
submission across every organisation, create/update/deliver/parse/generate. If
these were the same permission, granting a member org the ability to view
their own note would also expose every other organisation's submissions. Keep
them separate when adding new roles.

### Keeping the legacy `role` column and the granular roles in sync

`users.role` (the single `nep_admin|nep_coordinator|member_org` enum) remains the
backbone for all existing business routes (programme entries, advisory, taxonomy,
etc.) — nothing about that changed. What was missing is that the granular
`roles()`/`permissions()` tables were never actually populated for real users, so
`hasPermission()` always returned `false`.

`User::booted()` now listens for `saved` and keeps the `role_user` pivot in sync
with the `role` column automatically: creating or updating a user's `role`
detaches any stale legacy-role pivot row and attaches the matching `Role` (looked
up by name). Any *additional*, non-legacy role granted through the Role
Management screen is left untouched. `RolePermissionSeeder` also runs a one-time,
idempotent backfill (`backfillUserRoles()`) so existing/seeded accounts (including
ones inserted via raw `DB::table()` calls, which bypass Eloquent events) get
synced too.

This is what makes `permission:` middleware — previously registered but dead code
— actually usable.

## API Endpoints

Every route below (and every other protected route in the app — see the full
audit table further down) is gated by the granular `permission:` middleware,
not `role:`. `admin/users/*`, `admin/roles/*`, and `admin/permissions/*` map
directly to `users.*`/`roles.*`/`permissions.*` — the concrete example this
feature is built around. Nothing hard-codes `nep_admin`/`nep_coordinator`/
`member_org` in a route definition anywhere anymore (the only remaining
`role:`-based route protection is the raw `role_id`/`permission_id` CRUD-safety
validation, and `RoleMiddlewareTest`, which intentionally tests the
`RoleMiddleware` component in isolation — it isn't used on any real app route).

### Role Management Endpoints

#### GET `/api/admin/roles`
List all roles with user counts and permissions

#### GET `/api/admin/roles/{role}`
Show specific role details with users and permissions

#### POST `/api/admin/roles`
Create a new role
```json
{
    "name": "custom_role",
    "display_name": "Custom Role",
    "description": "A custom role",
    "is_system": false,
    "permissions": [1, 2, 3]
}
```

#### PATCH `/api/admin/roles/{role}`
Update a role (system roles cannot be modified)
```json
{
    "display_name": "Updated Name",
    "permissions": [1, 2, 3]
}
```

#### DELETE `/api/admin/roles/{role}`
Delete a role (system roles cannot be deleted)

#### POST `/api/admin/roles/{role}/users/{user}`
Assign a role to a user

#### DELETE `/api/admin/roles/{role}/users/{user}`
Remove a role from a user

### Permission Management Endpoints

Gated by `permissions.view`/`permissions.create`/`permissions.update`/`permissions.delete`. Only `nep_admin` holds these by default, but any role granted them gets access.

#### GET `/api/admin/permissions`
List all permissions grouped by group

#### POST `/api/admin/permissions`
Create a new permission
```json
{
    "name": "custom.permission",
    "display_name": "Custom Permission",
    "group": "Custom",
    "description": "A custom permission"
}
```

#### PATCH `/api/admin/permissions/{permission}`
Update a permission

#### DELETE `/api/admin/permissions/{permission}`
Delete a permission

### User Management Endpoints

User management endpoints include role information in responses.

#### GET `/api/admin/users`
List all users with their roles
- Requires `users.view` — held by `nep_admin` and `nep_coordinator` (coordinator is read-only: they lack `users.create`/`users.update`)
- Returns users with loaded `roles` relationship

#### GET `/api/admin/users/{user}`
Show one user with roles, permissions, and `effective_permissions`
- Requires `users.view`

#### POST `/api/admin/users`
Create a new user
- Requires `users.create` — held by `nep_admin` only by default
- Assigns role specified in request; blocked by the escalation guard if the actor doesn't hold every permission the target role grants (see below)

#### PATCH `/api/admin/users/{user}`
Update a user
- Requires `users.update`
- Can update user role, but never their own (`422 "You cannot change your own role."`), and never in a way that would leave zero active `nep_admin` accounts

## Privilege-escalation guards

- **Cannot change your own role** — `UserManagementController::update()` rejects any request where the target user is the requester, if `role` is in the payload. Applies even to `nep_admin`.
- **Cannot grant more than you have** — `User::canGrantRole()`/`canGrantPermissionIds()` block: assigning a `role` to a new/existing user (`UserManagementController::store()`/`invite()`/`update()`), assigning a `Role` to a user via Role Management (`RoleManagementController::assignToUser()`), and minting/editing a `Role`'s permission set (`RoleManagementController::store()`/`update()`) — all check that every permission involved is already in the actor's own `effectivePermissions()`. `nep_admin` always passes trivially.
- **Cannot lock out the last admin** — see "Last active admin protection" below.

## Usage Examples

### Protecting Routes with Permissions (the real mechanism)

This is how every protected route in the app is actually written now:

```php
// In routes/api.php — this is the real admin/users group
Route::prefix('admin/users')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->middleware('permission:users.view');
    Route::post('/', [UserManagementController::class, 'store'])->middleware('permission:users.create');
});

// Multiple permissions (user needs at least one — OR semantics, same as RoleMiddleware used to have)
Route::middleware('permission:users.view,users.create')->get('/users', [UserController::class, 'index']);
```

### `role:` middleware (legacy — do not use for new routes)

`RoleMiddleware` still exists and is registered (`role` alias in
`bootstrap/app.php`), but no real application route uses it anymore — it's
retained for `RoleMiddlewareTest` (which tests the middleware component in
isolation) and as a documented example of what NOT to do going forward:

```php
// Don't do this for new routes — hard-codes role names, ignores custom roles
Route::middleware('role:nep_admin,nep_coordinator')->group(function () { ... });
```

### Checking Permissions in Controllers

```php
public function someMethod(Request $request)
{
    $user = $request->user();
    
    if ($user->hasPermission('users.create')) {
        // User can create users
    }
    
    if ($user->hasRole('nep_admin')) {
        // User is admin
    }
    
    if ($user->hasAnyRole(['nep_admin', 'nep_coordinator'])) {
        // User is admin or coordinator
    }
}
```

### Assigning Roles to Users

```php
// Via API
POST /api/admin/roles/{roleId}/users/{userId}

// Via code
$role = Role::where('name', 'nep_coordinator')->first();
$user = User::find(1);
$user->roles()->attach($role);

// Or sync without detaching
$user->roles()->syncWithoutDetaching([$role->id]);
```

### Managing Permissions

```php
// Create a permission
$permission = Permission::create([
    'name' => 'reports.export',
    'display_name' => 'Export Reports',
    'group' => 'Reports',
    'description' => 'Export reports to PDF/Excel'
]);

// Assign to role
$role = Role::where('name', 'nep_coordinator')->first();
$role->permissions()->sync([$permission->id]);

// Check if role has permission
if ($role->hasPermission('reports.export')) {
    // Role can export reports
}
```

## Seeding Default Data

Run the following command to seed default roles and permissions:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

This will:
1. Create all default permissions
2. Create the three system roles (nep_admin, nep_coordinator, member_org)
3. Assign appropriate permissions to each role

## API Security Audit

Every route in `routes/api.php` (excluding public routes: `/login`,
`/forgot-password`, `/reset-password`, and the token-scoped adviser file
download) requires `auth:sanctum`. Routes with a required permission below are
additionally gated by `permission:<name>`; "any authenticated user" means no
further gate beyond being logged in and active (row-level scoping, e.g.
member_org only seeing their own organisation's data, happens inside the
controller — see the code comment on the programme-entries block in
`routes/api.php`).

| Endpoint | Required permission |
|---|---|
| `GET/POST/PUT /programme-entries*` (base CRUD, draft/submitted/pdf) | any authenticated user |
| `PATCH /programme-entries/{id}/verify` | `programmes.verify` |
| `GET /programme-entries/{id}/activities`, `/geography`, `/government-agreements` | `programmes.view` |
| `POST /programme-entries/{id}/activities`, `PUT .../keywords`, `/geography`, `/government-agreements`, AI autofill endpoints | `programmes.update` |
| `GET /taxonomy/categories` | `taxonomy.view` |
| `POST/PUT/PATCH /taxonomy/*` (write) | `taxonomy.create` / `taxonomy.update` / `taxonomy.delete` |
| `GET /adviser/programme-entries/{id}/advisory-note` | `advisory.view` |
| `GET /policy-documents*` | `policy.view` |
| `POST /policy-documents` | `policy.create` |
| `PATCH /policy-documents/{id}` | `policy.update` |
| `DELETE /policy-documents/{id}` | `policy.delete` |
| `GET /provinces/counts`, `/taxonomy/categories/counts`, `/dashboard/stats`, `/dashboard/recent-activity` | `dashboard.view` |
| `GET /map/entries`, `/map/entries/geojson` | `map.view` |
| `GET /map/entries/export`, `/map/entries/export/pdf` | `map.export` |
| `GET/POST/PATCH /adviser/submissions*`, `/adviser/coordinators`, `/adviser/map/overlap-query`, parse/extract/generate/create-programme-entry endpoints | `advisory.manage` |
| `PATCH /adviser/submissions/{id}/deliver` | `advisory.deliver` |
| `GET/POST/PATCH /admin/users*` | `users.view` / `users.create` / `users.update` |
| `GET/POST/PATCH/DELETE /admin/roles*` | `roles.view` / `roles.create` / `roles.update` / `roles.delete` / `roles.assign` |
| `GET/POST/PATCH/DELETE /admin/permissions*` | `permissions.view` / `permissions.create` / `permissions.update` / `permissions.delete` |
| `GET /admin/organisations*` | `organisations.view` |
| `POST/PUT/PATCH /admin/organisations*` (write) | `organisations.create` / `organisations.update` |
| `GET /user`, `/session`, `/notifications*`, `/provinces`, `/refdata/*`, `/organisations/me`, `/sessions*`, `/change-password`, `/logout` | any authenticated user (self-service / reference data) |

## Testing

```bash
php artisan test tests/Feature/RoleBasedAccessControlTest.php tests/Feature/PrivilegeEscalationTest.php tests/Feature/RoutePermissionTest.php tests/Feature/RoleMiddlewareTest.php
# or the whole suite:
php artisan test
```

`tests/TestCase.php` seeds `RolePermissionSeeder` once per test run (`$seed = true; $seeder = RolePermissionSeeder::class;`), so every feature test automatically has real Role/Permission rows to sync against — individual test files don't need to seed it themselves.

- **`RoleBasedAccessControlTest.php`** (seeds via the real `RolePermissionSeeder`, not hand-rolled fixtures, so it can't drift from production data): admin/coordinator/member access to users/roles/permissions endpoints, `hasPermission()`/`hasRole()`/`effectivePermissions()` correctness including after a role change, system role protections, custom role CRUD, ID validation, and all three last-active-admin protections.
- **`PrivilegeEscalationTest.php`** (new): login/session/`/user` payload shape (roles + permissions arrays), a limited custom role (`users.view`+`users.create` only) cannot create a `nep_admin` account or even a `member_org` account (the guard is strict, not role-hierarchy-based), `nep_admin` can grant any role, a user cannot change their own role, `roles.assign`/role-creation escalation guards, and that `advisory.view` (member's own note) vs `advisory.manage` (staff workspace) don't leak into each other.
- **`RoutePermissionTest.php`**: broad route coverage across the whole app (not just admin screens) — confirms the permission conversion reproduced the exact prior role-based access matrix.
- **`RoleMiddlewareTest.php`**: tests the legacy `RoleMiddleware` component in isolation (not used on any real route anymore).

## Migration

To create the RBAC tables, run:

```bash
php artisan migrate
```

The migration file is `database/migrations/2026_08_09_000001_create_roles_table.php`.

## Frontend (web-app)

### Where permissions live

`POST /login`, `GET /session`, and `GET /user` all return `roles: [{id, name,
display_name}]` and `permissions: string[]` (via
`AuthController::currentUserPayload()`). `stores/auth.ts` stores both and
exposes `hasPermission(name)` / `hasAnyPermission(names)`; `composables/
usePermission.ts` wraps these as `can(permission)` / `canAny([...])` /
`canAll([...])` — **this is the primary mechanism for any new access check.**
The old role-name helpers (`isAdmin`, `isCoordinator`, `hasRole()`) are kept
only as backward-compatible wrappers for pre-existing call sites; don't use
them for anything new, since a custom role wouldn't match any of them.

### Route protection

Every route's `meta.roles: [...]` was converted to `meta.permission: 'name' |
['name', ...]` (any-of, matching the backend's OR semantics). The
`router.beforeEach` guard checks `authStore.hasAnyPermission(...)` instead of
comparing role names, and redirects to `/403` on a mismatch — this is a UX
convenience; the real boundary is the backend `permission:` middleware.
Two routes (`/map`, `/manager/dashboard`) previously had **no** frontend gate
at all — their API calls were already staff-only, so a `member_org` user could
load a broken page shell. Both now require `map.view` / `dashboard.view`.

### Sidebar navigation

`AppShell.vue`'s sidebar used to be two fully-hardcoded role branches
(`if (userRole === 'nep_admin') { ... } else if (userRole === 'nep_coordinator')
{ ... } else { ... }`). It's now one nav-item list per layout, each item
carrying an optional `permission`, filtered dynamically
(`items.filter(i => !i.permission || auth.hasPermission(i.permission))`). Which
of the two visual layouts (grouped "staff" sections vs. flat "workspace" list)
to render is decided by `hasPermission('dashboard.view')` rather than a role
comparison — a future custom role granted `dashboard.view` automatically gets
the staff layout with only the sections/items its other permissions unlock.

### Button/action-level gating

Create/edit/delete/deactivate buttons on the Users, Roles, and Permissions
screens are gated with `can('users.create')`, `can('roles.update')`, etc.
(`UserTable.vue`, `RoleTable.vue`, `PermissionTable.vue`, and the three
`*ManagementView.vue`/`*ManagementPanel.vue` page-level guards). The
`EntryDetailView.vue` "verify" action switched from `auth.isAdmin` to
`auth.hasPermission('programmes.verify')` for the same reason. As always: this
is UX only, the backend enforces the real permission independently.

### Admin screens

Role and Permission Management live at `/admin/roles` and `/admin/permissions`,
following the existing `/admin/users` pattern (`services/*.service.ts` →
`composables/use*.ts` → `stores/*Admin.ts` → `views/admin/*View.vue`, reusing
`BaseModal`, `PageHeader`, `EmptyState`, and toast components):

- **Roles** (`RoleManagementView.vue`): search, create/edit with a grouped
  permission-checkbox matrix, delete (disabled for system roles), and a
  "Manage Users" modal to assign/remove the role from individual accounts.
- **Permissions** (`PermissionManagementView.vue`): search, create/edit/delete,
  grouped by module.
- The Users screen shows a user's granular roles and, on the detail modal,
  their full effective-permission list (from `GET /admin/users/{id}`, which
  previously 404'd — the frontend was already calling it).

## Security Considerations

1. **System Roles**: System roles (nep_admin, nep_coordinator, member_org) cannot be deleted or modified to ensure system stability

2. **Backend Enforcement**: All permission checks are enforced at the backend level. Frontend restrictions are for UX only.

3. **Principle of Least Privilege**: Default roles are configured with minimal necessary permissions

4. **Permission Naming**: Permissions follow a consistent pattern: `resource.action` (e.g., `users.create`, `programmes.view`)

5. **Last active admin protection**: `User::isLastActiveAdmin()` blocks three actions that would otherwise lock everyone out — demoting/deactivating the sole active `nep_admin` (`UserManagementController::update()`/`deactivate()`) and removing the `nep_admin` role from them via Role Management (`RoleManagementController::removeFromUser()`).

6. **Mass assignment / IDOR**: role and permission IDs are validated with `exists:` rules; route-model binding 404s on unknown role/user/permission IDs rather than silently no-oping; passwords stay in `$hidden` on the `User` model so they never appear in API responses (including `/admin/users/{id}`).

7. **Privilege escalation**: an actor can never grant a role/permission set carrying anything they don't already hold themselves (`canGrantRole()`/`canGrantPermissionIds()`), and can never change their own role. See "Privilege-escalation guards" above.

8. **No stale permission cache**: permissions are computed fresh from the database on every request (`effectivePermissions()` isn't cached anywhere) — revoking a permission takes effect on the user's very next request. If caching is added later (see Future Enhancements), it must be invalidated on every role/permission/assignment change.

## Deliberate behavior changes from this pass

- **`admin/users` moved from `role:nep_admin` to permission-based gating**
  (`users.view`/`users.create`/`users.update`). `nep_coordinator` has held
  `users.view` in `RolePermissionSeeder` since the RBAC system was first
  scaffolded, but it was inert because the route only checked `role:nep_admin`.
  Now that the route is genuinely permission-based (matching every other admin
  screen, and the whole point of this pass — no hard-coded role name should
  gate a feature), that grant activates: **coordinators gain read-only access
  to the staff/member user list.** They still can't create, edit, deactivate,
  or reset credentials for anyone (`users.create`/`users.update` are
  `nep_admin`-only). If this is undesired, remove `users.view` from
  `nep_coordinator`'s permission list in `RolePermissionSeeder` — no other code
  change is needed, which is exactly the point of the system being dynamic.
- **`/map` and `/manager/dashboard` frontend routes now require a permission**
  (`map.view` / `dashboard.view`). Previously ungated at the frontend, relying
  entirely on their API calls 403-ing for `member_org`. No API behavior
  changed — this only stops a broken page shell from loading for a role that
  was never going to see real data on it.

## Acceptance Criteria Met

✅ User roles and permissions are loaded dynamically (`/login`, `/session`, `/user`)
✅ No route, controller, or frontend guard hard-codes a role name for access control (the sole exception, `RoleMiddleware`, is unused on any real route — kept only for its own isolated test)
✅ Sidebar navigation is permission-based, not two hard-coded role branches
✅ Frontend routes are permission-based (`meta.permission`)
✅ Buttons/actions on the Users/Roles/Permissions screens are permission-based
✅ Backend APIs are permission-protected — every route in the app, not just the admin screens (see the API Security Audit table)
✅ Unauthorized API requests return 403; unauthorized frontend routes redirect to `/403`
✅ Role permissions can be changed dynamically — no code change needed, verified by `test_role_change_updates_effective_permissions`
✅ Users automatically receive permissions from their role(s); multiple roles work (union of permissions) if manually assigned via Role Management, though the app still surfaces one primary `role` per user in its own UI
✅ Removing a permission removes access on the user's very next request (no cache)
✅ Super Admin (`nep_admin`) protected: last active admin can't be demoted, deactivated, or stripped of the role; can't grant permissions/roles you don't hold yourself; can't change your own role
✅ No protected API can be bypassed from Postman/curl — the same `permission:` middleware runs regardless of client
✅ Tests cover permission grants/denials, role changes, escalation attempts, and the last-admin protections

## Future Enhancements

1. Add permission caching for better performance (must invalidate on role/permission/assignment change — see Security Considerations #8)
2. Implement permission inheritance between roles
3. Add audit logging for role/permission changes
4. Implement temporary role assignments with expiration
5. Consider a dedicated "who can see what" permission for `/admin/programmes` (currently reuses `dashboard.view` to reproduce today's exact nep_admin+nep_coordinator access without a semantically-perfect name)