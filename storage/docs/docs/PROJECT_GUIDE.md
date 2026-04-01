# 📂 CodeCanvas Project Documentation

This guide explains the purpose of every file in the CodeCanvas project, organized by function.

---

## 🚀 **1. Public Pages (Frontend)**
*These are the HTML pages visible to visitors before logging in.*

| File | Purpose |
|------|---------|
| `index.html` | **Landing Page.** The main homepage of CodeCanvas. |
| `login.html` | **Login Page.** Contains the login form for both Users and Admins. Connects to `auth/login.php`. |
| `signup.html` | **Signup Page.** Registration form for new users. Connects to `auth/signup.php`. |
| `how-it-works.html` | Explains the features and workflow of CodeCanvas. |
| `getting-started.html` | A guide for new users on how to begin. |
| `personal-website.html` | Landing page for the "Personal Website" solution. |
| `portfolio-website.html` | Landing page for the "Portfolio Website" solution. |
| `business-landing.html` | Landing page for the "Business Website" solution. |
| `templates.html` | Showcase of available templates (public view). |
| `faqs.html` | Frequently Asked Questions page. |
| `contact.html` | Contact support page. |

---

## 👤 **2. User Dashboard & Core App**
*These pages are accessible only after a user logs in.*

| File | Purpose |
|------|---------|
| `dashboard.php` | **Main User Dashboard.** Displays user's projects. Requires login (`require_auth.php`). |
| `new-project.php` | Page to create a new project. |
| `project-view.html` | **Project Editor/Viewer.** The interface where users edit their website. |
| `settings.php` | User account settings (profile, password). |
| `profile.php` | User profile management. |
| `change-template.html` | UI for changing the template of an existing project. |
| `view-settings.html` | UI for viewing/editing project settings. |
| `logout.php` | **User Logout.** Destroys the session and redirects to `login.html`. |
| `require_auth.php` | **Security File.** Included at the top of user pages to ensure the user is logged in. |

---

## 🛠️ **3. Admin Panel**
*Files located in the `admin/` folder. Accessible only to Admins.*

| File | Purpose |
|------|---------|
| `admin/dashboard.php` | **Admin Dashboard.** Overview of system stats (users, projects, templates). |
| `admin/templates.php` | **Template Management.** List of all templates with edit/delete options. |
| `admin/template-add.php` | Form to upload/add a new template. |
| `admin/template-edit.php` | Form to edit an existing template. |
| `admin/login.php` | *(Legacy)* Old admin login handling. Now unified in `auth/login.php`. |
| `admin/logout.php` | **Admin Logout.** Destroys admin session and redirects to login. |
| `admin/require_admin.php`| **Security File.** Ensures the user is logged in AND has the 'admin' role. |
| `admin/admin-style.css` | Stylesheet specific to the admin panel. |
| `admin/schema.sql` | SQL schema for admin-related tables. |

---

## 🔐 **4. Authentication (Backend API)**
*Files located in the `auth/` folder. These handle the logic for logging in and signing up.*

| File | Purpose |
|------|---------|
| `auth/login.php` | **Login Handler.** Receives POST requests, validates credentials, sets session, and returns JSON redirect URL. |
| `auth/signup.php` | **Signup Handler.** Creates new account, hash password, auto-logs in, and returns JSON redirect URL. |
| `auth/logout.php` | **Logout Logic.** Helper file for logout (redirects to `login.html`). |
| `auth/session.php` | Session management helper functions. |

---

## ⚙️ **5. Configuration & Setup**

| File | Purpose |
|------|---------|
| `config/database.php` | **Database Connection.** Contains DB credentials (host, user, pass, db_name). |
| `setup-unified-auth.php` | **Auto-Setup Script.** One-click script to create tables and set up the database. |
| `unified_auth_schema.sql` | **Database Schema.** SQL commands to create the `users` table. |
| `health-check.php` | **System Check.** Verifies that all critical files exist and the system is healthy. |

---

## 🧹 **6. Debug & Temporary Files**
*These files can be deleted once the system is stable.*

| File | Purpose |
|------|---------|
| `check-session.php` | Debug script to inspect current session variables. |
| `inspect-db.php` | Debug script to view database table structure. |
| `test-db.php` | Debug script to test database connection. |
| `test-signup.php` | Debug script to test signup logic manually. |
| `test-dashboard.php` | Debug script to test dashboard access. |
| `dashboard-debug.php` | Debug script for dashboard errors. |
| `auto-fix.php` | Older script for fixing database issues. |
| `create_admins_table.sql`| Old SQL for separate admins table (now unified). |
| `quick_fix.sql` | Quick SQL patches. |

---

## 📚 **7. Documentation (Markdown)**
*Guides and Summaries created during development.*

| File | Purpose |
|------|---------|
| `README.md` | General project overview. |
| `FILE_STRUCTURE.md` | Explains file organization. |
| `FINAL_FIX.md` | **(Important)** Summary of the final authentication fixes. |
| `UNIFIED_LOGIN_SYSTEM.md`| Detailed guide on the new auth system. |
| `COMPLETE_AUTH_FIX.md` | Breakdown of authentication repairs. |
| `REDIRECT_FIX.md` | Explanation of the login redirect fix. |
| `TROUBLESHOOTING.md` | Common errors and solutions. |
| `ALL_FIXED.md` | Summary of completed tasks. |

---

## 🔄 **Dependencies**

- **Frontend:** HTML5, CSS3, JavaScript (Fetch API)
- **Backend:** PHP 8+
- **Database:** MySQL / MariaDB
- **Server:** Apache (XAMPP)

---

### ✅ **Recommended Action**
- Keep the **Public**, **User**, **Admin**, **Auth**, and **Configuration** files.
- You can safely clean up (delete) the **Debug & Temporary Files** if everything is working correctly.
