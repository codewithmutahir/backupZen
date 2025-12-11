<?php

/**
 * Plugin Name: backupZen
 * Plugin URI: https://mutahir.qzz.io
 * Description: All-in-One Backup solution for WordPress - Manual Backup & Restore (Files + DB)
 * Version: 1.0.0
 * Author: Mutahir Hussain
 * Author URI: https://mutahir.qzz.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: backupzen
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package BackupZen
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class BackupZen
{
    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Backup directory name (relative to wp-content).
     *
     * @var string
     */
    const BACKUP_DIR = 'backupZen_backups';

    /**
     * Single instance of the class.
     *
     * @var BackupZen
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return BackupZen
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
   private function init_hooks()
{
    // Activation hook
    register_activation_hook(__FILE__, array($this, 'activate'));

    // Admin menu
    add_action('admin_menu', array($this, 'add_admin_menu'));

    // Enqueue admin scripts and styles
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    
    // Add premium modal to admin footer
    add_action('admin_footer', array($this, 'render_premium_modal'));
    
    // Add developer footer
    add_action('admin_footer', array($this, 'render_developer_footer'));

    // Load includes
    $this->load_includes();

    // Initialize handlers
    $this->init_handlers();

    // Handle form submissions
    add_action('admin_init', array($this, 'handle_form_submissions'));
    
    // One-time migration to fix email settings
    add_action('admin_init', array($this, 'migrate_email_settings'));

    // ====== AJAX HANDLERS ======
    add_action('wp_ajax_backupzen_create_backup', array($this, 'ajax_create_backup'));
    add_action('wp_ajax_backupzen_run_backup', array($this, 'ajax_run_backup'));
    add_action('wp_ajax_backupzen_get_progress', array($this, 'ajax_get_progress'));
    
    // ====== CRON HANDLER ======
    add_action('backupzen_run_backup_event', array($this, 'cron_run_backup'));
    
    //delete action 
    add_action('wp_ajax_backupzen_delete_backup', array($this, 'ajax_delete_backup'));
	   
    // create traditional backup
	add_action('wp_ajax_backupzen_create_traditional_backup', array($this, 'ajax_create_traditional_backup'));
	
	// ====== EARLY ACCESS HANDLER ======
	add_action('wp_ajax_backupzen_early_access', array($this, 'ajax_early_access_submit'));
	
	// ====== RESTORE AJAX HANDLERS ======
    add_action('wp_ajax_backupzen_restore_backup', array($this, 'ajax_restore_backup'));
    add_action('wp_ajax_backupzen_run_restore', array($this, 'ajax_run_restore'));
    add_action('wp_ajax_backupzen_get_restore_progress', array($this, 'ajax_get_restore_progress'));
    add_action('wp_ajax_backupzen_upload_and_restore', array($this, 'ajax_upload_and_restore'));
    
    // ====== CRON HANDLERS ======
    add_action('backupzen_run_backup_event', array($this, 'cron_run_backup'));
    add_action('backupzen_run_restore_event', array($this, 'cron_run_restore'));
    
    // Register custom cron schedule
    add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
    
    // Register cron hook
    add_action('backupzen_scheduled_backup_event', array($this, 'run_scheduled_backup'));
    
}
	


    /**
     * Load required includes.
     *
     * @return void
     */
    private function load_includes()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/BzenPackager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/BackupScanner.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/TimezoneConverter.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/ScheduleManager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/EmailNotifier.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/CleanupManager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Backup/ScheduleController.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Admin/Feedback.php';
        
        // Load PRO Features Manager
        require_once plugin_dir_path(__FILE__) . 'includes/Premium/ProFeaturesManager.php';
        
        // Initialize Schedule Controller
        new \BackupZen\Backup\ScheduleController();
        
        // Initialize Feedback Widget
        new BackupZen_Feedback();
    }

    /**
     * Initialize download and restore handlers.
     *
     * @return void
     */
    private function init_handlers()
    {
        // Download handler.
        add_action('admin_post_backupzen_download', array($this, 'handle_download'));

        // Restore handler.
        add_action('admin_post_backupzen_restore', array($this, 'handle_restore'));
    }

    /**
     * Plugin activation callback.
     * Creates backup directory, .htaccess protection, and database tables.
     *
     * @return void
     */
    public function activate()
    {
        $backup_dir = $this->get_backup_dir_path();

        // Create backup directory if it doesn't exist.
        if (! file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // Create .htaccess to protect directory.
        $htaccess_file = trailingslashit($backup_dir) . '.htaccess';
        if (! file_exists($htaccess_file)) {
            $htaccess_content = "# BackupZen - Deny direct access\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing.
        $index_file = trailingslashit($backup_dir) . 'index.php';
        if (! file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
        
        // Create database table for early access emails
        $this->create_early_access_table();
    }
    
    /**
     * Create database table for early access emails
     * 
     * @return void
     */
    private function create_early_access_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'backupzen_early_access';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get backup directory absolute path.
     *
     * @return string
     */
    public function get_backup_dir_path()
    {
        return trailingslashit(WP_CONTENT_DIR) . self::BACKUP_DIR;
    }

    /**
     * Get backup directory URL.
     *
     * @return string
     */
    public function get_backup_dir_url()
    {
        return trailingslashit(content_url()) . self::BACKUP_DIR;
    }

    /**
     * Render premium modal in admin footer
     * 
     * @return void
     */
    public function render_premium_modal()
    {
        // Only render on backupZen pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'backupzen') !== 0) {
            return;
        }
        
        // Include premium modal template
        $modal_file = plugin_dir_path(__FILE__) . 'views/premium-modal.php';
        if (file_exists($modal_file)) {
            include $modal_file;
        }
    }
    
    /**
     * Render developer footer on plugin pages
     * 
     * @return void
     */
    public function render_developer_footer()
    {
        // Only render on backupZen pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'backupzen') !== 0) {
            return;
        }
        ?>
        <div style="margin: 32px 0 20px 0; padding: 16px 0; border-top: 1px solid #e2e8f0; text-align: center;">
            <p style="margin: 0; font-size: 13px; color: #64748b;">
                <?php echo esc_html__('Crafted with', 'backupzen'); ?> 
                <span style="color: #dc2626;">❤</span> 
                <?php echo esc_html__('by', 'backupzen'); ?>
                <a href="https://mutahir.qzz.io" target="_blank" rel="noopener" style="color: #3b82f6; text-decoration: none; font-weight: 600; transition: color 0.2s ease;">
                    <?php echo esc_html__('Mutahir Hussain', 'backupzen'); ?>
                </a>
            </p>
        </div>
        <?php
    }

        /**
     * Add admin menu with submenu pages.
     *
     * @return void
     */
    public function add_admin_menu()
    {
        // Main menu page (Dashboard)
        add_menu_page(
            __('BackupZen', 'backupzen'),           // Page title
            __('BackupZen', 'backupzen'),           // Menu title
            'manage_options',                        // Capability
            'backupzen',                            // Menu slug
            array($this, 'render_dashboard_page'),  // Callback
            'dashicons-backup',                     // Icon
            75                                      // Position
        );
    
        // Dashboard submenu (rename first item)
        add_submenu_page(
            'backupzen',                            // Parent slug
            __('Dashboard', 'backupzen'),           // Page title
            __('Dashboard', 'backupzen'),           // Menu title
            'manage_options',                       // Capability
            'backupzen',                           // Menu slug (same as parent)
            array($this, 'render_dashboard_page')  // Callback
        );
    
        // Manual Backup submenu
        add_submenu_page(
            'backupzen',
            __('Manual Backup', 'backupzen'),
            __('Manual Backup', 'backupzen'),
            'manage_options',
            'backupzen-manual',
            array($this, 'render_manual_backup_page')
        );
    
        // Scheduled Backups submenu
        add_submenu_page(
            'backupzen',
            __('Scheduled Backups', 'backupzen'),
            __('Scheduled Backups', 'backupzen'),
            'manage_options',
            'backupzen-scheduled',
            array($this, 'render_scheduled_page')
        );
    
        // Available Backups submenu
        add_submenu_page(
            'backupzen',
            __('Available Backups', 'backupzen'),
            __('Available Backups', 'backupzen'),
            'manage_options',
            'backupzen-backups',
            array($this, 'render_backups_page')
        );
    
    }


       /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
        public function enqueue_admin_assets($hook)
    {
        // Check if we're on our admin page
        $is_our_page = false;
        
        if ('tools_page_backupzen' === $hook) {
            $is_our_page = true;
        }
        
        if (strpos($hook, 'backupzen') !== false) {
            $is_our_page = true;
        }
        
        if (isset($_GET['page']) && strpos($_GET['page'], 'backupzen') === 0) {
            $is_our_page = true;
        }
        
        if (!$is_our_page) {
            return;
        }
        
        // Enqueue Google Font
        wp_enqueue_style(
            'backupzen-poppins-font',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap',
            array(),
            null
        );
        
        // Dashicons
        wp_enqueue_style('dashicons');
    
        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');
    
        // Enqueue admin styles
        wp_enqueue_style(
            'backupzen-admin',
            plugins_url('assets/admin.css', __FILE__),
            array(),
            self::VERSION . '-' . time()
        );
        
        // Enqueue PRO features CSS
        wp_enqueue_style(
            'backupzen-pro-features',
            plugins_url('assets/css/pro-features.css', __FILE__),
            array('backupzen-admin'),
            self::VERSION . '-' . time()
        );
        
        // Enqueue schedule page CSS
        if (isset($_GET['page']) && $_GET['page'] === 'backupzen-scheduled') {
            wp_enqueue_style(
                'backupzen-schedule',
                plugins_url('assets/css/schedule.css', __FILE__),
                array('backupzen-admin'),
                self::VERSION . '-' . time()
            );
        }
    
        // Enqueue admin scripts
        wp_enqueue_script(
            'backupzen-admin',
            plugins_url('assets/admin.js', __FILE__),
            array('jquery'),
            self::VERSION . '-' . time(),
            true
        );
        
        // Enqueue PRO features JS
        wp_enqueue_script(
            'backupzen-pro-features',
            plugins_url('assets/js/pro-features.js', __FILE__),
            array('jquery'),
            self::VERSION . '-' . time(),
            true
        );
        
        // Enqueue scheduled backups JS on scheduled page
        if (isset($_GET['page']) && $_GET['page'] === 'backupzen-scheduled') {
            wp_enqueue_script(
                'backupzen-schedule',
                plugins_url('assets/js/schedule.js', __FILE__),
                array('jquery'),
                self::VERSION . '-' . time(),
                true
            );
            
            // Localize for schedule page - FIXED
            wp_localize_script(
                'backupzen-schedule',
                'backupzenData',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('backupzen_nonce'),
                )
            );
        }
    
        // Localize main admin script - FIXED
        wp_localize_script(
            'backupzen-admin',
            'backupZen',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('backupzen_nonce'),
                'serverTimestamp' => current_time('timestamp'),
                'downloadUrl' => wp_nonce_url(
                    add_query_arg(
                        array('action' => 'backupzen_download'),
                        admin_url('admin-post.php')
                    ),
                    'backupzen_download'
                ),
            )
        );
        
        // Add inline script to verify everything loaded
        wp_add_inline_script(
            'backupzen-admin',
            'console.log("BackupZen script loaded successfully");',
            'before'
        );
    }
    
       /**
     * Handle form submissions.
     *
     * @return void
     */
    public function handle_form_submissions()
    {
        // Handle backup creation.
        if (isset($_POST['backupzen_create']) && isset($_POST['backupzen_create_nonce'])) {
            // Verify nonce.
            if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['backupzen_create_nonce'])), 'backupzen_create_backup')) {
                wp_die(esc_html__('Security check failed.', 'backupzen'));
            }
    
            // Capability check.
            if (! current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have sufficient permissions.', 'backupzen'));
            }
    
            // Get form data.
            $backup_files   = isset($_POST['backup_files']) ? 1 : 0;
            $backup_database = isset($_POST['backup_database']) ? 1 : 0;
            $backup_format   = isset($_POST['backup_format']) ? sanitize_text_field(wp_unslash($_POST['backup_format'])) : 'zip';
    
            // IMPORTANT: Handle each format
            switch ($backup_format) {
                case 'bzen':
                    $this->handle_bzen_backup($backup_files, $backup_database);
                    break;
                    
                case 'zip':
                    $this->handle_zip_backup($backup_files, $backup_database);
                    break;
                    
                case 'sql':
                    $this->handle_sql_backup($backup_database);
                    break;
                    
                case 'sql.gz':
                    $this->handle_sqlgz_backup($backup_database);
                    break;
                    
                default:
                    add_action('admin_notices', function () use ($backup_format) {
                        ?>
                        <div class="notice notice-error is-dismissible">
                            <p><?php echo esc_html(sprintf(__('Unknown backup format: "%s"', 'backupzen'), $backup_format)); ?></p>
                        </div>
                        <?php
                    });
                    break;
            }
        }
    }
    	
    	
    /**
     * Handle ZIP backup creation (traditional method).
     */
    private function handle_zip_backup($include_files, $include_database)
    {
        // Generate filename
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename  = sprintf('%s-%s.zip', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        // Create ZIP archive
        $zip = new \ZipArchive();
        if (true !== $zip->open($file_path, \ZipArchive::CREATE)) {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html__('Failed to create ZIP archive.', 'backupzen'); ?></p>
                </div>
                <?php
            });
            return;
        }
    
        // Add database if requested
        if ($include_database) {
            $temp_db = trailingslashit($this->get_backup_dir_path()) . 'temp_database.sql';
            $packager = new \BackupZen\Backup\BzenPackager();
            // Use reflection to call private method
            $method = new \ReflectionMethod($packager, 'export_database_to_file');
            $method->setAccessible(true);
            $method->invoke($packager, $temp_db);
            
            if (file_exists($temp_db)) {
                $zip->addFile($temp_db, 'database.sql');
            }
        }
    
        // Add files if requested
        if ($include_files) {
            // Use the same logic from BzenPackager
            $packager = new \BackupZen\Backup\BzenPackager();
            $method = new \ReflectionMethod($packager, 'add_files_to_zip');
            $method->setAccessible(true);
            $method->invoke($packager, $zip);
        }
    
        $zip->close();
    
        // Clean up temp database
        if ($include_database && file_exists($temp_db)) {
            @unlink($temp_db);
        }
    
        // Show success message
        add_action('admin_notices', function () use ($filename) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php echo esc_html__('Backup created successfully!', 'backupzen'); ?></strong><br>
                    <?php echo esc_html(sprintf(__('File: %s', 'backupzen'), $filename)); ?>
                </p>
            </div>
            <?php
        });
    }
    
    	
    	/**
     * Handle SQL backup creation.
     */
    private function handle_sql_backup($include_database)
    {
        if (!$include_database) {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php echo esc_html__('Database backup must be selected for SQL format.', 'backupzen'); ?></p>
                </div>
                <?php
            });
            return;
        }
    
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename  = sprintf('%s-%s.sql', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        $packager = new \BackupZen\Backup\BzenPackager();
        $method = new \ReflectionMethod($packager, 'export_database_to_file');
        $method->setAccessible(true);
        $result = $method->invoke($packager, $file_path);
    
        if ($result['success']) {
            add_action('admin_notices', function () use ($filename) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php echo esc_html__('SQL backup created successfully!', 'backupzen'); ?></strong><br>
                        <?php echo esc_html(sprintf(__('File: %s', 'backupzen'), $filename)); ?>
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Handle SQL.GZ backup creation.
     */
    private function handle_sqlgz_backup($include_database)
    {
        if (!$include_database) {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php echo esc_html__('Database backup must be selected for SQL.GZ format.', 'backupzen'); ?></p>
                </div>
                <?php
            });
            return;
        }
    
        // First create SQL file
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $temp_sql  = trailingslashit($this->get_backup_dir_path()) . 'temp_' . $timestamp . '.sql';
        $filename  = sprintf('%s-%s.sql.gz', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        $packager = new \BackupZen\Backup\BzenPackager();
        $method = new \ReflectionMethod($packager, 'export_database_to_file');
        $method->setAccessible(true);
        $method->invoke($packager, $temp_sql);
    
        // Compress it
        $input = fopen($temp_sql, 'rb');
        $output = gzopen($file_path, 'wb9');
        
        while (!feof($input)) {
            gzwrite($output, fread($input, 1024 * 512));
        }
        
        fclose($input);
        gzclose($output);
        
        // Delete temp SQL
        @unlink($temp_sql);
    
        add_action('admin_notices', function () use ($filename) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php echo esc_html__('SQL.GZ backup created successfully!', 'backupzen'); ?></strong><br>
                    <?php echo esc_html(sprintf(__('File: %s', 'backupzen'), $filename)); ?>
                </p>
            </div>
            <?php
        });
    }
    
    // **
    //  * AJAX handler for creating backup - COMPLETE FIX
    //  */
    
    public function ajax_create_backup()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Get form data
        $backup_files = isset($_POST['backup_files']) ? 1 : 0;
        $backup_database = isset($_POST['backup_database']) ? 1 : 0;
        $backup_format = isset($_POST['backup_format']) ? sanitize_text_field(wp_unslash($_POST['backup_format'])) : 'bzen';
    
        // Only support BZEN format for now
        if ('bzen' !== $backup_format) {
            wp_send_json_error(array('message' => sprintf(__('Backup format "%s" is not yet implemented.', 'backupzen'), $backup_format)));
        }
    
        // Create unique session ID
        $session_id = uniqid('backup_', true);
        
        error_log('BackupZen: Creating backup with session ' . $session_id);
    
        // Store backup parameters - CRITICAL: Set transient timeout to 10 minutes
        set_transient('backupzen_params_' . $session_id, array(
            'backup_files' => $backup_files,
            'backup_database' => $backup_database,
            'session_id' => $session_id,
            'started' => time(),
        ), 600); // 10 minutes
    
        // Set initial progress - CRITICAL: Set transient timeout to 10 minutes
        set_transient('backupzen_progress_' . $session_id, array(
            'status' => 'starting',
            'step' => __('Initializing backup...', 'backupzen'),
            'progress' => 0,
            'timestamp' => time(),
        ), 600); // 10 minutes
    
        // Create spawn URL with all parameters
        $spawn_url = add_query_arg(array(
            'action' => 'backupzen_run_backup',
            'session_id' => $session_id,
            'nonce' => wp_create_nonce('backupzen_nonce'),
        ), admin_url('admin-ajax.php'));
    
        error_log('BackupZen: Spawn URL: ' . $spawn_url);
    
        // Return immediately with spawn URL for client-side spawning
        wp_send_json_success(array(
            'session_id' => $session_id,
            'spawn_url' => $spawn_url,
            'message' => __('Backup started', 'backupzen')
        ));
    }


    	
    /**
     * Spawn backup process in background using WordPress Cron.
     * This is more reliable than wp_remote_post for long-running tasks.
     */
    private function spawn_background_backup($session_id, $backup_files, $backup_database)
    {
        // Store backup parameters in transient
        set_transient('backupzen_params_' . $session_id, array(
            'backup_files' => $backup_files,
            'backup_database' => $backup_database,
            'session_id' => $session_id,
        ), 300);
    
        // Schedule immediate background task
        if (!wp_next_scheduled('backupzen_run_backup_event', array($session_id))) {
            wp_schedule_single_event(time(), 'backupzen_run_backup_event', array($session_id));
        }
    
        // Also try spawning via HTTP as fallback (non-blocking)
        wp_remote_post(admin_url('admin-ajax.php'), array(
            'blocking' => false,
            'timeout' => 0.01,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => array(
                'action' => 'backupzen_run_backup',
                'nonce' => wp_create_nonce('backupzen_nonce'),
                'session_id' => $session_id,
            ),
            'cookies' => array(),
        ));
    
        // Force cron to run immediately
        spawn_cron();
    }
    
    
    /**
     * AJAX handler to run backup - SUPPORTS BOTH GET AND POST
     */
    public function ajax_run_backup()
    {
        // Get session ID from GET or POST
        $session_id = '';
        if (isset($_GET['session_id'])) {
            $session_id = sanitize_text_field(wp_unslash($_GET['session_id']));
        } elseif (isset($_POST['session_id'])) {
            $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
        }
        
        if (empty($session_id)) {
            error_log('BackupZen Run: No session ID provided');
            die('No session ID'); // Don't use wp_send_json_error for iframe spawning
        }
    
        // Verify nonce from GET or POST
        $nonce = '';
        if (isset($_GET['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        } elseif (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        }
        
        if (!wp_verify_nonce($nonce, 'backupzen_nonce')) {
            error_log('BackupZen Run: Nonce verification failed');
            die('Invalid nonce'); // Don't use wp_send_json_error for iframe spawning
        }
    
        error_log('BackupZen Run: Starting for session ' . $session_id);
    
        // Get parameters
        $params = get_transient('backupzen_params_' . $session_id);
        if (false === $params) {
            error_log('BackupZen Run: Parameters not found for session ' . $session_id);
            die('Parameters not found'); // Don't use wp_send_json_error for iframe spawning
        }
    
        $backup_files = isset($params['backup_files']) ? intval($params['backup_files']) : 0;
        $backup_database = isset($params['backup_database']) ? intval($params['backup_database']) : 0;
    
        // Send minimal response and close connection immediately
        if (!headers_sent()) {
            // Send headers to close connection
            header('Content-Type: text/html; charset=utf-8');
            header('Connection: close');
            echo '<!DOCTYPE html><html><body>Background process started</body></html>';
            
            // Calculate content length
            $size = ob_get_length();
            header('Content-Length: ' . $size);
            
            // Flush output buffers
            ob_end_flush();
            flush();
            
            // FastCGI finish
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }
    
        // Now we're running in background
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
        ignore_user_abort(true);
    
        // Small delay to ensure connection is closed
        sleep(1);
    
        error_log('BackupZen Run: Connection closed, starting backup process');
    
        // Run backup
        try {
            $this->create_backup_with_progress($backup_files, $backup_database, $session_id);
            error_log('BackupZen Run: Backup completed successfully for session ' . $session_id);
        } catch (Exception $e) {
            error_log('BackupZen Run: Exception during backup: ' . $e->getMessage());
        }
    
        // Clean up
        delete_transient('backupzen_params_' . $session_id);
    
        error_log('BackupZen Run: All done for session ' . $session_id);
        exit;
    }
    	
    // 	**
    //  * WordPress Cron callback for running backup.
    //  */
    public function cron_run_backup($session_id)
    {
        if (empty($session_id)) {
            error_log('BackupZen Cron: No session ID provided');
            return;
        }
    
        // Get backup parameters
        $params = get_transient('backupzen_params_' . $session_id);
        if (false === $params) {
            error_log('BackupZen Cron: Backup parameters not found for session ' . $session_id);
            return;
        }
    
        $backup_files = isset($params['backup_files']) ? intval($params['backup_files']) : 0;
        $backup_database = isset($params['backup_database']) ? intval($params['backup_database']) : 0;
    
        error_log('BackupZen Cron: Starting backup with session ' . $session_id);
    
        // Increase limits
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
        
        // Run the backup
        $this->create_backup_with_progress($backup_files, $backup_database, $session_id);
        
        // Clean up
        delete_transient('backupzen_params_' . $session_id);
        
        error_log('BackupZen Cron: Backup completed for session ' . $session_id);
    }
    
    
    /**
     * AJAX handler for getting backup progress - FIXED
     */
    public function ajax_get_progress()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required.', 'backupzen')));
        }
        
        // Read DIRECTLY from database to bypass object cache
        global $wpdb;
        $option_name = '_transient_backupzen_progress_' . $session_id;
        
        $progress_data = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option_name
        ));
        
        if (empty($progress_data)) {
            // Check if parameters still exist (backup hasn't started yet)
            $params_exist = get_transient('backupzen_params_' . $session_id);
            if (false !== $params_exist) {
                // Backup is queued but not started yet
                wp_send_json_success(array(
                    'status' => 'starting',
                    'step' => __('Waiting for backup to start...', 'backupzen'),
                    'progress' => 0,
                    'timestamp' => time(),
                ));
            } else {
                wp_send_json_error(array('message' => __('Progress data not found.', 'backupzen')));
            }
            return;
        }
        
        $progress = maybe_unserialize($progress_data);
        
        if (false === $progress) {
            wp_send_json_error(array('message' => __('Invalid progress data.', 'backupzen')));
        }
    
        wp_send_json_success($progress);
    }


    /**
     * Create backup with REAL progress tracking.
     */
    private function create_backup_with_progress($include_files, $include_database, $session_id)
    {
    // Update progress helper that writes DIRECTLY to database (bypassing object cache)
    $update_progress = function($step, $progress) use ($session_id) {
        global $wpdb;
        
        $data = array(
            'status' => 'running',
            'step' => $step,
            'progress' => $progress,
            'timestamp' => time(),
        );
        
        // Write directly to database to bypass object cache issues
        $option_name = '_transient_backupzen_progress_' . $session_id;
        $timeout_name = '_transient_timeout_backupzen_progress_' . $session_id;
        $timeout = time() + 300; // 5 minutes
        
        // Delete existing transient first
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
            $option_name,
            $timeout_name
        ));
        
        // Insert new values
        $wpdb->insert(
            $wpdb->options,
            array(
                'option_name' => $option_name,
                'option_value' => maybe_serialize($data),
                'autoload' => 'no'
            ),
            array('%s', '%s', '%s')
        );
        
        $wpdb->insert(
            $wpdb->options,
            array(
                'option_name' => $timeout_name,
                'option_value' => $timeout,
                'autoload' => 'no'
            ),
            array('%s', '%d', '%s')
        );
        
        // Force database flush to ensure writes are immediately visible
        $wpdb->flush();
        
        // Log progress for debugging
        error_log(sprintf('BackupZen Progress: %s - %d%%', $step, $progress));
    };

    try {
        $update_progress(__('Starting backup process...', 'backupzen'), 0);
        
        // Generate filename
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename = sprintf('%s-%s.bzen', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;

        $update_progress(__('Preparing backup directory...', 'backupzen'), 3);

        // Prepare options
        $options = array(
            'include_files' => (bool) $include_files,
            'include_database' => (bool) $include_database,
        );

        // Create BZEN package with progress callbacks
        $packager = new \BackupZen\Backup\BzenPackager();
        
        $update_progress(__('Initializing backup package...', 'backupzen'), 5);
        
        // Use the method with progress callback
        $result = $packager->create_with_progress($file_path, $options, $update_progress);

        if ($result['success']) {
            if (!isset($result['filename'])) {
                $result['filename'] = $filename;
            }
            
            $update_progress(__('Backup completed successfully!', 'backupzen'), 100);
            
            // Set final completed status
            global $wpdb;
            $option_name = '_transient_backupzen_progress_' . $session_id;
            $data = array(
                'status' => 'completed',
                'step' => __('Backup completed successfully!', 'backupzen'),
                'progress' => 100,
                'result' => $result,
            );
            
            $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($data)),
                array('option_name' => $option_name)
            );
            
            // Send email notification if enabled
            $this->send_backup_email_if_enabled($result, $filename);
            
            return $result;
        } else {
            $error_message = isset($result['message']) && !empty($result['message']) 
                ? $result['message'] 
                : __('Unknown error occurred during backup creation', 'backupzen');
            
            error_log('BackupZen Error: ' . $error_message);
            
            // Set error status
            global $wpdb;
            $option_name = '_transient_backupzen_progress_' . $session_id;
            $data = array(
                'status' => 'error',
                'step' => $error_message,
                'progress' => 0,
                'error_details' => $result,
            );
            
            $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($data)),
                array('option_name' => $option_name)
            );
            
            // Send email notification for failure if enabled
            $this->send_backup_email_if_enabled($result, $filename);
            
            return $result;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        
        error_log('BackupZen Exception: ' . $error_message);
        
        // Set error status
        global $wpdb;
        $option_name = '_transient_backupzen_progress_' . $session_id;
        $data = array(
            'status' => 'error',
            'step' => $error_message ? $error_message : __('An exception occurred during backup creation', 'backupzen'),
            'progress' => 0,
        );
        
        $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($data)),
            array('option_name' => $option_name)
        );
        
        // Send email notification for exception if enabled
        $exception_result = array(
            'success' => false,
            'message' => $error_message ? $error_message : __('An exception occurred during backup creation', 'backupzen'),
        );
        $this->send_backup_email_if_enabled($exception_result, 'backup-failed');
        
        return $exception_result;
    }
}

    /**
     * Handle backup file download.
     *
     * @return void
     */
    public function handle_download()
    {
        // Capability check.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'backupzen'));
        }

        // Verify nonce.
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'backupzen_download')) {
            wp_die(esc_html__('Security check failed.', 'backupzen'));
        }

        // Get filename and sanitize.
        if (! isset($_GET['file'])) {
            wp_die(esc_html__('File parameter is required.', 'backupzen'));
        }

        $filename = basename(sanitize_file_name(wp_unslash($_GET['file'])));
        $filepath = trailingslashit($this->get_backup_dir_path()) . $filename;

        // Validate file exists and is in backup directory.
        if (! file_exists($filepath)) {
            wp_die(esc_html__('File not found.', 'backupzen'));
        }

        // Prevent directory traversal.
        $real_filepath = realpath($filepath);
        $real_backup_dir = realpath($this->get_backup_dir_path());
        if (false === $real_filepath || false === $real_backup_dir || 0 !== strpos($real_filepath, $real_backup_dir)) {
            wp_die(esc_html__('Invalid file path.', 'backupzen'));
        }

        // Get file mime type.
        $mime_type = 'application/octet-stream';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_types = array(
            'zip'  => 'application/zip',
            'sql'  => 'application/sql',
            'gz'   => 'application/gzip',
            'bzen' => 'application/octet-stream',
        );
        if (isset($mime_types[$extension])) {
            $mime_type = $mime_types[$extension];
        }

        // Set headers for download.
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Stream file.
        readfile($filepath);
        exit;
    }

    /**
     * Create rollback backup before restore.
     *
     * @param string $rollback_dir Rollback directory path.
     * @return array Result array with 'success' boolean and rollback paths.
     * @since 1.0.0
     */
    private function create_rollback($rollback_dir)
    {
        $result = array(
            'success' => true,
            'db_path' => '',
            'files_path' => '',
        );

        // Create rollback directory.
        if (! file_exists($rollback_dir)) {
            wp_mkdir_p($rollback_dir);
        }

        // Backup current database.
        global $wpdb;
        $db_file = trailingslashit($rollback_dir) . 'rollback_database_' . current_time('Y-m-d_His') . '.sql';

        // Export current database.
        $db_backup_result = $this->export_database($db_file);
        if ($db_backup_result['success']) {
            $result['db_path'] = $db_file;
        }

        // Backup changed files (we'll track which files get changed during restore).
        $files_dir = trailingslashit($rollback_dir) . 'files';
        wp_mkdir_p($files_dir);
        $result['files_path'] = $files_dir;

        return $result;
    }

    /**
     * Export current database to SQL file.
     *
     * @param string $output_file Output SQL file path.
     * @return array Result array with 'success' boolean.
     * @since 1.0.0
     */
    private function export_database($output_file)
    {
        global $wpdb;

        $handle = fopen($output_file, 'w');
        if (false === $handle) {
            return array(
                'success' => false,
                'message' => __('Failed to create rollback database file.', 'backupzen'),
            );
        }

        // Write SQL header.
        fwrite($handle, "-- WordPress Database Backup\n");
        fwrite($handle, "-- Generated: " . current_time('mysql') . "\n\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

        // Get all tables.
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);

        foreach ($tables as $table) {
            $table_name = $table[0];

            // Skip if table doesn't match WordPress prefix (safety check).
            if (0 !== strpos($table_name, $wpdb->prefix)) {
                continue;
            }

            // Get table structure.
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
            if ($create_table) {
                fwrite($handle, "\n-- Table structure for `{$table_name}`\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table_name}`;\n");
                fwrite($handle, $create_table[1] . ";\n\n");
            }

            // Get table data.
            $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            if (! empty($rows)) {
                fwrite($handle, "-- Data for table `{$table_name}`\n");
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        $values[] = $wpdb->prepare('%s', $value);
                    }
                    $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    $values_str = implode(', ', $values);
                    fwrite($handle, "INSERT INTO `{$table_name}` ({$columns}) VALUES ({$values_str});\n");
                }
                fwrite($handle, "\n");
            }
        }

        fclose($handle);

        return array(
            'success' => true,
        );
    }

       /**
     * Restore database from SQL file - FIXED VERSION
     *
     * @param string $sql_file Path to SQL file.
     * @param array  $log      Restore log array (passed by reference).
     * @return array Result array with 'success' boolean and message.
     * @since 1.0.0
     */
    private function restore_database($sql_file, &$log = array())
    {
        if (!file_exists($sql_file)) {
            return array(
                'success' => false,
                'message' => __('SQL file not found.', 'backupzen'),
            );
        }
    
        // Validate SQL file
        $sql_content = file_get_contents($sql_file);
        if (empty($sql_content)) {
            return array(
                'success' => false,
                'message' => __('SQL file is empty.', 'backupzen'),
            );
        }
    
        // IMPROVED Security checks: Block ONLY truly dangerous SQL statements
        $dangerous_patterns = array(
            // Prevent dropping the entire database
            '/DROP\s+DATABASE\s+/i',
            // Prevent creating new databases
            '/CREATE\s+DATABASE\s+/i',
            // Prevent switching to different database (but allow current DB)
            '/USE\s+(?!' . preg_quote(DB_NAME, '/') . '\b)[`\w]+/i',
            // Prevent loading external files
            '/LOAD\s+DATA\s+INFILE/i',
            // Prevent executing system commands
            '/INTO\s+OUTFILE/i',
            '/INTO\s+DUMPFILE/i',
        );
    
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $sql_content)) {
                return array(
                    'success' => false,
                    'message' => __('SQL file contains potentially dangerous statements. Pattern matched: ' . $pattern, 'backupzen'),
                );
            }
        }
    
        global $wpdb;
    
        // Disable caching during restore
        wp_suspend_cache_addition(true);
    
        // Try mysqldump import first (if available and file is large)
        $file_size = filesize($sql_file);
        $use_mysqldump = $file_size > 10 * 1024 * 1024; // 10MB threshold
    
        if ($use_mysqldump && function_exists('exec')) {
            // Try to use mysql CLI for large files
            $db_name = DB_NAME;
            $db_user = DB_USER;
            $db_pass = DB_PASSWORD;
            $db_host = DB_HOST;
    
            // Parse host:port if needed
            $host_parts = explode(':', $db_host);
            $db_host_only = $host_parts[0];
            $db_port = isset($host_parts[1]) ? $host_parts[1] : '3306';
    
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
                escapeshellarg($db_host_only),
                escapeshellarg($db_port),
                escapeshellarg($db_user),
                escapeshellarg($db_pass),
                escapeshellarg($db_name),
                escapeshellarg($sql_file)
            );
    
            $output = array();
            $return_var = 0;
            @exec($command, $output, $return_var);
    
            if (0 === $return_var) {
                // Count tables restored
                $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
                $log['tables_restored'] = count($tables);
    
                wp_suspend_cache_addition(false);
                return array(
                    'success' => true,
                    'message' => __('Database restored successfully using mysql CLI.', 'backupzen'),
                );
            }
            // Fall through to wpdb method if mysql CLI fails
        }
    
        // Split SQL into individual statements (chunked for large files)
        $statements = $this->split_sql_statements($sql_content);
    
        $errors = array();
        $tables_restored = 0;
        $chunk_size = 100; // Process 100 statements at a time
    
        for ($i = 0; $i < count($statements); $i += $chunk_size) {
            $chunk = array_slice($statements, $i, $chunk_size);
    
            foreach ($chunk as $statement) {
                $statement = trim($statement);
                if (empty($statement) || '--' === substr($statement, 0, 2)) {
                    continue;
                }
    
                // Track table creation/insertion
                if (preg_match('/CREATE\s+TABLE/i', $statement) || preg_match('/INSERT\s+INTO/i', $statement)) {
                    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                        $tables_restored++;
                    }
                }
    
                // Execute statement
                $result = $wpdb->query($statement);
                if (false === $result && !empty($wpdb->last_error)) {
                    $errors[] = $wpdb->last_error;
                    
                    // If too many errors, stop
                    if (count($errors) > 10) {
                        break 2;
                    }
                }
            }
    
            // Small delay to prevent overwhelming the database
            usleep(10000); // 10ms
        }
    
        $log['tables_restored'] = $tables_restored;
    
        // Re-enable caching
        wp_suspend_cache_addition(false);
    
        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => __('Database restore encountered errors: ', 'backupzen') . implode(', ', array_unique(array_slice($errors, 0, 5))),
            );
        }
    
        return array(
            'success' => true,
            'message' => sprintf(__('Database restored successfully. %d tables processed.', 'backupzen'), $tables_restored),
        );
    }

    /**
     * Split SQL content into individual statements.
     *
     * @param string $sql SQL content.
     * @return array Array of SQL statements.
     * @since 1.0.0
     */
    private function split_sql_statements($sql)
    {
        // Remove comments.
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Split by semicolon, but preserve semicolons inside quotes.
        $statements = array();
        $current = '';
        $in_string = false;
        $string_char = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            $prev_char = $i > 0 ? $sql[$i - 1] : '';

            if (! $in_string && ("'" === $char || '"' === $char)) {
                $in_string = true;
                $string_char = $char;
            } elseif ($in_string && $char === $string_char && '\\' !== $prev_char) {
                $in_string = false;
            }

            $current .= $char;

            if (! $in_string && ';' === $char) {
                $statements[] = trim($current);
                $current = '';
            }
        }

        if (! empty(trim($current))) {
            $statements[] = trim($current);
        }

        return array_filter($statements);
    }

    /**
     * Restore files from extracted directory.
     *
     * @param string $files_path Path to extracted files directory.
     * @param array  $log        Restore log array (passed by reference).
     * @return array Result array with 'success' boolean and message.
     * @since 1.0.0
     */
    private function restore_files($files_path, &$log = array())
    {
        if (! is_dir($files_path)) {
            return array(
                'success' => false,
                'message' => __('Files directory not found.', 'backupzen'),
            );
        }

        $restored_count = 0;
        $errors = array();

        // Initialize WP_Filesystem for safe file operations.
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Get wp-content path.
        $wp_content_dir = WP_CONTENT_DIR;

        // Recursively copy files using WP_Filesystem.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($files_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $source = $item->getPathname();
            $relative_path = str_replace(trailingslashit($files_path), '', $source);
            $destination = trailingslashit($wp_content_dir) . $relative_path;

            // Never overwrite wp-config.php.
            if ('wp-config.php' === basename($destination)) {
                continue;
            }

            // Ensure destination directory exists.
            $dest_dir = dirname($destination);
            if (! $wp_filesystem->is_dir($dest_dir)) {
                $wp_filesystem->mkdir($dest_dir, FS_CHMOD_DIR);
            }

            if ($item->isDir()) {
                if (! $wp_filesystem->is_dir($destination)) {
                    $wp_filesystem->mkdir($destination, FS_CHMOD_DIR);
                }
            } else {
                // Copy file using WP_Filesystem.
                $file_content = $wp_filesystem->get_contents($source);
                if (false !== $file_content) {
                    if ($wp_filesystem->put_contents($destination, $file_content, FS_CHMOD_FILE)) {
                        $restored_count++;
                    } else {
                        $errors[] = sprintf(__('Failed to copy: %s', 'backupzen'), $relative_path);
                    }
                } else {
                    $errors[] = sprintf(__('Failed to read: %s', 'backupzen'), $relative_path);
                }
            }
        }

        $log['files_restored'] = $restored_count;

        if (! empty($errors)) {
            return array(
                'success' => false,
                'message' => __('Some files failed to restore: ', 'backupzen') . implode(', ', array_slice($errors, 0, 5)),
                'restored_count' => $restored_count,
            );
        }

        return array(
            'success' => true,
            'message' => sprintf(__('Restored %d files successfully.', 'backupzen'), $restored_count),
            'restored_count' => $restored_count,
        );
    }

    /**
     * Create pre-restore backup as .bzen file.
     *
     * @return array Result array with 'success' boolean and backup file path.
     * @since 1.0.0
     */
    private function create_pre_restore_backup()
    {
        $timestamp = current_time('Ymd_His');
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $backup_filename = sprintf('temp-restore-%s.bzen', $timestamp);
        $backup_path = trailingslashit($this->get_backup_dir_path()) . $backup_filename;

        // Create backup with both files and database.
        $packager = new \BackupZen\Backup\BzenPackager();
        $result = $packager->create($backup_path, array(
            'include_files'   => true,
            'include_database' => true,
        ));

        if (! $result['success']) {
            return array(
                'success' => false,
                'message' => __('Failed to create pre-restore backup.', 'backupzen'),
            );
        }

        return array(
            'success' => true,
            'file_path' => $backup_path,
            'filename' => $backup_filename,
        );
    }

    /**
     * Handle backup restore.
     *
     * @return void
     */
    public function handle_restore()
    {
        // Capability check.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'backupzen'));
        }

        // Verify nonce.
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'backupzen_restore')) {
            wp_die(esc_html__('Security check failed.', 'backupzen'));
        }

        // Get filename and sanitize.
        if (! isset($_POST['file']) || empty($_POST['file'])) {
            wp_die(esc_html__('File parameter is required.', 'backupzen'));
        }

        $filename = basename(sanitize_file_name(wp_unslash($_POST['file'])));
        $filepath = trailingslashit($this->get_backup_dir_path()) . $filename;

        // Validate file exists and is in backup directory.
        if (! file_exists($filepath)) {
            wp_die(esc_html__('File not found.', 'backupzen'));
        }

        // Prevent directory traversal.
        $real_filepath = realpath($filepath);
        $real_backup_dir = realpath($this->get_backup_dir_path());
        if (false === $real_filepath || false === $real_backup_dir || 0 !== strpos($real_filepath, $real_backup_dir)) {
            wp_die(esc_html__('Invalid file path.', 'backupzen'));
        }

        // Detect file type.
        $scanner = new \BackupZen\Backup\BackupScanner($this->get_backup_dir_path());
        $type = $scanner->detect_file_type($filename);

        // Only handle .bzen files for now.
        if ('bzen' !== $type) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_notice' => '1',
                    'file' => rawurlencode($filename),
                ),
                admin_url('tools.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Create pre-restore backup.
        $pre_backup_result = $this->create_pre_restore_backup();
        if (! $pre_backup_result['success']) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_error' => '1',
                    'message' => rawurlencode($pre_backup_result['message']),
                ),
                admin_url('tools.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Get payload from BZEN file with checksum verification.
        $packager = new \BackupZen\Backup\BzenPackager();
        $payload_result = $packager->get_payload($filepath);

        if (! $payload_result['success']) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_error' => '1',
                    'message' => rawurlencode($payload_result['message']),
                ),
                admin_url('tools.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        $metadata = $payload_result['metadata'];
        $payload = $payload_result['payload'];

        // Extract to temporary directory.
        $temp_dir = trailingslashit(WP_CONTENT_DIR) . 'backupzen_temp_' . uniqid();
        if (! wp_mkdir_p($temp_dir)) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_error' => '1',
                    'message' => rawurlencode(__('Failed to create temporary directory.', 'backupzen')),
                ),
                admin_url('tools.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        $extract_result = $packager->extract($filepath, $temp_dir);
        if (! $extract_result['success']) {
            $this->delete_directory($temp_dir);
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_error' => '1',
                    'message' => rawurlencode($extract_result['message']),
                ),
                admin_url('tools.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Initialize restore logging.
        $restore_log = array(
            'files_restored' => 0,
            'tables_restored' => 0,
            'errors' => array(),
        );

        $restore_errors = array();
        $restore_success = true;

        // Restore database if included.
        $include_db = isset($metadata['options']['include_database']) && $metadata['options']['include_database'];
        if ($include_db && ! empty($extract_result['db_path']) && file_exists($extract_result['db_path'])) {
            $db_result = $this->restore_database($extract_result['db_path'], $restore_log);
            if (! $db_result['success']) {
                $restore_errors[] = $db_result['message'];
                $restore_success = false;
            }
        }

        // Restore files if included.
        $include_files = isset($metadata['options']['include_files']) && $metadata['options']['include_files'];
        if ($include_files && ! empty($extract_result['files_path']) && is_dir($extract_result['files_path'])) {
            $files_result = $this->restore_files($extract_result['files_path'], $restore_log);
            if (! $files_result['success']) {
                $restore_errors[] = $files_result['message'];
                $restore_success = false;
            }
        }

        // Cleanup temp directory.
        $this->delete_directory($temp_dir);

        // Redirect with result and logging info.
        if ($restore_success) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_success' => '1',
                    'file' => rawurlencode($filename),
                    'files_count' => $restore_log['files_restored'],
                    'tables_count' => $restore_log['tables_restored'],
                    'pre_backup' => rawurlencode($pre_backup_result['filename']),
                ),
                admin_url('tools.php')
            );
        } else {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'backupzen',
                    'restore_error' => '1',
                    'message' => rawurlencode(implode(' ', $restore_errors)),
                    'pre_backup' => rawurlencode($pre_backup_result['filename']),
                ),
                admin_url('tools.php')
            );
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir Directory path.
     * @return bool True on success, false on failure.
     * @since 1.0.0
     */
    private function delete_directory($dir)
    {
        if (! is_dir($dir)) {
            // Directory doesn't exist, consider it "deleted".
            return true;
        }

        // Try to scan directory.
        $files = @scandir($dir);
        if (false === $files) {
            // If scandir fails, try to remove directory directly.
            // This handles cases where directory is empty or inaccessible.
            @rmdir($dir);
            return true;
        }

        $files = array_diff($files, array('.', '..'));

        foreach ($files as $file) {
            $path = trailingslashit($dir) . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        // Try to remove directory, suppress errors if it fails.
        @rmdir($dir);
        return true;
    }

    /**
     * Handle BZEN backup creation.
     *
     * @param int $include_files   Whether to include files (1) or not (0).
     * @param int $include_database Whether to include database (1) or not (0).
     * @return void
     */
    private function handle_bzen_backup($include_files, $include_database)
    {
        // Generate filename.
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename  = sprintf('%s-%s.bzen', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;

        // Prepare options.
        $options = array(
            'include_files'   => (bool) $include_files,
            'include_database' => (bool) $include_database,
        );

        // Create BZEN package.
        $packager = new \BackupZen\Backup\BzenPackager();
        $result = $packager->create($file_path, $options);

        // Show result message.
        if ($result['success']) {
            add_action('admin_notices', function () use ($filename, $result) {
            ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php echo esc_html__('Backup created successfully!', 'backupzen'); ?></strong><br>
                        <?php echo esc_html(sprintf(__('File: %s', 'backupzen'), $filename)); ?><br>
                        <?php echo esc_html(sprintf(__('Size: %s', 'backupzen'), size_format($result['file_size']))); ?><br>
                        <?php echo esc_html(sprintf(__('Checksum: %s', 'backupzen'), substr($result['checksum'], 0, 16) . '...')); ?>
                    </p>
                </div>
            <?php
            });
        } else {
            add_action('admin_notices', function () use ($result) {
            ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php echo esc_html__('Backup creation failed:', 'backupzen'); ?></strong> <?php echo esc_html($result['message']); ?></p>
                </div>
        <?php
            });
        }
    }


    
    /**
     * One-time migration to fix email settings.
     * Changes email_on_failure default from TRUE to FALSE.
     * 
     * @return void
     */
    public function migrate_email_settings()
    {
        // Check if migration already ran
        $migration_done = get_option('backupzen_email_migration_v1', false);
        
        if (!$migration_done) {
            // Get current setting (old default was TRUE)
            $email_on_failure = get_option('backupzen_email_on_failure', null);
            
            // Only update if it was set to true (old default) or not set at all
            if ($email_on_failure === true || $email_on_failure === null) {
                update_option('backupzen_email_on_failure', false);
                error_log('BackupZen: ✓ Email settings migrated! You will now receive emails for ALL backups (not just failures)');
                
                // Set a transient to show admin notice
                set_transient('backupzen_email_migration_notice', true, 60);
            }
            
            // Mark migration as complete
            update_option('backupzen_email_migration_v1', true);
        }
        
        // Show admin notice if migration just ran
        if (get_transient('backupzen_email_migration_notice')) {
            add_action('admin_notices', array($this, 'show_migration_notice'));
        }
    }
    
    /**
     * Show migration success notice
     * 
     * @return void
     */
    public function show_migration_notice()
    {
        // Only show on BackupZen pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'backupzen') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php echo esc_html__('BackupZen Settings Updated!', 'backupzen'); ?></strong><br>
                <?php echo esc_html__('✓ Email notifications will now be sent for ALL scheduled backups (success + failure).', 'backupzen'); ?><br>
                <?php echo esc_html__('✓ Time display issue fixed - timestamps will now show correctly.', 'backupzen'); ?>
            </p>
        </div>
        <?php
        
        // Delete the transient so it only shows once
        delete_transient('backupzen_email_migration_notice');
    }
    
    /**
     * AJAX handler for deleting backup files
     * Add this method to your BackupZen class
     */
    public function ajax_delete_backup()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Get filename
        if (!isset($_POST['filename']) || empty($_POST['filename'])) {
            wp_send_json_error(array('message' => __('Filename is required.', 'backupzen')));
        }
    
        $filename = basename(sanitize_file_name(wp_unslash($_POST['filename'])));
        $filepath = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        // Validate file exists
        if (!file_exists($filepath)) {
            wp_send_json_error(array('message' => __('Backup file not found.', 'backupzen')));
        }
    
        // Prevent directory traversal
        $real_filepath = realpath($filepath);
        $real_backup_dir = realpath($this->get_backup_dir_path());
        
        if (false === $real_filepath || false === $real_backup_dir || 0 !== strpos($real_filepath, $real_backup_dir)) {
            wp_send_json_error(array('message' => __('Invalid file path.', 'backupzen')));
        }
    
        // Only allow deletion of backup files (check extensions)
        $allowed_extensions = array('zip', 'sql', 'gz', 'bzen');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Special handling for .sql.gz files
        if ($extension === 'gz') {
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $second_extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if ($second_extension !== 'sql') {
                wp_send_json_error(array('message' => __('Invalid file type.', 'backupzen')));
            }
        } elseif (!in_array($extension, $allowed_extensions, true)) {
            wp_send_json_error(array('message' => __('Invalid file type.', 'backupzen')));
        }
    
        // Attempt to delete the file
        if (@unlink($filepath)) {
            // Log the deletion
            error_log(sprintf('BackupZen: Backup file deleted - %s by user %s', $filename, wp_get_current_user()->user_login));
            
            wp_send_json_success(array(
                'message' => __('Backup deleted successfully.', 'backupzen'),
                'filename' => $filename,
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete backup file. Check file permissions.', 'backupzen'),
            ));
        }
    }
    
    /**
     * Handle early access email submission
     * 
     * @return void
     */
    public function ajax_early_access_submit()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
        
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
        
        // Get and validate email
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            wp_send_json_error(array('message' => __('Email address is required.', 'backupzen')));
        }
        
        $email = sanitize_email(wp_unslash($_POST['email']));
        
        // Validate email format
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'backupzen')));
        }
        
        // Ensure table exists (in case plugin was updated)
        $this->create_early_access_table();
        
        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'backupzen_early_access';
        
        // Check if email already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE email = %s",
            $email
        ));
        
        if ($existing > 0) {
            wp_send_json_error(array('message' => __('This email is already registered for early access.', 'backupzen')));
        }
        
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('BackupZen: Failed to save early access email - ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Failed to save email. Please try again.', 'backupzen')));
        }
        
        // Log success
        error_log(sprintf('BackupZen: Early access email registered - %s', $email));
        
        // Send success response
        wp_send_json_success(array(
            'message' => __('Thank you! We will notify you when PRO is available.', 'backupzen'),
            'email' => $email,
        ));
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip()
    {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        // Validate IP format
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '';
    }


    /**
     * OPTIONAL: Add bulk delete functionality
     * This allows selecting multiple backups and deleting them at once
     */
    public function ajax_bulk_delete_backups()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Get filenames array
        if (!isset($_POST['filenames']) || !is_array($_POST['filenames'])) {
            wp_send_json_error(array('message' => __('No files specified.', 'backupzen')));
        }
    
        $filenames = array_map('sanitize_file_name', wp_unslash($_POST['filenames']));
        $deleted = array();
        $failed = array();
    
        foreach ($filenames as $filename) {
            $filename = basename($filename);
            $filepath = trailingslashit($this->get_backup_dir_path()) . $filename;
    
            if (!file_exists($filepath)) {
                $failed[] = $filename . ' (not found)';
                continue;
            }
    
            // Security checks
            $real_filepath = realpath($filepath);
            $real_backup_dir = realpath($this->get_backup_dir_path());
            
            if (false === $real_filepath || false === $real_backup_dir || 0 !== strpos($real_filepath, $real_backup_dir)) {
                $failed[] = $filename . ' (invalid path)';
                continue;
            }
    
            // Delete file
            if (@unlink($filepath)) {
                $deleted[] = $filename;
            } else {
                $failed[] = $filename . ' (delete failed)';
            }
        }
    
        // Log bulk deletion
        if (!empty($deleted)) {
            error_log(sprintf(
                'BackupZen: Bulk delete - %d files deleted by user %s: %s',
                count($deleted),
                wp_get_current_user()->user_login,
                implode(', ', $deleted)
            ));
        }
    
        $message = sprintf(
            __('Deleted %d of %d backup(s).', 'backupzen'),
            count($deleted),
            count($filenames)
        );
    
        if (!empty($failed)) {
            $message .= ' ' . sprintf(__('Failed: %s', 'backupzen'), implode(', ', $failed));
        }
    
        wp_send_json_success(array(
            'message' => $message,
            'deleted' => $deleted,
            'failed' => $failed,
        ));
    }
	
    		/**
     * AJAX handler for creating traditional backups (ZIP, SQL, SQL.GZ)
     */
    public function ajax_create_traditional_backup()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Increase limits
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
    
        // Get form data
        $backup_files = isset($_POST['backup_files']) ? intval($_POST['backup_files']) : 0;
        $backup_database = isset($_POST['backup_database']) ? intval($_POST['backup_database']) : 0;
        $backup_format = isset($_POST['backup_format']) ? sanitize_text_field(wp_unslash($_POST['backup_format'])) : 'zip';
    
        error_log('BackupZen: Creating traditional backup - Format: ' . $backup_format);
    
        // Handle based on format
        $result = array('success' => false, 'message' => 'Unknown format');
    
        switch ($backup_format) {
            case 'zip':
                $result = $this->create_zip_backup($backup_files, $backup_database);
                break;
                
            case 'sql':
                $result = $this->create_sql_backup($backup_database);
                break;
                
            case 'sql.gz':
                $result = $this->create_sqlgz_backup($backup_database);
                break;
        }
    
        if ($result['success']) {
            // Send email notification if enabled
            $filename = isset($result['filename']) ? $result['filename'] : 'traditional-backup';
            $this->send_backup_email_if_enabled($result, $filename);
            
            wp_send_json_success($result);
        } else {
            // Send email notification for failure if enabled
            $filename = isset($result['filename']) ? $result['filename'] : 'traditional-backup-failed';
            $this->send_backup_email_if_enabled($result, $filename);
            
            wp_send_json_error($result);
        }
    }

    /**
     * Create ZIP backup
     */
    private function create_zip_backup($include_files, $include_database)
    {
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename  = sprintf('%s-%s.zip', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        $zip = new \ZipArchive();
        if (true !== $zip->open($file_path, \ZipArchive::CREATE)) {
            return array(
                'success' => false,
                'message' => __('Failed to create ZIP archive.', 'backupzen')
            );
        }
    
        // Add database if requested
        if ($include_database) {
            $temp_db = trailingslashit($this->get_backup_dir_path()) . 'temp_database_' . uniqid() . '.sql';
            $packager = new \BackupZen\Backup\BzenPackager();
            
            // Use reflection to call private method
            $method = new \ReflectionMethod($packager, 'export_database_to_file');
            $method->setAccessible(true);
            $db_result = $method->invoke($packager, $temp_db);
            
            if ($db_result['success'] && file_exists($temp_db)) {
                $zip->addFile($temp_db, 'database.sql');
            } else {
                $zip->close();
                @unlink($file_path);
                return array(
                    'success' => false,
                    'message' => __('Failed to export database.', 'backupzen')
                );
            }
        }
    
        // Add files if requested
        if ($include_files) {
            $packager = new \BackupZen\Backup\BzenPackager();
            $method = new \ReflectionMethod($packager, 'add_files_to_zip');
            $method->setAccessible(true);
            $method->invoke($packager, $zip);
        }
    
        $zip->close();
    
        // Clean up temp database
        if ($include_database && isset($temp_db) && file_exists($temp_db)) {
            @unlink($temp_db);
        }
    
        if (file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => sprintf(__('ZIP backup created: %s', 'backupzen'), $filename),
                'filename' => $filename,
                'size' => filesize($file_path)
            );
        }
    
        return array(
            'success' => false,
            'message' => __('Failed to create ZIP backup.', 'backupzen')
        );
    }

    /**
     * Create SQL backup
     */
    private function create_sql_backup($include_database)
    {
        if (!$include_database) {
            return array(
                'success' => false,
                'message' => __('Database backup must be selected for SQL format.', 'backupzen')
            );
        }
    
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename  = sprintf('%s-%s.sql', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        $packager = new \BackupZen\Backup\BzenPackager();
        $method = new \ReflectionMethod($packager, 'export_database_to_file');
        $method->setAccessible(true);
        $result = $method->invoke($packager, $file_path);
    
        if ($result['success'] && file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => sprintf(__('SQL backup created: %s', 'backupzen'), $filename),
                'filename' => $filename,
                'size' => filesize($file_path)
            );
        }
    
        return array(
            'success' => false,
            'message' => __('Failed to create SQL backup.', 'backupzen')
        );
    }

    /**
     * Create SQL.GZ backup
     */
    private function create_sqlgz_backup($include_database)
    {
        if (!$include_database) {
            return array(
                'success' => false,
                'message' => __('Database backup must be selected for SQL.GZ format.', 'backupzen')
            );
        }
    
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $temp_sql  = trailingslashit($this->get_backup_dir_path()) . 'temp_' . uniqid() . '.sql';
        $filename  = sprintf('%s-%s.sql.gz', $site_name, $timestamp);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        // Create SQL file first
        $packager = new \BackupZen\Backup\BzenPackager();
        $method = new \ReflectionMethod($packager, 'export_database_to_file');
        $method->setAccessible(true);
        $result = $method->invoke($packager, $temp_sql);
    
        if (!$result['success'] || !file_exists($temp_sql)) {
            return array(
                'success' => false,
                'message' => __('Failed to export database.', 'backupzen')
            );
        }
    
        // Compress it
        $input = @fopen($temp_sql, 'rb');
        $output = @gzopen($file_path, 'wb9');
        
        if (!$input || !$output) {
            @unlink($temp_sql);
            return array(
                'success' => false,
                'message' => __('Failed to compress SQL file.', 'backupzen')
            );
        }
        
        while (!feof($input)) {
            gzwrite($output, fread($input, 1024 * 512));
        }
        
        fclose($input);
        gzclose($output);
        
        // Delete temp SQL
        @unlink($temp_sql);
    
        if (file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => sprintf(__('SQL.GZ backup created: %s', 'backupzen'), $filename),
                'filename' => $filename,
                'size' => filesize($file_path)
            );
        }
    
        return array(
            'success' => false,
            'message' => __('Failed to create SQL.GZ backup.', 'backupzen')
        );
    }



    /**
     * AJAX handler for initiating restore from existing backup file
     */
    public function ajax_restore_backup()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Get filename
        if (!isset($_POST['filename']) || empty($_POST['filename'])) {
            wp_send_json_error(array('message' => __('Filename is required.', 'backupzen')));
        }
    
        $filename = basename(sanitize_file_name(wp_unslash($_POST['filename'])));
        $filepath = trailingslashit($this->get_backup_dir_path()) . $filename;
    
        // Validate file exists
        if (!file_exists($filepath)) {
            wp_send_json_error(array('message' => __('Backup file not found.', 'backupzen')));
        }
    
        // Prevent directory traversal
        $real_filepath = realpath($filepath);
        $real_backup_dir = realpath($this->get_backup_dir_path());
        
        if (false === $real_filepath || false === $real_backup_dir || 0 !== strpos($real_filepath, $real_backup_dir)) {
            wp_send_json_error(array('message' => __('Invalid file path.', 'backupzen')));
        }
    
        // Create unique session ID
        $session_id = uniqid('restore_', true);
        
        error_log('BackupZen: Creating restore session ' . $session_id . ' for file: ' . $filename);
    
        // Store restore parameters
        set_transient('backupzen_restore_params_' . $session_id, array(
            'filename' => $filename,
            'filepath' => $filepath,
            'session_id' => $session_id,
            'started' => time(),
        ), 600); // 10 minutes
    
        // Set initial progress
        set_transient('backupzen_restore_progress_' . $session_id, array(
            'status' => 'starting',
            'step' => __('Initializing restore...', 'backupzen'),
            'progress' => 0,
            'timestamp' => time(),
        ), 600);
    
        // Create spawn URL
        $spawn_url = add_query_arg(array(
            'action' => 'backupzen_run_restore',
            'session_id' => $session_id,
            'nonce' => wp_create_nonce('backupzen_nonce'),
        ), admin_url('admin-ajax.php'));
    
        error_log('BackupZen: Restore spawn URL: ' . $spawn_url);
    
        // Return spawn URL for client-side spawning
        wp_send_json_success(array(
            'session_id' => $session_id,
            'spawn_url' => $spawn_url,
            'message' => __('Restore started', 'backupzen')
        ));
    }
    
    /**
     * AJAX handler to run restore - SUPPORTS BOTH GET AND POST
     */
    public function ajax_run_restore()
    {
        // Get session ID from GET or POST
        $session_id = '';
        if (isset($_GET['session_id'])) {
            $session_id = sanitize_text_field(wp_unslash($_GET['session_id']));
        } elseif (isset($_POST['session_id'])) {
            $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
        }
        
        if (empty($session_id)) {
            error_log('BackupZen Restore: No session ID provided');
            die('No session ID');
        }
    
        // Verify nonce from GET or POST
        $nonce = '';
        if (isset($_GET['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        } elseif (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        }
        
        if (!wp_verify_nonce($nonce, 'backupzen_nonce')) {
            error_log('BackupZen Restore: Nonce verification failed');
            die('Invalid nonce');
        }
    
        error_log('BackupZen Restore: Starting for session ' . $session_id);
    
        // Get parameters
        $params = get_transient('backupzen_restore_params_' . $session_id);
        if (false === $params) {
            error_log('BackupZen Restore: Parameters not found for session ' . $session_id);
            die('Parameters not found');
        }
    
        $filepath = isset($params['filepath']) ? $params['filepath'] : '';
    
        // Send minimal response and close connection immediately
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('Connection: close');
            echo '<!DOCTYPE html><html><body>Restore process started</body></html>';
            
            $size = ob_get_length();
            header('Content-Length: ' . $size);
            
            ob_end_flush();
            flush();
            
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }
    
        // Now running in background
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
        ignore_user_abort(true);
    
        sleep(1);
    
        error_log('BackupZen Restore: Connection closed, starting restore process');
    
        // Run restore
        try {
            $this->run_restore_with_progress($filepath, $session_id);
            error_log('BackupZen Restore: Completed successfully for session ' . $session_id);
        } catch (Exception $e) {
            error_log('BackupZen Restore: Exception - ' . $e->getMessage());
        }
    
        // Clean up
        delete_transient('backupzen_restore_params_' . $session_id);
    
        error_log('BackupZen Restore: All done for session ' . $session_id);
        exit;
    }
    
    /**
     * WordPress Cron callback for running restore
     */
    public function cron_run_restore($session_id)
    {
        if (empty($session_id)) {
            error_log('BackupZen Cron Restore: No session ID provided');
            return;
        }
    
        $params = get_transient('backupzen_restore_params_' . $session_id);
        if (false === $params) {
            error_log('BackupZen Cron Restore: Parameters not found for session ' . $session_id);
            return;
        }
    
        $filepath = isset($params['filepath']) ? $params['filepath'] : '';
    
        error_log('BackupZen Cron Restore: Starting with session ' . $session_id);
    
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
        
        $this->run_restore_with_progress($filepath, $session_id);
        
        delete_transient('backupzen_restore_params_' . $session_id);
        
        error_log('BackupZen Cron Restore: Completed for session ' . $session_id);
    }
    
    /**
     * AJAX handler for getting restore progress
     */
    public function ajax_get_restore_progress()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required.', 'backupzen')));
        }
        
        // Read DIRECTLY from database
        global $wpdb;
        $option_name = '_transient_backupzen_restore_progress_' . $session_id;
        
        $progress_data = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option_name
        ));
        
        if (empty($progress_data)) {
            $params_exist = get_transient('backupzen_restore_params_' . $session_id);
            if (false !== $params_exist) {
                wp_send_json_success(array(
                    'status' => 'starting',
                    'step' => __('Waiting for restore to start...', 'backupzen'),
                    'progress' => 0,
                    'timestamp' => time(),
                ));
            } else {
                wp_send_json_error(array('message' => __('Progress data not found.', 'backupzen')));
            }
            return;
        }
        
        $progress = maybe_unserialize($progress_data);
        
        if (false === $progress) {
            wp_send_json_error(array('message' => __('Invalid progress data.', 'backupzen')));
        }
    
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX handler for upload and restore
     */
    public function ajax_upload_and_restore()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }
    
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'backupzen')));
        }
    
        // Check if file was uploaded
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('File upload failed.', 'backupzen')));
        }
    
        $uploaded_file = $_FILES['backup_file'];
        $filename = sanitize_file_name($uploaded_file['name']);
        
        // Validate file type
        $allowed_types = array('bzen', 'zip', 'sql', 'gz');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_types, true)) {
            wp_send_json_error(array('message' => __('Invalid file type.', 'backupzen')));
        }
    
        // Move uploaded file to backup directory
        $upload_path = trailingslashit($this->get_backup_dir_path()) . $filename;
        
        if (!move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
            wp_send_json_error(array('message' => __('Failed to save uploaded file.', 'backupzen')));
        }
    
        error_log('BackupZen: File uploaded successfully: ' . $filename);
    
        // Create restore session
        $session_id = uniqid('restore_upload_', true);
        
        set_transient('backupzen_restore_params_' . $session_id, array(
            'filename' => $filename,
            'filepath' => $upload_path,
            'session_id' => $session_id,
            'started' => time(),
        ), 600);
    
        set_transient('backupzen_restore_progress_' . $session_id, array(
            'status' => 'starting',
            'step' => __('File uploaded, preparing restore...', 'backupzen'),
            'progress' => 0,
            'timestamp' => time(),
        ), 600);
    
        $spawn_url = add_query_arg(array(
            'action' => 'backupzen_run_restore',
            'session_id' => $session_id,
            'nonce' => wp_create_nonce('backupzen_nonce'),
        ), admin_url('admin-ajax.php'));
    
        wp_send_json_success(array(
            'session_id' => $session_id,
            'spawn_url' => $spawn_url,
            'message' => __('File uploaded, restore started', 'backupzen')
        ));
    }
    
    /**
     * Run restore with progress tracking
     */
    private function run_restore_with_progress($filepath, $session_id)
    {
        // Update progress helper
        $update_progress = function($step, $progress) use ($session_id) {
            global $wpdb;
            
            $data = array(
                'status' => 'running',
                'step' => $step,
                'progress' => $progress,
                'timestamp' => time(),
            );
            
            $option_name = '_transient_backupzen_restore_progress_' . $session_id;
            $timeout_name = '_transient_timeout_backupzen_restore_progress_' . $session_id;
            $timeout = time() + 600;
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                $option_name,
                $timeout_name
            ));
            
            $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name' => $option_name,
                    'option_value' => maybe_serialize($data),
                    'autoload' => 'no'
                ),
                array('%s', '%s', '%s')
            );
            
            $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name' => $timeout_name,
                    'option_value' => $timeout,
                    'autoload' => 'no'
                ),
                array('%s', '%d', '%s')
            );
            
            $wpdb->flush();
            
            error_log(sprintf('BackupZen Restore Progress: %s - %d%%', $step, $progress));
        };
    
        try {
            $update_progress(__('Starting restore process...', 'backupzen'), 0);
            
            if (!file_exists($filepath)) {
                throw new Exception(__('Backup file not found.', 'backupzen'));
            }
    
            $update_progress(__('Creating pre-restore backup...', 'backupzen'), 5);
            
            // Create pre-restore backup
            $pre_backup_result = $this->create_pre_restore_backup();
            if (!$pre_backup_result['success']) {
                throw new Exception($pre_backup_result['message']);
            }
    
            $update_progress(__('Reading backup file...', 'backupzen'), 10);
            
            // Get payload from BZEN file
            $packager = new \BackupZen\Backup\BzenPackager();
            $payload_result = $packager->get_payload($filepath);
    
            if (!$payload_result['success']) {
                throw new Exception($payload_result['message']);
            }
    
            $metadata = $payload_result['metadata'];
    
            $update_progress(__('Extracting backup files...', 'backupzen'), 20);
            
            // Extract to temporary directory
            $temp_dir = trailingslashit(WP_CONTENT_DIR) . 'backupzen_temp_restore_' . uniqid();
            if (!wp_mkdir_p($temp_dir)) {
                throw new Exception(__('Failed to create temporary directory.', 'backupzen'));
            }
    
            $extract_result = $packager->extract($filepath, $temp_dir);
            if (!$extract_result['success']) {
                $this->delete_directory($temp_dir);
                throw new Exception($extract_result['message']);
            }
    
            $update_progress(__('Preparing to restore database...', 'backupzen'), 40);
            
            // Initialize restore logging
            $restore_log = array(
                'files_restored' => 0,
                'tables_restored' => 0,
                'errors' => array(),
            );
    
            $restore_errors = array();
    
            // Restore database if included
            $include_db = isset($metadata['options']['include_database']) && $metadata['options']['include_database'];
            if ($include_db && !empty($extract_result['db_path']) && file_exists($extract_result['db_path'])) {
                $update_progress(__('Restoring database...', 'backupzen'), 50);
                
                $db_result = $this->restore_database($extract_result['db_path'], $restore_log);
                if (!$db_result['success']) {
                    $restore_errors[] = $db_result['message'];
                }
            }
    
            $update_progress(__('Restoring files...', 'backupzen'), 70);
            
            // Restore files if included
            $include_files = isset($metadata['options']['include_files']) && $metadata['options']['include_files'];
            if ($include_files && !empty($extract_result['files_path']) && is_dir($extract_result['files_path'])) {
                $files_result = $this->restore_files($extract_result['files_path'], $restore_log);
                if (!$files_result['success']) {
                    $restore_errors[] = $files_result['message'];
                }
            }
    
            $update_progress(__('Cleaning up temporary files...', 'backupzen'), 95);
            
            // Cleanup temp directory
            $this->delete_directory($temp_dir);
    
            if (!empty($restore_errors)) {
                throw new Exception(implode(' ', $restore_errors));
            }
    
            $update_progress(__('Restore completed successfully!', 'backupzen'), 100);
            
            // Set final completed status
            global $wpdb;
            $option_name = '_transient_backupzen_restore_progress_' . $session_id;
            $data = array(
                'status' => 'completed',
                'step' => __('Restore completed successfully!', 'backupzen'),
                'progress' => 100,
                'files_restored' => $restore_log['files_restored'],
                'tables_restored' => $restore_log['tables_restored'],
            );
            
            $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($data)),
                array('option_name' => $option_name)
            );
    
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            error_log('BackupZen Restore Exception: ' . $error_message);
            
            global $wpdb;
            $option_name = '_transient_backupzen_restore_progress_' . $session_id;
            $data = array(
                'status' => 'error',
                'step' => $error_message ? $error_message : __('An error occurred during restore', 'backupzen'),
                'progress' => 0,
            );
            
            $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($data)),
                array('option_name' => $option_name)
            );
        }
    }
    
    
        
    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_custom_cron_schedules($schedules)
    {
        // Add weekly schedule if it doesn't exist
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => 604800, // 7 days in seconds
                'display'  => __('Once Weekly', 'backupzen'),
            );
        }
        
        return $schedules;
    }
    
    // REMOVED: Old debug functions - no longer needed
    // - show_cron_debug_notice
    // - test_cron_manually

        
            /**
     * IMPROVED: Update cron schedule with better debugging.
     */
    private function update_cron_schedule($enabled, $frequency, $time)
    {
        // Use ScheduleManager class
        $schedule_manager = new \BackupZen\Backup\ScheduleManager();
        return $schedule_manager->update_schedule($enabled, $frequency, $time);
    }
    
    /**
     * DEPRECATED: Old update_cron_schedule method (kept for reference)
     * Now using ScheduleManager class
     */
    private function update_cron_schedule_old($enabled, $frequency, $time)
    {
        error_log('BackupZen: update_cron_schedule called - Enabled: ' . ($enabled ? 'YES' : 'NO'));
        
        // Clear existing schedule
        $timestamp = wp_next_scheduled('backupzen_scheduled_backup_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'backupzen_scheduled_backup_event');
            error_log('BackupZen: Cleared existing schedule at ' . date('Y-m-d H:i:s', $timestamp));
        }
        
        // If disabled, stop here
        if (!$enabled) {
            error_log('BackupZen: Schedule disabled, not rescheduling');
            return;
        }
        
        // Calculate next run time
        $next_run = $this->calculate_next_run_time($frequency, $time);
        
        error_log('BackupZen: Calculated next run: ' . date('Y-m-d H:i:s', $next_run) . ' (in ' . human_time_diff(time(), $next_run) . ')');
        
        // Schedule the event
        if ($next_run) {
            $result = wp_schedule_event($next_run, $frequency, 'backupzen_scheduled_backup_event');
            
            if ($result === false) {
                error_log('BackupZen: ERROR - Failed to schedule event!');
            } else {
                error_log('BackupZen: Successfully scheduled backup');
                
                // Verify it was scheduled
                $verify = wp_next_scheduled('backupzen_scheduled_backup_event');
                if ($verify) {
                    error_log('BackupZen: VERIFIED - Next run at ' . date('Y-m-d H:i:s', $verify));
                } else {
                    error_log('BackupZen: ERROR - Schedule verification failed!');
                }
            }
        } else {
            error_log('BackupZen: ERROR - calculate_next_run_time returned null');
        }
    }

    
        /**
     * Calculate next run time - SIMPLIFIED
     */
    private function calculate_next_run_time($frequency, $time)
    {
        $current_time = current_time('timestamp');
        
        // Parse time
        list($hour, $minute) = explode(':', $time);
        $hour = intval($hour);
        $minute = intval($minute);
        
        switch ($frequency) {
            case 'immediate':
                // Run in 2 minutes
                $next_run = $current_time + 120;
                break;
                
            case 'hourly':
                // Run at the top of the next hour
                $next_run = strtotime('+1 hour', $current_time);
                $next_run = strtotime(date('Y-m-d H:00:00', $next_run));
                break;
                
            case 'twicedaily':
                // Run twice daily at specified time
                $today_run = strtotime(sprintf('today %02d:%02d:00', $hour, $minute), $current_time);
                $today_run2 = strtotime(sprintf('today %02d:%02d:00', ($hour + 12) % 24, $minute), $current_time);
                
                if ($current_time < $today_run) {
                    $next_run = $today_run;
                } elseif ($current_time < $today_run2) {
                    $next_run = $today_run2;
                } else {
                    $next_run = strtotime(sprintf('tomorrow %02d:%02d:00', $hour, $minute), $current_time);
                }
                break;
                
            case 'daily':
                // Run daily at specified time
                $today_run = strtotime(sprintf('today %02d:%02d:00', $hour, $minute), $current_time);
                
                if ($current_time < $today_run) {
                    $next_run = $today_run;
                } else {
                    $next_run = strtotime(sprintf('tomorrow %02d:%02d:00', $hour, $minute), $current_time);
                }
                break;
                
            case 'weekly':
                // Run weekly on Monday at specified time
                $current_day = date('N', $current_time);
                
                if ($current_day == 1) {
                    $today_run = strtotime(sprintf('today %02d:%02d:00', $hour, $minute), $current_time);
                    if ($current_time < $today_run) {
                        $next_run = $today_run;
                    } else {
                        $next_run = strtotime(sprintf('next monday %02d:%02d:00', $hour, $minute), $current_time);
                    }
                } else {
                    $next_run = strtotime(sprintf('next monday %02d:%02d:00', $hour, $minute), $current_time);
                }
                break;
                
            default:
                $next_run = strtotime('+1 day', $current_time);
                break;
        }
        
        error_log('BackupZen: Next backup scheduled for ' . date('Y-m-d H:i:s', $next_run));
        
        return $next_run;
    }
    
    /**
 * FIXED: Run scheduled backup with proper email handling
 */
