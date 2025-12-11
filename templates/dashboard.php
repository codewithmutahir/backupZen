<?php
/**
 * Dashboard Template
 * 
 * Save this as: templates/dashboard.php
 * 
 * @package BackupZen
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

$stats = $this->get_backup_stats();
?>

<div class="wrap backupzen-dashboard">
    <h1 class="dashboard-title">
        <span class="dashicons dashicons-backup"></span>
        <?php echo esc_html__('BackupZen Dashboard', 'backupzen'); ?>
    </h1>
    <p class="dashboard-subtitle">
        <?php echo esc_html__('Complete backup solution for your WordPress site', 'backupzen'); ?>
    </p>

    <!-- Stats Cards -->
    <div class="dashboard-stats-grid">
        <!-- Total Backups Card -->
        <div class="stat-card stat-card-primary">
            <div class="stat-card-icon">
                <span class="dashicons dashicons-database"></span>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo esc_html($stats['total_backups']); ?></div>
                <div class="stat-card-label"><?php echo esc_html__('Total Backups', 'backupzen'); ?></div>
            </div>
            <div class="stat-card-trend">
                <span class="dashicons dashicons-arrow-up-alt"></span>
                <?php echo esc_html__('Active', 'backupzen'); ?>
            </div>
        </div>

        <!-- Total Size Card -->
        <div class="stat-card stat-card-success">
            <div class="stat-card-icon">
                <span class="dashicons dashicons-cloud"></span>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo esc_html(size_format($stats['total_size'])); ?></div>
                <div class="stat-card-label"><?php echo esc_html__('Storage Used', 'backupzen'); ?></div>
            </div>
            <div class="stat-card-trend">
                <span class="dashicons dashicons-chart-area"></span>
                <?php echo esc_html(number_format(($stats['total_size'] / $site_size) * 100, 1)); ?>%
            </div>
        </div>

        <!-- Site Size Card -->
        <div class="stat-card stat-card-info">
            <div class="stat-card-icon">
                <span class="dashicons dashicons-wordpress"></span>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value"><?php echo esc_html(size_format($site_size)); ?></div>
                <div class="stat-card-label"><?php echo esc_html__('Site Size', 'backupzen'); ?></div>
            </div>
            <div class="stat-card-trend">
                <span class="dashicons dashicons-admin-site"></span>
                <?php echo esc_html__('Estimated', 'backupzen'); ?>
            </div>
        </div>

        <!-- Latest Backup Card -->
        <div class="stat-card stat-card-warning">
            <div class="stat-card-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value">
                    <?php 
                    if ($latest_backup) {
                        echo esc_html(human_time_diff($latest_backup['created'], current_time('timestamp')));
                    } else {
                        echo esc_html__('Never', 'backupzen');
                    }
                    ?>
                </div>
                <div class="stat-card-label"><?php echo esc_html__('Last Backup', 'backupzen'); ?></div>
            </div>
            <div class="stat-card-trend">
                <?php if ($latest_backup) : ?>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('Success', 'backupzen'); ?>
                <?php else : ?>
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html__('No backup', 'backupzen'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dashboard-quick-actions">
        <h2 class="section-title">
            <span class="dashicons dashicons-superhero"></span>
            <?php echo esc_html__('Quick Actions', 'backupzen'); ?>
        </h2>
        
        <div class="quick-actions-grid">
            <a href="<?php echo esc_url(admin_url('admin.php?page=backupzen-manual')); ?>" class="quick-action-card quick-action-primary">
                <div class="quick-action-icon">
                    <span class="dashicons dashicons-cloud-saved"></span>
                </div>
                <div class="quick-action-content">
                    <h3><?php echo esc_html__('Create Backup', 'backupzen'); ?></h3>
                    <p><?php echo esc_html__('Create a manual backup now', 'backupzen'); ?></p>
                </div>
                <span class="quick-action-arrow dashicons dashicons-arrow-right-alt2"></span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=backupzen-scheduled')); ?>" class="quick-action-card quick-action-success">
                <div class="quick-action-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="quick-action-content">
                    <h3><?php echo esc_html__('Schedule Backups', 'backupzen'); ?></h3>
                    <p><?php echo esc_html__('Set up automatic backups', 'backupzen'); ?></p>
                </div>
                <span class="quick-action-arrow dashicons dashicons-arrow-right-alt2"></span>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=backupzen-backups')); ?>" class="quick-action-card quick-action-info">
                <div class="quick-action-icon">
                    <span class="dashicons dashicons-database-view"></span>
                </div>
                <div class="quick-action-content">
                    <h3><?php echo esc_html__('View Backups', 'backupzen'); ?></h3>
                    <p><?php echo esc_html__('Browse all available backups', 'backupzen'); ?></p>
                </div>
                <span class="quick-action-arrow dashicons dashicons-arrow-right-alt2"></span>
            </a>
        </div>
    </div>

    <!-- Recent Backups & Backup Types -->
    <div class="dashboard-two-column">
        <!-- Recent Backups -->
        <div class="dashboard-panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <span class="dashicons dashicons-backup"></span>
                    <?php echo esc_html__('Recent Backups', 'backupzen'); ?>
                </h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=backupzen-backups')); ?>" class="panel-link">
                    <?php echo esc_html__('View All', 'backupzen'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            </div>
            <div class="panel-content">
                <?php if (!empty($backups)) : ?>
                    <div class="recent-backups-list">
                        <?php foreach (array_slice($backups, 0, 5) as $backup) : ?>
                            <div class="recent-backup-item">
                                <div class="backup-item-icon">
                                    <span class="dashicons dashicons-<?php echo esc_attr(\BackupZen\Backup\BackupScanner::get_type_icon($backup['type'])); ?>"></span>
                                </div>
                                <div class="backup-item-info">
                                    <div class="backup-item-name"><?php echo esc_html($backup['filename']); ?></div>
                                    <div class="backup-item-meta">
                                        <?php echo esc_html(human_time_diff($backup['created'], current_time('timestamp'))); ?> ago
                                        • <?php echo esc_html(size_format($backup['size'])); ?>
                                    </div>
                                </div>
                                <div class="backup-item-type">
                                    <span class="type-badge type-<?php echo esc_attr($backup['type']); ?>">
                                        <?php echo esc_html(strtoupper($backup['type'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="panel-empty-state">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php echo esc_html__('No backups yet. Create your first backup!', 'backupzen'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Backup Types Distribution -->
        <div class="dashboard-panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <span class="dashicons dashicons-chart-pie"></span>
                    <?php echo esc_html__('Backup Types', 'backupzen'); ?>
                </h2>
            </div>
            <div class="panel-content">
                <div class="backup-types-chart">
                    <?php if ($stats['total_backups'] > 0) : ?>
                        <?php foreach ($stats['by_type'] as $type => $count) : ?>
                            <?php if ($count > 0) : ?>
                                <div class="type-stat-item">
                                    <div class="type-stat-label">
                                        <span class="type-color type-color-<?php echo esc_attr($type); ?>"></span>
                                        <?php echo esc_html(strtoupper($type)); ?>
                                    </div>
                                    <div class="type-stat-bar">
                                        <div class="type-stat-fill type-fill-<?php echo esc_attr($type); ?>" 
                                             style="width: <?php echo esc_attr(($count / $stats['total_backups']) * 100); ?>%"></div>
                                    </div>
                                    <div class="type-stat-count"><?php echo esc_html($count); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="panel-empty-state">
                            <span class="dashicons dashicons-chart-pie"></span>
                            <p><?php echo esc_html__('No data to display', 'backupzen'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PRO Features Showcase -->
    <?php
    $pro_manager = \BackupZen\Premium\ProFeaturesManager::get_instance();
    if (!$pro_manager->is_pro()) :
    ?>
    <div class="backupzen-pro-features-showcase">
        <h2 class="section-title">
            <span class="dashicons dashicons-star-filled"></span>
            <?php echo esc_html__('Unlock PRO Features', 'backupzen'); ?>
            <span class="backupzen-pro-badge"><?php echo esc_html__('PRO', 'backupzen'); ?></span>
        </h2>
        
        <!-- Category Tabs -->
        <div class="backupzen-pro-categories">
            <button type="button" class="backupzen-pro-category-tab active" data-category="all">
                <span class="dashicons dashicons-category"></span>
                <?php echo esc_html__('All Features', 'backupzen'); ?>
            </button>
            <button type="button" class="backupzen-pro-category-tab" data-category="backup">
                <span class="dashicons dashicons-backup"></span>
                <?php echo esc_html__('Backup & Restore', 'backupzen'); ?>
            </button>
            <button type="button" class="backupzen-pro-category-tab" data-category="cloud">
                <span class="dashicons dashicons-cloud"></span>
                <?php echo esc_html__('Cloud Storage', 'backupzen'); ?>
            </button>
            <button type="button" class="backupzen-pro-category-tab" data-category="schedule">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html__('Scheduling', 'backupzen'); ?>
            </button>
            <button type="button" class="backupzen-pro-category-tab" data-category="migration">
                <span class="dashicons dashicons-migrate"></span>
                <?php echo esc_html__('Migration', 'backupzen'); ?>
            </button>
        </div>
        
        <!-- PRO Features Grid -->
        <div class="backupzen-pro-features-grid">
            <?php
            $featured_pro_features = [
                'incremental_backups',
                'backup_encryption',
                'cloud_google_drive',
                'schedule_hourly',
                'one_click_staging',
                'migration_tools'
            ];
            
            foreach ($featured_pro_features as $feature_id) {
                $feature = $pro_manager->get_feature($feature_id);
                if ($feature) :
            ?>
                <div class="backupzen-pro-feature-card" data-feature-id="<?php echo esc_attr($feature_id); ?>" data-category="<?php echo esc_attr($feature['category']); ?>">
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
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - render_pro_badge() already escapes output
                            echo $pro_manager->render_pro_badge();
                            ?>
                        </h3>
                        <p class="pro-feature-description">
                            <?php echo esc_html($feature['description']); ?>
                        </p>
                        <?php if (!empty($feature['benefits'])) : ?>
                            <ul class="pro-feature-benefits">
                                <?php foreach (array_slice($feature['benefits'], 0, 3) as $benefit) : ?>
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
                endif;
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- System Information -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2 class="panel-title">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php echo esc_html__('System Information', 'backupzen'); ?>
            </h2>
        </div>
        <div class="panel-content">
            <div class="system-info-grid">
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('WordPress Version:', 'backupzen'); ?></span>
                    <span class="system-info-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
                </div>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('PHP Version:', 'backupzen'); ?></span>
                    <span class="system-info-value"><?php echo esc_html(PHP_VERSION); ?></span>
                </div>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('MySQL Version:', 'backupzen'); ?></span>
                    <span class="system-info-value"><?php global $wpdb; echo esc_html($wpdb->db_version()); ?></span>
                </div>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('Memory Limit:', 'backupzen'); ?></span>
                    <span class="system-info-value"><?php echo esc_html(WP_MEMORY_LIMIT); ?></span>
                </div>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('Max Upload Size:', 'backupzen'); ?></span>
                    <span class="system-info-value"><?php echo esc_html(size_format(wp_max_upload_size())); ?></span>
                </div>
                <div class="system-info-item">
                    <span class="system-info-label"><?php echo esc_html__('Backup Directory:', 'backupzen'); ?></span>
                    <span class="system-info-value system-info-path"><?php echo esc_html($this->get_backup_dir_path()); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>