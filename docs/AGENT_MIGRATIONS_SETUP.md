# Agent Tiers Migration Setup Guide

## Overview

This guide explains how to set up the agent tiers infrastructure for OBSOLIO. The agent tiers system enables tiered pricing for AI agents (Basic, Professional, Specialized, Enterprise).

---

## Required Migrations

Three migrations need to be applied in order:

### 1. Create `agent_tiers` Table
**File:** `2026_01_04_140244_create_agent_tiers_table.php`

**Creates:**
```sql
CREATE TABLE agent_tiers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Purpose:** Stores tier definitions (Basic, Professional, Specialized, Enterprise)

---

### 2. Add `tier_id` to `agents` Table
**File:** `2026_01_04_140259_add_tier_id_to_agents_table.php`

**Adds:**
```sql
ALTER TABLE agents
ADD COLUMN tier_id BIGINT NULL,
ADD CONSTRAINT agents_tier_id_foreign
    FOREIGN KEY (tier_id) REFERENCES agent_tiers(id) ON DELETE SET NULL;

CREATE INDEX agents_tier_id_index ON agents(tier_id);
```

**Purpose:** Links agents to their pricing tier

---

### 3. Create `agent_pricing` Table
**File:** `2026_01_04_140302_create_agent_pricing_table.php`

**Creates:**
```sql
CREATE TABLE agent_pricing (
    id BIGSERIAL PRIMARY KEY,
    agent_id UUID NOT NULL,
    tier_id BIGINT NULL,
    monthly_price DECIMAL(10,2) NOT NULL,
    price_per_task DECIMAL(10,4) NULL,
    included_tasks_per_month INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE DEFAULT CURRENT_DATE,
    effective_to DATE NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES agent_tiers(id) ON DELETE SET NULL
);
```

**Purpose:** Stores pricing information for agents per tier

---

## Setup Steps

### Step 1: Install Dependencies

```bash
cd /home/user/Obsolio-BE
composer install
```

### Step 2: Check Current Migration Status

```bash
# Check what migrations have been applied
php artisan migrate:status | grep agent_tier
```

**Expected output if NOT applied:**
```
  Pending  2026_01_04_140244_create_agent_tiers_table
  Pending  2026_01_04_140259_add_tier_id_to_agents_table
  Pending  2026_01_04_140302_create_agent_pricing_table
```

**Expected output if APPLIED:**
```
  Ran  2026_01_04_140244_create_agent_tiers_table
  Ran  2026_01_04_140259_add_tier_id_to_agents_table
  Ran  2026_01_04_140302_create_agent_pricing_table
```

### Step 3: Run Migrations

**Option A: Run all pending migrations**
```bash
php artisan migrate
```

**Option B: Run specific migrations only**
```bash
# Run in order
php artisan migrate --path=database/migrations/2026_01_04_140244_create_agent_tiers_table.php
php artisan migrate --path=database/migrations/2026_01_04_140259_add_tier_id_to_agents_table.php
php artisan migrate --path=database/migrations/2026_01_04_140302_create_agent_pricing_table.php
```

### Step 4: Seed Agent Tiers Data

```bash
php artisan db:seed --class=AgentTiersSeeder
```

**This will insert:**
- [1] Basic - Simple, repetitive tasks with low AI cost
- [2] Professional - Medium complexity tasks requiring analysis
- [3] Specialized - Complex, industry-specific expert tasks
- [4] Enterprise - Custom solutions with fine-tuned models

### Step 5: Verify Setup

```bash
php artisan tinker

# Check agent_tiers table
>>> DB::table('agent_tiers')->get();
=> [
     {id: 1, name: "Basic", description: "Simple, repetitive tasks..."},
     {id: 2, name: "Professional", description: "Medium complexity..."},
     {id: 3, name: "Specialized", description: "Complex, industry-specific..."},
     {id: 4, name: "Enterprise", description: "Custom solutions..."},
   ]

# Check if tier_id column exists on agents table
>>> Schema::hasColumn('agents', 'tier_id');
=> true

# Check agent_pricing table exists
>>> Schema::hasTable('agent_pricing');
=> true
```

---

## Verification Script

We've provided a PHP script to verify the migration status:

```bash
php verify-agent-migrations.php
```

**Expected output:**
```
==============================================
  OBSOLIO Agent Migrations Verification
==============================================

1. Checking database connection...
   ✅ Database connection successful

2. Checking migration status...
   ✅ 2026_01_04_140244_create_agent_tiers_table - APPLIED
   ✅ 2026_01_04_140259_add_tier_id_to_agents_table - APPLIED
   ✅ 2026_01_04_140302_create_agent_pricing_table - APPLIED

3. Checking table existence...
   agent_tiers table: ✅ EXISTS
   agent_pricing table: ✅ EXISTS
   agents.tier_id column: ✅ EXISTS

4. Migration status: ✅ ALL MIGRATIONS APPLIED

5. Checking agent_tiers data...
   ✅ Agent tiers data exists (4 tiers)
      - [1] Basic: Simple, repetitive tasks with low AI cost
      - [2] Professional: Medium complexity tasks requiring analysis
      - [3] Specialized: Complex, industry-specific expert tasks
      - [4] Enterprise: Custom solutions with fine-tuned models

