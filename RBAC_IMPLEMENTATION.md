# User Role Management and Permissions (RBAC) Implementation

## Overview

This document describes the Role-Based Access Control (RBAC) system implemented for the NEP (National Education Platform) system. The RBAC system provides fine-grained access control through roles and permissions.

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
   - All permissions assigned
   - Cannot be deleted or modified

2. **nep_coordinator** (NEP Coordinator)
   - Organisation-level access
   - Limited administrative functions
   - Permissions:
     - users.view
     - organisations.view
     - programmes.view, programmes.create, programmes.update
     - advisory.view, advisory.create, advisory.update
     - dashboard.view
     - reports.view, reports.export
     - taxonomy.view

3. **member_org** (Member Organisation)
   - Basic access to assigned programmes
   - Permissions:
     - programmes.view, programmes.create, programmes.update
     - advisory.view

### Permission Groups

- **Users** - User management permissions
- **Organisations** - Organisation management permissions
- **Programmes** - Programme entry permissions
- **Advisory** - Advisory submission permissions
- **Roles** - Role management permissions
- **Permissions** - Permission management permissions
- **Taxonomy** - Taxonomy management permissions
- **Dashboard** - Dashboard access permissions
- **Reports** - Report viewing and export permissions

## API Endpoints

### Role Management Endpoints

All role management endpoints require `nep_admin` role.

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

All permission management endpoints require `nep_admin` role.

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

User management endpoints now include role information in responses.

#### GET `/api/admin/users`
List all users with their roles
- Requires `nep_admin` role
- Returns users with loaded `roles` relationship

#### POST `/api/admin/users`
Create a new user
- Requires `nep_admin` role
- Assigns role specified in request

#### PATCH `/api/admin/users/{user}`
Update a user
- Requires `nep_admin` role
- Can update user role

## Usage Examples

### Protecting Routes with Roles

```php
// In routes/api.php
Route::middleware('role:nep_admin')->group(function () {
    Route::get('/admin/users', [UserManagementController::class, 'index']);
    Route::post('/admin/users', [UserManagementController::class, 'store']);
});

// Multiple roles allowed
Route::middleware('role:nep_admin,nep_coordinator')->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});
```

### Protecting Routes with Permissions

```php
// In routes/api.php
Route::middleware('permission:users.view')->get('/users', [UserController::class, 'index']);
Route::middleware('permission:users.create,users.update')->post('/users', [UserController::class, 'store']);

// Multiple permissions (user needs at least one)
Route::middleware('permission:users.view,users.create')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
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

## Testing

A comprehensive test suite is provided in `tests/Feature/RoleBasedAccessControlTest.php`.

Run the tests with:

```bash
php artisan test tests/Feature/RoleBasedAccessControlTest.php
```

The test suite covers:
- Admin can view all users
- Coordinator cannot view users
- Member cannot view users
- Admin can create users
- Admin can assign roles
- Admin can manage permissions
- Permission checking works correctly
- Role checking works correctly
- Unauthorized actions are blocked
- System roles cannot be deleted
- System roles cannot be modified
- Backend enforces permissions

## Migration

To create the RBAC tables, run:

```bash
php artisan migrate
```

The migration file is `database/migrations/2026_08_09_000001_create_roles_table.php`.

## Security Considerations

1. **System Roles**: System roles (nep_admin, nep_coordinator, member_org) cannot be deleted or modified to ensure system stability

2. **Backend Enforcement**: All permission checks are enforced at the backend level. Frontend restrictions are for UX only.

3. **Principle of Least Privilege**: Default roles are configured with minimal necessary permissions

4. **Permission Naming**: Permissions follow a consistent pattern: `resource.action` (e.g., `users.create`, `programmes.view`)

## Acceptance Criteria Met

✅ Admin can view all users and their roles
✅ Admin can assign a role to a user
✅ Admin can change a user's role
✅ Admin can manage role permissions
✅ Coordinator can only access organization-level functions
✅ Member can only access assigned programs
✅ Member cannot create organizations
✅ Member cannot assign roles or manage other users
✅ Unauthorized actions are blocked
✅ Role permissions are enforced by the backend API
✅ User role is clearly displayed in the user management page

## Future Enhancements

1. Add permission caching for better performance
2. Implement permission inheritance between roles
3. Add audit logging for role/permission changes
4. Create frontend components for role management UI
5. Add ability to create custom roles with specific permissions
6. Implement temporary role assignments with expiration