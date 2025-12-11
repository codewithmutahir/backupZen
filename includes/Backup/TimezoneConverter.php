<?php
/**
 * Timezone Converter Class
 * Handles timezone conversions for scheduling
 * 
 * @package BackupZen
 * @since 1.2.0
 */

namespace BackupZen\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class TimezoneConverter
{
    /**
     * Get WordPress timezone
     * 
     * @return \DateTimeZone
     */
    public static function get_wp_timezone()
    {
        $timezone_string = get_option('timezone_string');
        
        if ($timezone_string) {
            return new \DateTimeZone($timezone_string);
        }
        
        // Fallback to offset
        $offset = get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs(($offset - $hours) * 60);
        $offset_string = sprintf('%+03d:%02d', $hours, $minutes);
        
        return new \DateTimeZone($offset_string);
    }
    
    /**
     * Get UTC timezone
     * 
     * @return \DateTimeZone
     */
    public static function get_utc_timezone()
    {
        return new \DateTimeZone('UTC');
    }
    
    /**
     * Get timezone information
     * 
     * @return array Timezone info
     */
    public static function get_timezone_info()
    {
        $timezone_string = get_option('timezone_string');
        $gmt_offset = get_option('gmt_offset', 0);
        
        if (empty($timezone_string)) {
            $timezone_name = 'UTC' . ($gmt_offset >= 0 ? '+' : '') . $gmt_offset;
        } else {
            $timezone_name = $timezone_string;
        }
        
        $offset_string = self::get_offset_string();
        $current_time = current_time('Y-m-d H:i:s');
        
        return array(
            'timezone_name' => $timezone_name,
            'offset_string' => $offset_string,
            'current_time' => $current_time,
            'gmt_offset' => $gmt_offset,
        );
    }
    
    /**
     * Get offset string (e.g., "UTC+5:30")
     * 
     * @return string
     */
    public static function get_offset_string()
    {
        $timezone_string = get_option('timezone_string');
        
        if (!empty($timezone_string)) {
            try {
                $tz = new \DateTimeZone($timezone_string);
                $now = new \DateTime('now', $tz);
                $offset = $tz->getOffset($now);
                $hours = floor($offset / 3600);
                $minutes = abs(($offset % 3600) / 60);
                return sprintf('UTC%+d:%02d', $hours, $minutes);
            } catch (\Exception $e) {
                // Fall through to offset method
            }
        }
        
        $gmt_offset = get_option('gmt_offset', 0);
        $hours = (int) $gmt_offset;
        $minutes = abs(($gmt_offset - $hours) * 60);
        return sprintf('UTC%+d:%02d', $hours, $minutes);
    }
    
    /**
     * Get current local time
     * 
     * @param string $format Format string
     * @return string Formatted time
     */
    public static function get_current_local_time($format = 'Y-m-d H:i:s')
    {
        return current_time($format);
    }
    
    /**
     * Validate time format (HH:MM)
     * 
     * @param string $time Time string
     * @return bool
     */
    public static function validate_time_format($time)
    {
        return (bool) preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Calculate next run time
     * Converts user's local time to UTC timestamp for WP-Cron
     * 
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @param string $time Time in H:i format (LOCAL TIME)
     * @return int|false UTC timestamp
     */
    public static function calculate_next_run_time($frequency, $time)
    {
        try {
            // Parse time
            $time_parts = explode(':', $time);
            if (count($time_parts) !== 2) {
                return false;
            }
            
            $hour = (int) $time_parts[0];
            $minute = (int) $time_parts[1];
            
            // Get current time in WordPress timezone
            $wp_tz = self::get_wp_timezone();
            $now = new \DateTime('now', $wp_tz);
            
            // Create target time today in WordPress timezone
            $target = new \DateTime('now', $wp_tz);
            $target->setTime($hour, $minute, 0);
            
            // If target time has passed today, move to next occurrence
            if ($target <= $now) {
                switch ($frequency) {
                    case 'hourly':
                        $target->modify('+1 hour');
                        break;
                    case 'twicedaily':
                        $target->modify('+12 hours');
                        break;
                    case 'daily':
                        $target->modify('+1 day');
                        break;
                    case 'weekly':
                        $target->modify('+7 days');
                        break;
                    case 'monthly':
                        $target->modify('+30 days');
                        break;
                    default:
                        $target->modify('+1 day');
                }
            }
            
            // Convert to UTC timestamp for WP-Cron
            $target->setTimezone(self::get_utc_timezone());
            return $target->getTimestamp();
            
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BackupZen TimezoneConverter: Error calculating next run time: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Convert UTC timestamp to local time
     * 
     * @param int $utc_timestamp UTC timestamp
     * @param string $format Format string
     * @return string Formatted local time
     */
    public static function utc_timestamp_to_local_time($utc_timestamp, $format = 'Y-m-d H:i:s')
    {
        try {
            $wp_tz = self::get_wp_timezone();
            $datetime = new \DateTime('@' . $utc_timestamp);
            $datetime->setTimezone($wp_tz);
            return $datetime->format($format);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BackupZen TimezoneConverter: Error converting timestamp: ' . $e->getMessage());
            }
            // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Fallback for local time display
            return date($format, $utc_timestamp);
        }
    }
    
    /**
     * Format time for display with timezone info
     * Uses 12-hour format for better user experience
     * 
     * @param int $utc_timestamp UTC timestamp
     * @param bool $include_timezone Include timezone in display
     * @return string Formatted display string
     */
    public static function format_time_display($utc_timestamp, $include_timezone = false)
    {
        // Use 12-hour format (g:i A) for better user experience
        $local_time = self::utc_timestamp_to_local_time($utc_timestamp, 'M j, Y \a\t g:i A');
        
        if ($include_timezone) {
            $offset = self::get_offset_string();
            return $local_time . ' (' . $offset . ')';
        }
        
        return $local_time;
    }
    
    /**
     * Convert 24-hour time to 12-hour format
     * 
     * @param string $time24 Time in H:i format (24-hour)
     * @return string Time in g:i A format (12-hour)
     */
    public static function convert_to_12_hour($time24)
    {
        if (empty($time24)) {
            return '';
        }
        
        try {
            $datetime = \DateTime::createFromFormat('H:i', $time24);
            if ($datetime) {
                return $datetime->format('g:i A');
            }
        } catch (\Exception $e) {
            // Fallback
        }
        
        return $time24;
    }
    
    /**
     * Convert 12-hour time to 24-hour format
     * 
     * @param string $time12 Time in g:i A format (12-hour)
     * @return string Time in H:i format (24-hour)
     */
    public static function convert_to_24_hour($time12)
    {
        if (empty($time12)) {
            return '';
        }
        
        try {
            $datetime = \DateTime::createFromFormat('g:i A', $time12);
            if ($datetime) {
                return $datetime->format('H:i');
            }
            // Try alternate format
            $datetime = \DateTime::createFromFormat('g:iA', $time12);
            if ($datetime) {
                return $datetime->format('H:i');
            }
        } catch (\Exception $e) {
            // Fallback
        }
        
        return $time12;
    }
}
