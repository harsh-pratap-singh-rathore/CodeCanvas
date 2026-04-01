# ✅ CodeCanvas Database Reset Checklist

## Follow these steps in order:

---

## 🔴 STEP 1: Start XAMPP
- [ ] Open XAMPP Control Panel
- [ ] Click "Start" for **Apache**
- [ ] Click "Start" for **MySQL**
- [ ] Wait for both to show **green** status
- [ ] Verify no error messages appear

---

## 🔴 STEP 2: Open phpMyAdmin
- [ ] Open your web browser
- [ ] Go to: **http://localhost/phpmyadmin**
- [ ] Verify phpMyAdmin loads successfully

---

## 🔴 STEP 3: Execute Database Reset
- [ ] In phpMyAdmin, click the **"SQL"** tab at the top
- [ ] Open file: `C:\xampp\htdocs\CodeCanvas\database\COMPLETE_DATABASE_RESET.sql`
- [ ] Select **ALL** content (Ctrl+A) and copy (Ctrl+C)
- [ ] Paste into the SQL query box in phpMyAdmin
- [ ] Click the **"Go"** button (bottom right)
- [ ] Wait for success message: "X queries executed successfully"

---

## 🔴 STEP 4: Verify Database
- [ ] In phpMyAdmin, click on **"codecanvas"** database in left sidebar
- [ ] Verify you see **3 tables**: users, templates, projects
- [ ] Click on **"users"** table
- [ ] Click **"Browse"** tab
- [ ] Verify you see **2 users** (1 admin, 1 regular user)

---

## 🔴 STEP 5: Run Verification Tool
- [ ] Open browser
- [ ] Go to: **http://localhost/CodeCanvas/verify-database.php**
- [ ] Verify all checks show **green** status
- [ ] Look for "✅ All Checks Passed!" message

---

## 🔴 STEP 6: Test Login
- [ ] Go to: **http://localhost/CodeCanvas/public/login.html**
- [ ] Enter email: **admin@codecanvas.com**
- [ ] Enter password: **admin123**
- [ ] Click **"Login"** button
- [ ] Verify you're redirected to admin dashboard
- [ ] Verify dashboard shows template statistics

---

## 🔴 STEP 7: Test User Login
- [ ] Logout from admin dashboard
- [ ] Go back to login page
- [ ] Enter email: **user@codecanvas.com**
- [ ] Enter password: **user123**
- [ ] Click **"Login"** button
- [ ] Verify you're redirected to user dashboard

---

## ✅ SUCCESS CRITERIA

You should now have:
- ✅ Database `codecanvas` created
- ✅ 3 tables (users, templates, projects)
- ✅ 2 user accounts (admin + regular user)
- ✅ 6 default templates
- ✅ Working admin login
- ✅ Working user login
- ✅ All verification checks passing

---

## 🚨 If Something Goes Wrong

### Problem: "Access denied for user 'root'@'localhost'"
**Solution:** 
1. Check XAMPP MySQL is running
2. Verify credentials in `config/database.php`
3. Default XAMPP: user=`root`, password=`` (empty)

### Problem: "Unknown database 'codecanvas'"
**Solution:**
1. Make sure you ran the COMPLETE reset script
2. The script should create the database automatically
3. If not, manually create it in phpMyAdmin first

### Problem: "Table already exists"
**Solution:**
1. The reset script uses `DROP DATABASE IF EXISTS`
2. If error persists, manually delete `codecanvas` database
3. Then run the reset script again

### Problem: Login not working
**Solution:**
1. Clear browser cache and cookies
2. Verify users exist: Run `SELECT * FROM users;` in phpMyAdmin
3. Use exact credentials: `admin@codecanvas.com` / `admin123`
4. Check browser console for JavaScript errors

### Problem: Verification tool shows errors
**Solution:**
1. Read the specific error message
2. Most likely: database not created or tables missing
3. Re-run the reset script
4. Refresh the verification page

---

## 📚 Reference Files

| File | Purpose |
|------|---------|
| `database/COMPLETE_DATABASE_RESET.sql` | Main reset script |
| `database/DATABASE_SETUP_GUIDE.md` | Detailed instructions |
| `database/CREDENTIALS.md` | Quick reference |
| `database/REMAPPING_SUMMARY.md` | What was done |
| `verify-database.php` | Verification tool |

---

## 🎯 Quick Commands

### Check if database exists (phpMyAdmin SQL):
```sql
SHOW DATABASES LIKE 'codecanvas';
```

### Check tables (phpMyAdmin SQL):
```sql
USE codecanvas;
SHOW TABLES;
```

### Check users (phpMyAdmin SQL):
```sql
SELECT id, email, name, role FROM users;
```

### Check templates (phpMyAdmin SQL):
```sql
SELECT id, name, template_type, status FROM templates;
```

---

**Ready to start? Begin with STEP 1! 🚀**
