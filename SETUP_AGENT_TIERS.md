# 🚀 Agent Tiers Setup - Quick Start

This document provides a quick overview of setting up the agent tiers feature for OBSOLIO.

---

## 📋 What Are Agent Tiers?

Agent Tiers classify AI agents into pricing categories:
- **Basic** (Free) - Simple, repetitive tasks
- **Professional** ($) - Medium complexity tasks
- **Specialized** ($$) - Complex, industry-specific tasks
- **Enterprise** ($$$) - Custom fine-tuned solutions

---

## ⚡ Quick Setup (5 Steps)

### 1️⃣ Setup Environment

```bash
# Copy environment file
cp .env.example .env

# Install dependencies
composer install

# Generate app key
php artisan key:generate
```

### 2️⃣ Configure Database

Edit `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=obsolio_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3️⃣ Check Migration Status

```bash
# Option A: Using shell script (no Laravel needed)
./check-migrations.sh

# Option B: Using PHP script (requires composer install)
php verify-agent-migrations.php

# Option C: Using Laravel
php artisan migrate:status | grep agent_tier
```

### 4️⃣ Run Migrations

```bash
# Run all pending migrations
php artisan migrate

# Or run specific ones
php artisan migrate --path=database/migrations/2026_01_04_140244_create_agent_tiers_table.php
php artisan migrate --path=database/migrations/2026_01_04_140259_add_tier_id_to_agents_table.php
php artisan migrate --path=database/migrations/2026_01_04_140302_create_agent_pricing_table.php
```

### 5️⃣ Seed Tier Data

```bash
php artisan db:seed --class=AgentTiersSeeder
```

---

## ✅ Verification

After setup, verify everything works:

```bash
php artisan tinker

# Check agent_tiers table
>>> \App\Models\AgentTier::all();

# Expected output:
=> [
     {id: 1, name: "Basic", ...},
     {id: 2, name: "Professional", ...},
     {id: 3, name: "Specialized", ...},
     {id: 4, name: "Enterprise", ...},
   ]
```

---

## 🎯 What This Enables

Once set up, the system will:

✅ **Automatically assign default agents** to new tenants
✅ **Track agent tiers** in metadata
✅ **Enable tier-based pricing** for agents
✅ **Support smart agent selection** based on plan limits

---

## 📊 Migration Files

| File | Purpose |
|------|---------|
| `2026_01_04_140244_create_agent_tiers_table.php` | Creates `agent_tiers` table |
| `2026_01_04_140259_add_tier_id_to_agents_table.php` | Adds `tier_id` column to `agents` |
| `2026_01_04_140302_create_agent_pricing_table.php` | Creates `agent_pricing` table |

---

## 🔧 Verification Tools

We've provided three verification tools:

### 1. Shell Script (Fastest)
```bash
./check-migrations.sh
```
- ✅ No Laravel dependencies needed
- ✅ Quick database check
- ✅ Works with just PostgreSQL client

### 2. PHP Script
```bash
php verify-agent-migrations.php
```
- ✅ Detailed verification
- ✅ Can auto-run migrations
- ✅ Requires composer install

### 3. Manual Check
```bash
php artisan migrate:status
php artisan tinker
>>> Schema::hasTable('agent_tiers');
```

---

## 🐛 Troubleshooting

### Problem: Migrations already exist

**Error:** `Table 'agent_tiers' already exists`

**Solution:** The table was created manually. Mark migration as run:
```bash
php artisan tinker
>>> DB::table('migrations')->insert([
    'migration' => '2026_01_04_140244_create_agent_tiers_table',
    'batch' => DB::table('migrations')->max('batch') + 1
]);
```

### Problem: No tier data

**Error:** `agent_tiers` table is empty

**Solution:**
```bash
php artisan db:seed --class=AgentTiersSeeder
```

### Problem: Foreign key error

**Error:** `Foreign key constraint fails`

**Solution:** Run migrations in order (see Step 4 above)

---

## 📚 Documentation

- **Full Setup Guide:** `docs/AGENT_MIGRATIONS_SETUP.md`
- **Agent Assignment:** `docs/DEFAULT_AGENT_ASSIGNMENT.md`
- **Testing Guide:** `tests/Feature/AgentAssignmentTest.php`

---

## 🚦 Current Status

Based on the codebase analysis:

| Component | Status |
|-----------|--------|
| Migration files | ✅ Present |
| Seeder file | ✅ Present |
| Models (AgentTier, AgentPricing) | ✅ Created |
| CreateTrialSubscription listener | ✅ Updated with tier support |
| Backward compatibility | ✅ Built-in |
| Documentation | ✅ Complete |
| Tests | ✅ Included |

**Next Step:** Run migrations when database is configured

---

## 💡 Notes

- **Backward Compatible:** Code works with or without migrations
- **Safe to Run:** Won't break existing functionality
- **No Data Loss:** Migrations are additive only
- **Tested:** Full test suite included

---

## 🆘 Need Help?

1. Check detailed docs: `docs/AGENT_MIGRATIONS_SETUP.md`
2. Run verification: `./check-migrations.sh`
3. Check logs: `storage/logs/laravel.log`
4. Contact: dev-team@obsolio.com

---

## ⏭️ After Setup

Once migrations are complete:

1. ✅ Test new user registration
2. ✅ Verify agents are auto-assigned
3. ✅ Check invoices are generated
4. ✅ Review logs for any issues

**Your OBSOLIO backend is now ready with full agent tier support! 🎉**
