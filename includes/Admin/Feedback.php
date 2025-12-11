<?php
/**
 * Feedback Widget Handler
 *
 * Handles floating rating widget, modal display, and email submission.
 *
 * @package BackupZen
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feedback class.
 *
 * @since 1.0.0
 */
class BackupZen_Feedback
{
    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function __construct()
    {
        // Enqueue assets only on BackupZen admin pages.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Inject modal HTML in admin footer.
        add_action('admin_footer', array($this, 'render_modal'));

        // AJAX handler for feedback submission.
        add_action('wp_ajax_backupzen_feedback_submit', array($this, 'handle_submission'));
    }

    /**
     * Check if current page is a BackupZen admin page.
     *
     * @param string $hook Current admin page hook.
     * @return bool
     */
    private function is_backupzen_page($hook)
    {
        // Check if hook contains backupzen.
        if (strpos($hook, 'backupzen') !== false) {
            return true;
        }

        // Check GET parameter as fallback.
        if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'backupzen') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Get plugin directory path.
     *
     * @return string
     */
    private function get_plugin_dir()
    {
        return dirname(dirname(dirname(__FILE__)));
    }

    /**
     * Get main plugin file path.
     *
     * @return string
     */
    private function get_plugin_file()
    {
        return $this->get_plugin_dir() . '/backupZen.php';
    }

