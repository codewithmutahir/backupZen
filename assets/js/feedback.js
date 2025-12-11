/**
 * Feedback Widget JavaScript - COMPLETE FIX
 *
 * @package BackupZen
 * @since 1.0.0
 */
(function($) {
    'use strict';

    const FeedbackWidget = {
        currentStep: 1,
        selectedType: null,
        selectedRating: 0,
        formData: {},

        /**
         * Initialize the feedback widget.
         */
        init: function() {
            console.log('BackupZen Feedback: Initializing...');
            
            // Wait for DOM to be fully ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.bindEvents.bind(this));
            } else {
                this.bindEvents();
            }
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            console.log('BackupZen Feedback: Binding event handlers...');
            
            const self = this;
            
            // Test if elements exist
            console.log('BackupZen Feedback: Elements check:');
            console.log('  - Trigger:', $('.backupzen-feedback-trigger').length);
            console.log('  - Modal:', $('#backupzen-feedback-modal').length);
            console.log('  - Submit button:', $('.backupzen-btn-submit').length);
            console.log('  - backupzenFeedback object:', typeof backupzenFeedback);
            
            // CRITICAL FIX: Use proper event delegation with 'on' method
            // This ensures events work even if elements are added dynamically
            
            // Open modal
            $(document).on('click', '.backupzen-feedback-trigger', function(e) {
                e.preventDefault();
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.backupzen-feedback-modal-close, .backupzen-feedback-modal-overlay, .backupzen-btn-close-modal', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Select feedback type
            $(document).on('click', '.backupzen-feedback-option', function(e) {
                e.preventDefault();
                self.selectType.call(self, e);
            });

            // Star rating
            $(document).on('click', '.backupzen-star', function(e) {
                e.preventDefault();
                self.selectRating.call(self, e);
            });
            
            $(document).on('mouseenter', '.backupzen-star', function(e) {
                self.hoverStar.call(self, e);
            });
            
            $(document).on('mouseleave', '.backupzen-stars', function(e) {
                self.clearHover.call(self);
            });

            // Navigation buttons
            $(document).on('click', '.backupzen-btn-back', function(e) {
                e.preventDefault();
                self.goToPreviousStep();
            });

            // Form submission - CRITICAL FIX
            $(document).on('click', '.backupzen-btn-submit', function(e) {
                console.log('BackupZen Feedback: Submit button clicked!');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                self.handleSubmit.call(self, e);
            });
            
            // BACKUP: Also bind directly after a short delay
            setTimeout(function() {
                $('.backupzen-btn-submit').off('click.feedback').on('click.feedback', function(e) {
                    console.log('BackupZen Feedback: Direct handler triggered!');
                    e.preventDefault();
                    e.stopPropagation();
                    self.handleSubmit.call(self, e);
                });
            }, 500);

            // Close on Escape key
            $(document).on('keydown', function(e) {
                self.handleKeydown.call(self, e);
            });
            
            console.log('BackupZen Feedback: Event handlers bound successfully');
        },

        /**
         * Open the feedback modal.
         */
        openModal: function() {
            console.log('BackupZen Feedback: Opening modal');
            const $modal = $('#backupzen-feedback-modal');
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
            this.resetModal();
        },

        /**
         * Close the feedback modal.
         */
        closeModal: function() {
            console.log('BackupZen Feedback: Closing modal');
            const $modal = $('#backupzen-feedback-modal');
            $modal.removeClass('active');
            $('body').css('overflow', '');
            this.resetModal();
        },

        /**
         * Reset modal to initial state.
         */
        resetModal: function() {
            console.log('BackupZen Feedback: Resetting modal');
            this.currentStep = 1;
            this.selectedType = null;
            this.selectedRating = 0;
            this.formData = {};

            // Show step 1, hide others
            $('.backupzen-feedback-step[data-step="1"]').show();
            $('.backupzen-feedback-step[data-step="2"]').hide();
            $('.backupzen-feedback-step[data-step="success"]').hide();

            // Reset forms
            $('#backupzen-feedback-form')[0]?.reset();
            $('#backupzen-idea-form')[0]?.reset();
            $('#backupzen-help-form')[0]?.reset();
            
            $('.backupzen-star').removeClass('active hovered');
            $('#backupzen-rating-input').val(0);

            // Reset submit button state
            const $submitBtn = $('.backupzen-btn-submit');
            $submitBtn.prop('disabled', false);
            $submitBtn.find('.backupzen-btn-text').show();
            $submitBtn.find('.backupzen-btn-spinner').hide();
            
            // Remove any error messages
            $('.backupzen-error-message').remove();
        },

        /**
         * Handle keyboard events.
         */
        handleKeydown: function(e) {
            if (e.key === 'Escape' && $('#backupzen-feedback-modal').hasClass('active')) {
                this.closeModal();
            }
        },

        /**
         * Select feedback type.
         */
        selectType: function(e) {
            const $option = $(e.currentTarget);
            this.selectedType = $option.data('type');
            
            console.log('BackupZen Feedback: Type selected:', this.selectedType);

            // Add loading state to option
            $option.css('opacity', '0.6').prop('disabled', true);

            // Update step 2 title and subtitle
            const titles = {
                feedback: {
                    title: 'Rate Your Experience',
                    subtitle: 'How would you rate your experience with backupZen?'
                },
                idea: {
                    title: 'Share Your Idea',
                    subtitle: 'We\'d love to hear your feature suggestions!'
                },
                help: {
                    title: 'Get Help',
                    subtitle: 'Tell us how we can assist you.'
                }
            };

            const typeData = titles[this.selectedType];
            $('#backupzen-step2-title').text(typeData.title);
            $('#backupzen-step2-subtitle').text(typeData.subtitle);

            // Show appropriate form
            $('.backupzen-feedback-form').hide();
            if (this.selectedType === 'feedback') {
                $('#backupzen-feedback-form').show();
            } else if (this.selectedType === 'idea') {
                $('#backupzen-idea-form').show();
            } else if (this.selectedType === 'help') {
                $('#backupzen-help-form').show();
            }

            // Small delay for smooth transition
            setTimeout(() => {
                this.goToStep(2);
                $option.css('opacity', '1').prop('disabled', false);
            }, 200);
        },

        /**
         * Select star rating.
         */
        selectRating: function(e) {
            const rating = parseInt($(e.currentTarget).data('rating'), 10);
            this.selectedRating = rating;
            
            console.log('BackupZen Feedback: Rating selected:', rating);

            // Update star display
            $('.backupzen-star').removeClass('active');
            $('.backupzen-star').each(function(index) {
                if (index < rating) {
                    $(this).addClass('active');
                }
            });

            // Update hidden input
            $('#backupzen-rating-input').val(rating);
        },

        /**
         * Hover over star.
         */
        hoverStar: function(e) {
            const rating = parseInt($(e.currentTarget).data('rating'), 10);
            $('.backupzen-star').removeClass('hovered');
            $('.backupzen-star').each(function(index) {
                if (index < rating) {
                    $(this).addClass('hovered');
                }
            });
        },

        /**
         * Clear hover state.
         */
        clearHover: function() {
            $('.backupzen-star').removeClass('hovered');
        },

        /**
         * Go to previous step.
         */
        goToPreviousStep: function() {
            console.log('BackupZen Feedback: Going back from step', this.currentStep);
            if (this.currentStep === 2) {
                this.goToStep(1);
            }
        },

        /**
         * Navigate to a specific step.
         */
        goToStep: function(step) {
            console.log('BackupZen Feedback: Navigating to step', step);
            this.currentStep = step;

            // Hide all steps
            $('.backupzen-feedback-step').hide();

            // Show current step
            $(`.backupzen-feedback-step[data-step="${step}"]`).show();
        },

        /**
         * Validate step 2 form.
         */
        validateStep2: function() {
            console.log('BackupZen Feedback: Validating form for type:', this.selectedType);
            
            if (this.selectedType === 'feedback') {
                console.log('BackupZen Feedback: Validating feedback - rating:', this.selectedRating);
                if (this.selectedRating === 0) {
                    this.showError('Please provide a rating by clicking on the stars.');
                    return false;
                }
            } else if (this.selectedType === 'idea') {
                const title = $('#idea_title').val().trim();
                const description = $('#idea_description').val().trim();
                console.log('BackupZen Feedback: Validating idea - title:', title, 'description:', description);
                
                if (!title) {
                    this.showError('Please enter an idea title.');
                    $('#idea_title').focus();
                    return false;
                }
                if (!description) {
                    this.showError('Please enter an idea description.');
                    $('#idea_description').focus();
                    return false;
                }
            } else if (this.selectedType === 'help') {
                const message = $('#help_message').val().trim();
                console.log('BackupZen Feedback: Validating help - message:', message);
                
                if (!message) {
                    this.showError('Please enter a message.');
                    $('#help_message').focus();
                    return false;
                }
            }

            console.log('BackupZen Feedback: Validation passed!');
            return true;
        },

        /**
         * Collect data from step 2.
         */
        collectStep2Data: function() {
            console.log('BackupZen Feedback: Collecting data for type:', this.selectedType);
            
            this.formData = {}; // Reset form data
            
            if (this.selectedType === 'feedback') {
                this.formData.rating = this.selectedRating;
                this.formData.message = $('#feedback_message').val().trim();
            } else if (this.selectedType === 'idea') {
                this.formData.idea_title = $('#idea_title').val().trim();
                this.formData.idea_description = $('#idea_description').val().trim();
            } else if (this.selectedType === 'help') {
                this.formData.message = $('#help_message').val().trim();
            }
            
            console.log('BackupZen Feedback: Collected form data:', this.formData);
        },

        /**
         * Handle form submission.
         */
        handleSubmit: function(e) {
            console.log('BackupZen Feedback: ========== SUBMIT HANDLER CALLED ==========');
            
            // CRITICAL: Check if backupzenFeedback exists
            if (typeof backupzenFeedback === 'undefined') {
                console.error('BackupZen Feedback: FATAL - backupzenFeedback object not found!');
                this.showError('Configuration error. Please refresh the page and try again.');
                return;
            }
            
            console.log('BackupZen Feedback: Current type:', this.selectedType);
            console.log('BackupZen Feedback: Current step:', this.currentStep);
            console.log('BackupZen Feedback: AJAX URL:', backupzenFeedback.ajaxurl);
            console.log('BackupZen Feedback: Nonce:', backupzenFeedback.nonce);

            // Validate step 2 first
            if (!this.validateStep2()) {
                console.log('BackupZen Feedback: Validation failed');
                return;
            }

            console.log('BackupZen Feedback: Validation passed, collecting data');

            // Collect step 2 data
            this.collectStep2Data();

            const $submitBtn = $('.backupzen-btn-submit');
            const $btnText = $submitBtn.find('.backupzen-btn-text');
            const $btnSpinner = $submitBtn.find('.backupzen-btn-spinner');

            // Disable submit button and show loading
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnSpinner.show();

            console.log('BackupZen Feedback: Loading state activated');

            // Prepare AJAX data
            const ajaxData = {
                action: 'backupzen_feedback_submit',
                nonce: backupzenFeedback.nonce,
                type: this.selectedType,
                rating: this.formData.rating || 0,
                message: this.formData.message || '',
                idea_title: this.formData.idea_title || '',
                idea_description: this.formData.idea_description || ''
            };

            console.log('BackupZen Feedback: Sending AJAX request');
            console.log('BackupZen Feedback: URL:', backupzenFeedback.ajaxurl);
            console.log('BackupZen Feedback: Data:', ajaxData);

            // Submit via AJAX
            $.ajax({
                url: backupzenFeedback.ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    console.log('BackupZen Feedback: AJAX SUCCESS');
                    console.log('BackupZen Feedback: Response:', response);
                    
                    if (response.success) {
                        console.log('BackupZen Feedback: Server returned success!');
                        this.showSuccess(response.data.message);
                    } else {
                        console.log('BackupZen Feedback: Server returned error:', response.data.message);
                        this.showError(response.data.message || 'An error occurred. Please try again.');
                        
                        // Re-enable submit button on error
                        $submitBtn.prop('disabled', false);
                        $btnText.show();
                        $btnSpinner.hide();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('BackupZen Feedback: AJAX ERROR');
                    console.error('  - Status:', status);
                    console.error('  - Error:', error);
                    console.error('  - Response:', xhr.responseText);
                    console.error('  - Status Code:', xhr.status);
                    
                    this.showError('Network error. Please check your connection and try again.');
                    
                    // Re-enable submit button on error
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnSpinner.hide();
                }
            });
        },

        /**
         * Show success message.
         */
        showSuccess: function(message) {
            console.log('BackupZen Feedback: Showing success:', message);
            $('#backupzen-success-message').text(message);
            this.goToStep('success');

            // Reset submit button state
            const $submitBtn = $('.backupzen-btn-submit');
            $submitBtn.prop('disabled', false);
            $submitBtn.find('.backupzen-btn-text').show();
            $submitBtn.find('.backupzen-btn-spinner').hide();
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            console.log('BackupZen Feedback: Showing error:', message);
            
            // Remove any existing error messages
            $('.backupzen-error-message').remove();

            // Create error div
            const $errorDiv = $('<div class="backupzen-error-message">')
                .text(message)
                .css({
                    'background': '#fee',
                    'color': '#c33',
                    'padding': '12px 16px',
                    'border-radius': '8px',
                    'margin-bottom': '20px',
                    'border': '1px solid #fcc',
                    'font-size': '14px'
                });

            // Insert error at top of current step
            const $currentStep = $('.backupzen-feedback-step:visible');
            $currentStep.prepend($errorDiv);

            // Scroll to top of modal to show error
            $('.backupzen-feedback-modal-container').scrollTop(0);

            // Remove error after 8 seconds
            setTimeout(() => {
                $errorDiv.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 8000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('BackupZen Feedback: DOM ready, initializing widget');
        console.log('BackupZen Feedback: jQuery version:', $.fn.jquery);
        console.log('BackupZen Feedback: backupzenFeedback object exists:', typeof backupzenFeedback !== 'undefined');
        
        if (typeof backupzenFeedback !== 'undefined') {
            console.log('BackupZen Feedback: Configuration:', backupzenFeedback);
        } else {
            console.error('BackupZen Feedback: WARNING - backupzenFeedback object not found!');
        }
        
        FeedbackWidget.init();
    });

})(jQuery);