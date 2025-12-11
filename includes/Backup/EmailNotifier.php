<?php
/**
 * Email Notifier Class - TIMEZONE-AWARE VERSION
 * Handles backup notification emails with proper timezone display
 * 
 * @package BackupZen
 * @since 1.2.0
 */

namespace BackupZen\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class EmailNotifier
{
    /**
     * Send backup notification email
     * 
     * ALL TIMES ARE DISPLAYED IN USER'S LOCAL TIMEZONE
     * 
     * @param array  $result   Backup result array
     * @param string $filename Backup filename
     * @param bool   $force_send If true, bypass email_on_failure check (for scheduled backups)
     * @return array Result with success status
     */
    public function send_backup_notification($result, $filename, $force_send = false)
    {
        error_log('BackupZen EmailNotifier: send_backup_notification called');
        error_log('BackupZen EmailNotifier: - filename: ' . $filename);
        error_log('BackupZen EmailNotifier: - backup success: ' . ($result['success'] ? 'YES' : 'NO'));
        error_log('BackupZen EmailNotifier: - force_send: ' . ($force_send ? 'YES' : 'NO'));
        
        $email_enabled = get_option('backupzen_email_notifications', false);
        error_log('BackupZen EmailNotifier: - email notifications enabled: ' . ($email_enabled ? 'YES' : 'NO'));
        
        if (!$email_enabled) {
            error_log('BackupZen EmailNotifier: ✗ Email notifications are disabled');
            return array(
                'success' => false,
                'message' => __('Email notifications are disabled', 'backupzen'),
            );
        }
        
        // Check if we should only send on failure (unless force_send is true)
        if (!$force_send) {
            $email_on_failure = get_option('backupzen_email_on_failure', false);
            error_log('BackupZen EmailNotifier: - email_on_failure setting: ' . ($email_on_failure ? 'YES' : 'NO'));
            
            if ($email_on_failure && $result['success']) {
                error_log('BackupZen EmailNotifier: ✗ Skipping email (email_on_failure enabled and backup succeeded)');
                return array(
                    'success' => true,
                    'message' => __('Email not sent (only on failure enabled)', 'backupzen'),
                );
            }
        } else {
            error_log('BackupZen EmailNotifier: ✓ force_send=true, bypassing email_on_failure check');
        }
        
        $email_address = get_option('backupzen_email_address', get_option('admin_email'));
        error_log('BackupZen EmailNotifier: - email address: ' . ($email_address ? $email_address : 'NOT SET'));
        
        if (empty($email_address)) {
            error_log('BackupZen EmailNotifier: ✗ Email address not configured');
            return array(
                'success' => false,
                'message' => __('Email address not configured', 'backupzen'),
            );
        }
        
        // Get timezone info for email
        $tz_info = TimezoneConverter::get_timezone_info();
        $current_local_time = TimezoneConverter::get_current_local_time('F j, Y \a\t g:i A');
        
        // Prepare email content
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        if ($result['success']) {
            $subject = sprintf(
                __('[%s] Backup Completed Successfully', 'backupzen'),
                $site_name
            );
            
            $status = '✓ SUCCESS';
            $status_color = '#10b981';
            
            $file_size = isset($result['file_size']) ? size_format($result['file_size']) : 'Unknown';
            
            $details = sprintf(
                __('Backup file: %s<br>File size: %s', 'backupzen'),
                esc_html($filename),
                esc_html($file_size)
            );
        } else {
            $subject = sprintf(
                __('[%s] Backup Failed', 'backupzen'),
                $site_name
            );
            
            $status = '✗ FAILED';
            $status_color = '#ef4444';
            
            $error_message = isset($result['message']) ? $result['message'] : __('Unknown error', 'backupzen');
            
            $details = sprintf(
                __('Error: %s', 'backupzen'),
                esc_html($error_message)
            );
        }
        
        // Build HTML email
        $message = $this->build_email_html(array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'status' => $status,
            'status_color' => $status_color,
            'details' => $details,
            'timestamp' => $current_local_time,
            'timezone' => $tz_info['offset_string'],
            'timezone_name' => $tz_info['timezone_name'],
        ));
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );
        
        // Send email
        error_log('BackupZen EmailNotifier: Attempting to send email via wp_mail()...');
        error_log('BackupZen EmailNotifier: - To: ' . $email_address);
        error_log('BackupZen EmailNotifier: - Subject: ' . $subject);
        error_log('BackupZen EmailNotifier: - Message length: ' . strlen($message) . ' bytes');
        error_log('BackupZen EmailNotifier: - Headers: ' . count($headers) . ' headers');
        
        $sent = wp_mail($email_address, $subject, $message, $headers);
        
        error_log('BackupZen EmailNotifier: wp_mail() returned: ' . ($sent ? 'TRUE (success)' : 'FALSE (failed)'));
        
        if ($sent) {
            error_log('BackupZen EmailNotifier: ✓✓✓ EMAIL SENT SUCCESSFULLY TO ' . $email_address . ' ✓✓✓');
            return array(
                'success' => true,
                'message' => __('Email notification sent successfully', 'backupzen'),
            );
        } else {
            error_log('BackupZen EmailNotifier: ✗✗✗ FAILED TO SEND EMAIL ✗✗✗');
            error_log('BackupZen EmailNotifier: Check WordPress debug log for wp_mail() errors');
            return array(
                'success' => false,
                'message' => __('Failed to send email notification', 'backupzen'),
            );
        }
    }
    
    /**
     * Send schedule confirmation email
     * Notifies user when backup schedule is created/updated
     * 
     * @param string $email_address Email address
     * @param array $schedule_info Schedule information (frequency, time, enabled)
     * @return array Result with success status
     */
    public function send_schedule_confirmation($email_address, $schedule_info)
    {
        if (empty($email_address) || !is_email($email_address)) {
            return array(
                'success' => false,
                'message' => __('Invalid email address', 'backupzen'),
            );
        }
        
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        // Get timezone info
        $tz_info = TimezoneConverter::get_timezone_info();
        $current_local_time = TimezoneConverter::get_current_local_time('F j, Y \a\t g:i A');
        
        // Convert time to 12-hour format for email
        $time_12hr = TimezoneConverter::convert_to_12_hour($schedule_info['time']);
        
        // Determine status
        $is_enabled = !empty($schedule_info['enabled']);
        
        if ($is_enabled) {
            $subject = sprintf(
                __('[%s] Backup Schedule Activated', 'backupzen'),
                $site_name
            );
            $status = '✓ SCHEDULE ACTIVE';
            $status_color = '#10b981';
            
            // Calculate next backup time
            $next_run = wp_next_scheduled('backupzen_scheduled_backup_event');
            $next_run_display = $next_run 
                ? TimezoneConverter::utc_timestamp_to_local_time($next_run, 'F j, Y \a\t g:i A')
                : __('Pending...', 'backupzen');
            
            $details = sprintf(
                __('Your backup schedule has been configured!<br><br>
                <strong>Frequency:</strong> %s<br>
                <strong>Time:</strong> %s<br>
                <strong>Next Backup:</strong> %s<br><br>
                Your site will be automatically backed up according to this schedule.', 'backupzen'),
                ucfirst(esc_html($schedule_info['frequency'])),
                esc_html($time_12hr),
                esc_html($next_run_display)
            );
        } else {
            $subject = sprintf(
                __('[%s] Backup Schedule Disabled', 'backupzen'),
                $site_name
            );
            $status = '⏸ SCHEDULE DISABLED';
            $status_color = '#f59e0b';
            
            $details = __('Automatic backups have been disabled. Your site will no longer be backed up automatically.<br><br>You can re-enable scheduled backups at any time from the BackupZen settings.', 'backupzen');
        }
        
        $message = $this->build_email_html(array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'status' => $status,
            'status_color' => $status_color,
            'details' => $details,
            'timestamp' => $current_local_time,
            'timezone' => $tz_info['offset_string'],
            'timezone_name' => $tz_info['timezone_name'],
        ));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );
        
        $sent = wp_mail($email_address, $subject, $message, $headers);
        
        if ($sent) {
            error_log('BackupZen: Schedule confirmation email sent to ' . $email_address);
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Schedule confirmation sent to %s', 'backupzen'),
                    $email_address
                ),
            );
        } else {
            error_log('BackupZen: Failed to send schedule confirmation email');
            return array(
                'success' => false,
                'message' => __('Failed to send confirmation email.', 'backupzen'),
            );
        }
    }
    
    /**
     * Send test email
     * 
     * @param string $email_address Email address to send test to
     * @return array Result with success status
     */
    public function send_test_email($email_address)
    {
        if (empty($email_address) || !is_email($email_address)) {
            return array(
                'success' => false,
                'message' => __('Invalid email address', 'backupzen'),
            );
        }
        
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        // Get timezone info
        $tz_info = TimezoneConverter::get_timezone_info();
        $current_local_time = TimezoneConverter::get_current_local_time('F j, Y \a\t g:i A');
        
        $subject = sprintf(
            __('[%s] Test Email from BackupZen', 'backupzen'),
            $site_name
        );
        
        $message = $this->build_email_html(array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'status' => '✓ TEST',
            'status_color' => '#3b82f6',
            'details' => __('This is a test email from BackupZen. If you received this, your email notifications are configured correctly!', 'backupzen'),
            'timestamp' => $current_local_time,
            'timezone' => $tz_info['offset_string'],
            'timezone_name' => $tz_info['timezone_name'],
        ));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );
        
        $sent = wp_mail($email_address, $subject, $message, $headers);
        
        if ($sent) {
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Test email sent successfully to %s', 'backupzen'),
                    $email_address
                ),
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to send test email. Please check your email configuration.', 'backupzen'),
            );
        }
    }
    
    /**
     * Build HTML email template
     * 
     * @param array $data Email data
     * @return string HTML email content
     */
    private function build_email_html($data)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($data['site_name']); ?> - Backup Notification</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                        🛡️ BackupZen
                                    </h1>
                                    <p style="margin: 10px 0 0 0; color: #e0e7ff; font-size: 14px;">
                                        <?php echo esc_html($data['site_name']); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Status Badge -->
                            <tr>
                                <td style="padding: 30px; text-align: center;">
                                    <div style="display: inline-block; background-color: <?php echo esc_attr($data['status_color']); ?>; color: #ffffff; padding: 12px 24px; border-radius: 24px; font-size: 16px; font-weight: 600;">
                                        <?php echo esc_html($data['status']); ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Details -->
                            <tr>
                                <td style="padding: 0 30px 30px 30px;">
                                    <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; border-left: 4px solid <?php echo esc_attr($data['status_color']); ?>;">
                                        <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.6;">
                                            <?php echo $data['details']; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Timestamp with Timezone Info -->
                            <tr>
                                <td style="padding: 0 30px 30px 30px;">
                                    <div style="background-color: #eff6ff; border-radius: 8px; padding: 15px; text-align: center;">
                                        <p style="margin: 0 0 5px 0; color: #1e40af; font-size: 13px; font-weight: 600;">
                                            📅 Timestamp
                                        </p>
                                        <p style="margin: 0; color: #374151; font-size: 14px;">
                                            <?php echo esc_html($data['timestamp']); ?>
                                        </p>
                                        <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 12px;">
                                            🌍 <?php echo esc_html($data['timezone_name']); ?> (<?php echo esc_html($data['timezone']); ?>)
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                                    <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 12px; line-height: 1.5;">
                                        This email was sent by <strong>BackupZen</strong> on<br>
                                        <a href="<?php echo esc_url($data['site_url']); ?>" style="color: #3b82f6; text-decoration: none;"><?php echo esc_html($data['site_url']); ?></a>
                                    </p>
                                    <p style="margin: 0; color: #9ca3af; font-size: 11px;">
                                        You are receiving this email because backup notifications are enabled in BackupZen settings.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}