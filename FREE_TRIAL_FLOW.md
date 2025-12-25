# Free Trial Flow Documentation

**Last Updated:** 2025-12-25
**Version:** 2.0.0

---

## Overview

OBSOLIO offers a **free trial period for all new users** (both personal and organization types). Users can register, verify their email, and immediately start using the platform **without providing payment information**. The trial subscription is automatically created upon email verification.

---

## Trial Periods

### Personal Plans
- **Free Personal**: No trial (free forever)
- **Pro Personal**: **14-day free trial**

### Organization Plans
- **Team**: **14-day free trial**
- **Business**: **14-day free trial**
- **Enterprise**: **30-day free trial**

---

## Free Trial Flow

### Step 1: User Registration

**Endpoint:** `POST /api/v1/auth/register`

**Process:**
1. User provides registration details (name, email, password, type, subdomain, etc.)
2. System creates:
   - Tenant with `status: pending_verification`
   - User with `status: pending_verification`
   - TenantMembership (owner role)
   - Organization (if type=organization)
3. System sends verification email
4. **NO subscription is created at this stage**
5. **NO payment required**

**Important Fields:**
- `trial_ends_at`: Set to 7 days from registration (placeholder, will be overwritten)
- `subdomain_preference`: Stores desired subdomain for later activation

**Response:**
```json
{
  "success": true,
  "message": "Registration successful! Please check your email to verify your account.",
  "data": {
    "email": "user@example.com",
    "workspace_preview": "my-workspace.obsolio.com",
    "verification_required": true
  }
}
```

---

### Step 2: Email Verification

**Endpoint:** `GET /api/verify-email/{id}/{hash}`

**Process:**
1. User clicks verification link in email
2. System validates signature and hash
3. System updates:
   - User `status` → `active`
   - User `email_verified_at` → current timestamp
   - Tenant `status` → `active`
   - Tenant `id` → Uses `subdomain_preference` if set
4. System creates tenant domain: `{subdomain}.obsolio.com`
5. **Event `Verified` is fired**

---

### Step 3: Automatic Trial Subscription Creation

**Event Listener:** `CreateTrialSubscription`

**Triggered By:** `Illuminate\Auth\Events\Verified` event

**Process:**
1. Listener detects email verification
2. Checks if tenant already has an active subscription (skip if yes)
3. Determines default plan based on tenant type:
   - **Personal/Individual** → "Free Personal" plan (tier: free)
   - **Organization** → "Team" plan (tier: team)
4. Creates subscription with:
   - `status`: `trialing` (if trial_days > 0) or `active` (for free plans)
   - `trial_ends_at`: Current date + plan's `trial_days`
   - `current_period_start`: Current date
   - `current_period_end`: Current date + 1 month
   - `billing_cycle`: `monthly` (default)
5. Links organization to tenant (if organization type)

**Subscription Fields:**
```php
[
    'tenant_id' => $tenant->id,
    'plan_id' => $defaultPlan->id,
    'status' => 'trialing',  // or 'active' for free plans
    'billing_cycle' => 'monthly',
    'starts_at' => now(),
    'trial_ends_at' => now()->addDays(14),  // Based on plan's trial_days
    'current_period_start' => now(),
    'current_period_end' => now()->addMonth(),
    'metadata' => [
        'created_via' => 'email_verification',
        'user_id' => $user->id,
        'plan_type' => 'personal',
        'plan_tier' => 'free'
    ]
]
```

---

### Step 4: User Accesses Platform

**Process:**
1. User logs in
2. User accesses `{subdomain}.obsolio.com`
3. Middleware checks:
   - Tenant status (must be `active`)
   - Subscription status (must be `active` or `trialing`)
4. User can access all features of their trial plan
5. **No payment method required during trial**

---

## Plan Assignment Logic

### Default Plans

| Tenant Type | Default Plan | Tier | Trial Days | Monthly Price |
|-------------|--------------|------|------------|---------------|
| `personal` or `individual` | Free Personal | free | 0 | $0 |
| `organization` | Team | team | 14 | $99 |

### Plan Selection Criteria

The `CreateTrialSubscription` listener selects plans based on:
1. `type` matching tenant type
2. `tier` matching default tier for that type
3. `is_active = true`
4. `is_published = true`

