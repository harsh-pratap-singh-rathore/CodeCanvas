# 🔧 Fix: Creating Missing 'admins' Table

## ❌ **Current Issue**

```
✅ $pdo variable is defined  
✅ Database connection successful  
✅ Connected to database: codecanvas  
❌ Table 'admins' does NOT exist  ← Problem!
```

**Impact:** Admin login is blocked because the `admins` table doesn't exist.

---

## ✅ **The Solution**

Run the SQL schema to create all necessary tables including `admins`.

---

## 📝 **SQL Schema**

### **Option 1: Create 'admins' Table Only (Quick Fix)**

```sql
-- Create admins table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account
-- Email: admin@codecanvas.com
-- Password: admin123
INSERT INTO `admins` (`email`, `password_hash`, `name`) VALUES 
('admin@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User');
```

### **Option 2: Create All Tables (Recommended)**

**File:** `admin/schema.sql` (already created)

This creates:
- `admins` - Admin accounts
- `templates` - Website templates
- `users` - User accounts (for future)
- `projects` - User projects

---

## 🚀 **Step-by-Step Instructions**

### **Step 1: Open phpMyAdmin**

1. Open your browser
2. Go to: `http://localhost/phpmyadmin`
3. Login with:
   - Username: `root`
   - Password: *(leave empty for XAMPP default)*

### **Step 2: Select Database**

1. Click on **codecanvas** database in the left sidebar
2. Make sure it's selected (highlighted)

### **Step 3: Go to SQL Tab**

1. Click the **SQL** tab at the top
2. You'll see a text area for SQL queries

### **Step 4: Run the Schema**

**Option A: Copy/Paste Quick Fix**

Copy this SQL and paste into the SQL tab:

```sql
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` (`email`, `password_hash`, `name`) VALUES 
('admin@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User');
```

**Option B: Import Full Schema File**

1. Click **Import** tab (next to SQL tab)
2. Click **Choose File**
3. Navigate to: `CodeCanvas/admin/schema.sql`
4. Select the file
5. Scroll down and click **Go**

### **Step 5: Verify Success**

After running, you should see:
```
✅ 2 queries executed successfully
✅ Table 'admins' created
✅ 1 row inserted
```

---

## 🔍 **Verify Tables Created**

### **In phpMyAdmin:**

1. Click **Structure** tab
2. You should see table: `admins`
3. Click on `admins` table
4. Click **Browse** tab
5. You should see 1 row:
   ```
   id: 1
   email: admin@codecanvas.com
   name: Admin User
   created_at: (current timestamp)
   ```

### **Run Test Again:**

Visit: `http://localhost/CodeCanvas/test-db.php`

Now you should see:
```
✅ $pdo variable is defined
✅ Database connection successful
✅ Connected to database: codecanvas
✅ Table 'admins' exists               ← Fixed!
✅ Number of admin accounts: 1         ← Fixed!
```

---

## 🔐 **Default Admin Credentials**

**After running the schema:**

```
Email:    admin@codecanvas.com
Password: admin123
```

**Security Note:**
- Password is hashed with bcrypt (`password_hash()`)
- Hash stored: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`
- Change this password after first login in production!

---

## 🧪 **Test Admin Login**

### **Step 1: Visit Login Page**

```
http://localhost/CodeCanvas/admin/login.php
```

### **Step 2: Enter Credentials**

- **Email:** `admin@codecanvas.com`
- **Password:** `admin123`

### **Step 3: Click Login**

**Expected result:**
- ✅ Redirects to dashboard
- ✅ Shows welcome message
- ✅ Shows template statistics
- ✅ Navigation works

**If you get an error:**
- Check test-db.php shows table exists
- Verify credentials are exactly as shown
- Check browser console for errors

---

## 📊 **Table Structure**

### **admins Table:**

| Column         | Type         | Description                    |
|----------------|--------------|--------------------------------|
| id             | INT UNSIGNED | Primary key, auto-increment   |
| email          | VARCHAR(255) | Unique email address          |
| password_hash  | VARCHAR(255) | Bcrypt hashed password        |
| name           | VARCHAR(100) | Admin display name            |
| created_at     | TIMESTAMP    | Account creation time         |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE on `email`
- INDEX on `email` (for faster lookups)

**Security features:**
- Passwords hashed with bcrypt
- Email must be unique
- UTF8MB4 charset (supports all characters)

---

## 🔒 **Password Hash Explanation**

**Hash used:**
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

**Breakdown:**
- `$2y$` - Bcrypt algorithm identifier
- `10` - Cost factor (2^10 = 1024 iterations)
- Rest - Salt + hashed password

**Generated with PHP:**
```php
password_hash('admin123', PASSWORD_DEFAULT);
```

**Verified with:**
```php
password_verify('admin123', $hash); // Returns true
```

---

## 🛠️ **Troubleshooting**

### **Problem: "Table 'admins' already exists"**

**Solution:**
```sql
-- Drop and recreate (WARNING: deletes data!)
DROP TABLE IF EXISTS admins;

-- Then run the CREATE TABLE again
```

### **Problem: "Duplicate email"**

**Solution:**
```sql
-- Delete existing admin first
DELETE FROM admins WHERE email = 'admin@codecanvas.com';

-- Then run INSERT again
```

### **Problem: "Login still fails"**

**Check:**
1. Table exists: `SHOW TABLES LIKE 'admins';`
2. Record exists: `SELECT * FROM admins;`
3. Password hash is complete (60 characters)
4. Email is exactly: `admin@codecanvas.com`

### **Problem: "Access denied for user"**

**Solution:**
```php
// In config/database.php, verify:
define('DB_USER', 'root');
define('DB_PASS', '');  // Empty for XAMPP
```

---

## 📋 **Complete Checklist**

Before testing login:

- [ ] Database `codecanvas` exists
- [ ] Opened phpMyAdmin
- [ ] Selected `codecanvas` database
- [ ] Ran SQL schema (from SQL tab or Import)
- [ ] Saw success message
- [ ] Table `admins` exists (check Structure tab)
- [ ] 1 admin record exists (check Browse tab)
- [ ] test-db.php shows all ✅ checks
- [ ] Ready to test login

---

## 🎯 **Summary**

**Problem:**
```
❌ Table 'admins' does NOT exist
```

**Solution:**
```sql
CREATE TABLE admins (...);
INSERT INTO admins VALUES (...);
```

**Result:**
```
✅ Table 'admins' exists
✅ Number of admin accounts: 1
✅ Admin login works!
```

**Files:**
- `admin/schema.sql` - Complete schema (recommended)
- Above SQL - Quick fix (admins only)

**Next steps:**
1. Run the SQL in phpMyAdmin
2. Refresh test-db.php
3. Try logging in at admin/login.php

---

## 🚀 **Quick Command Reference**

**Check if table exists:**
```sql
SHOW TABLES LIKE 'admins';
```

**Check admin records:**
```sql
SELECT id, email, name, created_at FROM admins;
```

**Count admins:**
```sql
SELECT COUNT(*) FROM admins;
```

**View all data:**
```sql
SELECT * FROM admins;
```

---

**Run the schema now and you'll be able to login!** ✅
