/**
 * BackupZen - Scheduled Backups JavaScript
 * Clean AJAX implementation
 * 
 * @package BackupZen
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    const BackupZenSchedule = {
        
        init: function() {
            console.log('BackupZen Schedule: Initializing...');
            
            this.bindEvents();
            this.updateCurrentTime();
            this.highlightCurrentTime();
        },
        
        bindEvents: function() {
            // Quick time buttons
            $(document).on('click', '.quick-time-btn', this.handleQuickTimeClick);
            
            // Time input change
            $('#schedule_time').on('change', this.handleTimeChange);
            
            // Save settings button
            $('#btn-save-schedule').on('click', this.handleSaveSchedule);
            
            // Quick toggle
            $('#quick-enable-toggle').on('change', this.handleQuickToggle);
            
            // Test email
            $('#btn-test-email').on('click', this.handleTestEmail);
            
            // Test backup
            $('#btn-test-backup').on('click', this.handleTestBackup);
            
            // Advanced toggle
            $('#advanced-toggle').on('click', this.handleAdvancedToggle);
        },
        
        handleQuickTimeClick: function(e) {
            e.preventDefault();
            const time = $(this).data('time');
            
            $('#schedule_time').val(time).trigger('change');
            
            $('.quick-time-btn').removeClass('selected');
            $(this).addClass('selected');
            
            console.log('Quick time selected:', time);
        },
        
        handleTimeChange: function() {
            const time = $(this).val();
            const time12hr = BackupZenSchedule.convertTo12Hour(time);
            
            $('#current-time-display').text(time12hr);
            
            // Highlight matching button
            $('.quick-time-btn').removeClass('selected');
            $('.quick-time-btn').each(function() {
                if ($(this).data('time') === time) {
                    $(this).addClass('selected');
                }
            });
            
            console.log('Time changed to:', time, '(' + time12hr + ')');
        },
        
        handleSaveSchedule: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Validate
            const time = $('#schedule_time').val();
            if (!time) {
                alert('Please select a backup time.');
                return;
            }
            
            // Collect all data
            const data = {
                action: 'backupzen_save_schedule',
                nonce: backupzenData.nonce,
                enabled: $('#schedule_enabled').is(':checked'),
                frequency: $('#schedule_frequency').val(),
                time: time,
                email_enabled: $('#email_notifications').is(':checked'),
                email_address: $('#email_address').val(),
                email_on_failure: $('#email_on_failure').is(':checked'),
                auto_cleanup: $('#auto_cleanup').is(':checked'),
                cleanup_keep: $('#cleanup_keep').val(),
                schedule_format: $('#schedule_format').val(),
                schedule_files: $('input[name="schedule_files"]').is(':checked'),
                schedule_database: $('input[name="schedule_database"]').is(':checked'),
            };
            
            console.log('Saving schedule with data:', data);
            
            // Show loading
            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update-alt spin"></span> Saving...'
            );
            
            // Send AJAX request
            $.ajax({
                url: backupzenData.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('Save response:', response);
                    
                    if (response.success) {
                        BackupZenSchedule.showNotice(response.data.message, 'success');
                        
                        // Update display
                        if (response.data.time_12hr) {
                            $('#current-time-display').text(response.data.time_12hr);
                        }
                        
                        // Reload after 1 second
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        BackupZenSchedule.showNotice(response.data.message || 'Failed to save settings', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', status, error);
                    BackupZenSchedule.showNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        handleQuickToggle: function() {
            const enabled = $(this).is(':checked');
            const $statusCard = $('.backupzen-status-card');
            
            if (enabled) {
                $statusCard.removeClass('status-inactive').addClass('status-active');
            } else {
                $statusCard.removeClass('status-active').addClass('status-inactive');
            }
            
            // Sync with main checkbox
            $('#schedule_enabled').prop('checked', enabled);
            
            // Send AJAX
            $.ajax({
                url: backupzenData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'backupzen_toggle_schedule',
                    nonce: backupzenData.nonce,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        BackupZenSchedule.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                }
            });
        },
        
        handleTestEmail: function(e) {
            e.preventDefault();
            
            const email = $('#email_address').val();
            if (!email || !BackupZenSchedule.isValidEmail(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sending...');
            
            $.ajax({
                url: backupzenData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'backupzen_test_email',
                    nonce: backupzenData.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        BackupZenSchedule.showNotice(response.data.message, 'success');
                    } else {
                        BackupZenSchedule.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        handleTestBackup: function(e) {
            e.preventDefault();
            
            if (!confirm('Run a test backup now?')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Running...');
            
            $.ajax({
                url: backupzenData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'backupzen_test_backup',
                    nonce: backupzenData.nonce
                },
                timeout: 300000,
                success: function(response) {
                    if (response.success) {
                        BackupZenSchedule.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        BackupZenSchedule.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        handleAdvancedToggle: function(e) {
            e.preventDefault();
            
            const $content = $('#advanced-content');
            const $arrow = $(this).find('.toggle-arrow');
            
            if ($content.is(':visible')) {
                $content.slideUp(300);
                $(this).removeClass('active');
                $arrow.removeClass('rotated');
            } else {
                $content.slideDown(300);
                $(this).addClass('active');
                $arrow.addClass('rotated');
            }
        },
        
        updateCurrentTime: function() {
            function update() {
                const now = new Date();
                const hours = now.getHours();
                const minutes = now.getMinutes();
                const seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                
                const displayHours = hours % 12 || 12;
                const timeString = displayHours + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0') + ' ' + ampm;
                
                $('#current-local-time').text(timeString);
            }
            
            update();
            setInterval(update, 1000);
        },
        
        highlightCurrentTime: function() {
            const currentTime = $('#schedule_time').val();
            if (currentTime) {
                const time12hr = this.convertTo12Hour(currentTime);
                $('#current-time-display').text(time12hr);
                
                $('.quick-time-btn').each(function() {
                    if ($(this).data('time') === currentTime) {
                        $(this).addClass('selected');
                    }
                });
            }
        },
        
        convertTo12Hour: function(time24) {
            if (!time24) return '';
            
            const parts = time24.split(':');
            if (parts.length < 2) return time24;
            
            const hours = parseInt(parts[0], 10);
            const minutes = parts[1];
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            const displayHours = hours % 12 || 12;
            
            return displayHours + ':' + minutes + ' ' + ampm;
        },
        
        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            const noticeClass = 'notice notice-' + type + ' is-dismissible';
            const $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
            
            $('.wrap .notice').remove();
            $('.backupzen-page-title').after($notice);
            
            $('html, body').animate({ scrollTop: 0 }, 300);
            
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        BackupZenSchedule.init();
    });
    
})(jQuery);