==============================================
  VERIFICATION COMPLETE
==============================================
✅ All agent-related migrations are applied!
✅ Database schema is ready for agent assignment.
```

---

## Manual Database Check (If Laravel Not Available)

If you can't run Laravel commands, check directly in PostgreSQL:

```sql
-- Check if agent_tiers table exists
SELECT EXISTS (
    SELECT FROM information_schema.tables
    WHERE table_schema = 'public'
    AND table_name = 'agent_tiers'
);

-- Check if tier_id column exists on agents
SELECT EXISTS (
    SELECT FROM information_schema.columns
    WHERE table_schema = 'public'
    AND table_name = 'agents'
    AND column_name = 'tier_id'
);

-- Check if agent_pricing table exists
SELECT EXISTS (
    SELECT FROM information_schema.tables
    WHERE table_schema = 'public'
    AND table_name = 'agent_pricing'
);

-- Check agent_tiers data
SELECT * FROM agent_tiers ORDER BY display_order;

-- Check migrations table
SELECT * FROM migrations WHERE migration LIKE '%agent_tier%';
```

---

## Troubleshooting

### Issue 1: Migration Already Exists Error

**Error:** `SQLSTATE[42P07]: Duplicate table: 7 ERROR: relation "agent_tiers" already exists`

**Solution:**
```bash
# Table exists but migration wasn't recorded
# Manually insert into migrations table
php artisan tinker

>>> DB::table('migrations')->insert([
    'migration' => '2026_01_04_140244_create_agent_tiers_table',
    'batch' => DB::table('migrations')->max('batch') + 1
]);

>>> DB::table('migrations')->insert([
    'migration' => '2026_01_04_140259_add_tier_id_to_agents_table',
    'batch' => DB::table('migrations')->max('batch') + 1
]);

>>> DB::table('migrations')->insert([
    'migration' => '2026_01_04_140302_create_agent_pricing_table',
    'batch' => DB::table('migrations')->max('batch') + 1
]);
```

---

### Issue 2: Foreign Key Constraint Error

**Error:** `SQLSTATE[23503]: Foreign key violation`

**Cause:** Trying to run migrations out of order

**Solution:** Run migrations in the correct order:
1. create_agent_tiers_table (first)
2. add_tier_id_to_agents_table (second)
3. create_agent_pricing_table (third)

---

### Issue 3: No Tiers Data

**Symptom:** Tables exist but `agent_tiers` is empty

**Solution:**
```bash
php artisan db:seed --class=AgentTiersSeeder
```

Or manually:
```sql
INSERT INTO agent_tiers (id, name, description, display_order, created_at, updated_at)
VALUES
(1, 'Basic', 'Simple, repetitive tasks with low AI cost', 1, NOW(), NOW()),
(2, 'Professional', 'Medium complexity tasks requiring analysis', 2, NOW(), NOW()),
(3, 'Specialized', 'Complex, industry-specific expert tasks', 3, NOW(), NOW()),
(4, 'Enterprise', 'Custom solutions with fine-tuned models', 4, NOW(), NOW());
```

---

## Impact on Agent Assignment

### With Migrations Applied:

The `CreateTrialSubscription` listener will:
1. ✅ Check for `agent_tiers` table
2. ✅ Query agents with `tier_id = 1` (Basic tier)
3. ✅ Assign free and basic tier agents to new tenants
4. ✅ Store tier information in metadata

### Without Migrations (Backward Compatible):

The listener will:
1. ✅ Skip tier checks
2. ✅ Only query agents with `price_model = 'free'`
3. ✅ Still assign free agents (legacy behavior)
4. ⚠️ No tier-based pricing functionality

---

## Testing After Migration

### Test 1: Check Schema

```bash
php artisan tinker

>>> Schema::hasTable('agent_tiers');
=> true

>>> Schema::hasColumn('agents', 'tier_id');
=> true

>>> Schema::hasTable('agent_pricing');
=> true
```

### Test 2: Verify Data

```bash
>>> \App\Models\AgentTier::count();
=> 4

>>> \App\Models\AgentTier::pluck('name');
=> ["Basic", "Professional", "Specialized", "Enterprise"]
```

### Test 3: Test Agent Assignment

```bash
# Register a new user and verify email
# Then check:
>>> $tenant = \App\Models\Tenant::latest()->first();
>>> $tenant->agents()->count();
=> 2  # Should match plan's max_agents

>>> $tenant->agents()->first()->tier;
=> {id: 1, name: "Basic", ...}
```

---

## Related Documentation

- [Default Agent Assignment](./DEFAULT_AGENT_ASSIGNMENT.md)
- [Subscription Plans](./SUBSCRIPTION_PLANS.md)
- [Agent Management](./AGENT_MANAGEMENT.md)

---

## Quick Checklist

- [ ] Install dependencies: `composer install`
- [ ] Check migration status: `php artisan migrate:status | grep agent_tier`
- [ ] Run migrations: `php artisan migrate`
- [ ] Seed agent tiers: `php artisan db:seed --class=AgentTiersSeeder`
- [ ] Verify setup: `php verify-agent-migrations.php`
- [ ] Test agent assignment: Register new user and check assigned agents

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Verify database: Use SQL queries above
- Contact: dev-team@obsolio.com
