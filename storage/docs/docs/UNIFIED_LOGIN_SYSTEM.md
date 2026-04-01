# 🔐 Unified Login System - Complete Guide

## 🎯 Overview

**One login form for both users and admins**
- Single `users` table with `role` field
- Role-based redirect after login
- Secure password hashing
- Protected admin routes

---

## 📁 Files Created/Updated

### **New Files:**
- `unified_auth_schema.sql` - Database schema
- `login.php` - Unified login page
- `admin/require_admin.php` - Admin route protection
- `require_auth.php` - User route protection

### **Updated Files:**
- `admin/dashboard.php` - Uses new auth
- `admin/templates.php` - Uses new auth
- `admin/template-add.php` - Uses new auth
- `admin/template-edit.php` - Uses new auth
- `admin/logout.php` - Redirects to unified login

---

## 🗄️ Database Schema

### **users Table:**

```sql
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',  ← Key field
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP,
    `updated_at` TIMESTAMP
);
```

**Key points:**
- ✅ `role` field determines user type
- ✅ `admin` → admin dashboard
- ✅ `user` → user dashboard
- ✅ `status` allows account activation/deactivation

---

## 🚀 Setup Instructions

### **Step 1: Run Schema**

**In phpMyAdmin:**
1. Select `codecanvas` database
2. Go to **SQL** tab
3. Open `unified_auth_schema.sql`
4. Copy all SQL
5. Paste and click **Go**

**What it does:**
- Drops old `admins` table
- Creates unified `users` table
- Inserts 2 default accounts (admin + user)
- Updates `projects` table to reference `users`

### **Step 2: Test Login**

Visit: `http://localhost/CodeCanvas/login.php`

**Test Accounts:**

**Admin:**
```
Email: admin@codecanvas.com
Password: admin123
→ Redirects to /admin/dashboard.php
```

**Regular User:**
```
Email: user@codecanvas.com
Password: user123
→ Redirects to /dashboard.php
```

---

## 🔐 How It Works

### **Login Flow:**

```
User visits login.php
    ↓
Enters email + password
    ↓
PHP queries users table
    ↓
Verifies password with password_verify()
    ↓
Checks user status (active/inactive)
    ↓
Sets session variables:
  - user_id
  - user_email
  - user_name
  - user_role  ← Important!
    ↓
Redirects based on role:
  - admin → admin/dashboard.php
  - user → dashboard.php
```

### **Session Variables:**

```php
$_SESSION['user_id']    // User ID
$_SESSION['user_email']  // Email
$_SESSION['user_name']   // Display name
$_SESSION['user_role']   // 'admin' or 'user'
```

---

## 🛡️ Route Protection

### **Admin Pages:**

At the top of every admin page:

```php
<?php
session_start();
require_once '../config/database.php';
require_once 'require_admin.php';  // ← Protects admin routes
```

**What `require_admin.php` does:**
1. Checks if user is logged in
2. Checks if user role is 'admin'
3. If not logged in → redirect to login.php
4. If not admin → redirect to dashboard.php

### **User Pages:**

