<?php
/**
 * Feedback Modal Template
 *
 * @package BackupZen
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}
?>

<!-- Floating Feedback Widget -->
<div id="backupzen-feedback-widget" class="backupzen-feedback-widget">
    <button type="button" class="backupzen-feedback-trigger" aria-label="<?php esc_attr_e('Open feedback', 'backupzen'); ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
        </svg>
    </button>
</div>

<!-- Feedback Modal -->
<div id="backupzen-feedback-modal" class="backupzen-feedback-modal" role="dialog" aria-labelledby="backupzen-modal-title" aria-hidden="true">
    <div class="backupzen-feedback-modal-overlay"></div>
    <div class="backupzen-feedback-modal-container">
        <button type="button" class="backupzen-feedback-modal-close" aria-label="<?php esc_attr_e('Close modal', 'backupzen'); ?>">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

        <!-- Step 1: Choose Type -->
        <div class="backupzen-feedback-step" data-step="1">
            <h2 id="backupzen-modal-title" class="backupzen-feedback-title">
                <?php esc_html_e('How can we help?', 'backupzen'); ?>
            </h2>
            <p class="backupzen-feedback-subtitle">
                <?php esc_html_e('We\'d love to hear from you!', 'backupzen'); ?>
            </p>
            
            <div class="backupzen-feedback-options">
                <button type="button" class="backupzen-feedback-option" data-type="feedback">
                    <div class="backupzen-feedback-option-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3><?php esc_html_e('Give Feedback', 'backupzen'); ?></h3>
                    <p><?php esc_html_e('Rate your experience', 'backupzen'); ?></p>
                </button>

                <button type="button" class="backupzen-feedback-option" data-type="idea">
                    <div class="backupzen-feedback-option-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3><?php esc_html_e('Share an Idea', 'backupzen'); ?></h3>
                    <p><?php esc_html_e('Suggest a feature', 'backupzen'); ?></p>
                </button>

                <button type="button" class="backupzen-feedback-option" data-type="help">
                    <div class="backupzen-feedback-option-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7C10.9 7 10 7.9 10 9H8C8 6.79 9.79 5 12 5C14.21 5 16 6.79 16 9C16 9.88 15.64 10.68 15.07 11.25Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3><?php esc_html_e('Get Help', 'backupzen'); ?></h3>
                    <p><?php esc_html_e('Need assistance?', 'backupzen'); ?></p>
                </button>
            </div>
        </div>

        <!-- Step 2: Type-Specific Form -->
        <div class="backupzen-feedback-step" data-step="2" style="display: none;">
            <h2 class="backupzen-feedback-title" id="backupzen-step2-title"></h2>
            <p class="backupzen-feedback-subtitle" id="backupzen-step2-subtitle"></p>

            <!-- Feedback Form -->
            <form id="backupzen-feedback-form" class="backupzen-feedback-form" style="display: none;">
                <div class="backupzen-feedback-rating">
                    <label><?php esc_html_e('Rate your experience', 'backupzen'); ?> <span class="required">*</span></label>
                    <div class="backupzen-stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <button type="button" class="backupzen-star" data-rating="<?php echo esc_attr($i); ?>" aria-label="<?php echo esc_attr(sprintf(__('Rate %d stars', 'backupzen'), $i)); ?>">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="currentColor"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="backupzen-rating-input" value="0">
                </div>
                <div class="backupzen-form-group">
                    <label for="feedback_message"><?php esc_html_e('Tell us about your experience', 'backupzen'); ?> <span class="optional">(<?php esc_html_e('Optional', 'backupzen'); ?>)</span></label>
                    <textarea id="feedback_message" name="message" rows="4" placeholder="<?php esc_attr_e('Share your thoughts, suggestions, or any issues you encountered...', 'backupzen'); ?>"></textarea>
                </div>
            </form>

            <!-- Idea Form -->
            <form id="backupzen-idea-form" class="backupzen-feedback-form" style="display: none;">
                <div class="backupzen-form-group">
                    <label for="idea_title"><?php esc_html_e('Idea Title', 'backupzen'); ?> <span class="required">*</span></label>
                    <input type="text" id="idea_title" name="idea_title" required>
                </div>
                <div class="backupzen-form-group">
                    <label for="idea_description"><?php esc_html_e('Description', 'backupzen'); ?> <span class="required">*</span></label>
                    <textarea id="idea_description" name="idea_description" rows="5" required></textarea>
                </div>
            </form>

            <!-- Help Form -->
            <form id="backupzen-help-form" class="backupzen-feedback-form" style="display: none;">
                <div class="backupzen-form-group">
                    <label for="help_message"><?php esc_html_e('Message', 'backupzen'); ?> <span class="required">*</span></label>
                    <textarea id="help_message" name="message" rows="5" required></textarea>
                </div>
            </form>

            <div class="backupzen-feedback-actions">
                <button type="button" class="backupzen-btn backupzen-btn-secondary backupzen-btn-back">
                    <span class="backupzen-btn-text"><?php esc_html_e('Back', 'backupzen'); ?></span>
                </button>
                <button type="button" class="backupzen-btn backupzen-btn-primary backupzen-btn-submit">
                    <span class="backupzen-btn-text"><?php esc_html_e('Submit', 'backupzen'); ?></span>
                    <span class="backupzen-btn-spinner" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-dasharray="31.416" stroke-dashoffset="31.416" opacity="0.3"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-dasharray="31.416" stroke-dashoffset="15.708">
                                <animateTransform attributeName="transform" type="rotate" values="0 12 12;360 12 12" dur="1s" repeatCount="indefinite"/>
                            </circle>
                        </svg>
                    </span>
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <div class="backupzen-feedback-step" data-step="success" style="display: none;">
            <div class="backupzen-feedback-success">
                <div class="backupzen-feedback-success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 16.17L4.83 12L3.41 13.41L9 19L21 7L19.59 5.59L9 16.17Z" fill="currentColor"/>
                    </svg>
                </div>
                <h2 class="backupzen-feedback-title"><?php esc_html_e('Thank You!', 'backupzen'); ?></h2>
                <p class="backupzen-feedback-subtitle" id="backupzen-success-message">
                    <?php esc_html_e('Your feedback has been submitted successfully. We appreciate your input!', 'backupzen'); ?>
                </p>
                <button type="button" class="backupzen-btn backupzen-btn-primary backupzen-btn-close-modal">
                    <?php esc_html_e('Close', 'backupzen'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

