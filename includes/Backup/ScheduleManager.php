<?php
/**
 * Schedule Manager Class - FIXED VERSION
 * 
 * Replace your includes/Backup/ScheduleManager.php with this
 * 
 * @package BackupZen
 * @since 1.2.0
 */

namespace BackupZen\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class ScheduleManager
{
    /**
     * Update schedule
     * 
     * @param bool $enabled Schedule enabled
     * @param string $frequency Frequency
     * @param string $time Time in HH:MM format (LOCAL TIME)
     * @return bool Success
     */
    public function update_schedule($enabled, $frequency, $time)
    {
        error_log('====================================');
        error_log('ScheduleManager: update_schedule called');
        error_log('Enabled: ' . ($enabled ? 'YES' : 'NO'));
        error_log('Frequency: ' . $frequency);
        error_log('Time (LOCAL): ' . $time);
        error_log('====================================');
        
        // Clear existing schedule first
        $timestamp = wp_next_scheduled('backupzen_scheduled_backup_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'backupzen_scheduled_backup_event');
            error_log('ScheduleManager: Cleared existing schedule at ' . date('Y-m-d H:i:s', $timestamp));
        }
        
        // If disabled, stop here
        if (!$enabled) {
            error_log('ScheduleManager: Schedule disabled, not rescheduling');
            return true;
        }
        
        // Calculate next run time
        $next_run = TimezoneConverter::calculate_next_run_time($frequency, $time);
        
        if (!$next_run) {
            error_log('ScheduleManager: ERROR - Failed to calculate next run time');
            return false;
        }
        
        error_log('ScheduleManager: Next run timestamp (UTC): ' . $next_run);
        error_log('ScheduleManager: Next run date (UTC): ' . gmdate('Y-m-d H:i:s', $next_run));
        error_log('ScheduleManager: Next run date (LOCAL): ' . date('Y-m-d H:i:s', $next_run));
        
        // Schedule the event
        $result = wp_schedule_event($next_run, $frequency, 'backupzen_scheduled_backup_event');
        
        if ($result === false) {
            error_log('ScheduleManager: ERROR - Failed to schedule event');
            return false;
        }
        
        error_log('ScheduleManager: Successfully scheduled backup');
        
        // Verify it was scheduled
        $verify = wp_next_scheduled('backupzen_scheduled_backup_event');
        if ($verify) {
            error_log('ScheduleManager: VERIFIED - Next run at ' . date('Y-m-d H:i:s', $verify));
            return true;
        } else {
            error_log('ScheduleManager: ERROR - Schedule verification failed');
            return false;
        }
    }
    
    /**
     * Get schedule status
     * 
     * @return array Status information
     */
    public function get_status()
    {
        $next_run = wp_next_scheduled('backupzen_scheduled_backup_event');
        $is_scheduled = (bool) $next_run;
        
        // Get the SAVED time setting (not the next run time)
        $saved_time = get_option('backupzen_schedule_time', '08:00');
        
        error_log('ScheduleManager: get_status called');
        error_log('ScheduleManager: Saved time setting: ' . $saved_time);
        error_log('ScheduleManager: Next scheduled run: ' . ($next_run ? date('Y-m-d H:i:s', $next_run) : 'NOT SCHEDULED'));
        
        if ($next_run) {
            $next_run_formatted = TimezoneConverter::format_time_display($next_run, false);
            $next_run_relative = human_time_diff(current_time('timestamp'), $next_run);
            
            return array(
                'is_scheduled' => true,
                'next_run_timestamp' => $next_run,
                'next_run_formatted' => $next_run_formatted,
                'next_run_relative' => $next_run_relative,
                'saved_time' => $saved_time, // Include the saved setting
            );
        }
        
        return array(
            'is_scheduled' => false,
            'next_run_timestamp' => null,
            'next_run_formatted' => null,
            'next_run_relative' => null,
            'saved_time' => $saved_time, // Include the saved setting
        );
    }
    
    /**
     * Get next scheduled run time
     * 
     * @return int|false Timestamp or false
     */
    public function get_next_run()
    {
        return wp_next_scheduled('backupzen_scheduled_backup_event');
    }
    
    /**
     * Check if schedule is active
     * 
     * @return bool
     */
    public function is_active()
    {
        $enabled = get_option('backupzen_schedule_enabled', false);
        $next_run = wp_next_scheduled('backupzen_scheduled_backup_event');
        
        return $enabled && $next_run;
    }
    
    /**
     * Manually trigger schedule update
     * Useful for fixing stuck schedules
     * 
     * @return bool Success
     */
    public function force_reschedule()
    {
        $enabled = get_option('backupzen_schedule_enabled', false);
        $frequency = get_option('backupzen_schedule_frequency', 'daily');
        $time = get_option('backupzen_schedule_time', '08:00');
        
        error_log('ScheduleManager: force_reschedule called');
        error_log('ScheduleManager: Current settings - Enabled: ' . ($enabled ? 'YES' : 'NO') . ', Frequency: ' . $frequency . ', Time: ' . $time);
        
        return $this->update_schedule($enabled, $frequency, $time);
    }
}