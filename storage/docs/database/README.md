# 📁 CodeCanvas Database Documentation

Welcome to the CodeCanvas database documentation folder. This folder contains everything you need to set up, manage, and understand the database structure.

---

## 🚀 Quick Start (3 Steps)

### 1. Open phpMyAdmin
Go to: **http://localhost/phpmyadmin**

### 2. Run the Reset Script
- Click **"SQL"** tab
- Copy/paste contents of `COMPLETE_DATABASE_RESET.sql`
- Click **"Go"**

### 3. Verify Setup
Go to: **http://localhost/CodeCanvas/verify-database.php**

**Done! ✅** You can now login with `admin@codecanvas.com` / `admin123`

---

## 📚 Documentation Files

### 🔴 **START HERE**
| File | Description | When to Use |
|------|-------------|-------------|
| **RESET_CHECKLIST.md** | Step-by-step checklist | First time setup |
| **CREDENTIALS.md** | Quick reference card | Need login info |

### 📖 **DETAILED GUIDES**
| File | Description | When to Use |
|------|-------------|-------------|
| **DATABASE_SETUP_GUIDE.md** | Comprehensive setup guide | Detailed instructions |
| **DATABASE_ARCHITECTURE.md** | Technical documentation | Understanding structure |
| **REMAPPING_SUMMARY.md** | What was changed | Review of changes |

