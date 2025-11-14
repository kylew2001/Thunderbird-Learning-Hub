# Work Knowledge Base

A PHP-based forum-style knowledge base system for organizing and storing work knowledge. Features hierarchical categories, rich text posts with file uploads, iMessage-style reply system, advanced search functionality, and PDF export capabilities.

## 🎯 Features

- **🏗️ Hierarchical Organization**: Categories → Subcategories → Posts → Updates
- **🔐 PIN Authentication**: Simple 7982 PIN login with 2-hour sessions
- **✨ Rich Text Editor**: TinyMCE integration with formatting, tables, lists, and code blocks
- **📎 File Uploads**: Images and documents (up to 20 MB per file)
- **💬 iMessage-style Updates**: Add updates to posts with timestamps
- **✏️ Edit Tracking**: "edited" indicators on modified posts and updates
- **📄 PDF Export**: Export posts with optional updates to PDF
- **🔍 Advanced Search**: Search posts, categories, and subcategories with highlighting
- **🎨 Blue/Purple Theme**: Professional, clean design
- **📊 MySQL Database**: All data stored in phpMyAdmin-compatible database
- **📱 Responsive Design**: Mobile-friendly layout

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache web server (or compatible)
- Web hosting (tested on InfinityFree.com)

## 🚀 Installation

### 1. Upload Files

Upload all files to your web hosting public_html directory (or subdirectory).

### 2. Set File Permissions

Ensure the uploads directory is writable:

```bash
chmod 755 uploads
chmod 755 uploads/images
chmod 755 uploads/files
```

### 3. Configure Database

**Step 3.1: Import Database Schema**

1. Log into your hosting control panel
2. Open phpMyAdmin
3. Create a new database (or select existing one)
4. Click "Import" tab
5. Choose `database/schema.sql`
6. Click "Go" to create tables

**Step 3.2: Update Configuration**

Edit `config.php` and update with your database credentials:

```php
define('DB_HOST', 'localhost');              // Your MySQL hostname
define('DB_NAME', 'your_database_name');     // Your database name
define('DB_USER', 'your_database_user');     // Your database username
define('DB_PASS', 'your_database_password'); // Your database password
```

**Note**: The config is pre-configured for InfinityFree with your credentials:
- Host: `sql100.infinityfree.com`
- Database: `if0_40307645_XXX`
- User: `if0_40307645`
- Password: Already set

### 4. Install TCPDF (Required for PDF Export)

**Option A: Manual Download**

1. Download TCPDF from: https://github.com/tecnickcom/TCPDF/releases
2. Extract the files
3. Upload to `vendor/tcpdf/` directory
4. The main file should be at: `vendor/tcpdf/tcpdf.php`

**Option B: If Composer is available**

```bash
cd vendor
composer require tecnickcom/tcpdf
```

### 5. Test Installation

Visit your website URL. You should be redirected to the login page.

**Default PIN**: `7982`

## ⚙️ Configuration Options

Edit `config.php` to customize:

- **PIN_CODE**: Change the login PIN (default: 7982)
- **SESSION_TIMEOUT**: Change session duration (default: 7200 seconds = 2 hours)
- **MAX_FILE_SIZE**: Change max upload size (default: 20 MB)
- **SITE_NAME**: Change site title
- **Timezone**: Adjust for your location (default: America/New_York)

## 📚 Usage Guide

### Creating Categories

1. Log in with PIN 7982
2. Click "Add Category" button
3. Enter category name (e.g., "Hardware Issues")
4. Optionally add emoji icon (e.g., 🔧)
5. Click "Create Category"

### Creating Subcategories

1. From home page, click "Add Subcategory" on a category
2. Enter subcategory name (e.g., "Printers")
3. Select parent category
4. Click "Create Subcategory"

### Creating Posts

1. Click on a subcategory to view posts
2. Click "Add Post" button
3. Enter title and content using the rich text editor
4. Optionally upload images and files
5. Click "Create Post"

### Adding Updates (Replies)

1. Open a post
2. Scroll to "Add Update" section at bottom
3. Enter your update content
4. Optionally attach files
5. Click "Add Update"

### Searching Content

1. Use the search bar on any page
2. Enter keywords to search posts, categories, and subcategories
3. Filter by category or content type
4. Click on highlighted results to view

### Editing Posts/Updates

- Click "Edit" button on any post or update
- Make changes
- Existing files can be deleted by checking boxes
- New files can be added
- Click "Update" to save

### Exporting to PDF

1. Open a post
2. Click "Export to PDF" button
3. PDF will download with post content and all updates

