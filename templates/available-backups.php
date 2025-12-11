<?php
/**
 * Available Backups Template
 * 
 * Save this as: templates/available-backups.php
 * 
 * @package BackupZen
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

// Get all backups
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables
$scanner = new \BackupZen\Backup\BackupScanner($this->get_backup_dir_path());
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables
$backups = $scanner->get_backups();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables
$total_size = array_sum(array_column($backups, 'size'));
?>

<div class="wrap">
    <h1 class="page-title">
        <span class="dashicons dashicons-database-view"></span>
        <?php echo esc_html__('Available Backups', 'backupzen'); ?>
    </h1>
    <p class="page-subtitle">
        <?php echo esc_html__('Manage, download, and restore your backup files', 'backupzen'); ?>
    </p>

    <div class="backupzen-container">
        <?php
        // PRO Features Showcase for Backups
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable
        $pro_manager = \BackupZen\Premium\ProFeaturesManager::get_instance();
        if (!$pro_manager->is_pro() && !empty($backups)) :
        ?>
        <div class="backupzen-pro-features-showcase" style="margin-bottom: 32px;">
            <h2 class="section-title">
                <span class="dashicons dashicons-star-filled"></span>
                <?php echo esc_html__('PRO Backup Features', 'backupzen'); ?>
                <span class="backupzen-pro-badge"><?php echo esc_html__('PRO', 'backupzen'); ?></span>
            </h2>
            
            <div class="backupzen-pro-features-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                <?php
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable
                $backup_pro_features = [
                    'partial_restore',
                    'backup_verification',
                    'backup_encryption',
                    'cloud_google_drive'
                ];
                
                foreach ($backup_pro_features as $feature_id) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable
                    $feature = $pro_manager->get_feature($feature_id);
                    if ($feature) :
                ?>
                    <div class="backupzen-pro-feature-card" data-feature-id="<?php echo esc_attr($feature_id); ?>" data-category="<?php echo esc_attr($feature['category']); ?>" style="height: auto;">
                        <div class="pro-feature-overlay"></div>
                        <div class="pro-feature-lock">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <div class="pro-feature-content">
                            <div class="pro-feature-icon" style="width: 48px; height: 48px; margin-bottom: 12px;">
                                <span class="dashicons <?php echo esc_attr($feature['icon']); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                            </div>
                            <h3 class="pro-feature-title" style="font-size: 16px;">
                                <?php echo esc_html($feature['title']); ?>
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - render_pro_badge() already escapes output
                                echo $pro_manager->render_pro_badge();
                                ?>
                            </h3>
                            <p class="pro-feature-description" style="font-size: 13px; margin-bottom: 12px;">
                                <?php echo esc_html($feature['description']); ?>
                            </p>
                            <button type="button" class="button button-primary backupzen-pro-upgrade-btn" style="padding: 8px 16px; font-size: 13px;">
                                <span class="dashicons dashicons-star-filled" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <?php echo esc_html__('Unlock PRO', 'backupzen'); ?>
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
        
        <?php
        // Show restore success notice
        if (isset($_GET['restore_success']) && '1' === $_GET['restore_success']) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable
            $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variable
            $files_count = isset($_GET['files_count']) ? absint($_GET['files_count']) : 0;
            $tables_count = isset($_GET['tables_count']) ? absint($_GET['tables_count']) : 0;
            $pre_backup = isset($_GET['pre_backup']) ? sanitize_text_field(wp_unslash($_GET['pre_backup'])) : '';
        ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php echo esc_html__('Restore completed successfully!', 'backupzen'); ?></strong><br>
                    <?php
                    /* translators: %s: Backup filename */
                    echo esc_html(sprintf(__('Your site has been restored from backup: %s', 'backupzen'), $file));
                    ?>
                    <?php if ($files_count > 0 || $tables_count > 0) : ?>
                        <br>
                        <?php
                        if ($files_count > 0) {
                            /* translators: %d: Number of files restored */
                            echo esc_html(sprintf(_n('%d file restored.', '%d files restored.', $files_count, 'backupzen'), $files_count));
                        }
                        if ($tables_count > 0) {
                            /* translators: %d: Number of database tables restored */
                            echo ' ' . esc_html(sprintf(_n('%d database table restored.', '%d database tables restored.', $tables_count, 'backupzen'), $tables_count));
                        }
                        ?>
                    <?php endif; ?>
                    <?php if (!empty($pre_backup)) : ?>
                        <br><br>
                        <strong><?php echo esc_html__('Pre-restore backup created:', 'backupzen'); ?></strong>
                        <?php echo esc_html($pre_backup); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php
        }

        // Show restore error notice
        if (isset($_GET['restore_error']) && '1' === $_GET['restore_error']) {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('An unknown error occurred during restore.', 'backupzen');
            $pre_backup = isset($_GET['pre_backup']) ? sanitize_text_field(wp_unslash($_GET['pre_backup'])) : '';
        ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php echo esc_html__('Restore failed!', 'backupzen'); ?></strong><br>
                    <?php echo esc_html($message); ?>
                    <?php if (!empty($pre_backup)) : ?>
                        <br><br>
                        <strong><?php echo esc_html__('Pre-restore backup available:', 'backupzen'); ?></strong>
                        <?php echo esc_html($pre_backup); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php
        }

        // Show restore notice for non-BZEN files
        if (isset($_GET['restore_notice']) && '1' === $_GET['restore_notice']) {
            $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
        ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php echo esc_html__('Restore functionality coming soon!', 'backupzen'); ?></strong><br>
                    <?php
                    /* translators: %s: Backup filename */
                    echo esc_html(sprintf(__('Restore logic for "%s" is not yet implemented.', 'backupzen'), $file));
                    ?>
                </p>
            </div>
        <?php
        }
        ?>

        <!-- Premium Backup Cards Section -->
        <div class="backupzen-section">
            <div class="backupzen-cards-header">
                <h2><?php echo esc_html__('Your Backups', 'backupzen'); ?></h2>
                <div class="backupzen-cards-stats">
                    <span class="stat-badge">
                        <span class="dashicons dashicons-backup"></span>
                        <?php
                        /* translators: %d: Number of backups */
                        echo esc_html(sprintf(_n('%d Backup', '%d Backups', count($backups), 'backupzen'), count($backups)));
                        ?>
                    </span>
                    <span class="stat-badge">
                        <span class="dashicons dashicons-database"></span>
                        <?php echo esc_html(size_format($total_size)); ?>
                    </span>
                </div>
            </div>

            <?php if (empty($backups)) : ?>
                <div class="backupzen-empty-state">
                    <div class="empty-state-icon">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <h3><?php echo esc_html__('No Backups Yet', 'backupzen'); ?></h3>
                    <p class="description">
                        <?php echo esc_html__('You haven\'t created any backups yet.', 'backupzen'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=backupzen-manual')); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-cloud-saved"></span>
                        <?php echo esc_html__('Create Your First Backup', 'backupzen'); ?>
                    </a>
                </div>
            <?php else : ?>
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

                                    <?php if (!empty($backup['checksum'])) : ?>
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
                                        <span><?php echo esc_html__('More Details', 'backupzen'); ?></span>
                                    </a>
                                </div>

                                <div class="card-details-content" id="details-<?php echo esc_attr($index); ?>" style="display: none;">
                                    <div class="details-grid">
                                        <?php if (!empty($backup['checksum'])) : ?>
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
            <?php endif; ?>
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