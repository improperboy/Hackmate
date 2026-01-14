# SQL Database Schema

This directory contains the database schema for the HackMate application.

## Files

### `hackmate_schema.sql`
Complete database schema including:
- User tables (users, roles, permissions)
- Team management (teams, team_members, join_requests)
- Project submissions (submissions, github_repos)
- Scoring system (scores, evaluation_rounds)
- Mentor assignments
- Announcements and notifications
- Support system (support_tickets)
- Certificates (blockchain_certificates)
- AI chatbot (chatbot_conversations)
- Event settings and configurations

## Usage

### Import Database (phpMyAdmin)
1. Open phpMyAdmin
2. Create new database: `hackmate`
3. Select the database
4. Click "Import" tab
5. Choose `hackmate_schema.sql`
6. Click "Go"

### Import Database (MySQL Command Line)
```bash
mysql -u root -p
CREATE DATABASE hackmate;
USE hackmate;
SOURCE /path/to/Hackmate/sql/hackmate_schema.sql;
EXIT;
```

### Import Database (Windows/XAMPP)
```bash
cd C:\xampp\mysql\bin
mysql.exe -u root -p hackmate < C:\xampp\htdocs\Hackmate\sql\hackmate_schema.sql
```

## Database Information

- **Database Engine**: MySQL 5.7+ or MariaDB 10.2+
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Default User**: Create manually after import

## Default Data

The schema includes:
- ✅ Table structures
- ✅ Indexes for performance
- ✅ Foreign key constraints
- ✅ Default system settings
- ⚠️ No default users (create via application or manually)

## Creating Admin User

After importing the database, create an admin user:

```sql
-- Generate password hash first using PHP
-- password_hash('your_password', PASSWORD_BCRYPT);

INSERT INTO users (username, email, password, role, status, created_at) 
VALUES (
    'admin', 
    'admin@hackmate.com', 
    '$2y$10$YourHashedPasswordHere', 
    'admin', 
    'active',
    NOW()
);
```

Or use the application's registration page and manually update the role in the database.

## Important Notes

⚠️ **Security Warning**: 
- This file is excluded from Git via `.gitignore`
- Never commit actual database dumps with user data
- Only the schema structure should be in version control

## Database Backup

To create a backup:

```bash
# Structure only
mysqldump -u root -p --no-data hackmate > hackmate_schema.sql

# Full backup (with data - DO NOT COMMIT)
mysqldump -u root -p hackmate > hackmate_backup_$(date +%Y%m%d).sql
```

## Maintenance

- **Optimization**: Run `OPTIMIZE TABLE` periodically
- **Backup**: Regular automated backups recommended
- **Indexes**: Monitor slow queries and add indexes as needed
- **Cleanup**: Remove old logs, conversations, and temporary data

---

For installation instructions, see the main [README.md](../README.md#-installation-guide)