## 🔍 Search Features

The advanced search system allows you to:

- **Search Posts**: By title and content
- **Search Categories**: By category names
- **Search Subcategories**: By subcategory names
- **Filter Results**: By specific category
- **Search Types**: All content, posts only, categories only, or subcategories only
- **Highlighted Results**: Search terms highlighted in yellow
- **Content Previews**: Shows context around found text

## 📁 File Structure

```
Work-Knowledge-Base/
├── index.php                  # Home page (categories + search)
├── login.php                  # PIN login
├── logout.php                 # Logout handler
├── search.php                 # Advanced search results
├── config.php                 # Configuration (DATABASE CREDENTIALS)
│
├── add_category.php           # Category management
├── edit_category.php
├── delete_category.php
│
├── add_subcategory.php        # Subcategory management
├── edit_subcategory.php
├── delete_subcategory.php
│
├── subcategory.php            # Posts list + search
├── add_post.php               # Post management
├── edit_post.php
├── delete_post.php
├── post.php                   # Post detail view
│
├── add_reply.php              # Update management
├── edit_reply.php
├── delete_reply.php
│
├── export_pdf.php             # PDF export
│
├── includes/
│   ├── auth_check.php         # Session validation
│   ├── db_connect.php         # Database connection
│   ├── header.php             # Page header
│   └── footer.php             # Page footer
│
├── assets/
│   └── css/
│       └── style.css          # Professional blue/purple theme
│
├── uploads/
│   ├── images/                # Uploaded images
│   └── files/                 # Uploaded files
│
├── database/
│   └── schema.sql             # Database schema (IMPORT THIS)
│
├── vendor/
│   ├── tcpdf/                 # TCPDF library (INSTALL THIS)
│   └── README.md              # Vendor setup instructions
│
└── README.md                  # This file
```

## 🗄️ Database Tables

The system uses 5 MySQL tables with proper relationships:

- **categories**: Broad categories
- **subcategories**: Subcategories under categories
- **posts**: Main posts within subcategories
- **replies**: Updates/replies to posts
- **files**: File attachments for posts and updates

All tables use UTF-8 encoding with CASCADE delete for data integrity and include FULLTEXT indexes for fast searching.

## 🔐 Security Notes

1. **Change Default PIN**: Edit `config.php` and change `PIN_CODE` from 7982 to your own PIN
2. **Secure config.php**: Ensure config.php is not publicly downloadable
3. **Session Security**: Sessions expire after 2 hours automatically
4. **File Uploads**: All file types are allowed - monitor uploads folder size
5. **Backup Database**: Regularly backup your database from phpMyAdmin

## 🛠️ Troubleshooting

### "Database connection failed"

- Check database credentials in `config.php`
- Verify database exists in phpMyAdmin
- Ensure database user has proper permissions

### "Session has expired" immediately after login

- Check server timezone settings
- Verify session settings in hosting control panel
- Increase `SESSION_TIMEOUT` in config.php if needed

### File uploads not working

- Check folder permissions (should be 755 or 777)
- Verify `upload_max_filesize` in php.ini
- Check disk space on hosting account

### PDF export shows error

- Verify TCPDF is installed in `vendor/tcpdf/`
- Check `vendor/tcpdf/tcpdf.php` exists
- Ensure PHP memory limit is sufficient

### TinyMCE editor not loading

- Check internet connection (uses CDN)
- Browser console for JavaScript errors
- Try different browser

### Search not working

- Verify database schema was imported correctly
- Check FULLTEXT indexes exist on posts table
- Review database error logs

## 🎨 Customization

### Changing Colors

Edit `assets/css/style.css` and modify these variables:

- Header gradient: `.header` background
- Primary blue: `#4299e1`
- Accent purple: `#667eea` to `#764ba2`
- Background: `#f0f4f8`

### Changing PIN

Edit `config.php`:

```php
define('PIN_CODE', '1234'); // Your new PIN
```

### Changing Session Duration

Edit `config.php`:

```php
define('SESSION_TIMEOUT', 14400); // 4 hours in seconds
```

## 📞 Support & Issues

For issues or questions:

1. Check this README thoroughly
2. Verify all installation steps completed
3. Check browser console for JavaScript errors
4. Check PHP error logs on your server
5. Ensure TCPDF is properly installed for PDF export

## 📜 License

This project is open source and available for personal and commercial use.

## 🙏 Credits

- TinyMCE Editor: https://www.tiny.cloud/
- TCPDF Library: https://tcpdf.org/

---

**🚀 Enjoy your Work Knowledge Base with Advanced Search!**