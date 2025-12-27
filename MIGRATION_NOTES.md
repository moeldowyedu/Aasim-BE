# Agent Table Migration Notes

## Overview
This document outlines the changes made to the agents table and related code for implementing asynchronous agent execution.

## Database Schema Changes

### Modified `agents` Table
**Removed Columns:**
- `category` (moved to normalized `agent_categories` table with many-to-many relationship)
- `total_installs` (derived analytics data - should be computed from `tenant_agents` table)
- `rating` (derived from reviews table when implemented)
- `review_count` (derived from reviews table when implemented)
- `is_marketplace` (not required - all agents are now marketplace-enabled by default)

**Added Columns:**
- `runtime_type` (varchar, NOT NULL) - Values: 'n8n' | 'custom'
- `execution_timeout_ms` (integer, default 30000) - Maximum execution time in milliseconds

### New Tables Created

1. **`agent_categories`** - Hierarchical category structure
   - Supports unlimited nesting (parent → children)
   - Fields: id, name, slug, parent_id, is_active

2. **`agent_category_map`** - Many-to-many pivot table
   - Links agents to multiple categories
   - Composite primary key: (agent_id, category_id)

3. **`agent_endpoints`** - Runtime endpoints for async execution
   - Each agent has TWO endpoints: trigger and callback
   - Fields: id, agent_id, type, url, secret, is_active

4. **`agent_runs`** - Track asynchronous executions
   - Status flow: pending → running → completed/failed
   - Fields: id, agent_id, status, input (jsonb), output (jsonb), error

## Eloquent Models Created/Updated

### Updated Models
- **Agent**: Removed old relationships, added new relationships for categories, endpoints, and runs

### New Models
- **AgentCategory**: Recursive relationship for parent/child categories
- **AgentEndpoint**: Endpoints configuration for agent execution
- **AgentRun**: Execution tracking model

## Controllers Requiring Updates

### Files That Need Manual Review/Updates:

#### 1. `app/Http/Controllers/Api/V1/AdminController.php`
**Issues:**
- Line 279-291: `listAgents()` filters by `category` and `is_marketplace` (removed columns)
- Line 311: Validation requires `category` field
- Line 322-323, 342-343: References to `is_marketplace`
- Line 383-384, 400-401: Update validation references `is_marketplace`
- Line 511-520: `agentAnalytics()` uses `category`, `total_installs`, and `rating` (removed columns)

**Required Changes:**
- Replace category filtering with relationship query to `agent_categories`
- Remove `is_marketplace` filters (all agents are marketplace by default)
- Update analytics to compute `total_installs` from `tenant_agents.count()`
- Add `runtime_type` and `execution_timeout_ms` to validation rules
- Update agent creation/update to handle category relationships via `agent_category_map`

#### 2. `app/Http/Controllers/Api/V1/MarketplaceController.php`
**Issues:**
- Line 17-25, 64-68: Uses `category` parameter and `byCategory()` scope
- Line 34, 51, 69: Orders by `rating` (removed column)
- Line 85-94: `categories()` method queries `category` column directly
- Line 110-114: Stats use `category`, `total_installs`, `rating` (removed columns)

**Required Changes:**
- Update category filtering to use `agent_categories` relationship
- Remove rating-based ordering (or compute from reviews when available)
- Rewrite `categories()` to query from `agent_categories` table
- Update stats to compute installs from `tenant_agents` and remove rating

#### 3. `app/Http/Controllers/Api/V1/AgentController.php`
**Needs Review:**
- Check if it references `total_installs`, `rating`, or `is_marketplace`
- Update `install()` method if it calls `incrementInstalls()` (method removed from model)

#### 4. `app/Models/Agent.php`
**Removed Methods:**
- `isMarketplace()` - no longer needed
- `incrementInstalls()` - should be computed from tenant_agents
- `updateRating()` - should be computed from reviews
- `scopeMarketplace()` - removed, all agents are marketplace-enabled
- `scopeByCategory()` - replaced with relationship-based queries

