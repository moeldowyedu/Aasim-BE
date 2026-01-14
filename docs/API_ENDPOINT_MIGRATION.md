# API Endpoint Migration Guide

## Overview

This document outlines the consolidation of duplicate API endpoints and provides a migration path for frontend/client applications.

---

## 🔄 Endpoint Consolidation

### Subscription Plans Endpoints

#### ❌ DEPRECATED (Old - Backward Compatible)

```
GET /api/v1/subscription-plans
GET /api/v1/subscription-plans/{id}
```

**Status:** Deprecated (still functional)
**Controller:** `SubscriptionPlanController::class`
**Removal:** Planned for v2.0
**Response Headers:**
- `X-API-Deprecated: true`
- `X-API-Deprecation-Info: Use /api/v1/pricing/plans instead. This endpoint will be removed in v2.0`

---

#### ✅ RECOMMENDED (New - Phase 5)

```
GET /api/v1/pricing/plans
GET /api/v1/pricing/plans/{id}
```

**Status:** Active (recommended)
**Controller:**
- List: `App\Http\Controllers\Api\SubscriptionController::class`
- Show: `SubscriptionPlanController::class`
**Features:**
- Part of Phase 5 pricing infrastructure
- Integrated with agent marketplace
- Enhanced pricing models
- Future-proof architecture

---

## 📋 Migration Path

### For Frontend Applications

#### Step 1: Update API Calls

**Before:**
```javascript
// OLD - Deprecated
const response = await fetch('/api/v1/subscription-plans');
const plans = await response.json();

// Get individual plan
const plan = await fetch('/api/v1/subscription-plans/123');
```

**After:**
```javascript
// NEW - Recommended
const response = await fetch('/api/v1/pricing/plans');
const plans = await response.json();

// Get individual plan
const plan = await fetch('/api/v1/pricing/plans/123');
```

#### Step 2: Check for Deprecation Headers

```javascript
const response = await fetch('/api/v1/subscription-plans');

if (response.headers.get('X-API-Deprecated') === 'true') {
    console.warn('⚠️ Using deprecated endpoint!');
    console.warn(response.headers.get('X-API-Deprecation-Info'));
}
```

#### Step 3: Test & Deploy

1. Update all references to old endpoints
2. Test in staging environment
3. Monitor deprecation warnings
4. Deploy to production
5. Remove old endpoint calls before v2.0

---

## 🗺️ Complete Endpoint Mapping

### Subscription Plans

| Old Endpoint | New Endpoint | Status | Notes |
|--------------|--------------|--------|-------|
| `GET /api/v1/subscription-plans` | `GET /api/v1/pricing/plans` | ✅ Available | List all plans |
| `GET /api/v1/subscription-plans/{id}` | `GET /api/v1/pricing/plans/{id}` | ✅ Available | Get single plan |

### Agent Marketplace

| Endpoint | Status | Notes |
|----------|--------|-------|
| `GET /api/v1/marketplace/agents` | ✅ Active | Browse agents (legacy) |
| `GET /api/v1/pricing/agents/marketplace` | ✅ Active | Browse agents (new) |

**Recommendation:** Use `/api/v1/pricing/agents/marketplace` for new implementations.

---

## 🔍 Response Format Comparison

### Old Endpoint Response

```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Free Plan",
      "type": "organization",
      "tier": "free",
      "price_monthly": 0.00,
      "max_agents": 2,
      "features": {...},
      "limits": {...}
    }
  ]
}
```

### New Endpoint Response

```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Free Plan",
      "type": "organization",
      "tier": "free",
      "price_monthly": 0.00,
      "max_agents": 2,
      "features": {...},
      "limits": {...},
      "billing_cycle": "monthly",
      "included_executions": 1000,
      "overage_price_per_execution": null
    }
  ]
}
```

**Note:** New endpoint includes additional Phase 5 fields.

---

## ⏰ Timeline

| Date | Action |
|------|--------|
| **Now** | Old endpoints marked as deprecated |
| **Now** | New endpoints fully functional |
| **3-6 months** | Grace period for migration |
| **v2.0** | Old endpoints removed |

---

## 🛠️ Testing Deprecation Warnings

### cURL Test

```bash
# Test deprecated endpoint
curl -i http://localhost:8000/api/v1/subscription-plans

# Look for headers:
# X-API-Deprecated: true
# X-API-Deprecation-Info: Use /api/v1/pricing/plans instead...

# Test new endpoint (no deprecation headers)
curl -i http://localhost:8000/api/v1/pricing/plans
```

