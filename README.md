# 🎬 CineScope - Professional Movie Review Platform

A full-featured movie review website with user authentication, CRUD operations, search, activity logging, and preferences.

## ✨ Features Implemented

| # | Feature | Status |
|---|---------|--------|
| 1 | Login/Logout System | ✅ |
| 2 | Item Search Mechanism | ✅ |
| 3 | Item Entry, Update, Delete | ✅ |
| 4 | Fully Responsive Design | ✅ |
| 5 | Session Handling | ✅ |
| 6 | Database Connectivity & CRUD | ✅ |
| 7 | Password Change | ✅ |
| 8 | Activity Log Maintenance | ✅ |
| 9 | Preference Setting (Theme) | ✅ |

## 🚀 Installation Guide

### Step 1: Setup Web Server
- **XAMPP**: Copy `cinescope/` to `C:\xampp\htdocs\`
- **WAMP**: Copy to `C:\wamp64\www\`
- **MAMP**: Copy to `/Applications/MAMP/htdocs/`

### Step 2: Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" → Create database `movie_review_db`
3. Go to "SQL" tab
4. Copy and paste contents of `database/schema.sql`
5. Click "Go"

### Step 3: Configure Database
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'movie_review_db');
define('DB_USER', 'root');
define('DB_PASS', '');