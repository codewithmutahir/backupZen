<?php
/**
 * Email Template for Feedback Submissions
 *
 * @package BackupZen
 * @since 1.0.0
 *
 * @var array $data Email data array.
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('backupZen %s Submission', 'backupzen'), ucfirst($data['type']))); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px 20px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-section h2 {
            color: #667eea;
            font-size: 18px;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .info-label {
            font-weight: 600;
            min-width: 140px;
            color: #666;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 18px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .message-box {
            background-color: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .debug-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        .debug-section h3 {
            color: #999;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 15px 0;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1><?php echo esc_html(sprintf(__('backupZen %s Submission', 'backupzen'), ucfirst($data['type']))); ?></h1>
        </div>

        <div class="email-body">
            <!-- Submission Type -->
            <div class="info-section">
                <h2><?php esc_html_e('Submission Details', 'backupzen'); ?></h2>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Type:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html(ucfirst($data['type'])); ?></span>
                </div>
                <?php if ('feedback' === $data['type'] && $data['rating'] > 0) : ?>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Rating:', 'backupzen'); ?></span>
                        <span class="info-value">
                            <span class="rating-display">
                                <span class="rating-stars"><?php echo esc_html(str_repeat('★', $data['rating'])); ?></span>
                                <span><?php echo esc_html($data['rating']); ?>/5</span>
                            </span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Information -->
            <div class="info-section">
                <h2><?php esc_html_e('User Information', 'backupzen'); ?></h2>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Name:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Email:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['email']); ?></span>
                </div>
            </div>

            <!-- Content -->
            <?php if ('idea' === $data['type']) : ?>
                <div class="info-section">
                    <h2><?php esc_html_e('Idea Details', 'backupzen'); ?></h2>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Title:', 'backupzen'); ?></span>
                        <span class="info-value"><?php echo esc_html($data['idea_title']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Description:', 'backupzen'); ?></span>
                        <div class="message-box"><?php echo esc_html($data['idea_description']); ?></div>
                    </div>
                </div>
            <?php elseif ('help' === $data['type'] && ! empty($data['message'])) : ?>
                <div class="info-section">
                    <h2><?php esc_html_e('Message', 'backupzen'); ?></h2>
                    <div class="message-box"><?php echo esc_html($data['message']); ?></div>
                </div>
            <?php elseif ('feedback' === $data['type'] && ! empty($data['message'])) : ?>
                <div class="info-section">
                    <h2><?php esc_html_e('Additional Feedback', 'backupzen'); ?></h2>
                    <div class="message-box"><?php echo esc_html($data['message']); ?></div>
                </div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="info-section">
                <h2><?php esc_html_e('System Information', 'backupzen'); ?></h2>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Website URL:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['site_url']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('WordPress Version:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['wp_version']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Plugin Version:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['plugin_version']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('User Role:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html($data['user_role']); ?></span>
                </div>
            </div>

            <!-- Debug Section -->
            <div class="debug-section">
                <h3><?php esc_html_e('Debug Information', 'backupzen'); ?></h3>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Submission Time:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html(current_time('mysql')); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><?php esc_html_e('Server Time:', 'backupzen'); ?></span>
                    <span class="info-value"><?php echo esc_html(gmdate('Y-m-d H:i:s')); ?> UTC</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><?php esc_html_e('This email was sent from the backupZen plugin feedback system.', 'backupzen'); ?></p>
        </div>
    </div>
</body>
</html>

