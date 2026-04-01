# 🔐 CodeCanvas Database Credentials

## Quick Reference Card

---

## Database Configuration

| Setting | Value |
|---------|-------|
| **Host** | `localhost` |
| **Database Name** | `codecanvas` |
| **Username** | `root` |
| **Password** | *(empty)* |
| **Port** | `3306` (default) |
| **Charset** | `utf8mb4` |
| **Collation** | `utf8mb4_unicode_ci` |

---

## Default User Accounts

### 👨‍💼 Admin Account
```
Email: admin@codecanvas.com
Password: admin123
Role: admin
```

### 👤 Demo User Account
```
Email: user@codecanvas.com
Password: user123
Role: user
```

---

## Database Tables

### 1️⃣ `users`
- Stores both admin and regular users
- Fields: id, email, password_hash, name, role, status, created_at, updated_at

### 2️⃣ `templates`
- Stores website templates
- Fields: id, name, slug, template_type, folder_path, thumbnail_url, status, created_at, updated_at

### 3️⃣ `projects`
- Stores user projects
- Fields: id, user_id, template_id, project_name, project_type, brand_name, description, skills, contact, status, created_at, updated_at

---

## Important URLs

| Purpose | URL |
|---------|-----|
| **phpMyAdmin** | http://localhost/phpmyadmin |
| **Database Verification** | http://localhost/CodeCanvas/verify-database.php |
| **Login Page** | http://localhost/CodeCanvas/public/login.html |
| **Admin Dashboard** | http://localhost/CodeCanvas/admin/dashboard.php |
| **User Dashboard** | http://localhost/CodeCanvas/app/dashboard.php |

---

## Files to Use

✅ **Use These:**
- `database/COMPLETE_DATABASE_RESET.sql` - Main reset script
- `database/DATABASE_SETUP_GUIDE.md` - Setup instructions
- `verify-database.php` - Database verification tool

❌ **Don't Use These (Deprecated):**
- `database/schema.sql`
- `database/unified_auth_schema.sql`
- `database/QUICK_FIX.sql`
- `database/create_admins_table.sql`

---

## Quick Setup Steps

1. **Start XAMPP** - Start Apache and MySQL
2. **Open phpMyAdmin** - http://localhost/phpmyadmin
3. **Run Reset Script** - Copy/paste `COMPLETE_DATABASE_RESET.sql` into SQL tab
4. **Verify Setup** - Visit http://localhost/CodeCanvas/verify-database.php
5. **Login** - Use admin credentials to test

---

## Need Help?

See `database/DATABASE_SETUP_GUIDE.md` for detailed instructions and troubleshooting.

---

**Last Updated:** 2026-02-17
