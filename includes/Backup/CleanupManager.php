<?php
/**
 * Cleanup Manager Class
 * Handles automatic cleanup of old backups
 * 
 * @package BackupZen
 * @since 1.1.0
 */

namespace BackupZen\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class CleanupManager
{
    private $backup_dir;
    
    /**
     * Constructor
     * 
     * @param string $backup_dir Path to backup directory
     */
    public function __construct($backup_dir)
    {
        $this->backup_dir = $backup_dir;
    }
    
    /**
     * Run auto-cleanup
     * Deletes old backups keeping only the most recent X
     * 
     * @return array Result with deleted count
     */
    public function run_cleanup()
    {
        $this->log('Starting auto-cleanup...');
        
        // Check if cleanup is enabled
        $auto_cleanup = get_option('backupzen_auto_cleanup', false);
        if (!$auto_cleanup) {
            $this->log('Auto-cleanup disabled');
            return array(
                'success' => true,
                'deleted' => 0,
                'message' => __('Auto-cleanup is disabled', 'backupzen'),
            );
        }
        
        // Get settings
        $cleanup_keep = get_option('backupzen_cleanup_keep', 5);
        $this->log('Keep last ' . $cleanup_keep . ' backups');
        
        // Get all backups sorted by date (newest first)
        $scanner = new BackupScanner($this->backup_dir);
        $backups = $scanner->get_backups();
        
        $this->log('Found ' . count($backups) . ' total backups');
        
        // Check if we need to delete any
        if (count($backups) <= $cleanup_keep) {
            $this->log('Not enough backups to clean up');
            return array(
                'success' => true,
                'deleted' => 0,
                /* translators: 1: Number of backups, 2: Backup limit */
                'message' => sprintf(__('Keeping all %1$d backups (limit: %2$d)', 'backupzen'), count($backups), $cleanup_keep),
            );
        }
        
        // Get backups to delete (oldest ones beyond the limit)
        $backups_to_delete = array_slice($backups, $cleanup_keep);
        $deleted_count = 0;
        $failed_count = 0;
        
        foreach ($backups_to_delete as $backup) {
            if ($this->delete_backup($backup)) {
                $deleted_count++;
                $this->log('Deleted: ' . $backup['filename']);
            } else {
                $failed_count++;
                $this->log('Failed to delete: ' . $backup['filename']);
            }
        }
        
        /* translators: 1: Number of backups deleted, 2: Number of backups kept */
        $message = sprintf(
            __('Deleted %1$d old backup(s), kept %2$d most recent', 'backupzen'),
            $deleted_count,
            $cleanup_keep
        );
        
        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('(%d failed)', 'backupzen'), $failed_count);
        }
        
        $this->log('Cleanup complete: ' . $message);
        
        return array(
            'success' => true,
            'deleted' => $deleted_count,
            'failed' => $failed_count,
            'kept' => $cleanup_keep,
            'message' => $message,
        );
    }
    
    /**
     * Delete a single backup file
     * 
     * @param array $backup Backup info array
     * @return bool Success status
     */
    private function delete_backup($backup)
    {
        if (!isset($backup['filepath']) || !file_exists($backup['filepath'])) {
            $this->log('File not found: ' . ($backup['filepath'] ?? 'unknown'));
            return false;
        }
        
        // Verify file is in backup directory (security check)
        $real_path = realpath($backup['filepath']);
        $real_backup_dir = realpath($this->backup_dir);
        
        if (strpos($real_path, $real_backup_dir) !== 0) {
            $this->log('Security check failed: File outside backup directory');
            return false;
        }
        
        // Delete file
        $result = @unlink($backup['filepath']);
        
        if (!$result) {
            $this->log('Failed to unlink file (permission denied?)');
        }
        
        return $result;
    }
    
    /**
     * Get cleanup preview
     * Shows what would be deleted without actually deleting
     * 
     * @return array Preview information
     */
    public function get_cleanup_preview()
    {
        $cleanup_keep = get_option('backupzen_cleanup_keep', 5);
        
        $scanner = new BackupScanner($this->backup_dir);
        $backups = $scanner->get_backups();
        
        $total_count = count($backups);
        $will_delete = max(0, $total_count - $cleanup_keep);
        
        $backups_to_delete = array();
        if ($will_delete > 0) {
            $backups_to_delete = array_slice($backups, $cleanup_keep);
        }
        
        return array(
            'total_backups' => $total_count,
            'keep_count' => $cleanup_keep,
            'delete_count' => $will_delete,
            'backups_to_delete' => $backups_to_delete,
        );
    }
    
    /**
     * Log messages
     */
    private function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BackupZen CleanupManager: ' . $message);
        }
    }
}

