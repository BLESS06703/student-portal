# Student Portal - Vocational School Management System

## Quick Start
1. Start MariaDB: `mariadbd-safe &`
2. Start Server: `php -S 0.0.0.0:8080 -t .`
3. Open: `http://localhost:8080`

## Default Accounts
- **Admin:** STU2026005 / student123
- **Teacher:** Register at /teacher_register.php with code TEACH2026
- **Student:** Register at /register.php (requires admin approval)

## Teacher Access Code
Current: `TEACH2026` — Change in teacher_register.php line 7

## Features
- Student registration with admin approval
- Course notes upload/download
- Assignment submission & grading
- GPA & exam tracking
- Attendance management
- Class rep announcements
- Teacher bulk grading & analytics
- Admin system dashboard & audit trail
- Database backup & restore
- Dark mode on all pages

## Security
- Passwords hashed with bcrypt
- CSRF protection on forms
- Prepared SQL statements
- Session-based authentication

## File Structure
- home.php — Landing page
- index.php — Login
- register.php — Student registration
- dashboard.php — Student dashboard
- admin.php — Admin panel
- teacher.php — Teacher panel
- notes.php — Course notes
- assignments.php — Assignment system
- profile.php — User profile