**Code:**
```php
SubscriptionPlan::where('type', 'personal')
    ->where('tier', 'free')
    ->where('is_active', true)
    ->where('is_published', true)
    ->first();
```

---

## Trial Expiration Handling

### Trial About to Expire

**When:** 7 days before `trial_ends_at`

**Action:**
- Send email notification to user
- Prompt to add payment method
- Show trial expiration warning in UI

### Trial Expired (No Payment)

**When:** `trial_ends_at` < current date AND no payment method

**Action:**
- Subscription `status` → `past_due`
- Restrict access to platform
- Show "Subscribe Now" screen
- Send email reminder

### Trial Converted (Payment Added)

**When:** User adds payment method before trial ends

**Action:**
- Subscription `status` → `active`
- Start billing cycle
- Create first invoice
- Send confirmation email

---

## Upgrading During Trial

Users can upgrade to a paid plan **during** their trial period:

**Endpoint:** `PUT /api/v1/subscriptions/change-plan`

**Process:**
1. User selects higher-tier plan
2. System calculates proration (if applicable)
3. Creates new subscription:
   - Cancels old subscription
   - Creates new subscription with chosen plan
   - Maintains remaining trial days OR starts paid period immediately
4. User provides payment method
5. System processes payment (or schedules for trial end)

**Example:**
```json
{
  "plan_id": "uuid-of-pro-plan",
  "billing_cycle": "monthly",
  "starts_immediately": false  // Continue trial until trial_ends_at
}
```

---

## Payment Flow After Trial

### Trial Ends → Payment Required

**When:** `trial_ends_at` reached

**Automatic Process:**
1. Check if user has payment method on file
2. If YES:
   - Create invoice for next period
   - Process payment via Paymob
   - Update subscription `status` → `active`
   - Set `current_period_start` and `current_period_end`
3. If NO:
   - Update subscription `status` → `past_due`
   - Send payment reminder email
   - Restrict platform access

### Adding Payment Method

**Endpoint:** `POST /api/v1/billing/payment-methods`

**Process:**
1. User enters payment details
2. System creates Paymob customer record
3. Stores payment method securely
4. Sets as default payment method
5. If trial expired, automatically retry billing

---

## Middleware: CheckSubscription

**File:** `app/Http/Middleware/CheckSubscription.php`

**Purpose:** Protect routes that require active subscription

**Logic:**
```php
// Allow access if:
$subscription = $tenant->activeSubscription;

return $subscription &&
       ($subscription->status === 'active' || $subscription->status === 'trialing') &&
       (!$subscription->trial_ends_at || $subscription->trial_ends_at->isFuture());
```

---

## Middleware: CheckTenantStatus

**File:** `app/Http/Middleware/CheckTenantStatus.php`

**Purpose:** Ensure tenant is active and verified

**Logic:**
```php
// Block access if tenant is not active
if ($tenant->status !== 'active') {
    return response()->json(['error' => 'Tenant not active'], 403);
}
```

---

## Database Schema

### Tenants Table

```sql
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255),
    type ENUM('personal', 'organization'),
    status ENUM('pending_verification', 'active', 'inactive', 'suspended'),
    subdomain_preference VARCHAR(255),
    organization_id UUID,  -- FK to organizations (for org types)
    -- plan_id REMOVED (moved to subscriptions table)
    -- billing_cycle REMOVED (moved to subscriptions table)
    -- trial_ends_at REMOVED (moved to subscriptions table)
    ...
);
```

### Subscriptions Table

```sql
CREATE TABLE subscriptions (
    id UUID PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,  -- FK to tenants
    plan_id UUID NOT NULL,  -- FK to subscription_plans
    status ENUM('trialing', 'active', 'past_due', 'canceled'),
    billing_cycle ENUM('monthly', 'annual'),
    starts_at TIMESTAMP,
    ends_at TIMESTAMP,
    trial_ends_at TIMESTAMP,  -- NULL for no trial
    canceled_at TIMESTAMP,
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    metadata JSONB,
    ...
);
```

### Subscription Plans Table

