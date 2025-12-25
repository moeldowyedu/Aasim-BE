# OBSOLIO Database Schema Refactoring - Implementation Summary

**Date:** 2025-12-25
**Status:** ✅ Ready for Migration
**Database Version:** 1.0.0 → 2.0.0

---

## What Was Implemented

### 1. Database Migrations (4 Files)

#### Migration 1: `2025_12_25_000001_add_organization_id_to_tenants.php`
- Adds `organization_id` UUID column to tenants table (nullable)
- Creates foreign key constraint: tenants.organization_id → organizations.id
- Adds index for performance
- **Safe to run** - Only adds columns

#### Migration 2: `2025_12_25_000002_add_plan_management_features.php`
- Adds plan management features to `subscription_plans` table:
  - `is_published` (boolean) - Control visibility to users
  - `is_archived` (boolean) - Soft archive without deletion
  - `plan_version` (string) - Version tracking (e.g., "1.0.0")
  - `parent_plan_id` (UUID FK) - Plan lineage for versioning
  - `display_order` (integer) - Control display order in UI
  - `highlight_features` (jsonb) - Featured highlights for marketing
  - `metadata` (jsonb) - Flexible additional data
- **Safe to run** - Only adds columns

#### Migration 3: `2025_12_25_000003_migrate_tenant_subscription_data.php`
- **DATA MIGRATION** - Critical step!
- Migrates subscription data from tenants to subscriptions table
- For each tenant with plan_id:
  - Creates subscription record with plan_id, billing_cycle, trial_ends_at
  - Sets appropriate status (trialing/active)
  - Adds metadata tracking migration source
- Links organization-type tenants to their organizations
- **Review carefully before running**

#### Migration 4: `2025_12_25_000004_cleanup_tenant_subscription_columns.php`
- **DESTRUCTIVE** - Removes redundant columns from tenants table:
  - Drops `plan_id` column and foreign key
  - Drops `billing_cycle` column
  - Drops `trial_ends_at` column
- **Only run after verifying data migration success**

### 2. Model Updates

#### Tenant Model (`app/Models/Tenant.php`)
**Removed from `$fillable`:**
- `plan_id`
- `trial_ends_at`
- `billing_cycle`

**Added to `$fillable`:**
- `organization_id`
- `subdomain_activated_at`

**New Relationships:**
- `organization()` - BelongsTo relationship (for organization tenants)
- `currentPlan()` - HasOneThrough via active subscription

**Updated Methods:**
- `isOnTrial()` - Now delegates to active subscription
- `trialDaysRemaining()` - Now delegates to active subscription

**New Helper Methods:**
- `hasActiveSubscription()` - Check if tenant has active subscription
- `billingCycle()` - Get current billing cycle
- `isPersonal()` - Check if personal tenant
- `isOrganization()` - Check if organization tenant

#### SubscriptionPlan Model (`app/Models/SubscriptionPlan.php`)
**Added to `$fillable`:**
- `is_published`, `is_archived`, `plan_version`
- `parent_plan_id`, `display_order`
- `highlight_features`, `metadata`

**Added to `$casts`:**
- `is_published`, `is_archived` as boolean
- `highlight_features`, `metadata` as array

**New Relationships:**
- `parentPlan()` - BelongsTo (for plan versioning)
- `childPlans()` - HasMany (plan versions)

**New Methods:**
- `isPublished()` - Check if plan is available
- `isArchived()` - Check if plan is archived
- `scopePublished()` - Get only published plans
- `scopeByType()` - Filter by type
- `scopeActive()` - Get active, non-archived plans
- `getDisplayName()` - Get formatted name
- `activeSubscriptionsCount()` - Count active subscriptions

### 3. Admin Tenant Management Controller

**New File:** `app/Http/Controllers/Api/V1/Admin/TenantManagementController.php`

**Endpoints Implemented:**

1. **`GET /api/v1/admin/tenants`**
   - List all tenants with advanced filtering
   - Filters: search, type, status, plan_id, has_subscription
   - Sorting and pagination
   - Includes computed fields (trial status, billing cycle, etc.)

2. **`GET /api/v1/admin/tenants/statistics`**
   - Comprehensive tenant statistics
   - Breakdown by type, status, plan
   - Recent signups, trial users, etc.

3. **`GET /api/v1/admin/tenants/{id}`**
   - Detailed tenant information
   - Includes subscriptions, organization, memberships, invoices
   - Computed fields for easy consumption

4. **`PUT /api/v1/admin/tenants/{id}/status`**
   - Update tenant status (active, inactive, suspended, etc.)
   - Activity logging included

5. **`PUT /api/v1/admin/tenants/{id}/subscription`**
   - Change tenant's subscription plan
   - Supports immediate or scheduled changes
   - Handles prorating
   - Cancels old subscription, creates new one

6. **`GET /api/v1/admin/tenants/{id}/subscriptions`**
   - View complete subscription history
   - All past and current subscriptions

7. **`POST /api/v1/admin/tenants/{id}/extend-trial`**
   - Extend trial period by X days
   - Activity logging with reason

8. **`DELETE /api/v1/admin/tenants/{id}`**
   - Soft delete tenant
   - Cancels active subscriptions
   - Activity logging

### 4. Routes Updated

**File:** `routes/api.php`

**Added Import:**
```php
use App\Http\Controllers\Api\V1\Admin\TenantManagementController;
```

