# Admin API Reference - Tenant & Plan Management

Quick reference for console.obsolio.com admin endpoints.

**Authentication:** All endpoints require `system_admin` role and JWT token.

**Base URL:** `https://api.obsolio.com/api/v1/admin`

---

## Tenant Management

### List All Tenants
```http
GET /tenants
```

**Query Parameters:**
- `search` - Search by name, email, or subdomain
- `type` - Filter by type (personal, organization)
- `status` - Filter by status (pending_verification, active, inactive, suspended)
- `plan_id` - Filter by plan UUID
- `has_subscription` - Filter by subscription existence (true/false)
- `sort_by` - Sort field (created_at, name, email, type, status)
- `sort_order` - Sort direction (asc, desc)
- `per_page` - Results per page (default: 20)

**Example:**
```bash
GET /tenants?type=organization&status=active&search=acme&per_page=50
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "tenant-uuid",
        "name": "Acme Corp",
        "email": "admin@acme.com",
        "type": "organization",
        "status": "active",
        "subdomain_preference": "acme",
        "is_on_trial": true,
        "trial_days_remaining": 7,
        "billing_cycle": "monthly",
        "has_active_subscription": true,
        "active_subscription": {
          "id": "subscription-uuid",
          "plan": {
            "name": "Business",
            "tier": "business",
            "price_monthly": 299.00
          }
        },
        "memberships_count": 15,
        "created_at": "2025-01-15T10:30:00Z"
      }
    ],
    "total": 150,
    "per_page": 50
  }
}
```

---

### Get Tenant Statistics
```http
GET /tenants/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_tenants": 1250,
    "by_type": {
      "personal": 800,
      "organization": 450
    },
    "by_status": {
      "active": 1100,
      "pending_verification": 50,
      "inactive": 75,
      "suspended": 25
    },
    "with_active_subscription": 1050,
    "on_trial": 120,
    "subscription_by_plan": [
      {
        "id": "plan-uuid",
        "name": "Free Personal",
        "type": "personal",
        "tier": "free",
        "active_subscriptions": 500
      }
    ],
    "recent_signups": 45
  }
}
```

---

### Get Tenant Details
```http
GET /tenants/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "tenant-uuid",
    "name": "Acme Corp",
    "email": "admin@acme.com",
    "phone": "+1234567890",
    "type": "organization",
    "status": "active",
    "subdomain_preference": "acme",
    "subdomain_activated_at": "2025-01-15T11:00:00Z",
    "is_on_trial": true,
    "trial_days_remaining": 7,
    "billing_cycle": "monthly",
    "has_active_subscription": true,
    "active_subscription": { /* full subscription object */ },
    "subscriptions": [ /* all subscriptions */ ],
    "organization": { /* organization details */ },
    "memberships": [ /* team members */ ],
    "invoices": [ /* recent invoices */ ],
    "payment_methods": [ /* payment methods */ ],
    "memberships_count": 15,
    "invoices_count": 3,
    "subscriptions_count": 2,
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-02-10T14:20:00Z"
  }
}
```

---

### Update Tenant Status
```http
PUT /tenants/{id}/status
```

**Request Body:**
```json
{
  "status": "active",
  "reason": "Payment verified"
}
```

**Status Options:**
- `pending_verification`
- `active`
- `inactive`
- `suspended`

**Response:**
```json
{
  "success": true,
  "message": "Tenant status updated successfully",
  "data": { /* updated tenant */ }
}
```

---

### Change Tenant Subscription
```http
PUT /tenants/{id}/subscription
```

**Request Body:**
```json
{
  "plan_id": "new-plan-uuid",
  "billing_cycle": "annual",
  "starts_immediately": true,
  "prorate": false
}
```

**Fields:**
- `plan_id` (required) - UUID of new plan
- `billing_cycle` (required) - "monthly" or "annual"
- `starts_immediately` (optional, default: false) - Start now or at end of current period
- `prorate` (optional, default: false) - Calculate prorated amount

**Response:**
```json
{
  "success": true,
  "message": "Subscription changed successfully",
  "data": {
    "tenant": { /* updated tenant */ },
    "subscription": {
      "id": "new-subscription-uuid",
      "plan": { /* new plan details */ },
      "status": "active",
      "billing_cycle": "annual",
      "starts_at": "2025-02-15T00:00:00Z",
      "current_period_end": "2026-02-15T00:00:00Z"
    }
  }
}
```

---

