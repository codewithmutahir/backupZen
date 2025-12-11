=== BackupZen ===
Contributors: mutahirhussain
Tags: backup, restore, database, scheduled-backup, files
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete WordPress backup solution with manual and scheduled backups for database and files.

== Description ==

BackupZen is a powerful yet easy-to-use backup plugin for WordPress that helps you protect your website with automated and manual backups.

= Key Features =

* **Manual Backups** - Create full backups of your WordPress site with one click
* **Scheduled Backups** - Automated daily backups at your preferred time
* **Database + Files** - Backup both your database and files together
* **Timezone Support** - Schedule backups in your local timezone
* **Email Notifications** - Get notified about backup status
* **Multiple Formats** - Support for BZEN, ZIP, SQL, and SQL.GZ formats
* **Backup Management** - View, download, and manage all your backups
* **Easy Restore** - Simple backup restoration process
* **Automatic Cleanup** - Configure retention policies to save storage
* **Security First** - Protected backup directory with proper permissions

= Perfect For =

* Bloggers who want peace of mind
* Small business websites
* E-commerce sites
* Developers testing changes
* Anyone who values their data

= What's Backed Up? =

* Complete WordPress database
* All WordPress files
* Plugins and themes
* Uploads directory
* WordPress configuration

= Backup Formats =

* **BZEN Format** - Custom format with metadata and checksums
* **ZIP Format** - Standard archive for files
* **SQL Format** - Plain SQL database dumps
* **SQL.GZ Format** - Compressed database dumps for space efficiency

= Timezone-Aware Scheduling =

Schedule your backups to run at the perfect time in YOUR timezone, not server time. BackupZen automatically handles timezone conversions so your backups run exactly when you want them to.

= Email Notifications =

Stay informed with email notifications for:
* Successful backups
* Failed backups with error details
* Storage warnings

= Privacy & Security =

* All backups stored locally on your server
* No external services or data transmission
* Protected backup directory
* Secure file permissions
* No user tracking

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "BackupZen"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the downloaded file and click "Install Now"
4. Click "Activate Plugin"

= After Activation =

1. Go to BackupZen in your admin menu
2. Review your dashboard
3. Create your first manual backup
4. Set up scheduled backups if desired

== Frequently Asked Questions ==

= Where are my backups stored? =

Backups are stored in `/wp-content/backupZen_backups/` on your server. This directory is automatically protected from direct web access.

= How large can my backups be? =

Backup size depends on your website size. BackupZen can handle large sites through efficient file processing and compression.

= Can I restore from a backup? =

Yes! You can download any backup and restore it manually, or use the restore feature (in development).

= Will scheduled backups slow down my site? =

No. Scheduled backups run in the background using WordPress cron. They won't affect your site's performance for visitors.

= How many backups should I keep? =

This depends on your needs and available storage. We recommend keeping at least 7 daily backups. You can configure automatic cleanup to manage storage.

= What if my backup fails? =

You'll receive an email notification with error details. Common issues include:
* Insufficient disk space
* File permission problems
* PHP timeout limits
* Memory limits

Check your error log or contact your hosting provider for assistance.

= Does this work with managed WordPress hosting? =

Yes! BackupZen works with most WordPress hosting providers, including shared hosting, VPS, and managed WordPress hosts.

= Is BackupZen compatible with multisite? =

Basic features work on multisite installations. Full multisite support is planned for future releases.

= Can I schedule backups more frequently than daily? =

The free version supports daily scheduled backups. More frequent scheduling options may be available in future releases.

= How do I restore a backup? =

Currently, you can download backups and restore them manually. An automated restore feature is in development. To restore manually:

1. Download your backup
2. Extract the files
3. Upload files via FTP
4. Import database via phpMyAdmin

= What happens if I run out of disk space? =

Configure automatic cleanup in settings to remove old backups. BackupZen will keep your most recent backups and delete older ones based on your retention policy.

= Can I exclude certain files or folders? =

File exclusion features are planned for future releases. Currently, all WordPress files are included in backups.

= Is my data safe? =

Yes! All backups remain on your server. BackupZen doesn't send your data to external services. The backup directory is protected from web access.

= How do I report a bug or request a feature? =

Please use the WordPress.org support forum or contact us through the plugin's feedback system.

== Screenshots ==

1. Dashboard overview showing backup status and quick actions
2. Manual backup page with one-click backup creation
3. Scheduled backups configuration with timezone support
4. Available backups list with download and manage options
5. Email notification settings
6. Clean and intuitive user interface

== Changelog ==

= 1.0.0 =
* Initial release
* Manual backup functionality
* Scheduled daily backups
* Email notifications
* BZEN, ZIP, SQL, SQL.GZ format support
* Backup scanner and management
* Timezone-aware scheduling
* Automatic cleanup with retention policies
* Protected backup directory
* WordPress 6.4 compatibility
* PHP 7.4+ support

== Upgrade Notice ==

= 1.0.0 =
Initial release of BackupZen. Start protecting your WordPress site today!

== Additional Info ==

= Support =

For support questions, feature requests, or bug reports, please use the WordPress.org support forum.

= Contributing =

We welcome contributions! Visit our GitHub repository to contribute code, report issues, or suggest features.

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Sufficient disk space (recommend 2x your site size)

= Recommendations =

* Keep WordPress and PHP updated
* Maintain adequate free disk space
* Test backups periodically
* Store backup copies off-site for disaster recovery

= Credits =

Developed with ❤️ for the WordPress community.

