# 🗄️ CodeCanvas Database Architecture

## Entity Relationship Diagram (ERD)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CODECANVAS DATABASE                          │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────┐
│        USERS             │
├──────────────────────────┤
│ 🔑 id (PK)               │
│ 📧 email (UNIQUE)        │
│ 🔒 password_hash         │
│ 👤 name                  │
│ 🎭 role                  │──┐
│    - user                │  │
│    - admin               │  │
│ 📊 status                │  │
│    - active              │  │
│    - inactive            │  │
│ 📅 created_at            │  │
│ 📅 updated_at            │  │
└──────────────────────────┘  │
                              │
                              │ 1
                              │
                              │
                              │ N
                              ▼
                    ┌──────────────────────────┐
                    │      PROJECTS            │
                    ├──────────────────────────┤
                    │ 🔑 id (PK)               │
                    │ 👤 user_id (FK)          │
                    │ 📄 template_id (FK)      │──┐
                    │ 📝 project_name          │  │
                    │ 🏷️  project_type          │  │
                    │    - personal            │  │
                    │    - portfolio           │  │
                    │    - business            │  │
                    │ 🏢 brand_name            │  │
                    │ 📄 description           │  │
                    │ 💼 skills                │  │
                    │ 📞 contact               │  │
                    │ 📊 status                │  │
                    │    - draft               │  │
                    │    - published           │  │
                    │ 📅 created_at            │  │
                    │ 📅 updated_at            │  │
                    └──────────────────────────┘  │
                                                  │
                                                  │ N
                                                  │
                                                  │
                                                  │ 1
                                                  ▼
                                        ┌──────────────────────────┐
                                        │      TEMPLATES           │
                                        ├──────────────────────────┤
                                        │ 🔑 id (PK)               │
                                        │ 📝 name                  │
                                        │ 🔗 slug (UNIQUE)         │
                                        │ 🏷️  template_type         │
                                        │    - personal            │
                                        │    - portfolio           │
                                        │    - business            │
                                        │ 📁 folder_path           │
                                        │ 🖼️  thumbnail_url         │
                                        │ 📊 status                │
                                        │    - active              │
                                        │    - inactive            │
                                        │ 📅 created_at            │
                                        │ 📅 updated_at            │
                                        └──────────────────────────┘
```

---

## Relationships

### 1️⃣ Users → Projects (One-to-Many)
- **One user** can have **many projects**
- **Foreign Key:** `projects.user_id` → `users.id`
- **On Delete:** CASCADE (deleting user deletes all their projects)

### 2️⃣ Templates → Projects (One-to-Many)
- **One template** can be used by **many projects**
- **Foreign Key:** `projects.template_id` → `templates.id`
- **On Delete:** RESTRICT (cannot delete template if used by projects)

---

## Table Details

### 📊 USERS Table
**Purpose:** Stores both admin and regular users in a unified table

**Indexes:**
- `PRIMARY KEY` on `id`
- `UNIQUE INDEX` on `email`
- `INDEX` on `role`
- `INDEX` on `status`

**Default Data:**
- Admin: admin@codecanvas.com (role: admin)
- User: user@codecanvas.com (role: user)

---

### 📊 TEMPLATES Table
**Purpose:** Stores website templates available for users

**Indexes:**
- `PRIMARY KEY` on `id`
- `UNIQUE INDEX` on `slug`
- `INDEX` on `status`
- `INDEX` on `template_type`

**Default Data:**
- Minimal Portfolio
- Modern Portfolio
- Classic Portfolio
- Elegant Portfolio
- Personal Basic
- Business Pro

---

### 📊 PROJECTS Table
**Purpose:** Links users to templates and stores project customization data

**Indexes:**
- `PRIMARY KEY` on `id`
- `FOREIGN KEY` on `user_id` → `users.id`
- `FOREIGN KEY` on `template_id` → `templates.id`
- `INDEX` on `user_id`
- `INDEX` on `template_id`
- `INDEX` on `status`
- `INDEX` on `project_type`

**Default Data:** None (empty initially)

---

## Data Flow

```
1. USER REGISTRATION
   ↓
   New record in USERS table (role: user)

2. USER LOGIN
   ↓
   Query USERS table → Verify credentials → Create session

3. CREATE PROJECT
   ↓
   User selects TEMPLATE → Creates PROJECT record
   ↓
   PROJECT.user_id = current user
   PROJECT.template_id = selected template

4. ADMIN MANAGEMENT
   ↓
   Admin (role: admin) manages TEMPLATES
   ↓
   Can activate/deactivate templates
```

---

## Security Features

### 🔒 Password Security
- Passwords stored as bcrypt hashes (`password_hash`)
- Never stored in plain text
- Uses PHP's `password_hash()` and `password_verify()`

### 🔒 SQL Injection Prevention
- All queries use PDO prepared statements
- Parameters bound separately from SQL
- `PDO::ATTR_EMULATE_PREPARES` set to `false`

### 🔒 Data Integrity
- Foreign key constraints enforce referential integrity
- CASCADE delete prevents orphaned records
- RESTRICT delete prevents data loss

### 🔒 Character Encoding
- UTF8MB4 charset supports all Unicode characters
- Prevents encoding-based attacks
- Supports international characters and emojis

---

## Query Examples

### Get all projects for a user:
```sql
SELECT p.*, t.name as template_name, t.template_type
FROM projects p
JOIN templates t ON p.template_id = t.id
WHERE p.user_id = ?
ORDER BY p.updated_at DESC;
```

### Get active templates:
```sql
SELECT * FROM templates
WHERE status = 'active'
ORDER BY name ASC;
```

### Get user with role:
```sql
SELECT id, email, name, role, status
FROM users
WHERE email = ?
AND status = 'active';
```

### Count projects by user:
```sql
SELECT u.name, COUNT(p.id) as project_count
FROM users u
LEFT JOIN projects p ON u.id = p.user_id
GROUP BY u.id
ORDER BY project_count DESC;
```

---

## Performance Optimization

### Indexes Created:
✅ Primary keys on all tables
✅ Unique indexes on email and slug
✅ Foreign key indexes
✅ Status and role indexes for filtering
✅ Type indexes for categorization

### Query Optimization:
✅ Prepared statements (cached execution plans)
✅ Indexed columns in WHERE clauses
✅ JOIN on indexed foreign keys
✅ Efficient data types (INT UNSIGNED for IDs)

---

## Storage Estimates

### Users Table:
- ~500 bytes per record
- 1,000 users ≈ 500 KB
- 10,000 users ≈ 5 MB

### Templates Table:
- ~400 bytes per record
- 100 templates ≈ 40 KB

### Projects Table:
- ~1,000 bytes per record (with text fields)
- 1,000 projects ≈ 1 MB
- 10,000 projects ≈ 10 MB

**Total for 10K users, 100 templates, 10K projects: ~16 MB**

---

## Backup Strategy

### Recommended:
1. **Daily backups** via phpMyAdmin export
2. **Export format:** SQL with structure and data
3. **Compression:** GZIP enabled
4. **Storage:** Local + cloud backup

### Quick Backup Command:
```bash
mysqldump -u root codecanvas > backup_$(date +%Y%m%d).sql
```

---

**Database Version:** 1.0
**Last Updated:** 2026-02-17
**Engine:** InnoDB
**Charset:** UTF8MB4
