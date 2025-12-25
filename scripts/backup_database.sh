#!/bin/bash

# Database Backup Script for OBSOLIO
# Run this before executing major migrations

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Set backup directory
BACKUP_DIR="./database/backups"
mkdir -p "$BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/obsolio_backup_${TIMESTAMP}.sql"

# PostgreSQL backup
if [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "Creating PostgreSQL backup..."
    PGPASSWORD=$DB_PASSWORD pg_dump \
        -h $DB_HOST \
        -p $DB_PORT \
        -U $DB_USERNAME \
        -d $DB_DATABASE \
        -F p \
        -f "$BACKUP_FILE"

    if [ $? -eq 0 ]; then
        # Compress the backup
        gzip "$BACKUP_FILE"
        echo "✅ Backup created successfully: ${BACKUP_FILE}.gz"

        # Keep only last 10 backups
        cd "$BACKUP_DIR"
        ls -t obsolio_backup_*.sql.gz | tail -n +11 | xargs -r rm
        echo "✅ Old backups cleaned up"
    else
        echo "❌ Backup failed!"
        exit 1
    fi
fi

# SQLite backup (for development)
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Creating SQLite backup..."
    cp "$DB_DATABASE" "${BACKUP_DIR}/obsolio_backup_${TIMESTAMP}.sqlite"

    if [ $? -eq 0 ]; then
        gzip "${BACKUP_DIR}/obsolio_backup_${TIMESTAMP}.sqlite"
        echo "✅ Backup created successfully"
    else
        echo "❌ Backup failed!"
        exit 1
    fi
fi

echo "Backup process completed!"
