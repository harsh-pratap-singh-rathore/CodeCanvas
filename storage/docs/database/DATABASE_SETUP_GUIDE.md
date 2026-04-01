# CodeCanvas Database Setup Guide

## 🚨 DATABASE RESET INSTRUCTIONS

Your CodeCanvas database has been completely remapped and is ready to be reset. Follow these steps carefully:

---

## Step 1: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Start **Apache** (if not already running)
3. Start **MySQL** (if not already running)
4. Wait for both services to show **green** status

---

## Step 2: Access phpMyAdmin

1. Open your web browser
2. Navigate to: `http://localhost/phpmyadmin`
3. You should see the phpMyAdmin interface

---

## Step 3: Execute Database Reset Script

1. In phpMyAdmin, click on the **SQL** tab at the top
2. Open the file: `c:\xampp\htdocs\CodeCanvas\database\COMPLETE_DATABASE_RESET.sql`
3. Copy the **ENTIRE** contents of that file
4. Paste it into the SQL query box in phpMyAdmin
5. Click the **Go** button (bottom right)
6. Wait for the success message

---

## Step 4: Verify Database Setup

After running the script, you should see:

### ✅ Database Created
- Database name: `codecanvas`
- Character set: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

### ✅ Tables Created (3 tables)
1. **users** - Stores both admin and regular users
2. **templates** - Stores website templates
3. **projects** - Stores user projects

### ✅ Default Data Inserted

**Admin Account:**
- Email: `admin@codecanvas.com`
- Password: `admin123`
- Role: `admin`

**Demo User Account:**
- Email: `user@codecanvas.com`
- Password: `user123`
- Role: `user`

**Templates:** 6 default templates inserted

---

## Step 5: Test the Application

1. Navigate to: `http://localhost/CodeCanvas/public/login.html`
2. Login with admin credentials:
   - Email: `admin@codecanvas.com`
   - Password: `admin123`
3. You should be redirected to the admin dashboard

---

## Database Schema Overview

### Table: `users`
| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| email | VARCHAR(255) | Unique email |
| password_hash | VARCHAR(255) | Bcrypt hashed password |
| name | VARCHAR(100) | User's full name |
| role | ENUM('user', 'admin') | User role |
| status | ENUM('active', 'inactive') | Account status |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

### Table: `templates`
| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| name | VARCHAR(100) | Template name |
| slug | VARCHAR(100) | URL-friendly slug (unique) |
| template_type | ENUM | 'personal', 'portfolio', 'business' |
| folder_path | VARCHAR(255) | Path to template files |
| thumbnail_url | VARCHAR(255) | Preview image URL |
| status | ENUM('active', 'inactive') | Template status |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

### Table: `projects`
| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| user_id | INT UNSIGNED | Foreign key to users |
| template_id | INT UNSIGNED | Foreign key to templates |
| project_name | VARCHAR(255) | Project name |
| project_type | ENUM | 'personal', 'portfolio', 'business' |
| brand_name | VARCHAR(255) | Brand/company name |
| description | TEXT | Project description |
| skills | TEXT | Skills/technologies used |
| contact | VARCHAR(255) | Contact information |
| status | ENUM('draft', 'published') | Project status |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

---

## Troubleshooting

### Error: "Access denied for user 'root'@'localhost'"
**Solution:** Check your database credentials in `config/database.php`
- Default XAMPP: user=`root`, password=`` (empty)

### Error: "Unknown database 'codecanvas'"
**Solution:** The reset script should create it automatically. If not:
1. In phpMyAdmin, click "New" to create database
2. Name it: `codecanvas`
3. Collation: `utf8mb4_unicode_ci`
4. Then run the reset script again

### Error: "Table already exists"
**Solution:** The reset script uses `DROP DATABASE IF EXISTS`, so this shouldn't happen. If it does:
1. Manually delete the `codecanvas` database in phpMyAdmin
2. Run the reset script again

### Login not working
**Solution:** 
1. Verify the database was created successfully
2. Check that the `users` table has data: `SELECT * FROM users;`
3. Clear browser cache and cookies
4. Try the exact credentials: `admin@codecanvas.com` / `admin123`

---

## Database Configuration

The database connection is configured in: `config/database.php`

**Current Settings:**
```php
DB_HOST: localhost
DB_NAME: codecanvas
DB_USER: root
DB_PASS: (empty - XAMPP default)
```

If you need to change these, edit `config/database.php`

---

## Next Steps After Setup

1. ✅ Login to admin panel: `http://localhost/CodeCanvas/admin/dashboard.php`
2. ✅ Manage templates in the admin panel
3. ✅ Login as regular user and create projects
4. ✅ Test the complete workflow

---

## Need Help?

If you encounter any issues:
1. Check XAMPP error logs: `xampp/mysql/data/mysql_error.log`
2. Check PHP error logs in XAMPP control panel
3. Verify all XAMPP services are running
4. Make sure port 3306 (MySQL) is not blocked

---

**Database Reset Complete! 🎉**