    /**
     * Enqueue feedback widget assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook)
    {
        if (! $this->is_backupzen_page($hook)) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'backupzen-feedback',
            plugins_url('assets/css/feedback.css', $this->get_plugin_file()),
            array(),
            BackupZen::VERSION
        );

        // Enqueue JS.
        wp_enqueue_script(
            'backupzen-feedback',
            plugins_url('assets/js/feedback.js', $this->get_plugin_file()),
            array('jquery'),
            BackupZen::VERSION,
            true
        );

        // Localize script with nonce and AJAX URL.
        wp_localize_script(
            'backupzen-feedback',
            'backupzenFeedback',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('backupzen_feedback_nonce'),
            )
        );
    }

    /**
     * Render feedback modal HTML.
     *
     * @return void
     */
    public function render_modal()
    {
        // Check if we're on a BackupZen page
        $screen = get_current_screen();
        $is_backupzen_page = false;

        // Method 1: Check screen ID
        if ($screen && strpos($screen->id, 'backupzen') !== false) {
            $is_backupzen_page = true;
        }

        // Method 2: Check GET parameter
        if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'backupzen') === 0) {
            $is_backupzen_page = true;
        }

        if (! $is_backupzen_page) {
            return;
        }

        include $this->get_plugin_dir() . '/views/feedback-modal.php';
    }

    /**
     * Handle feedback form submission.
     *
     * @return void
     */
    public function handle_submission()
    {
        error_log('BackupZen Feedback: handle_submission called');
        error_log('BackupZen Feedback: POST data: ' . print_r($_POST, true));

        // Verify nonce.
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'backupzen_feedback_nonce')) {
            error_log('BackupZen Feedback: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed.', 'backupzen')));
        }

        error_log('BackupZen Feedback: Nonce verified');

        // Check capabilities.
        if (! current_user_can('manage_options')) {
            error_log('BackupZen Feedback: User lacks manage_options capability');
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'backupzen')));
        }

        error_log('BackupZen Feedback: Permissions verified');

        // Sanitize input.
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $idea_title = isset($_POST['idea_title']) ? sanitize_text_field(wp_unslash($_POST['idea_title'])) : '';
        $idea_description = isset($_POST['idea_description']) ? sanitize_textarea_field(wp_unslash($_POST['idea_description'])) : '';

        // Get current user information.
        $current_user = wp_get_current_user();
        if (! $current_user || ! $current_user->exists()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit feedback.', 'backupzen')));
        }

        $name = ! empty($current_user->display_name) ? $current_user->display_name : $current_user->user_login;
        $email = $current_user->user_email;

        // Validate required fields.
        if (empty($type) || ! in_array($type, array('feedback', 'idea', 'help'), true)) {
            wp_send_json_error(array('message' => __('Invalid submission type.', 'backupzen')));
        }

        if (empty($email) || ! is_email($email)) {
            wp_send_json_error(array('message' => __('Valid email is required. Please ensure your WordPress user has an email address.', 'backupzen')));
        }

        // Validate type-specific fields.
        if ('feedback' === $type && $rating < 1) {
            wp_send_json_error(array('message' => __('Please provide a rating.', 'backupzen')));
        }

        if ('idea' === $type && (empty($idea_title) || empty($idea_description))) {
            wp_send_json_error(array('message' => __('Idea title and description are required.', 'backupzen')));
        }

        if ('help' === $type && empty($message)) {
            wp_send_json_error(array('message' => __('Message is required.', 'backupzen')));
        }

        // Prepare email data.
        $email_data = array(
            'type' => $type,
            'name' => $name,
            'email' => $email,
            'rating' => $rating,
            'message' => $message,
            'idea_title' => $idea_title,
            'idea_description' => $idea_description,
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => BackupZen::VERSION,
            'user_role' => $this->get_user_role(),
        );

        // Send email.
        $sent = $this->send_email($email_data);

        if ($sent) {
            wp_send_json_success(array(
                'message' => __('Thank you! Your feedback has been submitted successfully. We appreciate your input!', 'backupzen')
            ));
        } else {
            // Log the failure for debugging.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BackupZen Feedback: Email sending failed for ' . $type . ' submission from ' . $email);
            }
            
            wp_send_json_error(array(
                'message' => __('Failed to send email. Please check your WordPress email configuration or try again later.', 'backupzen')
            ));
        }
    }

    /**
     * Get current user role.
     *
     * @return string
     */
    private function get_user_role()
    {
        $user = wp_get_current_user();
        if (! $user || empty($user->roles)) {
            return __('Unknown', 'backupzen');
        }

        return implode(', ', $user->roles);
    }

    /**
     * Send feedback email.
     *
     * @param array $data Email data.
     * @return bool
     */
    private function send_email($data)
    {
        $to = 'siyalsiyal42@gmail.com';
        $subject = sprintf(
            /* translators: %s: Feedback type */
            __('backupZen %s Submission', 'backupzen'),
            ucfirst($data['type'])
        );

        // Load email template.
        ob_start();
        $template_path = $this->get_plugin_dir() . '/includes/Templates/email-feedback.php';
        if (! file_exists($template_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BackupZen Feedback: Email template not found at ' . $template_path);
            }
            return false;
        }
        include $template_path;
        $email_body = ob_get_clean();

        if (empty($email_body)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BackupZen Feedback: Email template produced empty output');
            }
            return false;
        }

        // Set headers for HTML email.
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field($data['name']) . ' <' . sanitize_email($data['email']) . '>',
            'Reply-To: ' . sanitize_email($data['email']),
        );

        // Log email attempt.
        error_log('BackupZen Feedback: Attempting to send email');
        error_log('BackupZen Feedback: To: ' . $to);
        error_log('BackupZen Feedback: Subject: ' . $subject);
        error_log('BackupZen Feedback: From: ' . $data['name'] . ' <' . $data['email'] . '>');
        error_log('BackupZen Feedback: Message length: ' . strlen($email_body) . ' bytes');

        // Send email.
        $sent = wp_mail($to, $subject, $email_body, $headers);

        if ($sent) {
            error_log('BackupZen Feedback: ✓✓✓ EMAIL SENT SUCCESSFULLY TO ' . $to . ' ✓✓✓');
        } else {
            error_log('BackupZen Feedback: ✗✗✗ FAILED TO SEND EMAIL ✗✗✗');
            error_log('BackupZen Feedback: Check WordPress debug log for wp_mail() errors');
            
            // Try to get last error if available.
            global $phpmailer;
            if (isset($phpmailer) && is_object($phpmailer) && ! empty($phpmailer->ErrorInfo)) {
                error_log('BackupZen Feedback: PHPMailer Error: ' . $phpmailer->ErrorInfo);
            }
        }

        return $sent;
    }
}

