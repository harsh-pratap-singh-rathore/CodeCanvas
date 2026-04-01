# Database Setup Instructions

## Prerequisites
- XAMPP installed with MySQL running
- PHP 7.4 or higher

## Setup Steps

### 1. Create Database

Open phpMyAdmin (http://localhost/phpmyadmin) or MySQL command line:

```sql
CREATE DATABASE codecanvas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Schema

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin
2. Select `codecanvas` database
3. Click "Import" tab
4. Choose `schema.sql` file
5. Click "Go"

**Option B: Using MySQL Command Line**
```bash
mysql -u root -p codecanvas < schema.sql
```

### 3. Configure Database Connection

Edit `config/database.php` if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'codecanvas');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password if any
```

### 4. Test Connection

Uncomment the test lines in `config/database.php`:
```php
$db = getDBConnection();
echo "Database connected successfully!";
```

Then visit: `http://localhost/CodeCanvas/config/database.php`

If you see "Database connected successfully!" - you're ready to go.

### 5. Remove Test Code

Comment out or remove the test connection code before deploying.

## Database Structure

### Tables Overview

- **users** - User accounts and authentication
- **projects** - User-created websites
- **tags** - Custom tags for organizing projects
- **project_tags** - Many-to-many relationship between projects and tags

### Key Features

- Foreign key constraints for data integrity
- Cascading deletes (when user deleted, their projects/tags are too)
- Proper indexes for fast queries
- Soft deletes for projects (trashed_at field)
- Timestamps for tracking creation/updates

## Sample Data

Uncomment the sample data section in `schema.sql` to populate with test data for dashboard testing.

## Security Notes

1. Change database password in production
2. Never commit `config/database.php` with real credentials
3. Use environment variables in production
4. Enable prepared statements (already configured)
5. Use password_hash() for user passwords (bcrypt)

---

Ready to build!
