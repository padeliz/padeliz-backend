#!/bin/bash

# Wait for MySQL to be ready using a more reliable method
echo "Waiting for MySQL to be ready..."
for i in {1..30}; do
    if mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; then
        echo "MySQL is ready!"
        break
    fi
    echo "Attempt $i/30: MySQL not ready yet, waiting..."
    sleep 2
done

# Final check
if ! mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; then
    echo "Error: MySQL failed to start within 60 seconds"
    exit 1
fi

echo "Setting up databases and users..."

# Use environment variables passed from docker-compose
DB_TESTING_DATABASE="${DB_TESTING_DATABASE:-db_padeliz_testing}"
DB_TESTING_USERNAME="${DB_TESTING_USERNAME:-db_user}"
DB_TESTING_PASSWORD="${DB_TESTING_PASSWORD:-db_pass}"

# Also get main database info from environment
MAIN_DATABASE="${MYSQL_DATABASE:-db_padeliz}"
MAIN_USER="${MYSQL_USER:-db_user}"
MAIN_PASSWORD="${MYSQL_PASSWORD:-db_pass}"

echo "Main database: $MAIN_DATABASE"
echo "Main user: $MAIN_USER"
echo "Testing database: $DB_TESTING_DATABASE"
echo "Testing user: $DB_TESTING_USERNAME"

# Create both databases and ensure user has access to both databases
mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" << EOF
-- Create main database if it doesn't exist
CREATE DATABASE IF NOT EXISTS \`$MAIN_DATABASE\`;

-- Create testing database
CREATE DATABASE IF NOT EXISTS \`$DB_TESTING_DATABASE\`;

-- Ensure the main user exists and has proper privileges
CREATE USER IF NOT EXISTS '$MAIN_USER'@'%' IDENTIFIED BY '$MAIN_PASSWORD';
GRANT ALL PRIVILEGES ON \`$MAIN_DATABASE\`.* TO '$MAIN_USER'@'%';
GRANT ALL PRIVILEGES ON \`$DB_TESTING_DATABASE\`.* TO '$MAIN_USER'@'%';

-- If testing user is different from main user, create it too
CREATE USER IF NOT EXISTS '$DB_TESTING_USERNAME'@'%' IDENTIFIED BY '$DB_TESTING_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_TESTING_DATABASE\`.* TO '$DB_TESTING_USERNAME'@'%';

FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo "Database setup completed successfully!"
    echo "Main database '$MAIN_DATABASE' is accessible by user '$MAIN_USER'"
    echo "Testing database '$DB_TESTING_DATABASE' is accessible by user '$DB_TESTING_USERNAME'"

    # Verify both databases are accessible
    if mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" -e "USE \`$MAIN_DATABASE\`" >/dev/null 2>&1; then
        echo "✓ Main database verification: OK"
    else
        echo "✗ Main database verification: FAILED"
    fi

    if mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" -e "USE \`$DB_TESTING_DATABASE\`" >/dev/null 2>&1; then
        echo "✓ Testing database verification: OK"
    else
        echo "✗ Testing database verification: FAILED"
    fi
else
    echo "Error setting up databases"
    exit 1
fi