public function run_scheduled_backup()
{
    error_log('BackupZen: ========================================');
    error_log('BackupZen: SCHEDULED BACKUP STARTED at ' . date('Y-m-d H:i:s'));
    error_log('BackupZen: ========================================');
    
    try {
        // Increase limits
        @set_time_limit(0);
        @ini_set('memory_limit', '768M');
        
        // Get schedule settings
        $schedule_files = get_option('backupzen_schedule_files', true);
        $schedule_database = get_option('backupzen_schedule_database', true);
        $schedule_format = get_option('backupzen_schedule_format', 'bzen');
        
        error_log('BackupZen: Settings - Files: ' . ($schedule_files ? 'YES' : 'NO') . 
                  ', DB: ' . ($schedule_database ? 'YES' : 'NO') . 
                  ', Format: ' . $schedule_format);
        
        // Generate filename
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $site_name = empty($site_name) ? 'site' : $site_name;
        $timestamp = current_time('Y-m-d_His');
        $filename = sprintf('%s-scheduled-%s.%s', $site_name, $timestamp, $schedule_format);
        $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
        
        error_log('BackupZen: Creating backup: ' . $filename);
        
        // Create backup based on format
        $result = false;
        
        switch ($schedule_format) {
            case 'bzen':
                $result = $this->create_bzen_backup_scheduled($file_path, $schedule_files, $schedule_database);
                break;
            case 'zip':
                $result = $this->create_zip_backup($schedule_files, $schedule_database);
                if ($result && isset($result['filename'])) {
                    $filename = $result['filename'];
                    $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
                }
                break;
            case 'sql':
                $result = $this->create_sql_backup($schedule_database);
                if ($result && isset($result['filename'])) {
                    $filename = $result['filename'];
                    $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
                }
                break;
            case 'sql.gz':
                $result = $this->create_sqlgz_backup($schedule_database);
                if ($result && isset($result['filename'])) {
                    $filename = $result['filename'];
                    $file_path = trailingslashit($this->get_backup_dir_path()) . $filename;
                }
                break;
        }
        
        // CRITICAL: Ensure result has proper structure
        if (!$result || !is_array($result)) {
            $result = array(
                'success' => false,
                'message' => 'Backup failed - no result returned'
            );
        }
        
        // Add file_size to result if missing but file exists
        if ($result['success'] && !isset($result['file_size']) && file_exists($file_path)) {
            $result['file_size'] = filesize($file_path);
        }
        
        // Log result
        if ($result['success']) {
            error_log('BackupZen: BACKUP SUCCESS!');
            error_log('BackupZen: File: ' . $filename);
            if (isset($result['file_size'])) {
                error_log('BackupZen: Size: ' . size_format($result['file_size']));
            }
        } else {
            error_log('BackupZen: BACKUP FAILED!');
            if (isset($result['message'])) {
                error_log('BackupZen: Error: ' . $result['message']);
            }
        }
        
        // Log result to history
        $this->log_backup_history($result);
        
        // ===== FIXED EMAIL NOTIFICATION LOGIC =====
        // CRITICAL: For scheduled backups, we ALWAYS send completion emails
        // regardless of email_on_failure setting, because scheduled backups are important
        $email_notifications = get_option('backupzen_email_notifications', false);
        $email_address = get_option('backupzen_email_address', '');
        $email_on_failure = get_option('backupzen_email_on_failure', false);
        
        error_log('BackupZen: ===== SCHEDULED BACKUP EMAIL NOTIFICATION =====');
        error_log('BackupZen: Email Settings:');
        error_log('- Notifications enabled: ' . ($email_notifications ? 'YES' : 'NO'));
        error_log('- Email address: ' . $email_address);
        error_log('- Email on failure only: ' . ($email_on_failure ? 'YES' : 'NO'));
        error_log('- Backup status: ' . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        error_log('- This is a SCHEDULED backup - will ALWAYS send email if notifications enabled');
        
        // For scheduled backups, ALWAYS send email if notifications are enabled
        // The email_on_failure setting is ignored for scheduled backups
        $should_send_email = false;
        
        if ($email_notifications && !empty($email_address)) {
            // ALWAYS send for scheduled backups (success + failure)
            $should_send_email = true;
            error_log('BackupZen: Scheduled backup - will send email: YES (ignoring email_on_failure setting)');
        } else {
            if (!$email_notifications) {
                error_log('BackupZen: Email notifications disabled - will send: NO');
            } elseif (empty($email_address)) {
                error_log('BackupZen: No email address configured - will send: NO');
            }
        }
        
        // Send email if conditions are met
        if ($should_send_email) {
            error_log('BackupZen: ===== ATTEMPTING TO SEND EMAIL =====');
            error_log('BackupZen: Email address: ' . $email_address);
            error_log('BackupZen: Backup success: ' . ($result['success'] ? 'YES' : 'NO'));
            error_log('BackupZen: Filename: ' . $filename);
            
            $email_notifier = new \BackupZen\Backup\EmailNotifier();
            
            // CRITICAL: Use force_send=true to bypass email_on_failure check
            // For scheduled backups, we ALWAYS want to send completion emails
            error_log('BackupZen: Calling send_backup_notification with force_send=true');
            
            // Now send the email - force_send=true bypasses email_on_failure check
            $email_result = $email_notifier->send_backup_notification($result, $filename, true);
            
            error_log('BackupZen: Email send result: ' . ($email_result['success'] ? 'SUCCESS' : 'FAILED'));
            error_log('BackupZen: Email result message: ' . $email_result['message']);
            
            if ($email_result['success']) {
                error_log('BackupZen: ✓✓✓ EMAIL SENT SUCCESSFULLY ✓✓✓');
            } else {
                error_log('BackupZen: ✗✗✗ EMAIL FAILED: ' . $email_result['message'] . ' ✗✗✗');
            }
            error_log('BackupZen: ===== EMAIL ATTEMPT COMPLETE =====');
        } else {
            error_log('BackupZen: ✗ Email NOT sent (conditions not met)');
            error_log('BackupZen: - Email enabled: ' . ($email_notifications ? 'YES' : 'NO'));
            error_log('BackupZen: - Email address set: ' . (!empty($email_address) ? 'YES' : 'NO'));
            error_log('BackupZen: - Email on failure only: ' . ($email_on_failure ? 'YES' : 'NO'));
            error_log('BackupZen: - Backup success: ' . ($result['success'] ? 'YES' : 'NO'));
        }
        
        // Run auto-cleanup if enabled
        if (get_option('backupzen_auto_cleanup', false)) {
            error_log('BackupZen: Running auto-cleanup...');
            $this->run_auto_cleanup();
        }
        
        error_log('BackupZen: ========================================');
        error_log('BackupZen: SCHEDULED BACKUP COMPLETED');
        error_log('BackupZen: ========================================');
        
    } catch (Exception $e) {
        error_log('BackupZen: EXCEPTION: ' . $e->getMessage());
        error_log('BackupZen: Stack trace: ' . $e->getTraceAsString());
        
        // Log as failed
        $this->log_backup_history(array(
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ));
        
        // Try to send failure email (always send on exceptions)
        $email_notifications = get_option('backupzen_email_notifications', false);
        $email_address = get_option('backupzen_email_address', '');
        
        if ($email_notifications && !empty($email_address)) {
            error_log('BackupZen: Sending exception failure email...');
            $email_notifier = new \BackupZen\Backup\EmailNotifier();
            
            // Force send on exception
            $original_setting = get_option('backupzen_email_on_failure');
            update_option('backupzen_email_on_failure', false);
            
            $email_notifier->send_backup_notification(
                array(
                    'success' => false,
                    'message' => 'Exception: ' . $e->getMessage()
                ),
                'scheduled-backup-failed'
            );
            
            update_option('backupzen_email_on_failure', $original_setting);
        }
    }
}
        
        
          /**
         * Add WP-CLI command for testing (optional but helpful).
         */
        public function register_cli_commands()
        {
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::add_command('backupzen test-cron', array($this, 'cli_test_cron'));
            }
        }
        
        public function cli_test_cron()
        {
            WP_CLI::log('Testing BackupZen cron...');
            
            $next_run = wp_next_scheduled('backupzen_scheduled_backup_event');
            
            if ($next_run) {
                WP_CLI::success('Cron is scheduled for: ' . date('Y-m-d H:i:s', $next_run));
            } else {
                WP_CLI::error('No cron event scheduled!');
            }
            
            WP_CLI::log('Running backup now...');
            $this->run_scheduled_backup();
            WP_CLI::success('Backup completed!');
        }
    
    /**
     * Create BZEN backup for scheduled task.
     */
    private function create_bzen_backup_scheduled($file_path, $include_files, $include_database)
    {
        $options = array(
            'include_files'   => (bool) $include_files,
            'include_database' => (bool) $include_database,
        );
        
        $packager = new \BackupZen\Backup\BzenPackager();
        return $packager->create($file_path, $options);
    }
    
    /**
     * Log backup history entry.
     * FIXED: Store UTC timestamp to avoid double timezone conversion
     *
     * @param array $result Backup result.
     */
    private function log_backup_history($result)
    {
        $history = get_option('backupzen_backup_history', array());
        
        $entry = array(
            'timestamp' => time(), // Use UTC timestamp (NOT current_time) to avoid double conversion
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] 
                ? __('Scheduled backup completed successfully', 'backupzen')
                : ($result['message'] ?? __('Backup failed', 'backupzen')),
        );
        
        error_log('BackupZen: Logging history with UTC timestamp: ' . $entry['timestamp'] . ' (' . date('Y-m-d H:i:s', $entry['timestamp']) . ' UTC)');
        
        // Add to beginning of array
        array_unshift($history, $entry);
        
        // Keep only last 50 entries
        $history = array_slice($history, 0, 50);
        
        update_option('backupzen_backup_history', $history);
    }
    
    /**
     * Send backup notification email.
     *
     * @param array  $result   Backup result.
     * @param string $filename Backup filename.
     */
    private function send_backup_email($result, $filename)
    {
        // Use EmailNotifier class
        $email_notifier = new \BackupZen\Backup\EmailNotifier();
        return $email_notifier->send_backup_notification($result, $filename);
    }
    
    /**
     * Send backup email notification if enabled.
     * 
     * @param array  $result   Backup result.
     * @param string $filename Backup filename.
     */
    private function send_backup_email_if_enabled($result, $filename)
    {
        // Check if email notifications are enabled
        $email_enabled = get_option('backupzen_email_notifications', false);
        $email_address = get_option('backupzen_email_address', '');
        
        if ($email_enabled && !empty($email_address)) {
            error_log('BackupZen: Sending backup email notification...');
            
            // Use EmailNotifier class
            $email_notifier = new \BackupZen\Backup\EmailNotifier();
            $email_result = $email_notifier->send_backup_notification($result, $filename);
            
            if ($email_result['success']) {
                error_log('BackupZen: ✓ Email sent successfully to ' . $email_address);
            } else {
                error_log('BackupZen: ✗ Email failed: ' . $email_result['message']);
            }
        } else {
            error_log('BackupZen: Email notification skipped (not enabled or no address configured)');
        }
    }
    
    /**
     * Run auto-cleanup of old backups.
     */
    private function run_auto_cleanup()
    {
        // Use CleanupManager class
        $cleanup_manager = new \BackupZen\Backup\CleanupManager($this->get_backup_dir_path());
        return $cleanup_manager->run_cleanup();
    }
    
    // REMOVED: Old AJAX handlers now handled by ScheduleController
    // - ajax_quick_toggle_schedule 
    // - ajax_test_scheduled_backup
    // - ajax_test_email
    // All these functions are now in includes/Backup/ScheduleController.php


    
            /**
     * Render Dashboard page.
     */
    public function render_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }
    
        // Get statistics
        $scanner = new \BackupZen\Backup\BackupScanner($this->get_backup_dir_path());
        $backups = $scanner->get_backups();
        $total_backups = count($backups);
        $total_size = array_sum(array_column($backups, 'size'));
        
        // Get latest backup
        $latest_backup = !empty($backups) ? $backups[0] : null;
        
        // Calculate site size estimate
        $site_size = $this->estimate_site_size();
        
        include plugin_dir_path(__FILE__) . 'templates/dashboard.php';
    }
    
    /**
     * Render Manual Backup page (your current page).
     */
    public function render_manual_backup_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }
        
        include plugin_dir_path(__FILE__) . 'templates/manual-backup.php';
    }
    
    /**
     * Render Scheduled Backups page.
     */
    public function render_scheduled_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }
        
        // Use new clean AJAX-based template (v2.0)
        include plugin_dir_path(__FILE__) . 'templates/scheduled-backups-new.php';
    }
    
    /**
     * Render Available Backups page.
     */
    public function render_backups_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }
        
        include plugin_dir_path(__FILE__) . 'templates/available-backups.php';
    }
    
    /**
     * Render Support page
     * 
     * @return void
     */
    public function render_support_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }
        
        include plugin_dir_path(__FILE__) . 'templates/support.php';
    }
    
    /**
     * Estimate total site size.
     * 
     * @return int Size in bytes
     */
    private function estimate_site_size()
    {
        $size = 0;
        
        // Estimate database size
        global $wpdb;
        $result = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
        foreach ($result as $table) {
            if (isset($table['Data_length']) && isset($table['Index_length'])) {
                $size += $table['Data_length'] + $table['Index_length'];
            }
        }
        
        // Add wp-content directory size (sampling for performance)
        $wp_content = WP_CONTENT_DIR;
        if (is_dir($wp_content)) {
            // Sample first 100 files for estimate
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($wp_content, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            $file_count = 0;
            $sample_size = 0;
            
            foreach ($iterator as $item) {
                if ($item->isFile() && $file_count < 100) {
                    $sample_size += $item->getSize();
                    $file_count++;
                }
                if ($file_count >= 100) break;
            }
            
            // Rough estimate: multiply sample by 10
            $size += $sample_size * 10;
        }
        
        return $size;
    }
    
    /**
     * Get backup statistics for dashboard.
     * 
     * @return array Stats array
     */
    private function get_backup_stats()
    {
        $scanner = new \BackupZen\Backup\BackupScanner($this->get_backup_dir_path());
        $backups = $scanner->get_backups();
        
        $stats = array(
            'total_backups' => count($backups),
            'total_size' => 0,
            'by_type' => array(
                'bzen' => 0,
                'zip' => 0,
                'sql' => 0,
                'sqlgz' => 0,
            ),
            'oldest_backup' => null,
            'newest_backup' => null,
        );
        
        if (!empty($backups)) {
            $stats['newest_backup'] = $backups[0];
            $stats['oldest_backup'] = end($backups);
            
            foreach ($backups as $backup) {
                $stats['total_size'] += $backup['size'];
                $type = $backup['type'];
                if (isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type]++;
                }
            }
        }
        
        return $stats;
    }


    /**
     * Render admin page.
     *
     * @return void
     */
    public function render_admin_page()
    {
        // Capability check.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'backupzen'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('backupZen - Manual Backup & Restore', 'backupzen'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Create manual backups of your WordPress files and database, or restore from a previous backup.', 'backupzen'); ?>
            </p>

            <div class="backupzen-container">
               <!-- Modern Create Backup Section -->
                <div class="backupzen-section backupzen-create-section">
                    <div class="create-header">
                        <div class="create-header-content">
                            <div class="create-icon-wrapper">
                                <span class="dashicons dashicons-cloud-saved"></span>
                            </div>
                            <div class="create-header-text">
                                <h2><?php echo esc_html__('Create Backup', 'backupzen'); ?></h2>
                                <p class="create-subtitle"><?php echo esc_html__('Protect your WordPress site with a complete backup', 'backupzen'); ?></p>
                            </div>
                        </div>
                    </div>
                
                    <form id="backupzen-create-form" method="post" action="">
                        <?php wp_nonce_field('backupzen_create_backup', 'backupzen_create_nonce'); ?>
                
                        <div class="create-content">
                            <!-- What to Backup -->
                            <div class="create-group">
                                <div class="group-header">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <h3><?php echo esc_html__('What to Backup', 'backupzen'); ?></h3>
                                </div>
                                
                                <div class="backup-options-grid">
                                    <!-- Files Option -->
                                    <label class="backup-option-card">
                                        <input type="checkbox" id="backup_files" name="backup_files" value="1" checked />
                                        <div class="option-card-content">
                                            <div class="option-icon">
                                                <span class="dashicons dashicons-media-default"></span>
                                            </div>
                                            <div class="option-info">
                                                <div class="option-title"><?php echo esc_html__('Files', 'backupzen'); ?></div>
                                                <div class="option-description"><?php echo esc_html__('Uploads, themes, and plugins', 'backupzen'); ?></div>
                                            </div>
                                            <div class="option-checkmark">
                                                <span class="dashicons dashicons-yes"></span>
                                            </div>
                                        </div>
                                    </label>
                
                                    <!-- Database Option -->
                                    <label class="backup-option-card">
                                        <input type="checkbox" id="backup_database" name="backup_database" value="1" checked />
                                        <div class="option-card-content">
                                            <div class="option-icon">
                                                <span class="dashicons dashicons-database"></span>
                                            </div>
                                            <div class="option-info">
                                                <div class="option-title"><?php echo esc_html__('Database', 'backupzen'); ?></div>
                                                <div class="option-description"><?php echo esc_html__('Posts, pages, and settings', 'backupzen'); ?></div>
                                            </div>
                                            <div class="option-checkmark">
                                                <span class="dashicons dashicons-yes"></span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                
                            <!-- Backup Format -->
                            <div class="create-group">
                                <div class="group-header">
                                    <span class="dashicons dashicons-archive"></span>
                                    <h3><?php echo esc_html__('Backup Format', 'backupzen'); ?></h3>
                                </div>
                                
                                <div class="format-options-grid">
                                    <!-- BZEN Format -->
                                    <label class="format-option-card">
                                        <input type="radio" name="backup_format" value="bzen" checked />
                                        <div class="format-card-content">
                                            <div class="format-badge-wrapper">
                                                <span class="format-badge-icon">
                                                    <span class="dashicons dashicons-admin-tools"></span>
                                                </span>
                                                <span class="format-recommended"><?php echo esc_html__('Recommended', 'backupzen'); ?></span>
                                            </div>
                                            <div class="format-title">BZEN</div>
                                            <div class="format-description"><?php echo esc_html__('Single file with real-time progress', 'backupzen'); ?></div>
                                            <div class="format-features">
                                                <span class="feature-tag"><?php echo esc_html__('Fast', 'backupzen'); ?></span>
                                                <span class="feature-tag"><?php echo esc_html__('Compressed', 'backupzen'); ?></span>
                                                <span class="feature-tag"><?php echo esc_html__('Verified', 'backupzen'); ?></span>
                                            </div>
                                        </div>
                                    </label>
                
                                    <!-- ZIP Format -->
                                    <label class="format-option-card">
                                        <input type="radio" name="backup_format" value="zip" />
                                        <div class="format-card-content">
                                            <div class="format-badge-wrapper">
                                                <span class="format-badge-icon">
                                                    <span class="dashicons dashicons-media-archive"></span>
                                                </span>
                                            </div>
                                            <div class="format-title">ZIP</div>
                                            <div class="format-description"><?php echo esc_html__('Standard archive format', 'backupzen'); ?></div>
                                            <div class="format-features">
                                                <span class="feature-tag"><?php echo esc_html__('Universal', 'backupzen'); ?></span>
                                                <span class="feature-tag"><?php echo esc_html__('Compatible', 'backupzen'); ?></span>
                                            </div>
                                        </div>
                                    </label>
                
                                    <!-- SQL Format -->
                                    <label class="format-option-card">
                                        <input type="radio" name="backup_format" value="sql" />
                                        <div class="format-card-content">
                                            <div class="format-badge-wrapper">
                                                <span class="format-badge-icon">
                                                    <span class="dashicons dashicons-database-view"></span>
                                                </span>
                                            </div>
                                            <div class="format-title">SQL</div>
                                            <div class="format-description"><?php echo esc_html__('Database only backup', 'backupzen'); ?></div>
                                            <div class="format-features">
                                                <span class="feature-tag"><?php echo esc_html__('Database', 'backupzen'); ?></span>
                                                <span class="feature-tag"><?php echo esc_html__('Portable', 'backupzen'); ?></span>
                                            </div>
                                        </div>
                                    </label>
                
                                    <!-- SQL.GZ Format -->
                                    <label class="format-option-card">
                                        <input type="radio" name="backup_format" value="sql.gz" />
                                        <div class="format-card-content">
                                            <div class="format-badge-wrapper">
                                                <span class="format-badge-icon">
                                                    <span class="dashicons dashicons-database-export"></span>
                                                </span>
                                            </div>
                                            <div class="format-title">SQL.GZ</div>
                                            <div class="format-description"><?php echo esc_html__('Compressed database backup', 'backupzen'); ?></div>
                                            <div class="format-features">
                                                <span class="feature-tag"><?php echo esc_html__('Small', 'backupzen'); ?></span>
                                                <span class="feature-tag"><?php echo esc_html__('Efficient', 'backupzen'); ?></span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                
                            <!-- Info Box -->
                            <div class="create-info-box">
                                <div class="info-icon">
                                    <span class="dashicons dashicons-info"></span>
                                </div>
                                <div class="info-content">
                                    <strong><?php echo esc_html__('Backup Info:', 'backupzen'); ?></strong>
                                    <?php echo esc_html__('Backups are stored securely in your wp-content directory and protected from direct access. Choose BZEN format for real-time progress tracking.', 'backupzen'); ?>
                                </div>
                            </div>
                        </div>
                
                        <!-- Action Button -->
                        <div class="create-actions">
                            <button type="submit" name="backupzen_create" class="btn-create-backup">
                                <span class="btn-icon">
                                    <span class="dashicons dashicons-cloud-saved"></span>
                                </span>
                                <span class="btn-text"><?php echo esc_html__('Create Backup Now', 'backupzen'); ?></span>
                                <span class="btn-shine"></span>
                            </button>
                            <p class="create-actions-note">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo esc_html__('This may take a few minutes depending on your site size', 'backupzen'); ?>
                            </p>
                        </div>
                    </form>
                </div>

               <!-- Modern Restore Backup Section -->
		<div class="backupzen-section backupzen-restore-section">
			<div class="restore-header">
				<div class="restore-header-content">
					<div class="restore-icon-wrapper">
						<span class="dashicons dashicons-upload"></span>
					</div>
					<div class="restore-header-text">
						<h2><?php echo esc_html__('Restore Backup', 'backupzen'); ?></h2>
						<p class="restore-subtitle"><?php echo esc_html__('Upload and restore from a previous backup', 'backupzen'); ?></p>
					</div>
				</div>
			</div>

			<form id="backupzen-restore-form" method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field('backupzen_restore_backup', 'backupzen_restore_nonce'); ?>

				<!-- Drag & Drop Upload Area -->
				<div class="restore-upload-container">
					<div class="restore-upload-area" id="restore-drop-zone">
						<input type="file" id="restore_file" name="restore_file" accept=".zip,.sql,.gz,.bzen" style="display: none;" />

						<div class="upload-content" id="upload-placeholder">
							<div class="upload-icon">
								<svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
									<rect x="8" y="16" width="48" height="40" rx="4" stroke="currentColor" stroke-width="2" fill="none"/>
									<path d="M32 12L32 36M32 12L24 20M32 12L40 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M20 48L44 48" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								</svg>
							</div>
							<h3 class="upload-title"><?php echo esc_html__('Drag & Drop your backup file here', 'backupzen'); ?></h3>
							<p class="upload-text"><?php echo esc_html__('or click to browse', 'backupzen'); ?></p>
							<div class="upload-formats">
								<span class="format-badge">ZIP</span>
								<span class="format-badge">SQL</span>
								<span class="format-badge">SQL.GZ</span>
								<span class="format-badge">BZEN</span>
							</div>
						</div>

						<!-- File Selected State -->
						<div class="upload-file-selected" id="file-selected" style="display: none;">
							<div class="file-icon">
								<span class="dashicons dashicons-media-archive"></span>
							</div>
							<div class="file-info">
								<div class="file-name" id="selected-file-name">backup.zip</div>
								<div class="file-meta">
									<span class="file-size" id="selected-file-size">0 MB</span>
									<span class="file-type" id="selected-file-type">ZIP</span>
								</div>
							</div>
							<button type="button" class="file-remove" id="remove-file">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					</div>

					<!-- Warning Box -->
					<div class="restore-warning">
						<div class="warning-icon">
							<span class="dashicons dashicons-warning"></span>
						</div>
						<div class="warning-content">
							<strong><?php echo esc_html__('Important:', 'backupzen'); ?></strong>
							<?php echo esc_html__('Restoring will overwrite your current site data. A pre-restore backup will be created automatically for safety.', 'backupzen'); ?>
						</div>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="restore-actions">
					<button type="button" class="btn-cancel" onclick="document.getElementById('restore_file').value = ''; document.getElementById('file-selected').style.display = 'none'; document.getElementById('upload-placeholder').style.display = 'block';">
						<span class="dashicons dashicons-dismiss"></span>
						<?php echo esc_html__('Cancel', 'backupzen'); ?>
					</button>
					<button type="submit" name="backupzen_restore" class="btn-restore-submit" id="btn-restore-submit" disabled>
						<span class="dashicons dashicons-update"></span>
						<?php echo esc_html__('Restore Backup', 'backupzen'); ?>
					</button>
				</div>
			</form>
		</div>

                <!-- Premium Backup Cards Section -->

<div class="backupzen-section">
    <div class="backupzen-cards-header">
        <h2><?php echo esc_html__('Available Backups', 'backupzen'); ?></h2>
        <div class="backupzen-cards-stats">
            <?php
            $scanner = new \BackupZen\Backup\BackupScanner($this->get_backup_dir_path());
            $backups = $scanner->get_backups();
            $total_size = array_sum(array_column($backups, 'size'));
            ?>
            <span class="stat-badge">
                <span class="dashicons dashicons-backup"></span>
                <?php echo esc_html(sprintf(_n('%d Backup', '%d Backups', count($backups), 'backupzen'), count($backups))); ?>
            </span>
            <span class="stat-badge">
                <span class="dashicons dashicons-database"></span>
                <?php echo esc_html(size_format($total_size)); ?>
            </span>
        </div>
    </div>

    <?php
    // Show restore success notice.
    if (isset($_GET['restore_success']) && '1' === $_GET['restore_success']) {
        $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
        $files_count = isset($_GET['files_count']) ? absint($_GET['files_count']) : 0;
        $tables_count = isset($_GET['tables_count']) ? absint($_GET['tables_count']) : 0;
        $pre_backup = isset($_GET['pre_backup']) ? sanitize_text_field(wp_unslash($_GET['pre_backup'])) : '';
    ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php echo esc_html__('Restore completed successfully!', 'backupzen'); ?></strong><br>
                <?php echo esc_html(sprintf(__('Your site has been restored from backup: %s', 'backupzen'), $file)); ?>
                <?php if ($files_count > 0 || $tables_count > 0) : ?>
                    <br>
                    <?php
                    if ($files_count > 0) {
                        echo esc_html(sprintf(_n('%d file restored.', '%d files restored.', $files_count, 'backupzen'), $files_count));
                    }
                    if ($tables_count > 0) {
                        echo ' ' . esc_html(sprintf(_n('%d database table restored.', '%d database tables restored.', $tables_count, 'backupzen'), $tables_count));
                    }
                    ?>
                <?php endif; ?>
                <?php if (! empty($pre_backup)) : ?>
                    <br><br>
                    <strong><?php echo esc_html__('Pre-restore backup created:', 'backupzen'); ?></strong>
                    <?php echo esc_html($pre_backup); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php
    }

    // Show restore error notice.
    if (isset($_GET['restore_error']) && '1' === $_GET['restore_error']) {
        $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('An unknown error occurred during restore.', 'backupzen');
        $pre_backup = isset($_GET['pre_backup']) ? sanitize_text_field(wp_unslash($_GET['pre_backup'])) : '';
    ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php echo esc_html__('Restore failed!', 'backupzen'); ?></strong><br>
                <?php echo esc_html($message); ?>
                <?php if (! empty($pre_backup)) : ?>
                    <br><br>
                    <strong><?php echo esc_html__('Pre-restore backup available:', 'backupzen'); ?></strong>
                    <?php echo esc_html($pre_backup); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php
    }

    // Show restore notice for non-BZEN files.
    if (isset($_GET['restore_notice']) && '1' === $_GET['restore_notice']) {
        $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
    ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php echo esc_html__('Restore functionality coming soon!', 'backupzen'); ?></strong><br>
                <?php echo esc_html(sprintf(__('Restore logic for "%s" is not yet implemented.', 'backupzen'), $file)); ?>
            </p>
        </div>
    <?php
    }

    if (empty($backups)) {
    ?>
        <div class="backupzen-empty-state">
            <div class="empty-state-icon">
                <span class="dashicons dashicons-cloud"></span>
            </div>
            <h3><?php echo esc_html__('No Backups Yet', 'backupzen'); ?></h3>
            <p class="description">
                <?php echo esc_html__('Create your first backup above to get started protecting your WordPress site.', 'backupzen'); ?>
            </p>
        </div>
    <?php
    } else {
    ?>
        <div class="backupzen-cards-grid">
            <?php foreach ($backups as $index => $backup) : 
                // Get type info
                $type_label = \BackupZen\Backup\BackupScanner::get_type_label($backup['type']);
                $type_icon = \BackupZen\Backup\BackupScanner::get_type_icon($backup['type']);
                
                // Determine badge color
                $badge_class = 'badge-default';
                switch ($backup['type']) {
                    case 'zip':
                        $badge_class = 'badge-blue';
                        break;
                    case 'sql':
                    case 'sqlgz':
                        $badge_class = 'badge-teal';
                        break;
                    case 'bzen':
                        $badge_class = 'badge-purple';
                        break;
                }
                
                // Download URL
                $download_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'backupzen_download',
                            'file'   => rawurlencode($backup['filename']),
                        ),
                        admin_url('admin-post.php')
                    ),
                    'backupzen_download'
                );
            ?>
                <div class="backupzen-card" data-backup-id="<?php echo esc_attr($index); ?>">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="card-icon">
                            <span class="dashicons dashicons-<?php echo esc_attr($type_icon); ?>"></span>
                        </div>
                        <div class="card-badge <?php echo esc_attr($badge_class); ?>">
                            <?php echo esc_html($type_label); ?>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <h3 class="card-title" title="<?php echo esc_attr($backup['filename']); ?>">
                            <?php echo esc_html($backup['filename']); ?>
                        </h3>
                        
                        <div class="card-meta">
                            <div class="meta-item">
                                <span class="dashicons dashicons-calendar"></span>
                                <span class="meta-label"><?php echo esc_html__('Created:', 'backupzen'); ?></span>
                                <span class="meta-value">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            __('%1$s at %2$s', 'backupzen'),
                                            date_i18n(get_option('date_format'), $backup['created']),
                                            date_i18n(get_option('time_format'), $backup['created'])
                                        )
                                    );
                                    ?>
                                </span>
                            </div>
                            
                            <div class="meta-item">
                                <span class="dashicons dashicons-portfolio"></span>
                                <span class="meta-label"><?php echo esc_html__('Size:', 'backupzen'); ?></span>
                                <span class="meta-value"><?php echo esc_html(size_format($backup['size'])); ?></span>
                            </div>

                            <?php if (! empty($backup['checksum'])) : ?>
                                <div class="meta-item">
                                    <span class="dashicons dashicons-shield"></span>
                                    <span class="meta-label"><?php echo esc_html__('Checksum:', 'backupzen'); ?></span>
                                    <span class="meta-value checksum-preview">
                                        <?php echo esc_html(substr($backup['checksum'], 0, 12) . '...'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Collapsible Details -->
                        <div class="card-details-toggle">
                            <a href="#" class="details-toggle-link" data-target="details-<?php echo esc_attr($index); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                <?php echo esc_html__('More Details', 'backupzen'); ?>
                            </a>
                        </div>

                        <div class="card-details-content" id="details-<?php echo esc_attr($index); ?>" style="display: none;">
                            <div class="details-grid">
                                <?php if (! empty($backup['checksum'])) : ?>
                                    <div class="detail-item">
                                        <strong><?php echo esc_html__('Full Checksum:', 'backupzen'); ?></strong>
                                        <code class="checksum-full"><?php echo esc_html($backup['checksum']); ?></code>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <strong><?php echo esc_html__('File Path:', 'backupzen'); ?></strong>
                                    <code><?php echo esc_html($backup['filepath']); ?></code>
                                </div>
                                
                                <div class="detail-item">
                                    <strong><?php echo esc_html__('Format:', 'backupzen'); ?></strong>
                                    <span><?php echo esc_html(strtoupper($backup['type'])); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <strong><?php echo esc_html__('File Size (bytes):', 'backupzen'); ?></strong>
                                    <span><?php echo esc_html(number_format($backup['size'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="card-actions">
                        <a href="<?php echo esc_url($download_url); ?>" class="btn btn-download">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Download', 'backupzen'); ?>
                        </a>
                        
                        <?php if ('bzen' === $backup['type']) : ?>
                            <button type="button" class="btn btn-restore backupzen-restore-btn" data-filename="<?php echo esc_attr($backup['filename']); ?>">
                                <span class="dashicons dashicons-undo"></span>
                                <?php echo esc_html__('Restore', 'backupzen'); ?>
                            </button>
                        <?php else : ?>
                            <button type="button" class="btn btn-restore btn-disabled" disabled title="<?php echo esc_attr__('Restore coming soon', 'backupzen'); ?>">
                                <span class="dashicons dashicons-undo"></span>
                                <?php echo esc_html__('Restore', 'backupzen'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-delete" data-filename="<?php echo esc_attr($backup['filename']); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php echo esc_html__('Delete', 'backupzen'); ?>
                        </button>
                    </div>

                    <!-- Hover Shimmer Effect -->
                    <div class="card-shimmer"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }
    ?>
</div>
                
            </div>
        </div>

        <!-- Restore Confirmation Modal -->
        <div id="backupzen-restore-modal" class="backupzen-modal" style="display: none;">
            <div class="backupzen-modal-overlay"></div>
            <div class="backupzen-modal-content">
                <div class="backupzen-modal-header">
                    <h2><?php echo esc_html__('Confirm Restore', 'backupzen'); ?></h2>
                    <button type="button" class="backupzen-modal-close">&times;</button>
                </div>
                <div class="backupzen-modal-body">
                    <p>
                        <strong><?php echo esc_html__('Warning: This is a destructive operation!', 'backupzen'); ?></strong>
                    </p>
                    <p>
                        <?php echo esc_html__('Restoring will overwrite your current database and files with the backup data. A pre-restore backup will be created automatically.', 'backupzen'); ?>
                    </p>
                    <p>
                        <?php echo esc_html__('Backup file:', 'backupzen'); ?> <strong id="restore-filename"></strong>
                    </p>
                    <p>
                        <label for="restore-confirm-input">
                            <?php echo esc_html__('Type "RESTORE" to confirm:', 'backupzen'); ?>
                        </label>
                        <input type="text" id="restore-confirm-input" class="regular-text" autocomplete="off" />
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('backupzen_restore', '_wpnonce'); ?>
                        <input type="hidden" name="action" value="backupzen_restore" />
                        <input type="hidden" name="file" value="" />
                        <input type="hidden" name="restore_confirm" id="restore-confirm-hidden" value="RESTORE" />
                        <p class="submit">
                            <button type="submit" id="restore-submit-btn" class="button button-primary" disabled>
                                <?php echo esc_html__('Confirm Restore', 'backupzen'); ?>
                            </button>
                            <button type="button" class="button backupzen-modal-close">
                                <?php echo esc_html__('Cancel', 'backupzen'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backup Progress Modal -->
        <div id="backupzen-progress-modal" class="backupzen-modal" style="display: none;">
            <div class="backupzen-modal-overlay"></div>
            <div class="backupzen-modal-content">
                <div class="backupzen-modal-header">
                    <h2><?php echo esc_html__('Creating Backup...', 'backupzen'); ?></h2>
                </div>
                <div class="backupzen-modal-body">
                    <div class="backupzen-progress-container">
                        <div class="backupzen-progress-bar-wrapper">
                            <div class="backupzen-progress-bar" style="width: 0%;"></div>
                        </div>
                        <div class="backupzen-progress-info">
                            <span class="backupzen-progress-text"><?php echo esc_html__('Initializing...', 'backupzen'); ?></span>
                            <span class="backupzen-progress-percent">0%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Fallback: Ensure backupZen object exists and load script if needed -->
        <script type="text/javascript">
        (function() {
            console.log('BackupZen: Initializing fallback...');
            
            // Create backupZen object if it doesn't exist
            if (typeof backupZen === 'undefined') {
                console.warn('BackupZen: Object not found, creating manually...');
                window.backupZen = {
                    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo esc_js(wp_create_nonce('backupzen_nonce')); ?>',
                    downloadUrl: '<?php echo esc_js(wp_nonce_url(add_query_arg(array('action' => 'backupzen_download'), admin_url('admin-post.php')), 'backupzen_download')); ?>'
                };
                console.log('BackupZen: Object created manually', window.backupZen);
            }
            
            // Check if script is loaded
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function($) {
                    // Try to load script if it's not already there
                    if (!$('#backupzen-admin-js').length) {
                        console.log('BackupZen: Loading script directly...');
                        var script = document.createElement('script');
                        script.id = 'backupzen-admin-js';
                        script.src = '<?php echo esc_js(plugins_url('assets/admin.js', __FILE__)); ?>?v=<?php echo esc_js(self::VERSION); ?>';
                        script.onload = function() {
                            console.log('BackupZen: Script loaded successfully');
                        };
                        script.onerror = function() {
                            console.error('BackupZen: Failed to load script');
                        };
                        document.head.appendChild(script);
                    }
                });
            }
        })();
        </script>
<?php
    }
}

// Initialize plugin.
BackupZen::get_instance();