At the top of user pages:

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'require_auth.php';  // ← Requires authentication
```

**What `require_auth.php` does:**
1. Checks if user is logged in
2. If not → redirect to login.php
3. Allows both admins and users

---

## 📊 User Roles

| Role  | Access | Dashboard | Can Access Admin? |
|-------|--------|-----------|-------------------|
| admin | Full   | /admin/dashboard.php | ✅ Yes |
| user  | Limited | /dashboard.php | ❌ No |

**Role checking:**
```php
if ($_SESSION['user_role'] === 'admin') {
    // Admin-only code
}
```

---

## 🔧 Code Examples

### **1. Login Logic (login.php):**

```php
// Get user by email
$stmt = $pdo->prepare("SELECT id, email, password_hash, name, role, status FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verify password
if (password_verify($password, $user['password_hash'])) {
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    
    // Role-based redirect
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
}
```

### **2. Admin Route Protection:**

```php
// admin/require_admin.php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}
```

### **3. User Route Protection:**

```php
// require_auth.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
```

### **4. Logout:**

```php
<?php
session_start();
session_destroy();
header('Location: ../login.php');
exit;
```

---

## ✅ Security Features

### **1. Password Hashing:**
```php
// When creating user:
$hash = password_hash($password, PASSWORD_DEFAULT);

// When verifying:
password_verify($password, $hash);
```

### **2. Prepared Statements:**
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

### **3. Status Check:**
```php
if ($user['status'] !== 'active') {
    $error = 'Account is inactive';
}
```

### **4. Session Protection:**
```php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
```

---

## 🧪 Testing

### **Test Admin Login:**

1. Visit: `http://localhost/CodeCanvas/login.php`
2. Email: `admin@codecanvas.com`
3. Password: `admin123`
4. Click **Sign In**
5. **Should redirect to:** `/admin/dashboard.php`
6. Should see: "Welcome, Admin User"

### **Test User Login:**

1. Visit: `http://localhost/CodeCanvas/login.php`
2. Email: `user@codecanvas.com`
3. Password: `user123`
4. Click **Sign In**
5. **Should redirect to:** `/dashboard.php`

### **Test Admin Protection:**

1. Login as regular user
2. Try to visit: `/admin/dashboard.php`
3. **Should redirect to:** `/dashboard.php`
4. ✅ Admin routes are protected!

### **Test Logout:**

1. Click logout
2. **Should redirect to:** `/login.php`
3. Try visiting admin pages
4. **Should redirect to:** `/login.php`
5. ✅ Session cleared!

---

## 🔄 Migration from Old System

### **What Changed:**

**Before (Separate):**
- `admins` table
- `users` table
- `admin/login.php` (admin only)
- `login.php` (users only)

**After (Unified):**
- ✅ Single `users` table
- ✅ `role` field ('admin' | 'user')
- ✅ Single `login.php` (both)
- ✅ Role-based redirect

### **Session Variables Changed:**

**Before:**
```php
$_SESSION['admin_id']
$_SESSION['admin_email']
$_SESSION['admin_name']
```

**After:**
```php
$_SESSION['user_id']
$_SESSION['user_email']
$_SESSION['user_name']
$_SESSION['user_role']  // 'admin' or 'user'
```

---

## 📋 Quick Reference

### **Default Accounts:**

| Email | Password | Role |
|-------|----------|------|
| admin@codecanvas.com | admin123 | admin |
| user@codecanvas.com | user123 | user |

### **URLs:**

| Page | URL | Access |
|------|-----|--------|
| Login | /login.php | Public |
| User Dashboard | /dashboard.php | Authenticated |
| Admin Dashboard | /admin/dashboard.php | Admin only |

### **Protection Files:**

| File | Purpose | Usage |
|------|---------|-------|
| require_admin.php | Admin route protection | Admin pages |
| require_auth.php | User authentication | User pages |

---

## 🎯 Benefits

**✅ Single Login Form**
- Users and admins use same form
- Cleaner UX
- No confusion

**✅ Role-Based Access**
- Automatic redirect based on role
- Admin routes protected
- Flexible permissions

**✅ Secure**
- Password hashing (bcrypt)
- Prepared statements
- Session validation

**✅ Simple**
- One users table
- Easy to maintain
- No overengineering

---

## 🚀 Next Steps

### **Add More Users:**

```sql
INSERT INTO users (email, password_hash, name, role) VALUES 
('newadmin@example.com', '$2y$10$...', 'New Admin', 'admin');
```

### **Create User Signup:**

Create `signup.php` to let users register (role = 'user' by default)

### **Add Permissions:**

Extend `users` table with permissions JSON or create `permissions` table

---

## ✅ Summary

**What you have now:**
- ✅ Unified login for users + admins
- ✅ Single users table with role field
- ✅ Role-based redirect
- ✅ Admin route protection
- ✅ Secure authentication
- ✅ No overengineering

**How to use:**
1. Run `unified_auth_schema.sql`
2. Visit `/login.php`
3. Login as admin or user
4. Auto-redirects to correct dashboard

**That's it! Clean, simple, secure.** 🎉
