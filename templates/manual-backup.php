<?php
/**
 * Manual Backup Template
 * 
 * Save this as: templates/manual-backup.php
 * 
 * @package BackupZen
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1 class="page-title">
        <span class="dashicons dashicons-cloud-saved"></span>
        <?php echo esc_html__('Manual Backup', 'backupzen'); ?>
    </h1>
    <p class="page-subtitle">
        <?php echo esc_html__('Create a manual backup of your WordPress site', 'backupzen'); ?>
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