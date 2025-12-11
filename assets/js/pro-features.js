/**
 * BackupZen PRO Features JavaScript
 * 
 * Handles premium feature interactions, modals, and locked feature behaviors
 * 
 * @package BackupZen
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * PRO Features Module
     */
    const BackupZenPro = {

        /**
         * Initialize PRO features
         */
        init: function() {
            console.log('BackupZen PRO: Initializing...');
            
            this.initProFeatureCards();
            this.initLockedFields();
            this.initPremiumModal();
            this.initCategoryTabs();
            this.initProUpgradeButtons();
            
            console.log('BackupZen PRO: Initialized successfully');
        },

        /**
         * Initialize PRO feature cards
         */
        initProFeatureCards: function() {
            $(document).on('click', '.backupzen-pro-feature-card', function(e) {
                e.preventDefault();
                
                const $card = $(this);
                const featureId = $card.data('feature-id');
                
                console.log('BackupZen PRO: Feature card clicked:', featureId);
                
                // Add click animation
                $card.css('transform', 'scale(0.98)');
                setTimeout(function() {
                    $card.css('transform', '');
                }, 200);
                
                // Open premium modal
                BackupZenPro.openPremiumModal(featureId);
            });
        },

        /**
         * Initialize locked form fields
         */
        initLockedFields: function() {
            // Prevent interactions with locked fields
            $(document).on('click', '.backupzen-pro-locked-field', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $field = $(this);
                const featureId = $field.data('feature-id');
                
                console.log('BackupZen PRO: Locked field clicked:', featureId);
                
                // Shake animation
                $field.addClass('backupzen-shake');
                setTimeout(function() {
                    $field.removeClass('backupzen-shake');
                }, 500);
                
                // Open premium modal
                BackupZenPro.openPremiumModal(featureId);
            });
            
            // Prevent form submission if locked field is selected
            $(document).on('change', '.backupzen-pro-locked-field input, .backupzen-pro-locked-field select', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).prop('checked', false);
                $(this).prop('selected', false);
                return false;
            });

            // Add shake animation CSS if not present
            if (!$('#backupzen-shake-style').length) {
                $('head').append(
                    '<style id="backupzen-shake-style">' +
                    '@keyframes shake {' +
                    '0%, 100% { transform: translateX(0); }' +
                    '10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }' +
                    '20%, 40%, 60%, 80% { transform: translateX(4px); }' +
                    '}' +
                    '.backupzen-shake { animation: shake 0.5s; }' +
                    '</style>'
                );
            }
        },

        /**
         * Initialize premium modal
         */
        initPremiumModal: function() {
            // Close modal on overlay click
            $(document).on('click', '.premium-modal-overlay', function(e) {
                if (e.target === this) {
                    BackupZenPro.closePremiumModal();
                }
            });
            
            // Close modal on close button
            $(document).on('click', '.premium-modal-close', function(e) {
                e.preventDefault();
                BackupZenPro.closePremiumModal();
            });
            
            // Close modal on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.backupzen-premium-modal.active').length) {
                    BackupZenPro.closePremiumModal();
                }
            });
            
            // Close button (secondary)
            $(document).on('click', '.premium-modal-btn-secondary', function(e) {
                e.preventDefault();
                BackupZenPro.closePremiumModal();
            });
        },

        /**
         * Open premium modal
         * 
         * @param {string} featureId
         */
        openPremiumModal: function(featureId) {
            console.log('BackupZen PRO: Opening modal for feature:', featureId);
            
            const $modal = $('#backupzen-premium-modal');
            
            if (!$modal.length) {
                console.error('BackupZen PRO: Premium modal not found');
                return;
            }
            
            // Update modal content if feature-specific
            if (featureId) {
                $modal.attr('data-feature-id', featureId);
            }
            
            // Show modal
            $modal.addClass('active');
            $('body').addClass('backupzen-modal-open');
            
            // Add body class to prevent scrolling
            if (!$('body').hasClass('backupzen-modal-open')) {
                $('body').css('overflow', 'hidden');
            }
        },

        /**
         * Close premium modal
         */
        closePremiumModal: function() {
            console.log('BackupZen PRO: Closing premium modal');
            
            const $modal = $('#backupzen-premium-modal');
            
            $modal.removeClass('active');
            $('body').removeClass('backupzen-modal-open');
            $('body').css('overflow', '');
        },

        /**
         * Initialize category tabs
         */
        initCategoryTabs: function() {
            $(document).on('click', '.backupzen-pro-category-tab', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const category = $tab.data('category');
                
                console.log('BackupZen PRO: Category tab clicked:', category);
                
                // Update active state
                $('.backupzen-pro-category-tab').removeClass('active');
                $tab.addClass('active');
                
                // Filter cards
                if (category === 'all') {
                    $('.backupzen-pro-feature-card').fadeIn(300);
                } else {
                    $('.backupzen-pro-feature-card').hide();
                    $('.backupzen-pro-feature-card[data-category="' + category + '"]').fadeIn(300);
                }
            });
        },

        /**
         * Initialize upgrade buttons
         */
        initProUpgradeButtons: function() {
            $(document).on('click', '.backupzen-pro-upgrade-btn', function(e) {
                e.stopPropagation();
                
                const $btn = $(this);
                const featureId = $btn.closest('[data-feature-id]').data('feature-id');
                
                console.log('BackupZen PRO: Upgrade button clicked for feature:', featureId);
                
                // Open modal with feature context
                BackupZenPro.openPremiumModal(featureId);
            });
        },

        /**
         * Track upgrade link clicks
         */
        trackUpgradeClick: function(featureId) {
            // Send analytics event (if analytics is available)
            if (typeof ga !== 'undefined') {
                ga('send', 'event', 'BackupZen PRO', 'Upgrade Click', featureId);
            }
            
            console.log('BackupZen PRO: Upgrade tracked:', featureId);
        },

        /**
         * Initialize early access form
         */
        initEarlyAccessForm: function() {
            $(document).on('submit', '#backupzen-early-access-form', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $emailInput = $('#early-access-email');
                const $submitBtn = $form.find('button[type="submit"]');
                const $errorMsg = $('#email-error');
                const email = $emailInput.val().trim();
                
                // Reset error
                $errorMsg.hide();
                $emailInput.css('border-color', '#e2e8f0');
                
                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    $errorMsg.text('Please enter a valid email address.').show();
                    $emailInput.css('border-color', '#dc2626');
                    $emailInput.focus();
                    return;
                }
                
                // Disable button and show loading
                $submitBtn.prop('disabled', true);
                const originalHtml = $submitBtn.html();
                $submitBtn.html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Submitting...');
                
                // Submit via AJAX
                $.ajax({
                    url: backupZen.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'backupzen_early_access',
                        nonce: backupZen.nonce,
                        email: email
                    },
                    success: function(response) {
                        console.log('BackupZen PRO: Early access response:', response);
                        
                        if (response.success) {
                            // Show success message
                            $('#backupzen-early-access-form-wrapper').fadeOut(300, function() {
                                $('#backupzen-early-access-success').fadeIn(300);
                            });
                            
                            // Auto-close modal after 3 seconds
                            setTimeout(function() {
                                BackupZenPro.closePremiumModal();
                                
                                // Reset form after closing
                                setTimeout(function() {
                                    $('#backupzen-early-access-success').hide();
                                    $('#backupzen-early-access-form-wrapper').show();
                                    $form[0].reset();
                                    $submitBtn.prop('disabled', false);
                                    $submitBtn.html(originalHtml);
                                }, 300);
                            }, 3000);
                        } else {
                            // Show error
                            const errorMessage = response.data && response.data.message 
                                ? response.data.message 
                                : 'Failed to submit. Please try again.';
                            $errorMsg.text(errorMessage).show();
                            $emailInput.css('border-color', '#dc2626');
                            $submitBtn.prop('disabled', false);
                            $submitBtn.html(originalHtml);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('BackupZen PRO: Early access error:', error);
                        $errorMsg.text('Network error. Please try again.').show();
                        $emailInput.css('border-color', '#dc2626');
                        $submitBtn.prop('disabled', false);
                        $submitBtn.html(originalHtml);
                    }
                });
            });
            
            // Input focus styling
            $('#early-access-email').on('focus', function() {
                $(this).css('border-color', '#667eea');
            }).on('blur', function() {
                if (!$(this).val()) {
                    $(this).css('border-color', '#e2e8f0');
                }
            });
        }
    };

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        BackupZenPro.init();
        BackupZenPro.initEarlyAccessForm();
    });

    /**
     * Expose to global scope for external access
     */
    window.BackupZenPro = BackupZenPro;

})(jQuery);

