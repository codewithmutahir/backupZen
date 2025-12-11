<?php
/**
 * PRO Features Manager
 * 
 * Manages premium features, license checking, and PRO UI components
 * 
 * @package BackupZen
 * @since 2.0.0
 */

namespace BackupZen\Premium;

if (!defined('ABSPATH')) exit;

class ProFeaturesManager
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * PRO features list with metadata
     */
    private $pro_features = [];

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_pro_features();
    }

    /**
     * Initialize PRO features list
     */
    private function init_pro_features()
    {
        $this->pro_features = [
            // Backup & Restore Features
            'incremental_backups' => [
                'title'       => __('Incremental Smart Backups', 'backupzen'),
                'description' => __('Only backup changed files since last backup, saving time and storage.', 'backupzen'),
                'icon'        => 'dashicons-update',
                'category'    => 'backup',
                'benefits'    => [
                    __('10x faster backups', 'backupzen'),
                    __('90% less storage used', 'backupzen'),
                    __('Smart file change detection', 'backupzen'),
                ],
            ],
            'backup_encryption' => [
                'title'       => __('Backup Encryption', 'backupzen'),
                'description' => __('Protect your backups with AES-256 military-grade encryption.', 'backupzen'),
                'icon'        => 'dashicons-shield',
                'category'    => 'backup',
                'benefits'    => [
                    __('AES-256 encryption', 'backupzen'),
                    __('Password-protected backups', 'backupzen'),
                    __('GDPR compliant', 'backupzen'),
                ],
            ],
            'changed_files_only' => [
                'title'       => __('Changed Files Detection', 'backupzen'),
                'description' => __('Intelligent detection of modified files for optimized backups.', 'backupzen'),
                'icon'        => 'dashicons-search',
                'category'    => 'backup',
                'benefits'    => [
                    __('Smart file scanning', 'backupzen'),
                    __('Skip unchanged files', 'backupzen'),
                    __('Blazing fast backups', 'backupzen'),
                ],
            ],
            'partial_restore' => [
                'title'       => __('Partial Restore', 'backupzen'),
                'description' => __('Restore only database or only files without affecting the rest.', 'backupzen'),
                'icon'        => 'dashicons-controls-play',
                'category'    => 'restore',
                'benefits'    => [
                    __('Database-only restore', 'backupzen'),
                    __('Files-only restore', 'backupzen'),
                    __('Selective restore options', 'backupzen'),
                ],
            ],
            'backup_verification' => [
                'title'       => __('Backup Verification', 'backupzen'),
                'description' => __('Automatically verify backup integrity to ensure restorability.', 'backupzen'),
                'icon'        => 'dashicons-yes-alt',
                'category'    => 'backup',
                'benefits'    => [
                    __('Checksum verification', 'backupzen'),
                    __('Test restore capability', 'backupzen'),
                    __('Peace of mind', 'backupzen'),
                ],
            ],
            
            // Cloud Storage
            'cloud_google_drive' => [
                'title'       => __('Google Drive Backup', 'backupzen'),
                'description' => __('Automatically sync backups to Google Drive for off-site protection.', 'backupzen'),
                'icon'        => 'dashicons-cloud',
                'category'    => 'cloud',
                'benefits'    => [
                    __('Auto-sync to Google Drive', 'backupzen'),
                    __('Off-site protection', 'backupzen'),
                    __('Unlimited storage', 'backupzen'),
                ],
            ],
            'cloud_dropbox' => [
                'title'       => __('Dropbox Backup', 'backupzen'),
                'description' => __('Store your backups safely in Dropbox cloud storage.', 'backupzen'),
                'icon'        => 'dashicons-cloud',
                'category'    => 'cloud',
                'benefits'    => [
                    __('Dropbox integration', 'backupzen'),
                    __('Automatic uploads', 'backupzen'),
                    __('Version history', 'backupzen'),
                ],
            ],
            'cloud_aws_s3' => [
                'title'       => __('Amazon S3 Backup', 'backupzen'),
                'description' => __('Enterprise-grade backup storage with Amazon S3.', 'backupzen'),
                'icon'        => 'dashicons-cloud-upload',
                'category'    => 'cloud',
                'benefits'    => [
                    __('AWS S3 integration', 'backupzen'),
                    __('99.999999999% durability', 'backupzen'),
                    __('Enterprise-grade', 'backupzen'),
                ],
            ],
            
            // Schedule Features
            'schedule_hourly' => [
                'title'       => __('Hourly Backups', 'backupzen'),
                'description' => __('Run backups every hour for maximum data protection.', 'backupzen'),
                'icon'        => 'dashicons-clock',
                'category'    => 'schedule',
                'benefits'    => [
                    __('Backup every hour', 'backupzen'),
                    __('Minimal data loss', 'backupzen'),
                    __('Perfect for busy sites', 'backupzen'),
                ],
            ],
            'schedule_weekly' => [
                'title'       => __('Weekly / Custom Schedule', 'backupzen'),
                'description' => __('Set custom backup schedules including weekly or specific days.', 'backupzen'),
                'icon'        => 'dashicons-calendar-alt',
                'category'    => 'schedule',
                'benefits'    => [
                    __('Weekly backups', 'backupzen'),
                    __('Choose specific days', 'backupzen'),
                    __('Flexible scheduling', 'backupzen'),
                ],
            ],
            'multiple_schedules' => [
                'title'       => __('Multiple Schedules', 'backupzen'),
                'description' => __('Create multiple backup schedules for different backup types.', 'backupzen'),
                'icon'        => 'dashicons-backup',
                'category'    => 'schedule',
                'benefits'    => [
                    __('Multiple schedules', 'backupzen'),
                    __('Different backup types', 'backupzen'),
                    __('Advanced automation', 'backupzen'),
                ],
            ],
            'cron_expressions' => [
                'title'       => __('Advanced Cron Expressions', 'backupzen'),
                'description' => __('Use custom cron expressions for precise scheduling control.', 'backupzen'),
                'icon'        => 'dashicons-admin-generic',
                'category'    => 'schedule',
                'benefits'    => [
                    __('Full cron syntax', 'backupzen'),
                    __('Maximum flexibility', 'backupzen'),
                    __('Developer-friendly', 'backupzen'),
                ],
            ],
            
            // Migration / Tools
            'one_click_staging' => [
                'title'       => __('One-Click Staging', 'backupzen'),
                'description' => __('Create a staging copy of your site with a single click.', 'backupzen'),
                'icon'        => 'dashicons-admin-site-alt3',
                'category'    => 'migration',
                'benefits'    => [
                    __('Instant staging site', 'backupzen'),
                    __('Test safely', 'backupzen'),
                    __('No coding required', 'backupzen'),
                ],
            ],
            'migration_tools' => [
                'title'       => __('Full Migration Tools', 'backupzen'),
                'description' => __('Migrate your site to any host or domain with ease.', 'backupzen'),
                'icon'        => 'dashicons-migrate',
                'category'    => 'migration',
                'benefits'    => [
                    __('Move to any host', 'backupzen'),
                    __('Change domain easily', 'backupzen'),
                    __('Zero downtime', 'backupzen'),
                ],
            ],
            'clone_subdomain' => [
                'title'       => __('Clone to Subdomain', 'backupzen'),
                'description' => __('Clone your entire site to a subdomain for testing.', 'backupzen'),
                'icon'        => 'dashicons-admin-multisite',
                'category'    => 'migration',
                'benefits'    => [
                    __('Clone to subdomain', 'backupzen'),
                    __('Perfect for testing', 'backupzen'),
                    __('Fully automated', 'backupzen'),
                ],
            ],
            'multisite_support' => [
                'title'       => __('Multisite Support', 'backupzen'),
                'description' => __('Full backup and restore support for WordPress Multisite.', 'backupzen'),
                'icon'        => 'dashicons-networking',
                'category'    => 'migration',
                'benefits'    => [
                    __('Network backups', 'backupzen'),
                    __('Individual site backups', 'backupzen'),
                    __('Multisite restore', 'backupzen'),
                ],
            ],
            
            // Reporting
            'email_reports' => [
                'title'       => __('Email Reports', 'backupzen'),
                'description' => __('Receive detailed email reports for all backup activities.', 'backupzen'),
                'icon'        => 'dashicons-email-alt',
                'category'    => 'reporting',
                'benefits'    => [
                    __('Detailed reports', 'backupzen'),
                    __('Success/failure stats', 'backupzen'),
                    __('Custom recipients', 'backupzen'),
                ],
            ],
            'whitelabel_reports' => [
                'title'       => __('White-Label PDF Reports', 'backupzen'),
                'description' => __('Generate professional PDF reports with your branding.', 'backupzen'),
                'icon'        => 'dashicons-media-document',
                'category'    => 'reporting',
                'benefits'    => [
                    __('PDF reports', 'backupzen'),
                    __('Your branding', 'backupzen'),
                    __('Client-ready', 'backupzen'),
                ],
            ],
        ];
    }

    /**
     * Check if user has PRO version
     * 
     * @return bool
     */
    public function is_pro()
    {
        // TODO: Implement actual license checking
        // For now, always return false (FREE version)
        return apply_filters('backupzen_is_pro', false);
    }

    /**
     * Check if a specific feature is available
     * 
     * @param string $feature_id
     * @return bool
     */
    public function is_feature_available($feature_id)
    {
        if ($this->is_pro()) {
            return true;
        }
        
        // Define free features
        $free_features = ['schedule_daily'];
        
        return in_array($feature_id, $free_features);
    }

    /**
     * Get all PRO features
     * 
     * @return array
     */
    public function get_all_features()
    {
        return $this->pro_features;
    }

    /**
     * Get features by category
     * 
     * @param string $category
     * @return array
     */
    public function get_features_by_category($category)
    {
        $features = [];
        foreach ($this->pro_features as $id => $feature) {
            if ($feature['category'] === $category) {
                $features[$id] = $feature;
            }
        }
        return $features;
    }

    /**
     * Get feature by ID
     * 
     * @param string $feature_id
     * @return array|null
     */
    public function get_feature($feature_id)
    {
        return isset($this->pro_features[$feature_id]) ? $this->pro_features[$feature_id] : null;
    }

    /**
     * Render PRO badge HTML
     * 
     * @return string
     */
    public function render_pro_badge()
    {
        return '<span class="backupzen-pro-badge">' . esc_html__('PRO', 'backupzen') . '</span>';
    }

    /**
     * Render lock icon HTML
     * 
     * @return string
     */
    public function render_lock_icon()
    {
        return '<span class="dashicons dashicons-lock backupzen-pro-lock"></span>';
    }

    /**
     * Render PRO feature card
     * 
     * @param string $feature_id
     * @param string $position 'dashboard'|'inline'
     * @return string
     */
    public function render_pro_feature_card($feature_id, $position = 'dashboard')
    {
        $feature = $this->get_feature($feature_id);
        if (!$feature) {
            return '';
        }

        $classes = 'backupzen-pro-feature-card';
        if ($position === 'inline') {
            $classes .= ' backupzen-pro-inline';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-feature-id="<?php echo esc_attr($feature_id); ?>">
            <div class="pro-feature-overlay"></div>
            <div class="pro-feature-lock">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <div class="pro-feature-content">
                <div class="pro-feature-icon">
                    <span class="dashicons <?php echo esc_attr($feature['icon']); ?>"></span>
                </div>
                <h3 class="pro-feature-title">
                    <?php echo esc_html($feature['title']); ?>
                    <?php echo $this->render_pro_badge(); ?>
                </h3>
                <p class="pro-feature-description">
                    <?php echo esc_html($feature['description']); ?>
                </p>
                <?php if (!empty($feature['benefits'])) : ?>
                    <ul class="pro-feature-benefits">
                        <?php foreach ($feature['benefits'] as $benefit) : ?>
                            <li>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html($benefit); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="button button-primary backupzen-pro-upgrade-btn">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php echo esc_html__('Upgrade to PRO', 'backupzen'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get upgrade URL
     * 
     * @return string
     */
    public function get_upgrade_url()
    {
        return apply_filters('backupzen_upgrade_url', 'https://example.com/backupzen-pro');
    }
}

