/**
 * BackupZen Admin JavaScript - FIXED VERSION
 * Handles both AJAX backups (BZEN) and traditional form submissions (ZIP, SQL, etc.)
 * 
 * @package BackupZen
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Verify jQuery is loaded
    if (typeof jQuery === 'undefined') {
        console.error('BackupZen: jQuery is not loaded!');
        return;
    }

    // Verify backupZen object exists
    if (typeof backupZen === 'undefined') {
        console.error('BackupZen: Configuration object not found!');
        return;
    }

    console.log('BackupZen: Script initialized', backupZen);

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        console.log('BackupZen: DOM ready, initializing...');
        
        initBackupForm();
        initRestoreModal();
        initBackupCards();
        initVisualFeedback();
        initDragDropRestore();
        initScheduledBackups();
        initCronDebugTools();
        initTimezoneClock();
    });

    /**
     * Initialize backup form with smart format detection
     */
    function initBackupForm() {
        var $form = $('#backupzen-create-form');
        
        if (!$form.length) {
            console.warn('BackupZen: Create form not found');
            return;
        }

        console.log('BackupZen: Backup form found, attaching handlers');

        // Intercept form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            var format = $('input[name="backup_format"]:checked').val();
            console.log('BackupZen: Form submitted with format:', format);

            // Validation
            var backupFiles = $('#backup_files').is(':checked');
            var backupDatabase = $('#backup_database').is(':checked');
            
            if (!backupFiles && !backupDatabase) {
                alert('Please select at least Files or Database to backup.');
                return;
            }

            // BZEN format = AJAX with progress modal
            if (format === 'bzen') {
                console.log('BackupZen: Using AJAX mode for BZEN format');
                handleBzenBackup();
            } else {
                // All other formats = traditional form submission
                console.log('BackupZen: Using traditional form submission for', format);
                handleTraditionalBackup($form, format);
            }
        });

        console.log('BackupZen: Form handler attached successfully');
    }

    /**
     * Handle BZEN backup with AJAX and progress tracking
     */
    function handleBzenBackup() {
        console.log('BackupZen: Starting BZEN backup process...');

        var backupFiles = $('#backup_files').is(':checked') ? 1 : 0;
        var backupDatabase = $('#backup_database').is(':checked') ? 1 : 0;

        // Validation
        if (!backupFiles && !backupDatabase) {
            alert('Please select at least Files or Database to backup.');
            return;
        }

        // Show progress modal
        showProgressModal();
        updateProgress('Initializing backup...', 0);

        // Create backup via AJAX
        $.ajax({
            url: backupZen.ajaxUrl,
            type: 'POST',
            data: {
                action: 'backupzen_create_backup',
                nonce: backupZen.nonce,
                backup_files: backupFiles,
                backup_database: backupDatabase,
                backup_format: 'bzen'
            },
            success: function(response) {
                console.log('BackupZen: Create backup response:', response);

                if (response.success && response.data.session_id) {
                    var sessionId = response.data.session_id;
                    var spawnUrl = response.data.spawn_url;
                    
                    console.log('BackupZen: Session created:', sessionId);
                    
                    // Spawn background process using hidden iframe
                    spawnBackgroundProcess(spawnUrl);
                    
                    // Start polling for progress
                    pollProgress(sessionId);
                } else {
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Failed to start backup.';
                    showError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('BackupZen: AJAX error:', error);
                showError('Failed to start backup: ' + error);
            }
        });
    }

    /**
     * Handle traditional form submission (ZIP, SQL, SQL.GZ formats)
     */
    function handleTraditionalBackup($form, format) {
        console.log('BackupZen: Submitting traditional backup form for format:', format);

        var backupFiles = $('#backup_files').is(':checked') ? 1 : 0;
        var backupDatabase = $('#backup_database').is(':checked') ? 1 : 0;

        // Validation
        if (!backupFiles && !backupDatabase) {
            alert('Please select at least Files or Database to backup.');
            return;
        }
        
        // SQL and SQL.GZ require database
        if ((format === 'sql' || format === 'sql.gz') && !backupDatabase) {
            alert('Database backup must be selected for ' + format.toUpperCase() + ' format.');
            return;
        }

        // Show loading indicator
        var $submitBtn = $form.find('button[type="submit"]');
        var originalHtml = $submitBtn.html();
        
        $submitBtn.prop('disabled', true);
        $submitBtn.html(
            '<span class="btn-icon"><span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span></span>' +
            '<span class="btn-text">Creating ' + format.toUpperCase() + ' backup...</span>'
        );
        
        // Add CSS for spinning animation if not already present
        if (!$('#backupzen-spin-style').length) {
            $('head').append(
                '<style id="backupzen-spin-style">' +
                '@keyframes spin { to { transform: rotate(360deg); } }' +
                '</style>'
            );
        }

        // Create a hidden input with name="backupzen_create" for the PHP handler
        if (!$form.find('input[name="backupzen_create"]').length) {
            $form.append('<input type="hidden" name="backupzen_create" value="1">');
        }

        // Allow the form to submit normally (this will trigger the PHP handler)
        $form.off('submit').submit();
    }

    /**
     * Spawn background process using hidden iframe
     */
    function spawnBackgroundProcess(url) {
        console.log('BackupZen: Spawning background process:', url);
        
        // Remove existing iframe if any
        $('#backupzen-spawn-iframe').remove();
        
        // Create hidden iframe
        var $iframe = $('<iframe>', {
            id: 'backupzen-spawn-iframe',
            src: url,
            style: 'display: none;'
        });
        
        $('body').append($iframe);
        
        console.log('BackupZen: Background process spawned');
    }

    /**
     * Poll for backup progress
     */
    function pollProgress(sessionId) {
        console.log('BackupZen: Starting progress polling for session:', sessionId);

        var pollCount = 0;
        var maxPolls = 600; // 10 minutes (600 * 1 second)
        var hasStarted = false;

        var interval = setInterval(function() {
            pollCount++;

            if (pollCount > maxPolls) {
                clearInterval(interval);
                showError('Backup process timed out. Please check your backup directory.');
                return;
            }

            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'backupzen_get_progress',
                    nonce: backupZen.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var progress = response.data;
                        
                        // Track if backup has actually started
                        if (progress.status === 'running' && !hasStarted) {
                            hasStarted = true;
                            console.log('BackupZen: Backup process has started');
                        }

                        console.log('BackupZen: Progress update:', progress.step, progress.progress + '%');

                        if (progress.status === 'completed') {
                            clearInterval(interval);
                            updateProgress(progress.step, 100);
                            
                            setTimeout(function() {
                                hideProgressModal();
                                showSuccessMessage('Backup created successfully!');
                                
                                // Reload page to show new backup
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            }, 1000);
                            
                        } else if (progress.status === 'error') {
                            clearInterval(interval);
                            showError(progress.step || 'An error occurred during backup creation.');
                            
                        } else {
                            // Update progress bar
                            updateProgress(progress.step, progress.progress);
                        }
                    } else {
                        console.log('BackupZen: No progress data yet (poll #' + pollCount + ')');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('BackupZen: Progress poll error:', error);
                    
                    // Don't stop polling on network errors, backup might still be running
                    if (pollCount > 30) { // After 30 seconds, show warning but keep polling
                        console.warn('BackupZen: Connection issues detected, but continuing to poll...');
                    }
                }
            });
        }, 1000); // Poll every 1 second
    }

    /**
     * Show progress modal
     */
    function showProgressModal() {
        var $modal = $('#backupzen-progress-modal');
        if (!$modal.length) {
            console.error('BackupZen: Progress modal not found!');
            return;
        }
        
        $modal.fadeIn(300);
        console.log('BackupZen: Progress modal shown');
    }

    /**
     * Hide progress modal
     */
    function hideProgressModal() {
        $('#backupzen-progress-modal').fadeOut(300);
    }

    /**
     * Update progress bar
     */
    function updateProgress(message, percent) {
        $('.backupzen-progress-text').text(message);
        $('.backupzen-progress-percent').text(percent + '%');
        $('.backupzen-progress-bar').css('width', percent + '%');
    }

    /**
     * Show error in progress modal
     */
    function showError(message) {
        console.error('BackupZen: Error -', message);
        
        $('.backupzen-progress-text').text(message);
        $('.backupzen-progress-bar').addClass('error');
        $('.backupzen-progress-percent').addClass('error').text('Error');
        
        // Add close button
        if (!$('.backupzen-modal-body .button').length) {
            $('.backupzen-modal-body').append(
                '<p style="margin-top: 20px; text-align: center;">' +
                '<button type="button" class="button button-primary" onclick="location.reload()">Close</button>' +
                '</p>'
            );
        }
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        var notice = $('<div class="notice notice-success is-dismissible" style="display: none;">' +
            '<p><strong>' + message + '</strong></p>' +
            '</div>');
        
        $('.wrap h1').after(notice);
        notice.slideDown(300);
    }

    /**
     * Initialize restore modal
     */
    function initRestoreModal() {
        var $modal = $('#backupzen-restore-modal');
        
        if (!$modal.length) {
            console.warn('BackupZen: Restore modal not found');
            return;
        }

        // Open modal when restore button clicked
        $(document).on('click', '.backupzen-restore-btn', function(e) {
            e.preventDefault();
            
            var filename = $(this).data('filename');
            console.log('BackupZen: Opening restore modal for:', filename);
            
            $('#restore-filename').text(filename);
            $modal.find('input[name="file"]').val(filename);
            $modal.fadeIn(300);
        });

        // Close modal
        $('.backupzen-modal-close').on('click', function() {
            $modal.fadeOut(300);
            resetRestoreModal();
        });

        // Close on overlay click
        $('.backupzen-modal-overlay').on('click', function() {
            $modal.fadeOut(300);
            resetRestoreModal();
        });

        // Confirmation input validation
        $('#restore-confirm-input').on('input', function() {
            var value = $(this).val();
            var $submitBtn = $('#restore-submit-btn');
            
            if (value === 'RESTORE') {
                $submitBtn.prop('disabled', false);
            } else {
                $submitBtn.prop('disabled', true);
            }
        });

        // Handle restore form submission with AJAX
        $modal.find('form').on('submit', function(e) {
            e.preventDefault();
            
            var filename = $(this).find('input[name="file"]').val();
            console.log('BackupZen: Starting restore for:', filename);
            
            // Close confirmation modal
            $modal.fadeOut(300);
            
            // Start restore process
            handleRestoreProcess(filename);
        });
    }

    /**
     * Handle restore process with progress tracking
     */
    function handleRestoreProcess(filename) {
        console.log('BackupZen: Initiating restore for:', filename);

        // Show restore progress modal
        showRestoreProgressModal();
        updateRestoreProgress('Preparing restore...', 0);

        // Start restore via AJAX
        $.ajax({
            url: backupZen.ajaxUrl,
            type: 'POST',
            data: {
                action: 'backupzen_restore_backup',
                nonce: backupZen.nonce,
                filename: filename
            },
            success: function(response) {
                console.log('BackupZen: Restore response:', response);

                if (response.success && response.data.session_id) {
                    var sessionId = response.data.session_id;
                    var spawnUrl = response.data.spawn_url;
                    
                    console.log('BackupZen: Restore session created:', sessionId);
                    
                    // Spawn background restore process
                    spawnBackgroundProcess(spawnUrl);
                    
                    // Start polling for restore progress
                    pollRestoreProgress(sessionId);
                } else {
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Failed to start restore.';
                    showRestoreError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('BackupZen: Restore AJAX error:', error);
                showRestoreError('Failed to start restore: ' + error);
            }
        });
    }

    /**
     * Poll for restore progress
     */
    function pollRestoreProgress(sessionId) {
        console.log('BackupZen: Starting restore progress polling for session:', sessionId);

        var pollCount = 0;
        var maxPolls = 600; // 10 minutes

        var interval = setInterval(function() {
            pollCount++;

            if (pollCount > maxPolls) {
                clearInterval(interval);
                showRestoreError('Restore process timed out.');
                return;
            }

            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'backupzen_get_restore_progress',
                    nonce: backupZen.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var progress = response.data;
                        
                        console.log('BackupZen: Restore progress:', progress.step, progress.progress + '%');

                        if (progress.status === 'completed') {
                            clearInterval(interval);
                            updateRestoreProgress(progress.step, 100);
                            
                            setTimeout(function() {
                                hideRestoreProgressModal();
                                showSuccessMessage('Site restored successfully!');
                                
                                // Reload page to show restored state
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            }, 1000);
                            
                        } else if (progress.status === 'error') {
                            clearInterval(interval);
                            showRestoreError(progress.step || 'An error occurred during restore.');
                            
                        } else {
                            // Update progress bar
                            updateRestoreProgress(progress.step, progress.progress);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('BackupZen: Restore progress poll error:', error);
                }
            });
        }, 1000);
    }

    /**
     * Show restore progress modal
     */
    function showRestoreProgressModal() {
        var modalHtml = 
            '<div id="backupzen-restore-progress-modal" class="backupzen-modal">' +
            '<div class="backupzen-modal-overlay"></div>' +
            '<div class="backupzen-modal-content">' +
            '<div class="backupzen-modal-header">' +
            '<h2>Restoring Backup...</h2>' +
            '</div>' +
            '<div class="backupzen-modal-body">' +
            '<div class="backupzen-progress-container">' +
            '<div class="backupzen-progress-bar-wrapper">' +
            '<div class="backupzen-restore-progress-bar" style="width: 0%;"></div>' +
            '</div>' +
            '<div class="backupzen-progress-info">' +
            '<span class="backupzen-restore-progress-text">Initializing...</span>' +
            '<span class="backupzen-restore-progress-percent">0%</span>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        // Remove existing modal if present
        $('#backupzen-restore-progress-modal').remove();
        
        // Add new modal
        $('body').append(modalHtml);
        $('#backupzen-restore-progress-modal').fadeIn(300);
    }

    /**
     * Hide restore progress modal
     */
    function hideRestoreProgressModal() {
        $('#backupzen-restore-progress-modal').fadeOut(300, function() {
            $(this).remove();
        });
    }

    /**
     * Update restore progress bar
     */
    function updateRestoreProgress(message, percent) {
        $('.backupzen-restore-progress-text').text(message);
        $('.backupzen-restore-progress-percent').text(percent + '%');
        $('.backupzen-restore-progress-bar').css('width', percent + '%');
    }

    /**
     * Show restore error with better UI
     */
    function showRestoreError(message) {
        console.error('BackupZen: Restore error -', message);
        
        $('.backupzen-restore-progress-text').text(message);
        $('.backupzen-restore-progress-bar').addClass('error').css('width', '100%');
        $('.backupzen-restore-progress-percent').addClass('error').text('Failed');
        
        if (!$('#backupzen-restore-progress-modal .error-actions').length) {
            $('#backupzen-restore-progress-modal .backupzen-modal-body').append(
                '<div class="error-actions" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">' +
                '<p style="margin: 0 0 15px 0; color: #64748b; font-size: 13px; text-align: center;">The restore operation encountered an error. Your site has not been modified.</p>' +
                '<div style="display: flex; gap: 10px; justify-content: center;">' +
                '<button type="button" class="button restore-error-retry" style="background: #3b82f6; color: white; border: none;"><span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Try Again</button>' +
                '<button type="button" class="button button-primary restore-error-close"><span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span> Close</button>' +
                '</div></div>'
            );
            
            $('.restore-error-close').on('click', function() {
                hideRestoreProgressModal();
            });
            
            $('.restore-error-retry').on('click', function() {
                hideRestoreProgressModal();
                setTimeout(function() { window.location.reload(); }, 300);
            });
        }
    }

    /**
     * Reset restore modal
     */
    function resetRestoreModal() {
        $('#restore-confirm-input').val('');
        $('#restore-submit-btn').prop('disabled', true);
    }

    /**
     * Initialize backup cards functionality
     */
    function initBackupCards() {
        // Toggle details section
        $(document).on('click', '.details-toggle-link', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var targetId = $link.data('target');
            var $target = $('#' + targetId);
            
            if ($target.is(':visible')) {
                $target.slideUp(300);
                $link.removeClass('active');
                $link.find('span:last').text('More Details');
            } else {
                $target.slideDown(300);
                $link.addClass('active');
                $link.find('span:last').text('Hide Details');
            }
        });

        // Delete button handler
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var filename = $btn.data('filename');
            
            if (!filename) {
                alert('Error: No filename specified.');
                return;
            }

            var confirmMessage = 'Are you sure you want to delete this backup?\n\n' +
                                'File: ' + filename + '\n\n' +
                                'This action cannot be undone!';
            
            if (!confirm(confirmMessage)) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update-alt"></span> Deleting...');
            $btn.css('opacity', '0.6');

            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'backupzen_delete_backup',
                    nonce: backupZen.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        var $card = $btn.closest('.backupzen-card');
                        
                        $card.css({
                            'transform': 'scale(0.9)',
                            'opacity': '0',
                            'transition': 'all 0.3s ease'
                        });
                        
                        setTimeout(function() {
                            $card.slideUp(300, function() {
                                $card.remove();
                                
                                if ($('.backupzen-cards-grid .backupzen-card').length === 0) {
                                    showEmptyState();
                                }
                                
                                updateBackupStats();
                            });
                        }, 300);
                        
                        showNotice('success', 'Backup deleted successfully!');
                    } else {
                        var errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to delete backup.';
                        showNotice('error', errorMsg);
                        
                        $btn.prop('disabled', false);
                        $btn.html('<span class="dashicons dashicons-trash"></span> Delete');
                        $btn.css('opacity', '1');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    showNotice('error', 'Failed to delete backup: ' + error);
                    
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-trash"></span> Delete');
                    $btn.css('opacity', '1');
                }
            });
        });
    }

    /**
     * Show empty state when no backups exist
     */
    function showEmptyState() {
        var emptyHTML = 
            '<div class="backupzen-empty-state" style="opacity: 0;">' +
            '<div class="empty-state-icon">' +
            '<span class="dashicons dashicons-cloud"></span>' +
            '</div>' +
            '<h3>No Backups Available</h3>' +
            '<p class="description">' +
            'All backups have been deleted. Create a new backup above to get started.' +
            '</p>' +
            '</div>';
        
        $('.backupzen-cards-grid').replaceWith(emptyHTML);
        $('.backupzen-empty-state').animate({ opacity: 1 }, 500);
    }

    /**
     * Update backup statistics in header
     */
    function updateBackupStats() {
        var count = $('.backupzen-cards-grid .backupzen-card').length;
        var label = count === 1 ? 'Backup' : 'Backups';
        
        $('.backupzen-cards-stats .stat-badge:first').html(
            '<span class="dashicons dashicons-backup"></span>' + count + ' ' + label
        );
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="display: none;">' +
            '<p><strong>' + message + '</strong></p>' +
            '</div>');
        
        $('.wrap h1').after(notice);
        notice.slideDown(300);
        
        setTimeout(function() {
            notice.slideUp(300, function() {
                notice.remove();
            });
        }, 5000);
    }

    /**
     * Initialize drag & drop file upload for restore
     */
    function initDragDropRestore() {
        var dropZone = $('#restore-drop-zone');
        var fileInput = $('#restore_file');
        var uploadPlaceholder = $('#upload-placeholder');
        var fileSelected = $('#file-selected');
        var removeFileBtn = $('#remove-file');
        var restoreSubmitBtn = $('#btn-restore-submit');
        var restoreForm = $('#backupzen-restore-form');

        if (!dropZone.length) {
            console.log('BackupZen: Restore drop zone not found');
            return;
        }

        // Make drop zone clickable
        dropZone.on('click', function(e) {
            if (!$(e.target).closest('#file-selected').length) {
                fileInput.click();
            }
        });

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
            dropZone.on(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Highlight drop zone when dragging over it
        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropZone.on(eventName, function() {
                dropZone.addClass('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            dropZone.on(eventName, function() {
                dropZone.removeClass('drag-over');
            });
        });

        // Handle dropped files
        dropZone.on('drop', function(e) {
            var dt = e.originalEvent.dataTransfer;
            var files = dt.files;

            if (files.length > 0) {
                fileInput[0].files = files;
                handleFileSelect(files[0]);
            }
        });

        // Handle file selection via input
        fileInput.on('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });

        // Remove file
        removeFileBtn.on('click', function(e) {
            e.stopPropagation();
            fileInput.val('');
            uploadPlaceholder.show();
            fileSelected.hide();
            restoreSubmitBtn.prop('disabled', true);
        });

        // Handle form submission
        restoreForm.on('submit', function(e) {
            e.preventDefault();
            
            var file = fileInput[0].files[0];
            if (!file) {
                alert('Please select a backup file to restore.');
                return;
            }

            var fileName = file.name;
            var fileExt = fileName.split('.').pop().toLowerCase();
            
            // Check if it's a BZEN file
            if (fileExt === 'bzen') {
                handleUploadedBzenRestore(file);
            } else {
                // For non-BZEN files, show "coming soon" modal
                showComingSoonModal(fileExt.toUpperCase());
            }
        });

        function handleFileSelect(file) {
            var fileName = file.name;
            var fileSize = formatFileSize(file.size);
            var fileExt = fileName.split('.').pop().toUpperCase();

            // Validate file type
            var allowedTypes = ['zip', 'sql', 'gz', 'bzen'];
            var ext = fileName.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(ext)) {
                alert('Invalid file type. Please upload a ZIP, SQL, SQL.GZ, or BZEN file.');
                fileInput.val('');
                return;
            }

            // Update UI
            $('#selected-file-name').text(fileName);
            $('#selected-file-size').text(fileSize);
            $('#selected-file-type').text(fileExt);
            
            uploadPlaceholder.hide();
            fileSelected.show();
            restoreSubmitBtn.prop('disabled', false);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function handleUploadedBzenRestore(file) {
            console.log('BackupZen: Uploading and restoring BZEN file:', file.name);

            showUploadProgressModal();
            updateUploadProgress('Uploading backup file...', 0);

            var formData = new FormData();
            formData.append('action', 'backupzen_upload_and_restore');
            formData.append('nonce', backupZen.nonce);
            formData.append('backup_file', file);

            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentComplete = Math.round((e.loaded / e.total) * 100);
                            updateUploadProgress('Uploading: ' + percentComplete + '%', percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    console.log('BackupZen: Upload response:', response);

                    if (response.success && response.data.session_id) {
                        updateUploadProgress('Upload complete, starting restore...', 100);
                        
                        setTimeout(function() {
                            hideUploadProgressModal();
                            
                            var sessionId = response.data.session_id;
                            var spawnUrl = response.data.spawn_url;
                            
                            showRestoreProgressModal();
                            updateRestoreProgress('Preparing restore...', 0);
                            
                            spawnBackgroundProcess(spawnUrl);
                            pollRestoreProgress(sessionId);
                        }, 500);
                    } else {
                        var errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to upload file.';
                        showUploadError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('BackupZen: Upload error:', error);
                    showUploadError('Failed to upload file: ' + error);
                }
            });
        }

        function showUploadProgressModal() {
            var modalHtml = 
                '<div id="backupzen-upload-progress-modal" class="backupzen-modal">' +
                '<div class="backupzen-modal-overlay"></div>' +
                '<div class="backupzen-modal-content">' +
                '<div class="backupzen-modal-header">' +
                '<h2>Uploading Backup File...</h2>' +
                '</div>' +
                '<div class="backupzen-modal-body">' +
                '<div class="backupzen-progress-container">' +
                '<div class="backupzen-progress-bar-wrapper">' +
                '<div class="backupzen-upload-progress-bar" style="width: 0%;"></div>' +
                '</div>' +
                '<div class="backupzen-progress-info">' +
                '<span class="backupzen-upload-progress-text">Initializing...</span>' +
                '<span class="backupzen-upload-progress-percent">0%</span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('#backupzen-upload-progress-modal').remove();
            $('body').append(modalHtml);
            $('#backupzen-upload-progress-modal').fadeIn(300);
        }

        function hideUploadProgressModal() {
            $('#backupzen-upload-progress-modal').fadeOut(300, function() {
                $(this).remove();
            });
        }

        function updateUploadProgress(message, percent) {
            $('.backupzen-upload-progress-text').text(message);
            $('.backupzen-upload-progress-percent').text(percent + '%');
            $('.backupzen-upload-progress-bar').css('width', percent + '%');
        }

        function showUploadError(message) {
            $('.backupzen-upload-progress-text').text(message);
            $('.backupzen-upload-progress-bar').addClass('error');
            $('.backupzen-upload-progress-percent').addClass('error').text('Error');
            
            if (!$('#backupzen-upload-progress-modal .backupzen-modal-body .button').length) {
                $('#backupzen-upload-progress-modal .backupzen-modal-body').append(
                    '<p style="margin-top: 20px; text-align: center;">' +
                    '<button type="button" class="button button-primary" onclick="jQuery(\'#backupzen-upload-progress-modal\').fadeOut(300, function() { jQuery(this).remove(); })">Close</button>' +
                    '</p>'
                );
            }
        }

        function showComingSoonModal(format) {
            var modalHtml = 
                '<div id="backupzen-coming-soon-modal" class="backupzen-modal">' +
                '<div class="backupzen-modal-overlay"></div>' +
                '<div class="backupzen-modal-content" style="max-width: 500px;">' +
                '<div class="backupzen-modal-header">' +
                '<h2>Feature Coming Soon</h2>' +
                '<button type="button" class="backupzen-modal-close" onclick="jQuery(\'#backupzen-coming-soon-modal\').fadeOut(300, function() { jQuery(this).remove(); })">&times;</button>' +
                '</div>' +
                '<div class="backupzen-modal-body" style="text-align: center; padding: 40px 30px;">' +
                '<div style="font-size: 64px; color: #3b82f6; margin-bottom: 20px;">' +
                '<span class="dashicons dashicons-info"></span>' +
                '</div>' +
                '<h3 style="margin: 0 0 15px 0; font-size: 20px;">Restore from ' + format + ' Files</h3>' +
                '<p style="color: #64748b; margin-bottom: 25px;">' +
                'Restore functionality for ' + format + ' format is currently under development and will be available in a future update.' +
                '</p>' +
                '<p style="color: #64748b; margin-bottom: 30px;">' +
                'For now, please use <strong>BZEN format</strong> for full backup and restore capabilities with real-time progress tracking.' +
                '</p>' +
                '<button type="button" class="button button-primary" onclick="jQuery(\'#backupzen-coming-soon-modal\').fadeOut(300, function() { jQuery(this).remove(); })" style="padding: 10px 30px;">' +
                'Got it' +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('#backupzen-coming-soon-modal').remove();
            $('body').append(modalHtml);
            $('#backupzen-coming-soon-modal').fadeIn(300);
            
            $('#backupzen-coming-soon-modal .backupzen-modal-overlay').on('click', function() {
                $('#backupzen-coming-soon-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    }

    /**
     * Initialize visual feedback for option cards
     */
    function initVisualFeedback() {
        $('.backup-option-card, .format-option-card').on('click', function() {
            var $input = $(this).find('input');
            
            if ($input.attr('type') === 'checkbox') {
                $input.prop('checked', !$input.prop('checked'));
            } else if ($input.attr('type') === 'radio') {
                $input.prop('checked', true);
            }
            
            $input.trigger('change');
        });
    
        $('.format-option-card input[type="radio"]').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Initialize scheduled backups functionality
     */
    function initScheduledBackups() {
        if (!$('#backupzen-schedule-form').length) {
            return;
        }

        // Quick toggle handler
        $('#quick-enable-toggle').on('change', function() {
            var enabled = $(this).is(':checked');
            
            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'backupzen_quick_toggle_schedule',
                    nonce: backupZen.nonce,
                    enabled: enabled ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        $('#schedule_enabled').prop('checked', enabled);
                        
                        $('.schedule-status-card')
                            .removeClass('status-active status-inactive')
                            .addClass(enabled ? 'status-active' : 'status-inactive');
                        
                        showScheduleNotice('success', response.data.message);
                        
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $('#quick-enable-toggle').prop('checked', !enabled);
                        showScheduleNotice('error', response.data.message || 'Failed to update schedule');
                    }
                },
                error: function() {
                    $('#quick-enable-toggle').prop('checked', !enabled);
                    showScheduleNotice('error', 'Failed to update schedule. Please try again.');
                }
            });
        });
        
        // Test backup button handler
        $('#btn-test-backup').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var originalHtml = $btn.html();
            
            if (!confirm('Run a test backup now? This will create a backup using your current schedule settings.')) {
                return;
            }
            
            $btn.prop('disabled', true);
            $btn.html(
                '<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span>' +
                '<span>Running backup...</span>'
            );
            
            $.ajax({
                url: backupZen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'backupzen_test_scheduled_backup',
                    nonce: backupZen.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showScheduleNotice('success', response.data.message);
                        
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showScheduleNotice('error', response.data.message || 'Test backup failed');
                        $btn.prop('disabled', false);
                        $btn.html(originalHtml);
                    }
                },
                error: function() {
                    showScheduleNotice('error', 'Test backup failed. Please try again.');
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                }
            });
        });
        
        $('#schedule_enabled').on('change', function() {
            var enabled = $(this).is(':checked');
            $('#quick-enable-toggle').prop('checked', enabled);
        });
        
        $('#backupzen-schedule-form').on('submit', function(e) {
            var filesChecked = $('input[name="schedule_files"]').is(':checked');
            var databaseChecked = $('input[name="schedule_database"]').is(':checked');
            
            if (!filesChecked && !databaseChecked) {
                e.preventDefault();
                alert('Please select at least Files or Database to backup.');
                return false;
            }
            
            var emailEnabled = $('input[name="email_notifications"]').is(':checked');
            if (emailEnabled) {
                var email = $('input[name="email_address"]').val();
                if (!email || !isValidEmail(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address for notifications.');
                    $('input[name="email_address"]').focus();
                    return false;
                }
            }
            
            return true;
        });
        
        $('#email_notifications').on('change', function() {
            var enabled = $(this).is(':checked');
            $('#email_address').prop('disabled', !enabled);
            $('input[name="email_on_failure"]').prop('disabled', !enabled);
        }).trigger('change');
        
        $('#auto_cleanup').on('change', function() {
            var enabled = $(this).is(':checked');
            $('#cleanup_keep').prop('disabled', !enabled);
        }).trigger('change');
    }

    /**
     * Show schedule notice
     */
    function showScheduleNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="display: none;">' +
            '<p><strong>' + message + '</strong></p>' +
            '</div>');
        
        $('.wrap h1').after(notice);
        notice.slideDown(300);
        
        setTimeout(function() {
            notice.slideUp(300, function() {
                notice.remove();
            });
        }, 5000);
    }
    
    /**
     * Validate email address
     */
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Initialize cron debug tools
     */
    function initCronDebugTools() {
        $('#btn-check-cron-status').on('click', function() {
            var $btn = $(this);
            var originalHtml = $btn.html();
            
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Checking...');
            
            $.post(backupZen.ajaxUrl, {
                action: 'backupzen_check_cron_status',
                nonce: backupZen.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                
                if (response.success) {
                    alert('Cron Status:\n\n' + response.data.message);
                    
                    if (response.data.should_reload) {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            });
        });
        
        $('#btn-view-debug-log').on('click', function() {
            $('#debug-log-content').html('Loading debug log...');
            $('#debug-log-modal').fadeIn(200);
            
            $.post(backupZen.ajaxUrl, {
                action: 'backupzen_get_debug_log',
                nonce: backupZen.nonce
            }, function(response) {
                if (response.success) {
                    $('#debug-log-content').html(response.data.log || 'No recent BackupZen log entries found.');
                } else {
                    $('#debug-log-content').html('Error loading log: ' + (response.data.message || 'Unknown error'));
                }
            });
        });
        
        $('#close-debug-log, #debug-log-modal').on('click', function(e) {
            if (e.target === this) {
                $('#debug-log-modal').fadeOut(200);
            }
        });
    }

    /**
     * Initialize timezone clock functionality
     */
    function initTimezoneClock() {
        if (!$('#local-time').length) {
            return;
        }

        // Get server timestamp from PHP (needs to be passed via localized script)
        var serverTimestamp = backupZen.serverTimestamp || Math.floor(Date.now() / 1000);
        var pageLoadTime = new Date();

        function updateLocalTime() {
            var now = new Date();
            var hours = String(now.getHours()).padStart(2, '0');
            var minutes = String(now.getMinutes()).padStart(2, '0');
            var seconds = String(now.getSeconds()).padStart(2, '0');
            $('#local-time').text(hours + ':' + minutes + ':' + seconds);
        }
        
        function updateServerTime() {
            var elapsed = (new Date() - pageLoadTime) / 1000;
            var serverTime = new Date((serverTimestamp + elapsed) * 1000);
            
            var hours = String(serverTime.getHours()).padStart(2, '0');
            var minutes = String(serverTime.getMinutes()).padStart(2, '0');
            var seconds = String(serverTime.getSeconds()).padStart(2, '0');
            $('#server-time').text(hours + ':' + minutes + ':' + seconds);
            
            var localTime = new Date();
            var diffMs = Math.abs(serverTime - localTime);
            var diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            var diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            var diffText = diffHours + 'h ' + diffMinutes + 'm';
            if (diffHours === 0 && diffMinutes === 0) {
                diffText = 'Same time';
                $('#time-diff').css('color', '#10b981');
            } else {
                $('#time-diff').css('color', '#dc2626');
            }
            $('#time-diff').text(diffText);
        }
        
        updateLocalTime();
        updateServerTime();
        setInterval(function() {
            updateLocalTime();
            updateServerTime();
        }, 1000);

        // Quick time button handlers
        $('.quick-time-btn').on('click', function(e) {
            e.preventDefault();
            var time = $(this).data('time');
            $('#schedule_time').val(time);
            
            $('.quick-time-btn').css('background', '');
            $(this).css('background', '#dbeafe');
            
            $(this).append('<span class="dashicons dashicons-yes" style="color: #10b981; margin-left: 4px;"></span>');
            setTimeout(function() {
                $('.quick-time-btn .dashicons-yes').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 1500);
        });
    }

})(jQuery);