### Postman Test

1. Send GET request to `/api/v1/subscription-plans`
2. Check "Headers" tab in response
3. Verify `X-API-Deprecated` header exists

---

## 📊 Benefits of Migration

### Why Migrate?

1. **Future-Proof:** New endpoints are part of Phase 5 architecture
2. **Enhanced Features:** Access to new pricing models and agent tiers
3. **Better Structure:** Consolidated under `/pricing` namespace
4. **Performance:** Optimized queries and caching
5. **Consistency:** Aligned with overall API structure

### What You Gain

- ✅ Agent tier-based pricing
- ✅ Usage-based billing support
- ✅ Execution quotas and overages
- ✅ Enhanced agent marketplace integration
- ✅ Better filtering and search capabilities

---

## 🚨 Breaking Changes in v2.0

When old endpoints are removed in v2.0:

### Removed Endpoints

```
❌ GET /api/v1/subscription-plans
❌ GET /api/v1/subscription-plans/{id}
```

### Error Response (v2.0+)

```json
{
  "success": false,
  "error": "Endpoint removed",
  "message": "This endpoint has been removed. Use /api/v1/pricing/plans instead.",
  "code": "ENDPOINT_REMOVED",
  "migration_guide": "https://docs.obsolio.com/api/migration-guide"
}
```

---

## 📖 Code Examples

### JavaScript/TypeScript

```typescript
// api.service.ts
class APIService {
  // ✅ GOOD - Using new endpoint
  async getSubscriptionPlans() {
    const response = await fetch('/api/v1/pricing/plans');
    return await response.json();
  }

  // ❌ BAD - Using deprecated endpoint
  async getSubscriptionPlansOld() {
    const response = await fetch('/api/v1/subscription-plans');
    return await response.json();
  }
}
```

### PHP/Laravel

```php
// ✅ GOOD - Using new endpoint
$plans = Http::get(config('api.base_url') . '/api/v1/pricing/plans');

// ❌ BAD - Using deprecated endpoint
$plans = Http::get(config('api.base_url') . '/api/v1/subscription-plans');
```

### Python

```python
import requests

# ✅ GOOD - Using new endpoint
response = requests.get('https://api.obsolio.com/api/v1/pricing/plans')
plans = response.json()

# ❌ BAD - Using deprecated endpoint
response = requests.get('https://api.obsolio.com/api/v1/subscription-plans')
```

---

## 🔧 Troubleshooting

### Issue: "No deprecation headers showing"

**Solution:** Clear HTTP cache and try again. Deprecation headers are added inline.

### Issue: "New endpoint returns different data"

**Solution:** New endpoint includes additional Phase 5 fields. Update your models to handle new fields gracefully:

```typescript
interface SubscriptionPlan {
  // Existing fields
  id: string;
  name: string;
  tier: string;
  price_monthly: number;

  // New Phase 5 fields (optional for backward compat)
  billing_cycle?: string;
  included_executions?: number;
  overage_price_per_execution?: number;
}
```

### Issue: "Migration timeline too short"

**Contact:** If you need more time to migrate, contact dev-team@obsolio.com

---

## 📞 Support

For questions or issues during migration:

- **Documentation:** [API Docs](https://docs.obsolio.com/api)
- **Email:** dev-team@obsolio.com
- **GitHub Issues:** [Report Issue](https://github.com/obsolio/api/issues)

---

## ✅ Migration Checklist

- [ ] Identify all uses of old endpoints in your codebase
- [ ] Update API calls to use new endpoints
- [ ] Test in development environment
- [ ] Deploy to staging and verify
- [ ] Monitor for deprecation warnings
- [ ] Deploy to production
- [ ] Remove old endpoint references
- [ ] Update API documentation/SDKs
- [ ] Notify dependent services/teams

---

## 📝 Changelog

### v1.1.0 (Current)
- ✅ Added deprecation headers to old endpoints
- ✅ Added `/api/v1/pricing/plans/{id}` endpoint
- ✅ Maintained backward compatibility

### v2.0.0 (Planned)
- ❌ Remove `/api/v1/subscription-plans` endpoints
- ✅ Fully migrate to Phase 5 pricing infrastructure

---

## Related Documentation

- [API Endpoints](./API_ENDPOINTS.md)
- [Agent Tiers](./AGENT_MIGRATIONS_SETUP.md)
- [Subscription Plans](./SUBSCRIPTION_PLANS.md)