## Data Migration Strategy

### Before Running Migrations

1. **Export existing category data:**
```sql
SELECT DISTINCT category FROM agents WHERE category IS NOT NULL;
```

2. **Create a data migration script to:**
   - Insert distinct categories into `agent_categories`
   - Map existing agents to their categories in `agent_category_map`
   - Set default `runtime_type` for existing agents (e.g., 'custom')

### Migration Script (Run BEFORE schema migration)

```php
// database/migrations/2025_12_27_000000_migrate_agent_categories_data.php
public function up()
{
    // Get all distinct categories
    $categories = DB::table('agents')
        ->select('category')
        ->distinct()
        ->whereNotNull('category')
        ->pluck('category');

    // Create category records
    $categoryMap = [];
    foreach ($categories as $category) {
        $id = (string) Str::uuid();
        DB::table('agent_categories')->insert([
            'id' => $id,
            'name' => ucfirst($category),
            'slug' => Str::slug($category),
            'parent_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryMap[$category] = $id;
    }

    // Map agents to categories
    $agents = DB::table('agents')
        ->select('id', 'category')
        ->whereNotNull('category')
        ->get();

    foreach ($agents as $agent) {
        if (isset($categoryMap[$agent->category])) {
            DB::table('agent_category_map')->insert([
                'agent_id' => $agent->id,
                'category_id' => $categoryMap[$agent->category],
            ]);
        }
    }

    // Set default runtime_type for existing agents
    DB::table('agents')->update(['runtime_type' => 'custom']);
}
```

### Run Order
1. `2025_12_27_000000_migrate_agent_categories_data.php` (data migration)
2. `2025_12_27_000001_modify_agents_table_for_async_execution.php` (schema change)
3. `2025_12_27_000002_create_agent_categories_table.php`
4. `2025_12_27_000003_create_agent_category_map_table.php`
5. `2025_12_27_000004_create_agent_endpoints_table.php`
6. `2025_12_27_000005_create_agent_runs_table.php`

## API Changes

### New Endpoints Added
- `POST /v1/agents/{id}/run` - Execute agent asynchronously
- `GET /v1/agent-runs/{run_id}` - Get execution status
- `POST /v1/webhooks/agents/callback` - Webhook for agent callbacks (no auth required)

### Breaking Changes
- Agents no longer have `category` as a direct field
- `total_installs`, `rating`, `review_count` removed (compute from relationships)
- `is_marketplace` removed (all agents are marketplace-enabled)
- All agents now require `runtime_type` ('n8n' | 'custom')

## Recommendations

1. **Update Frontend:**
   - Category filters should query `/api/v1/marketplace/categories` (which needs to be updated)
   - Agent cards should not display `total_installs` or `rating` until computed properly

2. **Analytics:**
   - Compute total installs: `$agent->tenants()->count()`
   - Implement reviews table and compute rating from there

3. **Testing:**
   - Test agent execution flow end-to-end
   - Verify webhook callback security (secret validation)
   - Test category relationships (create agent with multiple categories)

4. **Documentation:**
   - Document the async execution flow for external agent developers
   - Provide examples of n8n webhook configuration
   - Document callback payload structure

## Async Execution Flow

1. User calls `POST /v1/agents/{id}/run` with input JSON
2. Backend creates `AgentRun` record with status='pending'
3. Backend sends HTTP POST to agent's trigger endpoint
4. Agent responds immediately with "accepted"
5. Backend updates run status to 'running'
6. Agent processes asynchronously
7. Agent sends result to callback webhook: `POST /v1/webhooks/agents/callback`
8. Backend validates secret and updates `AgentRun` with output/error
9. User polls `GET /v1/agent-runs/{run_id}` to check status

## Security Considerations

- Webhook callbacks must validate the secret token
- Secrets should be generated securely (use `Str::random(64)`)
- Consider rate limiting on agent execution endpoints
- Implement timeout handling for long-running agents
- Add logging for all webhook callbacks
