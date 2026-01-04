# Quick Fix for Server - SystemAdminSeeder

## The Problem
The server still has the old seeder code trying to use `console` guard, and the migration to relax the constraint hasn't been deployed yet.

## Quick SQL Fix (Run on Server)

Connect to your PostgreSQL database and run:

```sql
-- Drop the restrictive constraint
ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_guard_tenant_check;

-- Add flexible constraint that allows web/api guards
ALTER TABLE roles
ADD CONSTRAINT roles_guard_tenant_check
CHECK (
    (guard_name = 'console' AND tenant_id IS NULL) OR
    (guard_name = 'tenant' AND tenant_id IS NOT NULL) OR
    (guard_name = 'web' AND tenant_id IS NULL) OR
    (guard_name = 'api' AND tenant_id IS NULL)
);
```

## Or Use Laravel Tinker

```bash
php artisan tinker

# Then run:
DB::statement("ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_guard_tenant_check");

DB::statement("
    ALTER TABLE roles
    ADD CONSTRAINT roles_guard_tenant_check
    CHECK (
        (guard_name = 'console' AND tenant_id IS NULL) OR
        (guard_name = 'tenant' AND tenant_id IS NOT NULL) OR
        (guard_name = 'web' AND tenant_id IS NULL) OR
        (guard_name = 'api' AND tenant_id IS NULL)
    )
");

exit
```

## Then Update SystemAdminSeeder.php on Server

Replace the role creation part with:

```php
// System Admin Dashboard role - use 'web' guard
$roleName = 'Super Admin';

// Create role with 'web' guard and no tenant_id (system-level admin)
$role = Role::firstOrCreate(
    ['name' => $roleName, 'guard_name' => 'web'],
    ['name' => $roleName, 'guard_name' => 'web']
);
```

## After Fixing

Run the seeder:
```bash
php artisan db:seed --force
```

---

**Alternative:** Deploy the new migration file and updated seeder from your local to the server, then run:
```bash
php artisan migrate --force
php artisan db:seed --force
```
