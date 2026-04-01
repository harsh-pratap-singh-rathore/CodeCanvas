# 📁 Project File Structure

## ✅ **New Refactored Structure** (Clean & Production-Ready)

```
CodeCanvas/
│
├── 📂 admin/                ← Admin Panel (Protected)
│   ├── dashboard.php        # Admin Dashboard
│   ├── templates.php        # Manage Templates
│   ├── template-add.php     # Add New Template
│   └── template-edit.php    # Edit Template
│
├── 📂 app/                  ← User App (Protected Dashboard)
│   ├── dashboard.php        # User Dashboard
│   ├── new-project.php      # Create New Website
│   ├── profile.php          # User Profile
│   ├── settings.php         # User Settings
│   ├── project.php          # (Optional) Future Project View
│   └── view-settings.html   # UI Drafts
│
├── 📂 auth/                 ← Authentication Logic (Backend API)
│   ├── login.php            # Handles user/admin login
│   ├── signup.php           # Handles new user registration
│   └── logout.php           # Handles logout & session destroy
│
├── 📂 config/               ← Configuration
│   └── database.php         # Database Connection (PDO)
│
├── 📂 core/                 ← Core Helpers & Middleware
│   ├── auth.php             # User Auth Check (Redirects if not logged in)
│   └── admin_auth.php       # Admin Auth Check (Redirects slightly differently)
│
├── 📂 database/             ← Database Schemas & Seeds
│   ├── unified_auth_schema.sql
│   └── ...
│
├── 📂 docs/                 ← Documentation (Centralized)
│   ├── PROJECT_GUIDE.md
│   ├── REFACTOR_REPORT.md
│   ├── FILE_STRUCTURE.md
│   └── (All other .md files)
│
├── 📂 public/               ← Public Frontend
│   ├── 📂 assets/
│   │   ├── css/             # Stylesheets (style.css, admin-style.css)
│   │   ├── js/              # JavaScript (main.js, new-project.js)
│   │   └── img/             # Images
│   │
│   ├── index.html           # Landing Page
│   ├── login.html           # Login Page
│   ├── signup.html          # Signup Page
│   ├── how-it-works.html
│   ├── getting-started.html
│   ├── templates.html
│   └── ...
│
├── 📂 templates/            ← Iframe Previews / Template Files
│   ├── minimal/
│   └── ...
│
└── index.php                ← Root Redirect (to public/index.html)
```

---

## 🔑 **Key Path Changes**

| Old Location | New Location |
|--------------|--------------|
| `login.html` | `public/login.html` |
| `dashboard.php` | `app/dashboard.php` |
| `auth/login.php` | `auth/login.php` (No change) |
| `require_auth.php` | `core/auth.php` |
| `admin/require_admin.php` | `core/admin_auth.php` |
| `assets/` | `public/assets/` |

---

## 🛠️ **Correct Include Paths**

**From `/app/dashboard.php`:**
```php
require_once '../config/database.php';
require_once '../core/auth.php';
<link href="../public/assets/css/style.css">
```

**From `/admin/dashboard.php`:**
```php
require_once '../config/database.php';
require_once '../core/admin_auth.php';
<link href="../public/assets/css/admin-style.css">
```

**From `/public/login.html` (JS):**
```javascript
fetch('../auth/login.php')
```
