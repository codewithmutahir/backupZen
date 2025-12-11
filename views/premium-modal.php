<?php
/**
 * Premium Modal View - PRO Under Development
 * 
 * Modal for collecting early access emails for BackupZen PRO
 * 
 * @package BackupZen
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;
?>

<!-- Premium Upgrade Modal - PRO Under Development -->
<div id="backupzen-premium-modal" class="backupzen-premium-modal">
    <div class="premium-modal-overlay"></div>
    
    <div class="premium-modal-container">
        <!-- Modal Header -->
        <div class="premium-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <button type="button" class="premium-modal-close" aria-label="<?php echo esc_attr__('Close', 'backupzen'); ?>">
                &times;
            </button>
            
            <div class="premium-modal-icon">
                <span class="dashicons dashicons-hammer"></span>
            </div>
            
            <h2 class="premium-modal-title">
                <?php echo esc_html__('BackupZen PRO is Under Development', 'backupzen'); ?>
            </h2>
            
            <p class="premium-modal-subtitle">
                <?php echo esc_html__('Premium features will be available soon!', 'backupzen'); ?>
            </p>
        </div>
        
        <!-- Modal Body -->
        <div class="premium-modal-body">
            <div id="backupzen-early-access-form-wrapper">
                <p class="premium-feature-description" style="text-align: center; margin-bottom: 24px;">
                    <?php echo esc_html__('We\'re working hard to bring you powerful PRO features. Be the first to know when BackupZen PRO launches!', 'backupzen'); ?>
                </p>
                
                <h3 class="premium-benefits-title">
                    <?php echo esc_html__('Upcoming PRO Features:', 'backupzen'); ?>
                </h3>
                
                <ul class="premium-benefits-list" style="margin-bottom: 32px;">
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php echo esc_html__('Incremental Smart Backups', 'backupzen'); ?></strong>
                            <span><?php echo esc_html__('10x faster backups with intelligent change detection', 'backupzen'); ?></span>
                        </div>
                    </li>
                    
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php echo esc_html__('Cloud Storage Integration', 'backupzen'); ?></strong>
                            <span><?php echo esc_html__('Google Drive, Dropbox, Amazon S3, and more', 'backupzen'); ?></span>
                        </div>
                    </li>
                    
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php echo esc_html__('Advanced Scheduling', 'backupzen'); ?></strong>
                            <span><?php echo esc_html__('Hourly backups, custom cron expressions', 'backupzen'); ?></span>
                        </div>
                    </li>
                    
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php echo esc_html__('Backup Encryption & Migration Tools', 'backupzen'); ?></strong>
                            <span><?php echo esc_html__('AES-256 encryption, one-click staging, multisite support', 'backupzen'); ?></span>
                        </div>
                    </li>
                </ul>
                
                <!-- Email Form -->
                <div class="backupzen-early-access-form">
                    <h4 style="text-align: center; margin: 0 0 16px 0; font-size: 16px; font-weight: 600; color: #1e293b;">
                        <?php echo esc_html__('Get Early Access Notification', 'backupzen'); ?>
                    </h4>
                    
                    <form id="backupzen-early-access-form" style="max-width: 400px; margin: 0 auto;">
                        <div style="margin-bottom: 16px;">
                            <label for="early-access-email" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #475569;">
                                <?php echo esc_html__('Your Email Address', 'backupzen'); ?> <span style="color: #dc2626;">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="early-access-email" 
                                name="email" 
                                placeholder="<?php echo esc_attr__('your@email.com', 'backupzen'); ?>"
                                required
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.2s ease;"
                            />
                            <span id="email-error" style="display: none; color: #dc2626; font-size: 13px; margin-top: 4px;"></span>
                        </div>
                        
                        <button 
                            type="submit" 
                            class="backupzen-early-access-submit"
                            style="width: 100%; padding: 14px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);"
                        >
                            <span class="dashicons dashicons-email" style="margin-right: 8px;"></span>
                            <?php echo esc_html__('Request Early Access', 'backupzen'); ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Success Message (hidden by default) -->
            <div id="backupzen-early-access-success" style="display: none; text-align: center; padding: 40px 20px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; color: #ffffff;"></span>
                </div>
                <h3 style="margin: 0 0 12px 0; font-size: 24px; font-weight: 700; color: #1e293b;">
                    <?php echo esc_html__('Thank You!', 'backupzen'); ?>
                </h3>
                <p style="margin: 0; font-size: 16px; color: #64748b; line-height: 1.6;">
                    <?php echo esc_html__('We will notify you when PRO is available. Get ready for amazing features!', 'backupzen'); ?>
                </p>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="premium-modal-footer">
            <button type="button" class="premium-modal-btn premium-modal-btn-secondary" style="width: 100%; max-width: 200px;">
                <span class="dashicons dashicons-dismiss"></span>
                <span><?php echo esc_html__('Close', 'backupzen'); ?></span>
            </button>
        </div>
    </div>
</div>

