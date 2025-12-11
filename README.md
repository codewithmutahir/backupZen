# BackupZen

![WordPress Plugin](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)
![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)

**All-in-One Backup Solution for WordPress** - Powerful, reliable, and easy-to-use backup and restore plugin with manual and scheduled backup capabilities.

---

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Usage](#-usage)
- [File Structure](#-file-structure)
- [Premium Features](#-premium-features)
- [Technical Details](#-technical-details)
- [Frequently Asked Questions](#-frequently-asked-questions)
- [Support](#-support)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### Core Features (FREE)

- **🔄 Manual Backups** - Create full backups of your WordPress site with one click
- **💾 Database + Files** - Backup both your database and files in a single operation
- **📅 Scheduled Backups** - Automated daily backups at your preferred time
- **🌍 Timezone Support** - Schedule backups in your local timezone
- **📧 Email Notifications** - Get notified about backup status via email
- **🔍 Backup Scanner** - View and manage all available backups
- **🗂️ Multiple Formats** - Support for BZEN, ZIP, SQL, and SQL.GZ formats
- **✅ Backup Verification** - Checksum validation for backup integrity
- **🧹 Automatic Cleanup** - Configure retention policies to manage storage
- **📊 Dashboard Overview** - See backup status at a glance
- **🎨 Modern UI** - Clean, intuitive interface built with WordPress standards

### Backup Types Supported

| Format | Description | Use Case |
|--------|-------------|----------|
| **BZEN** | Custom format with metadata | Full site backups with verification |
| **ZIP** | Standard archive format | File-only backups |
| **SQL** | Plain SQL dump | Database-only backups |
| **SQL.GZ** | Compressed SQL dump | Space-efficient database backups |

---

## 📦 Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- **Disk Space:** Sufficient space for backups (recommend 2x your site size)
- **PHP Extensions:** 
  - `mysqli` - Database operations
  - `zip` - Archive creation
  - `json` - Metadata handling

---

## 🚀 Installation

### Method 1: WordPress Admin (Recommended)

1. Download the `backupzen.zip` file
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded file and click **Install Now**
4. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download and extract the plugin files
2. Upload the `backupzen` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### Method 3: FTP Upload

```bash
# Extract the plugin
unzip backupzen.zip

# Upload via FTP to:
/wp-content/plugins/backupzen/

# Set proper permissions
chmod 755 /wp-content/plugins/backupzen
```

---

## 🎯 Quick Start

### Creating Your First Backup

1. Go to **BackupZen → Dashboard** in your WordPress admin
2. Click **Create Manual Backup**
3. Wait for the backup to complete
4. Download or manage your backup from **Available Backups**

### Setting Up Scheduled Backups

1. Navigate to **BackupZen → Scheduled Backups**
2. Toggle **Enable Schedule** to ON
3. Select your preferred time (uses your local timezone)
4. Choose frequency: **Daily** (Free) or upgrade for more options
5. Click **Save Schedule**

---

## 📖 Usage

### Dashboard Overview

The dashboard provides a quick overview of your backup system:

- **Backup Status** - Current backup health
- **Last Backup** - When the last backup was created
- **Total Backups** - Number of available backups
- **Storage Used** - Disk space consumed by backups
- **Quick Actions** - Create backup, view backups, settings

### Manual Backup

Create on-demand backups whenever needed:

```
BackupZen → Dashboard → Create Manual Backup
```

**What's Included:**
- Complete WordPress files
- Full database export
- Plugin and theme files
- Upload directory
- wp-config.php (with security measures)

### Scheduled Backups

Automate your backup workflow:

```
BackupZen → Scheduled Backups
```

**Configuration Options:**
- **Enable/Disable** - Turn scheduling on or off
- **Frequency** - Daily (FREE), Hourly/Weekly (PRO)
- **Time** - Choose your preferred backup time
- **Timezone** - Automatically uses WordPress timezone

### Available Backups

View and manage all your backups:

```
BackupZen → Available Backups
```

**Actions:**
- **Download** - Download backup to your computer
- **Restore** - Restore your site from this backup (coming soon)
- **Delete** - Remove backup to free up space
- **View Info** - See backup metadata and checksum

### Email Notifications

Stay informed about backup status:

```
BackupZen → Settings → Email Notifications
```

**Features:**
- Success/failure notifications
- Custom recipient emails
- Detailed error messages
- Backup size and duration info

---

## 📁 File Structure

```
backupzen/
├── assets/
│   ├── admin.css                 # Admin dashboard styles
│   ├── admin.js                  # Admin JavaScript
│   ├── css/
│   │   ├── feedback.css          # Feedback modal styles
│   │   ├── pro-features.css      # Premium UI styles
│   │   └── schedule.css          # Schedule page styles
│   └── js/
│       ├── feedback.js           # Feedback functionality
│       ├── pro-features.js       # Premium feature handlers
│       └── schedule.js           # Schedule management
├── includes/
│   ├── Admin/
│   │   └── Feedback.php          # User feedback system
│   ├── Backup/
│   │   ├── BackupScanner.php     # Scan and detect backups
│   │   ├── BzenPackager.php      # BZEN format handler
│   │   ├── CleanupManager.php    # Backup retention & cleanup
│   │   ├── EmailNotifier.php     # Email notification system
│   │   ├── ScheduleController.php # Schedule AJAX handler
│   │   ├── ScheduleManager.php   # Schedule management
│   │   └── TimezoneConverter.php # Timezone utilities
│   ├── Premium/
│   │   └── ProFeaturesManager.php # Premium features manager
│   └── Templates/
│       └── email-feedback.php    # Email template
├── templates/
│   ├── available-backups.php     # Backups list view
│   ├── dashboard.php             # Main dashboard
│   ├── manual-backup.php         # Manual backup page
│   └── scheduled-backups-new.php # Schedule configuration
├── views/
│   ├── feedback-modal.php        # Feedback modal
│   └── premium-modal.php         # Premium upgrade modal
├── backupZen.php                 # Main plugin file
└── README.md                     # This file
```

---

## 🌟 Premium Features

Upgrade to **BackupZen PRO** to unlock powerful advanced features:

### 🔄 Advanced Backup Features

- **Incremental Smart Backups** - 10x faster, only backup changed files
- **Backup Encryption** - AES-256 military-grade encryption
- **Changed Files Detection** - Intelligent file scanning and optimization
- **Backup Verification** - Automatic integrity testing
- **Partial Restore** - Restore only database or only files

### ☁️ Cloud Storage Integration

- **Google Drive** - Auto-sync backups to Google Drive
- **Dropbox** - Automatic Dropbox uploads with version history
- **Amazon S3** - Enterprise-grade AWS S3 integration
- Off-site protection for disaster recovery

### 📅 Advanced Scheduling

- **Hourly Backups** - Run backups every hour
- **Weekly/Custom Schedules** - Flexible scheduling options
- **Multiple Schedules** - Different schedules for different backup types
- **Cron Expressions** - Full cron syntax support for developers

### 🚀 Migration & Tools

- **One-Click Staging** - Create instant staging environments
- **Full Migration Tools** - Move to any host or domain
- **Clone to Subdomain** - Perfect for testing
- **Multisite Support** - Full WordPress Multisite compatibility

### 📊 Reporting & Monitoring

- **Email Reports** - Detailed backup activity reports
- **White-Label PDF Reports** - Professional branded reports for clients
- **Advanced Analytics** - Backup success rates and statistics

### 🎫 Priority Support

- **Priority Email Support** - Get help faster
- **Live Chat** - Real-time assistance
- **Premium Documentation** - Comprehensive guides and tutorials

[**Upgrade to PRO →**](https://example.com/backupzen-pro)

---

## 🔧 Technical Details

### Architecture

BackupZen follows WordPress coding standards and uses modern PHP practices:

- **Object-Oriented Design** - Clean, maintainable code structure
- **Namespace Organization** - `BackupZen\Backup`, `BackupZen\Admin`, `BackupZen\Premium`
- **Singleton Pattern** - Efficient resource management
- **WordPress Hooks** - Proper integration with WordPress core
- **AJAX Handlers** - Asynchronous operations for better UX
- **Security First** - Nonce verification, capability checks, sanitization

### BZEN File Format

The proprietary BZEN format includes:

```
┌─────────────────────────────┐
│   Metadata Header (JSON)     │  ← Site info, timestamps, checksums
├─────────────────────────────┤
│   Compressed Database Dump   │  ← SQL.GZ format
├─────────────────────────────┤
│   Compressed Files Archive   │  ← ZIP format
└─────────────────────────────┘
```

**Benefits:**
- Single-file backup solution
- Built-in integrity verification
- Metadata for easy identification
- Optimized for WordPress

### Database Schema

BackupZen uses WordPress options API for configuration:

```php
// Core settings
'backupzen_schedule_enabled'   // bool
'backupzen_schedule_frequency' // 'daily', 'hourly', 'weekly'
'backupzen_schedule_time'      // 'HH:MM' format
'backupzen_email_notifications' // bool
'backupzen_notification_email' // string
'backupzen_retention_days'     // int
```

### Hooks & Filters

Developers can extend BackupZen:

```php
// Check if PRO version
apply_filters('backupzen_is_pro', false);

// Customize upgrade URL
apply_filters('backupzen_upgrade_url', $url);

// Scheduled backup event
do_action('backupzen_scheduled_backup_event');

// Before backup
do_action('backupzen_before_backup');

// After backup
do_action('backupzen_after_backup', $backup_file);
```

### Performance Optimization

- **Chunked Processing** - Large files handled in chunks
- **Background Processing** - Non-blocking backup operations
- **Resource Management** - Memory and time limit handling
- **Smart Scanning** - Efficient file system operations
- **Compression** - GZIP compression for reduced file sizes

---

## ❓ Frequently Asked Questions

### General Questions

**Q: Is BackupZen free?**  
A: Yes! BackupZen offers a robust free version with manual and daily scheduled backups. Premium features are available in the PRO version.

**Q: Where are backups stored?**  
A: Backups are stored in `/wp-content/backupZen_backups/` by default. PRO users can also sync to cloud storage.

**Q: How large can backups be?**  
A: Backup size depends on your site. BackupZen handles large sites efficiently with chunked processing.

**Q: Can I restore from a backup?**  
A: Restore functionality is coming soon. Currently, you can download backups and restore manually.

### Technical Questions

**Q: What happens if a scheduled backup fails?**  
A: You'll receive an email notification with error details. The schedule will retry at the next scheduled time.

**Q: Can I run backups via WP-CLI?**  
A: WP-CLI support is planned for a future release.

**Q: Is BackupZen compatible with Multisite?**  
A: Basic features work on Multisite. Full Multisite support is available in the PRO version.

**Q: Does BackupZen work with managed hosting?**  
A: Yes! BackupZen works on most shared, VPS, and managed WordPress hosting providers.

### Troubleshooting

**Q: Backup is taking too long**  
A: Large sites may take time. Consider:
- Upgrading to PRO for incremental backups
- Excluding certain directories (PRO feature)
- Increasing PHP time limits with your host

**Q: Scheduled backups aren't running**  
A: Check that:
- WordPress cron is working (`wp cron test`)
- Timezone settings are correct
- No caching plugins are blocking cron

**Q: Out of disk space error**  
A: Enable automatic cleanup to remove old backups, or manually delete unused backups.

---

## 🆘 Support

### Free Support

- **Documentation**: [Plugin Documentation](https://example.com/docs)
- **WordPress Forum**: [Support Forum](https://wordpress.org/support/plugin/backupzen)
- **GitHub Issues**: [Report Bugs](https://github.com/yourusername/backupzen/issues)

### Premium Support

PRO users get priority support:

- **Priority Email**: support@example.com
- **Live Chat**: Available on our website
- **Response Time**: Within 24 hours (weekdays)

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### Reporting Bugs

1. Check if the issue already exists
2. Provide detailed reproduction steps
3. Include WordPress & PHP versions
4. Share any error messages

### Suggesting Features

1. Open a GitHub issue with `[Feature Request]` prefix
2. Describe the feature and use case
3. Explain how it would benefit users

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress coding standards
4. Write clear commit messages
5. Test thoroughly
6. Submit a pull request

### Code Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use meaningful variable and function names
- Add inline comments for complex logic
- Include PHPDoc blocks for all functions

---

## 📜 License

BackupZen is licensed under the **GNU General Public License v2 or later**.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for full details.

---

## 🙏 Credits

**Developed by:** [Your Name](https://example.com)  
**Plugin URI:** [https://example.com/backupzen](https://example.com/backupzen)  
**Support:** [support@example.com](mailto:support@example.com)

### Third-Party Libraries

- **WordPress Core** - Content management system
- **Dashicons** - Icon set for WordPress admin

---

## 📈 Changelog

### Version 1.0.0 (Current)
- Initial release
- Manual backup functionality
- Scheduled daily backups
- Email notifications
- BZEN, ZIP, SQL, SQL.GZ format support
- Backup scanner and management
- Timezone-aware scheduling
- Premium features framework

---

## 🔜 Roadmap

### Upcoming Features

- ✅ Restore functionality (In Progress)
- ⏳ WP-CLI support
- ⏳ Backup download from cloud storage
- ⏳ One-click restore from dashboard
- ⏳ Import backup from external source
- ⏳ Advanced exclusion rules
- ⏳ Backup splitting for large sites

---

<div align="center">

**[Website](https://example.com/backupzen)** • 
**[Documentation](https://example.com/docs)** • 
**[Support](https://example.com/support)** • 
**[Upgrade to PRO](https://example.com/backupzen-pro)**

Made with ❤️ for the WordPress community

</div>

