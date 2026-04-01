# 🔧 Signup/Login Troubleshooting Guide

## 📋 Quick Checklist

Before testing signup, please follow these steps:

### 1. Check XAMPP Services ✅
- Open XAMPP Control Panel
- **Apache** should be running (green)
- **MySQL** should be running (green)
- If not, click "Start" for both

### 2. Test Database Connection 🗄️
Visit: `http://localhost/CodeCanvas/test-db.php`

**Expected Results:**
- ✅ All tests should pass
- ✅ Should show 4 tables (users, projects, tags, project_tags)
- ✅ Should show users table structure
- ✅ Should show user count (0 initially)

**If test-db.php fails:**
- Database connection issue
- Check config/database.php settings
- Make sure database 'codecanvas' exists

### 3. Test Signup Form 📝
Visit: `http://localhost/CodeCanvas/signup.html`

**Steps:**
1. Fill in the form:
   - Name: Test User
   - Email: test@example.com
   - Password: password123

2. Open browser console (F12) and check "Console" tab

3. Click "Create Account"

4. Watch for:
   - Console logs showing the request
   - Success/error messages
   - **Popup alert** if successful
   - Redirect to dashboard if successful

---

## 🐛 Common Issues & Solutions

### Issue 1: No Popup / No Redirect
**Symptoms:**
- Form submits but nothing happens
- No popup alert
- No redirect

**Solution:**
1. Open browser console (F12)
2. Check for JavaScript errors
3. Look for response in console
4. Check if form has `id="signupForm"`

### Issue 2: "Network Error"
**Symptoms:**
- Red error message
- "Network error" in popup

**Solution:**
1. Check Apache is running
2. Make sure you're using correct URL: `http://localhost/CodeCanvas/signup.html`
3. Not `file:///C:/...` (won't work)
4. Check browser console for CORS errors

### Issue 3: "Server Error" / PHP Error
**Symptoms:**
- Error message from server
- Database error shown

**Solution:**
1. Visit test-db.php first to check database
2. Check XAMPP Apache error logs
3. Look in console for full error message
4. Check that database 'codecanvas' exists

### Issue 4: "Email Already Registered"
**Symptoms:**
- Using same email twice

**Solution:**
- Use a different email, OR
- Login instead of signup, OR
- Delete user from database to re-test

### Issue 5: Data Not in Database
**Symptoms:**
- Signup seems successful but user not in database

**Check phpMyAdmin:**
1. Visit: `http://localhost/phpmyadmin`
2. Click "codecanvas" database
3. Click "users" table
4. Click "Browse"
5. Check if user appears

**If user NOT in database:**
- Check PHP error logs
- Visit test-db.php to verify database works
- Check console logs for errors
- Look for error in response

---

## 🔍 Debugging Steps

### Step 1: Check Browser Console
1. Open signup page
2. Press F12
3. Go to "Console" tab
4. Try to signup
5. You should see:
   ```
   Form submitted with data: {...}
   Sending request to auth/signup.php...
   Response status: 200
   Response text: {"success":true,...}
   Parsed data: {...}
   Redirecting to: dashboard.html
   ```

### Step 2: Check Network Tab
1. Open signup page
2. Press F12
3. Go to "Network" tab
4. Try to signup
5. Click on "signup.php" request
6. Check "Response" tab
7. Should see JSON response

### Step 3: Check PHP Error Logs
**Location:**
- `C:\xampp\apache\logs\error.log`
- `C:\xampp\php\logs\php_error_log.txt`

**What to look for:**
- Database connection errors
- PHP syntax errors
- SQL errors

### Step 4: Check Database Manually
1. Visit: `http://localhost/phpmyadmin`
2. Select "codecanvas" database
3. Click "SQL" tab
4. Run:
   ```sql
   SELECT * FROM users ORDER BY id DESC LIMIT 10;
   ```
5. Check if users appear

---

## ✅ Expected Behavior

### When signup is SUCCESSFUL:

1. **Console logs:**
   ```
   Form submitted with data: {...}
   Sending request to auth/signup.php...
   Response status: 200
   Response text: {"success":true,"message":"Account created successfully! 🎉","redirect":"dashboard.html"}
   Parsed data: {success: true, ...}
Redirecting to: dashboard.html
   ```

2. **Visual feedback:**
   - Green success message appears
   - Button shows "Creating Account..."
   - **POPUP ALERT** shows: "✅ Signup Successful!"
   - Page redirects to dashboard after 1 second

3. **Database:**
   - New user row in users table
   - Password is hashed (starts with $2y$10$)
   - created_at timestamp is set
   - avatar_initials generated from name

4. **Session:**
   - Session created with user_id, user_name, user_email, user_initials
   - User stays logged in

### When signup FAILS:

1. **Console logs:**
   ```
   Signup failed: [error message]
   ```

2. **Visual feedback:**
   - Red error message appears
   - Specific error shown (e.g., "Email already registered")
   - **POPUP ALERT** shows error
   - Button re-enabled
   - Can try again

---

## 📸 How to Test in Steps

### Test 1: Fresh Install
```bash
# Visit test page
http://localhost/CodeCanvas/test-db.php

# Should show:
✅ Database connection successful
✅ Found 4 tables
✅ Users table structure
✅ Total users: 0
```

### Test 2: First Signup
```bash
# Visit signup page
http://localhost/CodeCanvas/signup.html

# Fill form:
Name: John Doe
Email: john@example.com
Password: password123

# Click "Create Account"
# Expected:
- Popup: "✅ Signup Successful!"
- Redirect to dashboard
```

### Test 3: Verify in Database
```bash
# Visit test page again
http://localhost/CodeCanvas/test-db.php

# Should now show:
✅ Total users: 1
User table with: john@example.com
```

### Test 4: Duplicate Email
```bash
# Try to signup again with same email
# Expected:
- Error message: "Email already registered"
- No popup
- Stay on signup page
```

### Test 5: Login
```bash
# Visit login page
http://localhost/CodeCanvas/login.html

# Use same credentials:
Email: john@example.com
Password: password123

# Click "Log In"
# Expected:
- Popup: "✅ Login Successful!"
- Redirect to dashboard
```

---

## 🎯 Quick Fix Commands

### Reset Database
```sql
-- Run in phpMyAdmin SQL tab
TRUNCATE TABLE users;
```

### Check Users
```sql
-- Run in phpMyAdmin SQL tab
SELECT id, name, email, created_at FROM users;
```

### Delete Specific User
```sql
-- Run in phpMyAdmin SQL tab
DELETE FROM users WHERE email = 'test@example.com';
```

---

## 📞 Still Not Working?

### Check These Files Exist:
- ✅ `auth/signup.php`
- ✅ `auth/login.php`
- ✅ `auth/logout.php`
- ✅ `auth/session.php`
- ✅ `config/database.php`

### Check Database:
```bash
# Open MySQL command line in XAMPP
C:\xampp\mysql\bin\mysql.exe -u root

# Then run:
USE codecanvas;
SHOW TABLES;
DESCRIBE users;
```

### Check Apache/PHP:
- Make sure PHP 7.4+ is installed
- Check `php.ini` has `extension=pdo_mysql` enabled
- Restart Apache after any config changes

---

## 🎉 Success Indicators

You'll know it's working when:

1. ✅ test-db.php shows all green checkmarks
2. ✅ Signup shows popup alert
3. ✅ User appears in database (phpMyAdmin)
4. ✅ Login works with created account
5. ✅ Dashboard shows after login
6. ✅ Logout redirects to homepage

---

**If you see the popup "✅ Signup Successful!" - everything is working! 🎉**