### View Subscription History
```http
GET /tenants/{id}/subscriptions
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant": { /* tenant details */ },
    "subscriptions": [
      {
        "id": "subscription-uuid-1",
        "plan": { /* plan details */ },
        "status": "active",
        "billing_cycle": "monthly",
        "starts_at": "2025-02-01T00:00:00Z",
        "created_at": "2025-02-01T00:00:00Z"
      },
      {
        "id": "subscription-uuid-2",
        "plan": { /* previous plan */ },
        "status": "canceled",
        "canceled_at": "2025-02-01T00:00:00Z",
        "created_at": "2025-01-15T00:00:00Z"
      }
    ]
  }
}
```

---

### Extend Trial Period
```http
POST /tenants/{id}/extend-trial
```

**Request Body:**
```json
{
  "days": 30,
  "reason": "Customer requested demo extension"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Trial extended by 30 days",
  "data": {
    "id": "subscription-uuid",
    "status": "trialing",
    "trial_ends_at": "2025-03-17T00:00:00Z"
  }
}
```

---

### Delete Tenant
```http
DELETE /tenants/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Tenant deleted successfully"
}
```

**Note:** This is a soft delete. Active subscriptions are canceled first.

---

## Subscription Plan Management

### List All Plans
```http
GET /subscription-plans
```

**Query Parameters:**
- `type` - Filter by type (personal, organization)
- `active` - Filter by active status (true/false)

**Example:**
```bash
GET /subscription-plans?type=organization&active=true
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "plan-uuid",
      "name": "Business",
      "type": "organization",
      "tier": "business",
      "price_monthly": 299.00,
      "price_annual": 2990.00,
      "max_users": 50,
      "max_agents": 100,
      "storage_gb": 200,
      "trial_days": 14,
      "is_active": true,
      "is_published": true,
      "is_archived": false,
      "plan_version": "1.0.0",
      "display_order": 3,
      "features": ["Feature 1", "Feature 2"],
      "limits": {
        "agents_per_month": 20000,
        "api_calls_per_day": 10000
      },
      "description": "For growing businesses"
    }
  ]
}
```

---

### Create Subscription Plan
```http
POST /subscription-plans
```

**Request Body:**
```json
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
  "features": [
    "All Pro features",
    "Up to 20 team members",
    "Team Collaboration"
  ],
  "limits": {
    "agents_per_month": 10000,
    "api_calls_per_day": 5000
  },
  "description": "Perfect for growing startups",
  "is_published": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subscription plan created successfully",
  "data": { /* created plan */ }
}
```

---

### Update Subscription Plan
```http
PUT /subscription-plans/{id}
```

**Request Body (all fields optional):**
```json
{
  "name": "Updated Plan Name",
  "price_monthly": 199.00,
  "price_annual": 1990.00,
  "max_users": 25,
  "is_published": true,
  "is_archived": false,
  "display_order": 2
}
```

**Response:**
```json
{
  "success": true,
  "message": "Plan updated successfully",
  "data": { /* updated plan */ }
}
```

---

### Archive Plan
```http
PUT /subscription-plans/{id}
```

**Request Body:**
```json
{
  "is_archived": true
}
```

**Note:** Archived plans won't show in public listings but existing subscriptions continue.

---

### Delete Plan
```http
DELETE /subscription-plans/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Plan deleted successfully"
}
```

**Error if active subscriptions exist:**
```json
{
  "success": false,
  "message": "Cannot delete plan with 15 active subscriptions"
}
```

---

## Analytics & Monitoring

### Analytics Overview
```http
GET /analytics/overview
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_tenants": 1250,
    "active_tenants": 1100,
    "total_users": 5430,
    "total_subscriptions": 1050,
    "total_revenue_monthly": 125000.00,
    "total_agents": 250,
    "active_agents": 235,
    "featured_agents": 15
  }
}
```

---

### Revenue Analytics
```http
GET /analytics/revenue?period=month
```

**Query Parameters:**
- `period` - day, week, month, year

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_revenue": 125000.00,
      "total_invoices": 1050,
      "average_invoice": 119.05,
      "period": "month"
    },
    "daily_breakdown": [
      {
        "date": "2025-02-01",
        "total": 4250.00,
        "count": 35
      }
    ]
  }
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Cannot delete plan with active subscriptions"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Insufficient permissions. System admin required."
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Tenant not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "The given data was invalid",
  "errors": {
    "plan_id": ["The plan id field is required."],
    "billing_cycle": ["The billing cycle must be monthly or annual."]
  }
}
```

### 500 Server Error
```json
{
  "success": false,
  "message": "Failed to change subscription",
  "error": "Detailed error message"
}
```

---

## Authentication

**Header:**
```
Authorization: Bearer {jwt_token}
```

**Required Role:** `system_admin`

**Example:**
```bash
curl -X GET "https://api.obsolio.com/api/v1/admin/tenants" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

---

**Last Updated:** 2025-12-25
**API Version:** 1.0.0

