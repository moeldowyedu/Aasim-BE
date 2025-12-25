# Database Schema Migration Guide

This guide explains how to migrate your OBSOLIO database from the old schema (with subscription data in tenants table) to the new professional schema (with proper separation of concerns).

## Overview

This migration will:
1. ✅ Add `organization_id` foreign key to tenants table
2. ✅ Add plan management features (versioning, archiving, publishing)
3. ✅ Migrate subscription data from `tenants` to `subscriptions` table
4. ✅ Remove redundant columns (`plan_id`, `billing_cycle`, `trial_ends_at`) from `tenants` table
5. ✅ Update models and add new admin endpoints

## Prerequisites

- **Backup your database** before running migrations
- Have access to the production database
- Ensure no critical operations are running
- Review all migration files before executing

---

## Step-by-Step Migration Process

### 1. Backup Database

**CRITICAL: Always backup before migrations!**

```bash
# Run the backup script
./scripts/backup_database.sh

# Or manually with pg_dump (PostgreSQL)
pg_dump -U postgres -h localhost -d obsolio > backup_$(date +%Y%m%d_%H%M%S).sql

# For SQLite (development)
cp database/database.sqlite database/database.sqlite.backup
```

### 2. Review Migration Files

The following migrations will be executed in order:

1. **`2025_12_25_000001_add_organization_id_to_tenants.php`**
   - Adds `organization_id` column to tenants
   - Creates foreign key to organizations table
   - Safe to run (only adds columns)

2. **`2025_12_25_000002_add_plan_management_features.php`**
   - Adds versioning/archiving columns to subscription_plans
   - Adds `is_published`, `is_archived`, `plan_version`, etc.
   - Safe to run (only adds columns)

3. **`2025_12_25_000003_migrate_tenant_subscription_data.php`**
   - **DATA MIGRATION** - Moves data from tenants to subscriptions
   - Links organization tenants to their organizations
   - Creates subscription records for tenants with plan_id
   - **Review this carefully!**

4. **`2025_12_25_000004_cleanup_tenant_subscription_columns.php`**
   - **DESTRUCTIVE** - Removes `plan_id`, `billing_cycle`, `trial_ends_at` from tenants
   - Only run after verifying data migration success
   - **Point of no return without rollback!**

### 3. Run Migrations (Development First!)

**Test in development environment first:**

```bash
# Check migration status
php artisan migrate:status

# Run migrations with verbose output
php artisan migrate --step --verbose

# If something goes wrong, rollback the last batch
php artisan migrate:rollback

# Or rollback specific steps
php artisan migrate:rollback --step=1
```

### 4. Verify Data Migration

**After running migration 3 (data migration), verify:**

```bash
# Check that subscriptions were created
php artisan tinker
>>> DB::table('subscriptions')->count()
>>> DB::table('subscriptions')->where('metadata->migrated_from_tenant', true)->count()

# Verify tenants have correct organization_id
>>> DB::table('tenants')->whereNotNull('organization_id')->count()

# Check for tenants without active subscriptions (should investigate)
>>> App\Models\Tenant::doesntHave('activeSubscription')->count()
```

**SQL Verification Queries:**

```sql
-- Check subscription migration
SELECT
    t.id as tenant_id,
    t.name as tenant_name,
    t.type,
    s.id as subscription_id,
    s.plan_id,
    s.status,
    s.billing_cycle
FROM tenants t
LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trialing', 'active')
ORDER BY t.created_at DESC
LIMIT 20;

-- Check organization links
SELECT
    t.id as tenant_id,
    t.name as tenant_name,
    t.type,
    t.organization_id,
    o.name as org_name
FROM tenants t
LEFT JOIN organizations o ON t.organization_id = o.id
WHERE t.type = 'organization'
LIMIT 20;

-- Count orphaned data
SELECT
    COUNT(*) as tenants_without_subscription
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM subscriptions s
    WHERE s.tenant_id = t.id
    AND s.status IN ('trialing', 'active')
);
```

### 5. Run Final Cleanup Migration

**Only after verifying data migration success:**

```bash
# Run the cleanup migration
php artisan migrate --step --verbose

# This will remove plan_id, billing_cycle, trial_ends_at from tenants table
```

### 6. Update Application Code

The following files have been updated:

- ✅ `app/Models/Tenant.php` - Removed plan_id, added helper methods
- ✅ `app/Models/SubscriptionPlan.php` - Added versioning/archiving support
- ✅ `app/Http/Controllers/Api/V1/Admin/TenantManagementController.php` - New admin endpoints
- ✅ `routes/api.php` - Added admin tenant management routes

**Clear application cache:**

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 7. Test New Admin Endpoints

**Tenant Management Endpoints (requires system_admin role):**

