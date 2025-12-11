<?php
/**
 * Scheduled Backups Template - Clean AJAX Implementation
 * 
 * @package BackupZen
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Get current settings from database
$schedule_enabled = get_option('backupzen_schedule_enabled', false);
$schedule_frequency = get_option('backupzen_schedule_frequency', 'daily');
$schedule_time = get_option('backupzen_schedule_time', '08:00');
$email_notifications = get_option('backupzen_email_notifications', true);
$email_address = get_option('backupzen_email_address', get_option('admin_email'));
$email_on_failure = get_option('backupzen_email_on_failure', false); // Default: send emails for ALL backups (success + failure)
$auto_cleanup = get_option('backupzen_auto_cleanup', true);
$cleanup_keep = get_option('backupzen_cleanup_keep', 5);
$schedule_format = get_option('backupzen_schedule_format', 'bzen');
$schedule_files = get_option('backupzen_schedule_files', true);
$schedule_database = get_option('backupzen_schedule_database', true);

// Get schedule status
$schedule_manager = new \BackupZen\Backup\ScheduleManager();
$status = $schedule_manager->get_status();

// Get timezone info
$tz_info = \BackupZen\Backup\TimezoneConverter::get_timezone_info();

// Get backup history
$backup_history = get_option('backupzen_backup_history', array());
$recent_history = array_slice($backup_history, 0, 5); 
?>

<div class="wrap backupzen-wrap">
    <h1 class="backupzen-page-title">
        <span class="dashicons dashicons-calendar-alt"></span>
        <?php echo esc_html__('Scheduled Backups', 'backupzen'); ?>
    </h1>
    <p class="backupzen-page-subtitle">
        <?php echo esc_html__('Automatically backup your site on a regular schedule', 'backupzen'); ?>
    </p>
    
    <div class="backupzen-container">
        
        <!-- Status Card -->
        <div class="backupzen-status-card <?php echo $schedule_enabled ? 'status-active' : 'status-inactive'; ?>">
            <div class="status-icon">
                <span class="dashicons dashicons-<?php echo $schedule_enabled ? 'yes-alt' : 'backup'; ?>"></span>
            </div>
            <div class="status-content">
                <h2 class="status-title">
                    <?php 
                    if ($schedule_enabled) {
                        echo esc_html__('Scheduled Backups Active', 'backupzen');
                    } else {
                        echo esc_html__('Scheduled Backups Inactive', 'backupzen');
                    }
                    ?>
                </h2>
                <p class="status-subtitle">
                    <?php 
                    if ($schedule_enabled && $status['is_scheduled']) {
                        $next_run_12hr = \BackupZen\Backup\TimezoneConverter::utc_timestamp_to_local_time(
                            $status['next_run_timestamp'], 
                            'M j, Y \a\t g:i A'
                        );
                        printf(
                            esc_html__('Next backup in %s at %s', 'backupzen'),
                            '<strong>' . esc_html($status['next_run_relative']) . '</strong>',
                            '<strong>' . esc_html($next_run_12hr) . '</strong>'
                        );
                    } elseif ($schedule_enabled) {
                        echo esc_html__('Save settings below to schedule your first backup', 'backupzen');
                    } else {
                        echo esc_html__('Turn on to protect your site automatically', 'backupzen');
                    }
                    ?>
                </p>
            </div>
            <div class="status-toggle">
                <label class="switch">
                    <input type="checkbox" id="quick-enable-toggle" <?php checked($schedule_enabled); ?> />
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Main Settings (NOT a form - using AJAX) -->
        <div id="backupzen-schedule-settings">
            
            <!-- Backup Schedule Section -->
            <div class="backupzen-card">
                <div class="card-header">
                    <h2>
                        <span class="dashicons dashicons-calendar"></span>
                        <?php echo esc_html__('Backup Schedule', 'backupzen'); ?>
                    </h2>
                    <p class="card-description">
                        <?php echo esc_html__('Choose when and how often to backup your site', 'backupzen'); ?>
                    </p>
                </div>
                
                <div class="card-body">
                    <div class="form-row">
                        <!-- Enable Schedule -->
                        <div class="form-field">
                            <label class="field-label">
                                <?php echo esc_html__('Enable Scheduled Backups', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <label class="switch">
                                    <input type="checkbox" id="schedule_enabled" <?php checked($schedule_enabled); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span class="field-hint">
                                    <?php echo esc_html__('Turn on automatic backups', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <!-- Frequency -->
                        <div class="form-field">
                            <label class="field-label" for="schedule_frequency">
                                <?php echo esc_html__('Frequency', 'backupzen'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="field-control">
                                <?php
                                $pro_manager = \BackupZen\Premium\ProFeaturesManager::get_instance();
                                $is_pro = $pro_manager->is_pro();
                                ?>
                                
                                <select id="schedule_frequency" class="field-input">
                                    <!-- FREE: Daily (Only free option) -->
                                    <option value="daily" <?php selected($schedule_frequency, 'daily'); ?>>
                                        <?php echo esc_html__('Daily', 'backupzen'); ?> - <?php echo esc_html__('FREE', 'backupzen'); ?>
                                    </option>
                                    
                                    <!-- PRO: Hourly (Locked) -->
                                    <option value="hourly" <?php selected($schedule_frequency, 'hourly'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                        <?php echo esc_html__('Hourly', 'backupzen'); ?> <?php echo $is_pro ? '' : '🔒 PRO'; ?>
                                    </option>
                                    
                                    <!-- PRO: Weekly (Locked) -->
                                    <option value="weekly" <?php selected($schedule_frequency, 'weekly'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                        <?php echo esc_html__('Weekly / Custom', 'backupzen'); ?> <?php echo $is_pro ? '' : '🔒 PRO'; ?>
                                    </option>
                                    
                                    <!-- PRO: Monthly (Locked) -->
                                    <option value="monthly" <?php selected($schedule_frequency, 'monthly'); ?> <?php echo $is_pro ? '' : 'disabled'; ?>>
                                        <?php echo esc_html__('Monthly', 'backupzen'); ?> <?php echo $is_pro ? '' : '🔒 PRO'; ?>
                                    </option>
                                </select>
                                
                                <span class="field-hint">
                                    <?php echo esc_html__('How often to run backups', 'backupzen'); ?>
                                    <?php if (!$is_pro) : ?>
                                        <br>
                                        <span style="color: #f59e0b; font-weight: 600;">
                                            <?php echo esc_html__('💡 Hourly, Weekly & Monthly schedules available in PRO', 'backupzen'); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                
                                <?php if (!$is_pro) : ?>
                                    <!-- PRO Showcase for Locked Schedules -->
                                    <div style="margin-top: 16px; padding: 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid #f59e0b;">
                                        <div style="display: flex; align-items: start; gap: 12px;">
                                            <span class="dashicons dashicons-star-filled" style="color: #d97706; font-size: 24px; width: 24px; height: 24px; flex-shrink: 0; margin-top: 2px;"></span>
                                            <div>
                                                <strong style="color: #92400e; font-size: 14px; display: block; margin-bottom: 4px;">
                                                    <?php echo esc_html__('Want More Flexible Schedules?', 'backupzen'); ?>
                                                </strong>
                                                <p style="color: #78350f; font-size: 13px; margin: 0 0 12px 0; line-height: 1.5;">
                                                    <?php echo esc_html__('Upgrade to PRO to unlock Hourly backups, Weekly schedules, and advanced cron expressions for complete scheduling control.', 'backupzen'); ?>
                                                </p>
                                                <button type="button" class="button button-primary backupzen-pro-upgrade-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border: none; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);">
                                                    <span class="dashicons dashicons-star-filled"></span>
                                                    <?php echo esc_html__('Upgrade to PRO', 'backupzen'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Time -->
                        <div class="form-field">
                            <label class="field-label" for="schedule_time">
                                <?php echo esc_html__('Time (Your Local Time)', 'backupzen'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="field-control">
                                <input 
                                    type="time" 
                                    id="schedule_time" 
                                    value="<?php echo esc_attr($schedule_time); ?>" 
                                    class="field-input" 
                                />
                                <span class="field-hint">
                                    <?php 
                                    $time_12hr = \BackupZen\Backup\TimezoneConverter::convert_to_12_hour($schedule_time);
                                    printf(
                                        esc_html__('Backups will run at this time in your timezone (%s). Currently set to: %s', 'backupzen'),
                                        '<strong>' . esc_html($tz_info['offset_string']) . '</strong>',
                                        '<strong id="current-time-display">' . esc_html($time_12hr) . '</strong>'
                                    );
                                    ?>
                                </span>
                                <!-- Quick Time Buttons -->
                                <div class="quick-time-selector">
                                    <?php
                                    $quick_times = array(
                                        '00:00' => '12:00 AM',
                                        '03:00' => '3:00 AM',
                                        '06:00' => '6:00 AM',
                                        '08:00' => '8:00 AM',
                                        '12:00' => '12:00 PM',
                                        '14:00' => '2:00 PM',
                                        '18:00' => '6:00 PM',
                                        '22:00' => '10:00 PM',
                                    );
                                    foreach ($quick_times as $time_24 => $time_label) :
                                        $is_selected = ($schedule_time === $time_24) ? 'selected' : '';
                                    ?>
                                    <button type="button" class="quick-time-btn <?php echo $is_selected; ?>" data-time="<?php echo esc_attr($time_24); ?>"><?php echo esc_html($time_label); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Notifications Section -->
            <div class="backupzen-card">
                <div class="card-header">
                    <h2>
                        <span class="dashicons dashicons-email"></span>
                        <?php echo esc_html__('Email Notifications', 'backupzen'); ?>
                    </h2>
                    <p class="card-description">
                        <?php echo esc_html__('Get notified when backups complete or fail', 'backupzen'); ?>
                    </p>
                </div>
                
                <div class="card-body">
                    <div class="form-row">
                        <!-- Enable Notifications -->
                        <div class="form-field">
                            <label class="field-label">
                                <?php echo esc_html__('Send Email Notifications', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <label class="switch">
                                    <input type="checkbox" id="email_notifications" <?php checked($email_notifications); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span class="field-hint">
                                    <?php echo esc_html__('Receive backup status emails', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <!-- Email Address -->
                        <div class="form-field">
                            <label class="field-label" for="email_address">
                                <?php echo esc_html__('Email Address', 'backupzen'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="field-control">
                                <input type="email" id="email_address" value="<?php echo esc_attr($email_address); ?>" class="field-input" />
                                <span class="field-hint">
                                    <?php echo esc_html__('Emails will show timestamps in your local timezone', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Notify on Failures -->
                        <div class="form-field">
                            <label class="field-label">
                                <?php echo esc_html__('Notify on Failures Only', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <label class="switch">
                                    <input type="checkbox" id="email_on_failure" <?php checked($email_on_failure); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span class="field-hint">
                                    <?php echo esc_html__('Only get alerted if backups fail', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Auto-Cleanup Section -->
            <div class="backupzen-card">
                <div class="card-header">
                    <h2>
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__('Auto-Cleanup', 'backupzen'); ?>
                    </h2>
                    <p class="card-description">
                        <?php echo esc_html__('Automatically remove old backups to save space', 'backupzen'); ?>
                    </p>
                </div>
                
                <div class="card-body">
                    <div class="form-row">
                        <!-- Enable Cleanup -->
                        <div class="form-field">
                            <label class="field-label">
                                <?php echo esc_html__('Enable Auto-Cleanup', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <label class="switch">
                                    <input type="checkbox" id="auto_cleanup" <?php checked($auto_cleanup); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span class="field-hint">
                                    <?php echo esc_html__('Delete old backups automatically', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Keep Last X -->
                        <div class="form-field">
                            <label class="field-label" for="cleanup_keep">
                                <?php echo esc_html__('Keep Last', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <div class="input-with-suffix">
                                    <input type="number" id="cleanup_keep" value="<?php echo esc_attr($cleanup_keep); ?>" min="1" max="100" class="field-input" />
                                    <span class="input-suffix"><?php echo esc_html__('backups', 'backupzen'); ?></span>
                                </div>
                                <span class="field-hint">
                                    <?php echo esc_html__('Older backups will be deleted', 'backupzen'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Settings (Collapsible) -->
            <div class="backupzen-card backupzen-advanced-card">
                <div class="card-header card-header-collapsible" id="advanced-toggle">
                    <h2>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('Advanced Settings', 'backupzen'); ?>
                        <span class="dashicons dashicons-arrow-down-alt2 toggle-arrow"></span>
                    </h2>
                </div>
                
                <div class="card-body" id="advanced-content" style="display: none;">
                    <div class="form-row">
                        <!-- Backup Format -->
                        <div class="form-field">
                            <label class="field-label" for="schedule_format">
                                <?php echo esc_html__('Backup Format', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <select id="schedule_format" class="field-input">
                                    <option value="bzen" <?php selected($schedule_format, 'bzen'); ?>>
                                        BZEN (<?php echo esc_html__('Recommended', 'backupzen'); ?>)
                                    </option>
                                    <option value="zip" <?php selected($schedule_format, 'zip'); ?>>ZIP</option>
                                    <option value="sql" <?php selected($schedule_format, 'sql'); ?>>SQL</option>
                                    <option value="sql.gz" <?php selected($schedule_format, 'sql.gz'); ?>>SQL.GZ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <!-- What to Backup -->
                        <div class="form-field form-field-full">
                            <label class="field-label">
                                <?php echo esc_html__('What to Backup', 'backupzen'); ?>
                            </label>
                            <div class="field-control">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="schedule_files" <?php checked($schedule_files); ?> />
                                    <span><?php echo esc_html__('Files (themes, plugins, uploads)', 'backupzen'); ?></span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="schedule_database" <?php checked($schedule_database); ?> />
                                    <span><?php echo esc_html__('Database (posts, pages, settings)', 'backupzen'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="backupzen-actions">
                <button type="button" id="btn-save-schedule" class="button button-primary button-hero">
                    <span class="dashicons dashicons-yes"></span>
                    <?php echo esc_html__('Save Schedule Settings', 'backupzen'); ?>
                </button>
            </div>
        </div>

        <!-- Recent Backups Section -->
        <div class="backupzen-card">
            <div class="card-header">
                <h2>
                    <span class="dashicons dashicons-backup"></span>
                    <?php echo esc_html__('Recent Scheduled Backups', 'backupzen'); ?>
                </h2>
            </div>
            
            <div class="card-body">
                <?php if (empty($recent_history)) : ?>
                    <div class="empty-state">
                        <span class="dashicons dashicons-clock"></span>
                        <p><?php echo esc_html__('No scheduled backups have run yet', 'backupzen'); ?></p>
                        <p class="description"><?php echo esc_html__('Once enabled, your backup history will appear here', 'backupzen'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="backup-history-table">
                        <?php foreach ($recent_history as $entry) : 
                            $local_time = \BackupZen\Backup\TimezoneConverter::utc_timestamp_to_local_time(
                                $entry['timestamp'],
                                'M j, Y \a\t g:i A'
                            );
                        ?>
                            <div class="history-row history-<?php echo esc_attr($entry['status']); ?>">
                                <div class="history-icon">
                                    <span class="dashicons dashicons-<?php echo $entry['status'] === 'success' ? 'yes' : 'no'; ?>"></span>
                                </div>
                                <div class="history-content">
                                    <div class="history-message"><?php echo esc_html($entry['message']); ?></div>
                                    <div class="history-time">
                                        <?php echo esc_html($local_time); ?>
                                        <span class="history-relative">
                                            (<?php echo esc_html(human_time_diff($entry['timestamp'], time())) . ' ' . esc_html__('ago', 'backupzen'); ?>)
                                        </span>
                                    </div>
                                </div>
                                <div class="history-badge">
                                    <?php echo esc_html($entry['status'] === 'success' ? __('Success', 'backupzen') : __('Failed', 'backupzen')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