```sql
CREATE TABLE subscription_plans (
    id UUID PRIMARY KEY,
    name VARCHAR(255),
    type ENUM('personal', 'organization'),
    tier ENUM('free', 'pro', 'team', 'business', 'enterprise'),
    price_monthly DECIMAL(10, 2),
    price_annual DECIMAL(10, 2),
    trial_days INTEGER DEFAULT 0,  -- Number of free trial days
    is_active BOOLEAN DEFAULT TRUE,
    is_published BOOLEAN DEFAULT TRUE,
    is_archived BOOLEAN DEFAULT FALSE,
    ...
);
```

---

## Testing the Free Trial Flow

### End-to-End Test

```bash
# 1. Register new user
POST /api/v1/auth/register
{
  "type": "organization",
  "fullName": "John Doe",
  "email": "john@acme.com",
  "password": "SecurePass123!",
  "phone": "+1234567890",
  "country": "USA",
  "subdomain": "acme",
  "organizationFullName": "Acme Corporation"
}

# 2. Verify email (click link in email)
GET /api/verify-email/{id}/{hash}

# 3. Check subscription was created
GET /api/v1/subscriptions/current
# Expected: status='trialing', trial_ends_at=14 days from now

# 4. Access platform (should work)
GET https://acme.obsolio.com/api/v1/dashboard/stats

# 5. Wait for trial to expire (or simulate)
# Update trial_ends_at to past date in database

# 6. Access platform (should fail)
GET https://acme.obsolio.com/api/v1/dashboard/stats
# Expected: 403 Forbidden or redirect to payment page

# 7. Add payment method and subscribe
POST /api/v1/payments/subscription
{
  "plan_id": "uuid-of-team-plan",
  "billing_cycle": "monthly"
}
```

---

## Migration Notes

### Existing Tenants

When running database migration `2025_12_25_000003_migrate_tenant_subscription_data.php`:

**For existing tenants with `plan_id`:**
- Subscription will be created automatically
- `trial_ends_at` from tenants table → `trial_ends_at` in subscriptions
- `billing_cycle` from tenants table → `billing_cycle` in subscriptions
- Status determined by trial expiration

**After migration:**
- Columns removed from tenants: `plan_id`, `billing_cycle`, `trial_ends_at`
- All subscription data now in `subscriptions` table

---

## Common Issues

### Issue: User verified but no subscription

**Cause:** Event listener didn't fire or failed

**Solution:**
- Check logs: `storage/logs/laravel.log`
- Manually create subscription:
```php
php artisan tinker
>>> $user = User::find('user-id');
>>> $tenant = $user->tenant;
>>> event(new \Illuminate\Auth\Events\Verified($user));
```

### Issue: Wrong default plan assigned

**Cause:** Plan seeder not run or plan not published

**Solution:**
```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

### Issue: Trial period not matching plan

**Cause:** Listener using hardcoded trial days

**Solution:** Listener correctly reads `trial_days` from plan, check plan configuration

---

## API Endpoints Summary

| Endpoint | Method | Description | Payment Required |
|----------|--------|-------------|------------------|
| `/auth/register` | POST | Register new account | ❌ No |
| `/verify-email/{id}/{hash}` | GET | Verify email | ❌ No |
| `/subscriptions/current` | GET | Get current subscription | ❌ No |
| `/subscriptions/change-plan` | PUT | Upgrade/downgrade plan | ✅ Yes (for paid plans) |
| `/payments/subscription` | POST | Create payment for plan | ✅ Yes |
| `/billing/payment-methods` | POST | Add payment method | ✅ Yes |

---

## Security Considerations

1. **Email Verification Required**: Users must verify email before accessing platform
2. **HMAC Signature**: Verification links use HMAC to prevent tampering
3. **Tenant Isolation**: All data scoped to tenant_id
4. **Middleware Protection**: Routes protected by CheckSubscription and CheckTenantStatus
5. **Trial Abuse Prevention**: One trial per email address

---

## Summary

OBSOLIO's free trial implementation provides a **frictionless onboarding experience**:

✅ **No payment required** to start using the platform
✅ **Automatic subscription creation** upon email verification
✅ **Plan-specific trial periods** (0-30 days based on plan)
✅ **Clear separation** between tenant identity and subscription billing
✅ **Seamless upgrade path** from trial to paid subscription
✅ **Proper middleware protection** to enforce subscription status

This approach maximizes conversion by removing friction while maintaining proper subscription management.