```bash
# List all tenants with filters
GET /api/v1/admin/tenants?type=organization&status=active&per_page=20

# Get tenant statistics
GET /api/v1/admin/tenants/statistics

# Get specific tenant details
GET /api/v1/admin/tenants/{id}

# Update tenant status
PUT /api/v1/admin/tenants/{id}/status
{
  "status": "active",
  "reason": "Verified payment"
}

# Change tenant subscription
PUT /api/v1/admin/tenants/{id}/subscription
{
  "plan_id": "uuid-of-new-plan",
  "billing_cycle": "annual",
  "starts_immediately": true
}

# View subscription history
GET /api/v1/admin/tenants/{id}/subscriptions

# Extend trial
POST /api/v1/admin/tenants/{id}/extend-trial
{
  "days": 30,
  "reason": "Customer request"
}

# Delete tenant
DELETE /api/v1/admin/tenants/{id}
```

**Plan Management Endpoints:**

```bash
# List all plans (with filters)
GET /api/v1/admin/subscription-plans?type=organization&active=true

# Create new plan
POST /api/v1/admin/subscription-plans
{
  "name": "Startup",
  "type": "organization",
  "tier": "team",
  "price_monthly": 149.00,
  "price_annual": 1490.00,
  "max_users": 20,
  "max_agents": 50,
  "storage_gb": 100,
  "trial_days": 14,
  "features": ["Feature 1", "Feature 2"],
  "limits": {"api_calls_per_day": 5000},
  "description": "Perfect for growing startups",
  "is_published": true
}

# Update plan
PUT /api/v1/admin/subscription-plans/{id}
{
  "price_monthly": 199.00,
  "is_published": true
}

# Archive plan
PUT /api/v1/admin/subscription-plans/{id}
{
  "is_archived": true
}

# Delete plan (only if no active subscriptions)
DELETE /api/v1/admin/subscription-plans/{id}
```

---

## Rollback Procedure

If something goes wrong:

### Quick Rollback (Recent Migration)

```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Rollback all today's migrations
php artisan migrate:rollback --batch=<batch_number>

# Check batch numbers
php artisan migrate:status
```

### Full Database Restore

```bash
# PostgreSQL restore
psql -U postgres -d obsolio < backup_file.sql

# SQLite restore
cp database/database.sqlite.backup database/database.sqlite
```

---

## Post-Migration Checklist

- [ ] Database backup created successfully
- [ ] All migrations ran without errors
- [ ] Data verification queries pass
- [ ] No tenants without subscriptions (or documented why)
- [ ] Organization links are correct
- [ ] Application cache cleared
- [ ] Admin endpoints tested and working
- [ ] Plan management endpoints tested
- [ ] Frontend integration tested (if applicable)
- [ ] Monitor logs for errors in first 24 hours

---

## Breaking Changes

### For API Consumers

1. **Tenant model no longer has direct `plan_id`, `billing_cycle`, `trial_ends_at` fields**
   - Use `$tenant->activeSubscription->plan_id` instead
   - Use `$tenant->currentPlan()` relationship
   - Helper methods provided: `$tenant->billingCycle()`, `$tenant->isOnTrial()`

2. **New relationships**
   - `$tenant->organization()` - belongs to relationship (for org tenants)
   - `$tenant->currentPlan()` - hasOneThrough relationship
   - `$tenant->activeSubscription` - hasOne relationship (existing)

3. **Subscription Plan fields**
   - New: `is_published`, `is_archived`, `plan_version`, `parent_plan_id`
   - Use `SubscriptionPlan::published()` scope for public plans

### Example Code Updates

**Before:**
```php
$tenant = Tenant::find($id);
$planId = $tenant->plan_id;
$billingCycle = $tenant->billing_cycle;
$onTrial = $tenant->isOnTrial();
```

**After:**
```php
$tenant = Tenant::find($id);
$planId = $tenant->activeSubscription?->plan_id;
$billingCycle = $tenant->billingCycle();
$onTrial = $tenant->isOnTrial(); // Still works, now uses subscription
```

---

## Support

If you encounter issues during migration:

1. **Stop the migration immediately**
2. **Do not run the cleanup migration if data migration failed**
3. **Restore from backup if necessary**
4. **Review migration logs**: `storage/logs/laravel.log`
5. **Check migration output for specific errors**

---

## Success Indicators

✅ All migrations completed without errors
✅ Subscription records exist for all tenants with plans
✅ Organization tenants linked to organizations
✅ Admin endpoints returning data correctly
✅ No 500 errors in application logs
✅ Existing functionality still works

---

**Last Updated:** 2025-12-25
**Migration Version:** 1.0.0
**Database Schema Version:** 2.0.0