### 💾 **SQL FILES**
| File | Status | Description |
|------|--------|-------------|
| **COMPLETE_DATABASE_RESET.sql** | ✅ **USE THIS** | Main reset script |
| schema.sql | ❌ Deprecated | Old schema (don't use) |
| unified_auth_schema.sql | ❌ Deprecated | Old auth schema (don't use) |
| QUICK_FIX.sql | ❌ Deprecated | Old quick fix (don't use) |
| create_admins_table.sql | ❌ Deprecated | Old admin table (don't use) |

---

## 🗄️ Database Overview

### Database Name: `codecanvas`

### Tables (3):
1. **users** - Stores admin and regular users
2. **templates** - Stores website templates
3. **projects** - Stores user projects

### Default Accounts:
- **Admin:** admin@codecanvas.com / admin123
- **User:** user@codecanvas.com / user123

---

## 🔧 Configuration

**Database Settings:**
```
Host: localhost
Database: codecanvas
User: root
Password: (empty)
Port: 3306
Charset: utf8mb4
```

**Config File:** `../config/database.php`

---

## ✅ Verification

After running the reset script, verify your setup:

### Option 1: Web Tool (Recommended)
Visit: **http://localhost/CodeCanvas/verify-database.php**

### Option 2: Manual Check (phpMyAdmin)
```sql
-- Check database exists
SHOW DATABASES LIKE 'codecanvas';

-- Check tables exist
USE codecanvas;
SHOW TABLES;

-- Check users
SELECT id, email, name, role FROM users;

-- Check templates
SELECT id, name, template_type, status FROM templates;
```

---

## 🎯 Common Tasks

### Reset Database Completely
1. Open phpMyAdmin
2. Run `COMPLETE_DATABASE_RESET.sql`
3. Verify with verification tool

### Add New User Manually
```sql
INSERT INTO users (email, password_hash, name, role)
VALUES ('user@example.com', '$2y$10$...', 'User Name', 'user');
```
*Note: Generate password hash using PHP `password_hash()`*

### Add New Template
```sql
INSERT INTO templates (name, slug, template_type, folder_path, status)
VALUES ('New Template', 'new-template', 'portfolio', 'templates/new/', 'active');
```

### Check User Projects
```sql
SELECT p.*, t.name as template_name
FROM projects p
JOIN templates t ON p.template_id = t.id
WHERE p.user_id = 1;
```

---

## 🚨 Troubleshooting

### Problem: "Access denied"
**Solution:** Check XAMPP MySQL is running, verify credentials in `config/database.php`

### Problem: "Unknown database"
**Solution:** Run `COMPLETE_DATABASE_RESET.sql` - it creates the database

### Problem: "Table doesn't exist"
**Solution:** Run `COMPLETE_DATABASE_RESET.sql` again

### Problem: Login fails
**Solution:** 
1. Verify users exist: `SELECT * FROM users;`
2. Use exact credentials: `admin@codecanvas.com` / `admin123`
3. Clear browser cache

### Problem: Verification tool shows errors
**Solution:** Read the specific error, usually means database/tables not created

---

## 📊 Database Schema

### USERS Table
```
id, email, password_hash, name, role, status, created_at, updated_at
```

### TEMPLATES Table
```
id, name, slug, template_type, folder_path, thumbnail_url, status, created_at, updated_at
```

### PROJECTS Table
```
id, user_id, template_id, project_name, project_type, brand_name, description,
skills, contact, status, created_at, updated_at
```

**Full schema details:** See `DATABASE_ARCHITECTURE.md`

---

## 🔒 Security

- ✅ Passwords hashed with bcrypt
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Foreign key constraints
- ✅ UTF8MB4 encoding
- ✅ Input validation in PHP code

---

## 📦 Backup & Restore

### Create Backup (phpMyAdmin)
1. Select `codecanvas` database
2. Click "Export" tab
3. Choose "Quick" export method
4. Format: SQL
5. Click "Go"

### Restore Backup (phpMyAdmin)
1. Select `codecanvas` database
2. Click "Import" tab
3. Choose your backup file
4. Click "Go"

### Command Line Backup
```bash
# Backup
mysqldump -u root codecanvas > backup.sql

# Restore
mysql -u root codecanvas < backup.sql
```

---

## 🔗 Related Files

| File | Location | Purpose |
|------|----------|---------|
| Database Config | `../config/database.php` | Connection settings |
| Verification Tool | `../verify-database.php` | Web-based checker |
| Setup Script | `../setup.php` | Alternative setup method |

---

## 📈 Next Steps

After database setup:

1. ✅ **Test Admin Login**
   - URL: http://localhost/CodeCanvas/public/login.html
   - Credentials: admin@codecanvas.com / admin123

2. ✅ **Test User Login**
   - Same URL
   - Credentials: user@codecanvas.com / user123

3. ✅ **Explore Admin Dashboard**
   - Manage templates
   - View statistics

4. ✅ **Create a Test Project**
   - Login as user
   - Create new project
   - Select template

---

## 💡 Tips

- **Always backup** before making schema changes
- **Use verification tool** after any database changes
- **Check phpMyAdmin** for detailed error messages
- **Read logs** in XAMPP control panel if issues occur
- **Keep credentials secure** in production

---

## 📞 Need Help?

1. Check `DATABASE_SETUP_GUIDE.md` for detailed instructions
2. Check `RESET_CHECKLIST.md` for step-by-step process
3. Check `DATABASE_ARCHITECTURE.md` for technical details
4. Run verification tool to diagnose issues

---

## 📝 File Index

```
database/
├── README.md ............................ This file
├── COMPLETE_DATABASE_RESET.sql .......... ✅ Main reset script
├── RESET_CHECKLIST.md ................... ✅ Step-by-step guide
├── DATABASE_SETUP_GUIDE.md .............. ✅ Detailed instructions
├── DATABASE_ARCHITECTURE.md ............. ✅ Technical docs
├── CREDENTIALS.md ....................... ✅ Quick reference
├── REMAPPING_SUMMARY.md ................. ✅ Change summary
├── schema.sql ........................... ❌ Deprecated
├── unified_auth_schema.sql .............. ❌ Deprecated
├── QUICK_FIX.sql ........................ ❌ Deprecated
└── create_admins_table.sql .............. ❌ Deprecated
```

---

**Last Updated:** 2026-02-17  
**Database Version:** 2.0  
**Status:** Production Ready ✅

---

**🎉 Your database is ready to go! Start with RESET_CHECKLIST.md**
