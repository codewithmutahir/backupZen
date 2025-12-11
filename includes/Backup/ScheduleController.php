<?php
/**
 * Schedule Controller - Clean Implementation
 * Handles all schedule-related AJAX and form submissions
 * 
 * @package BackupZen
 * @since 2.0.0
 */

namespace BackupZen\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class ScheduleController
{
    /**
     * Initialize controller
     */
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_backupzen_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_backupzen_toggle_schedule', array($this, 'ajax_toggle_schedule'));
        add_action('wp_ajax_backupzen_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_backupzen_test_backup', array($this, 'ajax_test_backup'));
    }
    
    /**
     * AJAX: Save schedule settings
     */
    public function ajax_save_schedule()
    {
        // Security check
        check_ajax_referer('backupzen_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        try {
            // Get and sanitize inputs
            $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
            $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
            $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '08:00';
            
            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
                wp_send_json_error(array('message' => 'Invalid time format'));
            }
            
            error_log('BackupZen Schedule Controller: Saving settings');
            error_log('- Enabled: ' . ($enabled ? 'YES' : 'NO'));
            error_log('- Frequency: ' . $frequency);
            error_log('- Time: ' . $time);
            
            // Save settings
            update_option('backupzen_schedule_enabled', $enabled);
            update_option('backupzen_schedule_frequency', $frequency);
            update_option('backupzen_schedule_time', $time);
            
            // Save email settings
            if (isset($_POST['email_enabled'])) {
                update_option('backupzen_email_notifications', (bool) $_POST['email_enabled']);
            }
            if (isset($_POST['email_address'])) {
                update_option('backupzen_email_address', sanitize_email($_POST['email_address']));
            }
            if (isset($_POST['email_on_failure'])) {
                update_option('backupzen_email_on_failure', (bool) $_POST['email_on_failure']);
            }
            
            // Save cleanup settings
            if (isset($_POST['auto_cleanup'])) {
                update_option('backupzen_auto_cleanup', (bool) $_POST['auto_cleanup']);
            }
            if (isset($_POST['cleanup_keep'])) {
                update_option('backupzen_cleanup_keep', absint($_POST['cleanup_keep']));
            }
            
            // Save advanced settings
            if (isset($_POST['schedule_format'])) {
                update_option('backupzen_schedule_format', sanitize_text_field($_POST['schedule_format']));
            }
            if (isset($_POST['schedule_files'])) {
                update_option('backupzen_schedule_files', (bool) $_POST['schedule_files']);
            }
            if (isset($_POST['schedule_database'])) {
                update_option('backupzen_schedule_database', (bool) $_POST['schedule_database']);
            }
            
            // Update WordPress cron schedule
            $schedule_manager = new ScheduleManager();
            $cron_result = $schedule_manager->update_schedule($enabled, $frequency, $time);
            
            // Send confirmation email if enabled
            $email_notifications = get_option('backupzen_email_notifications', false);
            $email_address = get_option('backupzen_email_address', '');
            
            if ($email_notifications && !empty($email_address)) {
                $email_notifier = new EmailNotifier();
                $schedule_info = array(
                    'enabled' => $enabled,
                    'frequency' => $frequency,
                    'time' => $time,
                );
                $email_notifier->send_schedule_confirmation($email_address, $schedule_info);
            }
            
            // Prepare response
            $next_run = wp_next_scheduled('backupzen_scheduled_backup_event');
            $time_12hr = TimezoneConverter::convert_to_12_hour($time);
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'Settings saved successfully! Backups will run at %s.',
                    $time_12hr
                ),
                'time' => $time,
                'time_12hr' => $time_12hr,
                'next_run' => $next_run ? TimezoneConverter::utc_timestamp_to_local_time($next_run, 'M j, Y \a\t g:i A') : null,
            ));
            
        } catch (\Exception $e) {
            error_log('BackupZen Schedule Controller Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to save settings: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Quick toggle schedule on/off
     */
    public function ajax_toggle_schedule()
    {
        check_ajax_referer('backupzen_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        update_option('backupzen_schedule_enabled', $enabled);
        
        // Update cron
        $frequency = get_option('backupzen_schedule_frequency', 'daily');
        $time = get_option('backupzen_schedule_time', '08:00');
        
        $schedule_manager = new ScheduleManager();
        $schedule_manager->update_schedule($enabled, $frequency, $time);
        
        wp_send_json_success(array(
            'message' => $enabled ? 'Scheduled backups enabled' : 'Scheduled backups disabled'
        ));
    }
    
    /**
     * AJAX: Send test email
     */
    public function ajax_test_email()
    {
        check_ajax_referer('backupzen_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        $email_notifier = new EmailNotifier();
        $result = $email_notifier->send_test_email($email);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * AJAX: Run test backup
     */
    public function ajax_test_backup()
    {
        check_ajax_referer('backupzen_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Trigger backup
        do_action('backupzen_scheduled_backup_event');
        
        wp_send_json_success(array('message' => 'Test backup initiated'));
    }
}

