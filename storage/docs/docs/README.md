# CodeCanvas

A clean, professional SaaS platform for AI-powered website generation.

## Overview

CodeCanvas helps users create professional websites without design or coding skills. AI generates content, selects templates, and builds complete websites from simple user input.

## Tech Stack

- **Frontend**: HTML, CSS, JavaScript, jQuery
- **Backend**: PHP (future)
- **Database**: MySQL (future)
- **AI Layer**: Python + LLM API (future)

## File Structure

```
/CodeCanvas
 ├── index.html               # Landing page
 ├── dashboard.html           # Dashboard (after login)
 ├── personal-website.html    # Personal website solution page
 ├── portfolio-website.html   # Portfolio solution page
 ├── business-landing.html    # Business landing solution page
 ├── templates.html           # Template gallery
 ├── how-it-works.html        # How CodeCanvas works
 ├── getting-started.html     # Getting started guide
 ├── faqs.html                # Frequently asked questions
 ├── contact.html             # Contact form
 ├── signup.html              # User registration
 ├── login.html               # User login
 ├── assets/
 │   ├── css/style.css        # Shared stylesheet
 │   ├── js/main.js           # Shared JavaScript
 │   └── images/              # Image assets
 ├── config/
 │   └── database.php         # Database configuration
 ├── schema.sql               # MySQL database schema
 ├── DATABASE_SETUP.md        # Database setup guide
 ├── PROJECT_SUMMARY.md       # Project overview
 └── README.md                # This file
```

## Design Philosophy

**Clean. White. Professional. Boring-in-a-good-way.**

- Pure white background (#FFFFFF)
- Near-black text (#0F0F0F)
- Secondary gray (#6B6B6B)
- Black accent only
- No gradients, no neon, no dark theme
- System fonts only (Inter, SF Pro, Manrope style)
- Flat UI with light borders
- Maximum restraint

Inspired by: Apple, Stripe, Vercel

## Features

### Frontend
- **Non-fixed header** with click-to-open dropdown menus
- **Multi-page navigation** with real content
- **Solution pages** for different use cases
- **Template gallery** with categorized options
- **Help & resources** section
- **Auth pages** (UI only, no backend yet)
- **Dashboard** (production-ready layout)
- **Responsive design** for all screen sizes

### Dashboard
- **Clean layout** with sidebar navigation
- **Project grid** with card-based UI
- **User menu** with profile/settings dropdown
- **Search functionality** (UI ready)
- **Project status badges** (Live/Draft)
- **Tag management** (UI ready)

### Database
- **MySQL schema** with proper relationships
- **Users, Projects, Tags** tables
- **Foreign key constraints**
- **Indexed for performance**
- **Soft delete support**

## Setup

### Frontend Only
1. Place files in your web server directory (e.g., `htdocs/CodeCanvas/`)
2. Access via browser: `http://localhost/CodeCanvas/`
3. No build process required

### Full Stack (with Database)
1. Follow frontend setup above
2. Create MySQL database: `codecanvas`
3. Import schema: `mysql -u root -p codecanvas < schema.sql`
4. Configure database in `config/database.php`
5. See `DATABASE_SETUP.md` for detailed instructions


## Navigation Structure

### Header Dropdowns

**Solutions:**
- Personal Website
- Portfolio Website
- Business Landing Page

**Templates:**
- Direct link to templates page

**Help & Resources:**
- How CodeCanvas Works
- Getting Started
- FAQs
- Contact Support

## Current Status

**Completed:**
- Frontend structure
- All pages with real content
- Dropdown navigation
- Responsive design
- Auth UI
- Dashboard UI
- MySQL schema
- Database configuration

**Pending:**
- Backend implementation (PHP)
- User authentication logic
- Project CRUD operations
- AI integration (Python)
- Website generation logic

## Contributing

This is a real SaaS product in development. Not a demo.

---

© 2026 CodeCanvas. All rights reserved.
