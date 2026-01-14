#!/bin/bash

# Script to check agent_tiers migration status
# Can be run without Laravel dependencies

echo ""
echo "=============================================="
echo "  OBSOLIO Agent Migrations Check"
echo "=============================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ ERROR: .env file not found${NC}"
    echo "   Copy .env.example to .env and configure database"
    exit 1
fi

# Extract database connection from .env
DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d '=' -f2)
DB_HOST=$(grep "^DB_HOST=" .env | cut -d '=' -f2)
DB_PORT=$(grep "^DB_PORT=" .env | cut -d '=' -f2)
DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2)
DB_USERNAME=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2)
DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f2)

echo "1. Database Configuration:"
echo "   Connection: $DB_CONNECTION"
echo "   Host: $DB_HOST:$DB_PORT"
echo "   Database: $DB_DATABASE"
echo ""

# Check if psql is available
if ! command -v psql &> /dev/null; then
    echo -e "${RED}❌ psql not found${NC}"
    echo "   Install PostgreSQL client: sudo apt-get install postgresql-client"
    echo ""
    echo "Alternative: Use the verification script"
    echo "   php verify-agent-migrations.php"
    exit 1
fi

# Build psql connection string
export PGPASSWORD="$DB_PASSWORD"
PSQL_CMD="psql -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d $DB_DATABASE -t -A"

echo "2. Checking database connection..."
if $PSQL_CMD -c "SELECT 1;" > /dev/null 2>&1; then
    echo -e "   ${GREEN}✅ Database connection successful${NC}"
else
    echo -e "   ${RED}❌ Database connection failed${NC}"
    echo "   Check your .env configuration"
    exit 1
fi

echo ""
echo "3. Checking migration status..."

# Check agent_tiers table
AGENT_TIERS_EXISTS=$($PSQL_CMD -c "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'agent_tiers');" 2>/dev/null)

if [ "$AGENT_TIERS_EXISTS" = "t" ]; then
    echo -e "   ${GREEN}✅ agent_tiers table exists${NC}"
else
    echo -e "   ${RED}❌ agent_tiers table missing${NC}"
fi

# Check tier_id column on agents
TIER_ID_EXISTS=$($PSQL_CMD -c "SELECT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'tier_id');" 2>/dev/null)

if [ "$TIER_ID_EXISTS" = "t" ]; then
    echo -e "   ${GREEN}✅ agents.tier_id column exists${NC}"
else
    echo -e "   ${RED}❌ agents.tier_id column missing${NC}"
fi

# Check agent_pricing table
AGENT_PRICING_EXISTS=$($PSQL_CMD -c "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'agent_pricing');" 2>/dev/null)

if [ "$AGENT_PRICING_EXISTS" = "t" ]; then
    echo -e "   ${GREEN}✅ agent_pricing table exists${NC}"
else
    echo -e "   ${RED}❌ agent_pricing table missing${NC}"
fi

echo ""
echo "4. Checking migrations table..."

# Check if migrations have been recorded
MIGRATION1=$($PSQL_CMD -c "SELECT COUNT(*) FROM migrations WHERE migration = '2026_01_04_140244_create_agent_tiers_table';" 2>/dev/null)
MIGRATION2=$($PSQL_CMD -c "SELECT COUNT(*) FROM migrations WHERE migration = '2026_01_04_140259_add_tier_id_to_agents_table';" 2>/dev/null)
MIGRATION3=$($PSQL_CMD -c "SELECT COUNT(*) FROM migrations WHERE migration = '2026_01_04_140302_create_agent_pricing_table';" 2>/dev/null)

if [ "$MIGRATION1" = "1" ]; then
    echo -e "   ${GREEN}✅ create_agent_tiers_table - RECORDED${NC}"
else
    echo -e "   ${RED}❌ create_agent_tiers_table - NOT RECORDED${NC}"
fi

if [ "$MIGRATION2" = "1" ]; then
    echo -e "   ${GREEN}✅ add_tier_id_to_agents_table - RECORDED${NC}"
else
    echo -e "   ${RED}❌ add_tier_id_to_agents_table - NOT RECORDED${NC}"
fi

if [ "$MIGRATION3" = "1" ]; then
    echo -e "   ${GREEN}✅ create_agent_pricing_table - RECORDED${NC}"
else
    echo -e "   ${RED}❌ create_agent_pricing_table - NOT RECORDED${NC}"
fi

echo ""

# Check agent_tiers data
if [ "$AGENT_TIERS_EXISTS" = "t" ]; then
    echo "5. Checking agent_tiers data..."
    TIER_COUNT=$($PSQL_CMD -c "SELECT COUNT(*) FROM agent_tiers;" 2>/dev/null)

    if [ "$TIER_COUNT" -gt "0" ]; then
        echo -e "   ${GREEN}✅ Agent tiers data exists ($TIER_COUNT tiers)${NC}"

        # Show tiers
        $PSQL_CMD -c "SELECT '      - [' || id || '] ' || name || ': ' || description FROM agent_tiers ORDER BY display_order;" 2>/dev/null | while read -r line; do
            echo "$line"
        done
    else
        echo -e "   ${YELLOW}⚠️  Agent tiers table is empty${NC}"
        echo "   Run: php artisan db:seed --class=AgentTiersSeeder"
    fi
else
    echo "5. Skipping data check (agent_tiers table doesn't exist)"
fi

echo ""
echo "=============================================="
echo "  SUMMARY"
echo "=============================================="

# Determine overall status
ALL_GOOD=true

if [ "$AGENT_TIERS_EXISTS" != "t" ]; then
    ALL_GOOD=false
fi

if [ "$TIER_ID_EXISTS" != "t" ]; then
    ALL_GOOD=false
fi

if [ "$AGENT_PRICING_EXISTS" != "t" ]; then
    ALL_GOOD=false
fi

if [ "$ALL_GOOD" = true ]; then
    echo -e "${GREEN}✅ All agent-related migrations are applied!${NC}"
    echo -e "${GREEN}✅ Database schema is ready for agent assignment.${NC}"

    if [ "$TIER_COUNT" = "0" ] || [ -z "$TIER_COUNT" ]; then
        echo ""
        echo -e "${YELLOW}⚠️  Next step: Seed agent tiers data${NC}"
        echo "   Run: php artisan db:seed --class=AgentTiersSeeder"
    fi
else
    echo -e "${RED}⚠️  Some migrations are missing.${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Install dependencies: composer install"
    echo "2. Run migrations: php artisan migrate"
    echo "3. Seed tiers: php artisan db:seed --class=AgentTiersSeeder"
    echo ""
    echo "Or run: php verify-agent-migrations.php"
fi

echo ""

# Clean up
unset PGPASSWORD

exit $([ "$ALL_GOOD" = true ] && echo 0 || echo 1)