**Replaced Old Routes:**
```php
// OLD (via TenantController)
Route::get('/tenants', [TenantController::class, 'indexAdmin']);
Route::get('/tenants/{id}', [TenantController::class, 'showAdmin']);
// etc...

// NEW (via TenantManagementController)
Route::get('/tenants', [TenantManagementController::class, 'index']);
Route::get('/tenants/statistics', [TenantManagementController::class, 'statistics']);
Route::get('/tenants/{id}', [TenantManagementController::class, 'show']);
Route::put('/tenants/{id}/status', [TenantManagementController::class, 'updateStatus']);
Route::put('/tenants/{id}/subscription', [TenantManagementController::class, 'changeSubscription']);
Route::get('/tenants/{id}/subscriptions', [TenantManagementController::class, 'subscriptionHistory']);
Route::post('/tenants/{id}/extend-trial', [TenantManagementController::class, 'extendTrial']);
Route::delete('/tenants/{id}', [TenantManagementController::class, 'destroy']);
```

### 5. Utilities

#### Database Backup Script
**File:** `scripts/backup_database.sh`
- Automated PostgreSQL and SQLite backup
- Compression support
- Keeps last 10 backups
- Timestamp-based naming

---

## Benefits of New Schema

### 1. Clean Separation of Concerns
- **Tenants** = Workspace identity (name, email, status, subdomain)
- **Subscriptions** = Billing relationship (plan, cycle, dates)
- **Plans** = Product offerings (features, pricing, limits)

### 2. Complete Subscription History
- Track all plan changes over time
- Audit trail for billing disputes
- Analytics on plan switching behavior

### 3. Flexible Plan Management
- Archive old plans without deleting
- Version plans for A/B testing
- Publish/unpublish plans dynamically
- Control plan visibility

### 4. Better Admin Control
- Comprehensive tenant management
- Change subscriptions without code
- Extend trials for customer service
- View detailed analytics

### 5. Scalability
- Easy to add plan addons
- Support for usage-based billing
- Plan downgrades/upgrades
- Multi-plan subscriptions (future)

---

## API Changes for Consumers

### Breaking Changes

#### Tenant Model
```php
// ❌ OLD (will break after cleanup migration)
$tenant->plan_id
$tenant->billing_cycle
$tenant->trial_ends_at

// ✅ NEW (works now and after migration)
$tenant->activeSubscription->plan_id
$tenant->billingCycle()
$tenant->isOnTrial()
$tenant->trialDaysRemaining()
$tenant->currentPlan() // relationship
```

#### Subscription Plan Query
```php
// ❌ OLD
SubscriptionPlan::where('is_active', true)->get()

// ✅ NEW (only show published plans)
SubscriptionPlan::published()->get()
```

### New Features Available

```php
// Check if tenant has active subscription
$tenant->hasActiveSubscription()

// Get current plan through relationship
$tenant->currentPlan

// Check tenant type
$tenant->isPersonal()
$tenant->isOrganization()

// Get organization for org tenants
$tenant->organization

// Plan management
$plan->isPublished()
$plan->isArchived()
$plan->activeSubscriptionsCount()
$plan->getDisplayName()
```

---

## Migration Execution Order

1. ✅ **Backup database** (scripts/backup_database.sh)
2. ✅ **Run migrations in order:**
   - Migration 1: Add organization_id
   - Migration 2: Add plan management features
   - Migration 3: Migrate data (VERIFY AFTER THIS!)
   - Migration 4: Cleanup old columns (DESTRUCTIVE!)
3. ✅ **Verify data** (see MIGRATION_GUIDE.md)
4. ✅ **Clear caches**
5. ✅ **Test admin endpoints**

---

## Documentation Files

1. **`MIGRATION_GUIDE.md`** - Detailed step-by-step migration instructions
2. **`IMPLEMENTATION_SUMMARY.md`** - This file
3. **`scripts/backup_database.sh`** - Backup script

---

## Testing Checklist

### Before Migration
- [ ] Database backup completed
- [ ] Review all migration files
- [ ] Test in development environment first
- [ ] Verify current data integrity

### After Migration 3 (Data Migration)
- [ ] All tenants have subscriptions created
- [ ] Organization tenants linked to organizations
- [ ] Trial dates migrated correctly
- [ ] Billing cycles preserved
- [ ] No orphaned data

### After Migration 4 (Cleanup)
- [ ] Application still loads without errors
- [ ] Tenant queries work correctly
- [ ] Subscription queries work correctly
- [ ] Admin endpoints functional

### Final Checks
- [ ] Monitor logs for errors (24 hours)
- [ ] Test user workflows
- [ ] Test admin workflows
- [ ] Performance acceptable

---

## Rollback Plan

### If Migration Fails
```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Or restore from backup
psql -U postgres -d obsolio < backup_file.sql
```

### Critical: Do Not Run Cleanup Migration If Data Migration Failed!

---

## Support

For questions or issues:
1. Check `storage/logs/laravel.log`
2. Review `MIGRATION_GUIDE.md`
3. Restore from backup if necessary
4. Contact development team

---

**Implementation Status:** ✅ Complete and Ready for Migration
**Risk Level:** Medium (data migration required)
**Estimated Downtime:** 5-10 minutes (for migration execution)
**Reversible:** Yes (before cleanup migration runs)

