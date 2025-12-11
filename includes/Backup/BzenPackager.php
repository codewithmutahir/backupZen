<?php
/**
 * BZEN Format Packager
 *
 * Handles creation, reading, and verification of .bzen backup files.
 *
 * @package BackupZen\Backup
 * @since 1.0.0
 */

namespace BackupZen\Backup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BzenPackager class.
 *
 * @since 1.0.0
 */
class BzenPackager {
	/**
	 * Magic header for BZEN files.
	 *
	 * @var string
	 */
	const MAGIC_HEADER = "BZEN\x00";

	/**
	 * Magic header length.
	 *
	 * @var int
	 */
	const MAGIC_HEADER_LENGTH = 5;

	/**
	 * Metadata length field size (4 bytes).
	 *
	 * @var int
	 */
	const METADATA_LENGTH_SIZE = 4;

	/**
	 * Payload length field size (8 bytes).
	 *
	 * @var int
	 */
	const PAYLOAD_LENGTH_SIZE = 8;

	/**
	 * Checksum length (SHA256 hex = 64 bytes).
	 *
	 * @var int
	 */
	const CHECKSUM_LENGTH = 64;

	/**
	 * Create a BZEN package file.
	 *
	 * @param string $output_path Full path where the .bzen file should be created.
	 * @param array  $options     Backup options (files, database, etc.).
	 * @return array Result array with 'success' boolean and 'message' or 'file_path'.
	 * @since 1.0.0
	 */
	public function create( $output_path, $options = array() ) {
		// Increase limits for backup operation.
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '768M' );

		// Validate output path.
		if ( empty( $output_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Output path is required.', 'backupzen' ),
			);
		}

		// Ensure directory exists.
		$output_dir = dirname( $output_path );
		if ( ! file_exists( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		// Create temporary directory for staging in wp-content.
		$temp_dir = trailingslashit( WP_CONTENT_DIR ) . 'backupzen_temp_' . uniqid();
		if ( ! wp_mkdir_p( $temp_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create temporary directory.', 'backupzen' ),
			);
		}

		// Prepare metadata.
		$metadata = $this->prepare_metadata( $options );

		// Create the payload using ZIP format (much more memory efficient).
		$payload_result = $this->create_zip_payload( $temp_dir, $options );
		
		if ( ! $payload_result['success'] ) {
			$this->delete_directory( $temp_dir );
			return $payload_result;
		}

		// Don't load entire file into memory - stream it instead.
		$zip_file = $payload_result['zip_file'];
		
		// Update metadata with actual stats.
		$metadata['file_count'] = $payload_result['file_count'];
		$metadata['database_size'] = $payload_result['database_size'];
		$metadata['original_sizes'] = $payload_result['original_sizes'];

		// Calculate checksum and compress in streaming mode.
		$compress_result = $this->compress_and_checksum_file( $zip_file );
		
		if ( ! $compress_result['success'] ) {
			$this->delete_directory( $temp_dir );
			return $compress_result;
		}

		$checksum = $compress_result['checksum'];
		$metadata['payload_hash'] = $checksum;

		// Build BZEN file by streaming (not loading into memory).
		$written = $this->build_bzen_file_streaming( 
			$output_path, 
			$metadata, 
			$compress_result['compressed_file'], 
			$checksum 
		);
		
		if ( ! $written['success'] ) {
			$this->delete_directory( $temp_dir );
			return $written;
		}

		// Cleanup temp directory.
		$this->delete_directory( $temp_dir );

		return array(
			'success'  => true,
			'file_path' => $output_path,
			'file_size' => $written['file_size'],
			'checksum'  => $checksum,
		);
	}

	/**
	 * Create ZIP payload with files and database.
	 *
	 * @param string $temp_dir Temporary directory for staging.
	 * @param array  $options  Backup options.
	 * @return array Result array with payload data.
	 * @since 1.0.0
	 */
	private function create_zip_payload( $temp_dir, $options ) {
		$include_files = isset( $options['include_files'] ) && $options['include_files'];
		$include_database = isset( $options['include_database'] ) && $options['include_database'];

		$file_count = 0;
		$database_size = 0;
		$original_sizes = array(
			'files' => 0,
			'database' => 0,
		);

		// Create ZIP file.
		$zip_file = trailingslashit( $temp_dir ) . 'backup.zip';
		$zip = new \ZipArchive();
		
		if ( true !== $zip->open( $zip_file, \ZipArchive::CREATE ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create ZIP archive.', 'backupzen' ),
			);
		}

		// Add database if requested.
		if ( $include_database ) {
			$db_file = trailingslashit( $temp_dir ) . 'database.sql';
			$db_result = $this->export_database_to_file( $db_file );
			
			if ( $db_result['success'] && file_exists( $db_file ) ) {
				$zip->addFile( $db_file, 'database.sql' );
				$database_size = filesize( $db_file );
				$original_sizes['database'] = $database_size;
			}
		}

		// Add files if requested.
		if ( $include_files ) {
			$files_result = $this->add_files_to_zip( $zip );
			$file_count = $files_result['file_count'];
			$original_sizes['files'] = $files_result['total_size'];
		}

		$zip->close();
		
		// Check if ZIP was created successfully.
		if ( ! file_exists( $zip_file ) || filesize( $zip_file ) === 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create ZIP archive. Check disk space and permissions.', 'backupzen' ),
			);
		}

		// Clean up temp database file.
		if ( $include_database && file_exists( trailingslashit( $temp_dir ) . 'database.sql' ) ) {
			@unlink( trailingslashit( $temp_dir ) . 'database.sql' );
		}

		return array(
			'success' => true,
			'zip_file' => $zip_file,
			'file_count' => $file_count,
			'database_size' => $database_size,
			'original_sizes' => $original_sizes,
		);
	}

    	/**
     * Add wp-content files to ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive object.
     * @return array Result with file count and size.
     * @since 1.0.0
     */
    private function add_files_to_zip( $zip ) {
    	$wp_content_dir = WP_CONTENT_DIR;
    	$file_count = 0;
    	$total_size = 0;
    
    	// Normalize paths for comparison (handle Windows).
    	$wp_content_dir = wp_normalize_path( $wp_content_dir );
    
    	// Directories to exclude (relative to wp-content).
    	$exclude_patterns = array(
    		'cache',
    		'upgrade',
    		'backupzen',           // CRITICAL: Exclude backup directories
    		'backupZen_backups',   // CRITICAL: Your actual backup directory!
    		'backupZen_rollbacks',
    		'backupzen_backups',
    		'backupzen_temp_',
    		'backup-db',
    		'ai1wm-backups',
    		'updraft',
    		'wphb-cache',
    		'w3tc-config',
    		'et-cache',
    	);
    
    	try {
    		$iterator = new \RecursiveIteratorIterator(
    			new \RecursiveDirectoryIterator( $wp_content_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
    			\RecursiveIteratorIterator::SELF_FIRST
    		);
    
    		foreach ( $iterator as $item ) {
    			if ( $item->isDir() ) {
    				continue; // Skip directories.
    			}
    
    			$source_path = wp_normalize_path( $item->getPathname() );
    			$relative_path = str_replace( trailingslashit( $wp_content_dir ), '', $source_path );
    			
    			// Skip excluded directories.
    			$should_skip = false;
    			foreach ( $exclude_patterns as $pattern ) {
    				if ( 0 === strpos( $relative_path, $pattern . '/' ) || $relative_path === $pattern ) {
    					$should_skip = true;
    					break;
    				}
    			}
    			
    			if ( $should_skip ) {
    				continue;
    			}
    
    			// Add file to zip with relative path.
    			if ( @$zip->addFile( $source_path, 'files/' . $relative_path ) ) {
    				$file_count++;
    				$total_size += $item->getSize();
    			}
    
    			// Prevent memory issues on very large sites.
    			if ( $file_count % 100 === 0 ) {
    				// Force garbage collection every 100 files.
    				if ( function_exists( 'gc_collect_cycles' ) ) {
    					gc_collect_cycles();
    				}
    			}
    		}
    	} catch ( \Exception $e ) {
    		// Continue even if there are errors.
    	}
    
    	return array(
    		'file_count' => $file_count,
    		'total_size' => $total_size,
    	);
    }


	/**
	 * Export database to SQL file.
	 *
	 * @param string $output_file Output file path.
	 * @param callable $progress_callback Optional progress callback function(string $step, int $percent).
	 * @return array Result array.
	 * @since 1.0.0
	 */
	private function export_database_to_file( $output_file, $progress_callback = null ) {
		global $wpdb;

		$report_progress = function($step, $percent) use ($progress_callback) {
			if (is_callable($progress_callback)) {
				call_user_func($progress_callback, $step, $percent);
			}
		};

		$handle = fopen( $output_file, 'w' );
		if ( false === $handle ) {
			return array( 'success' => false );
		}

		$report_progress(__('Preparing database export...', 'backupzen'), 0);

		// Write SQL header.
		fwrite( $handle, "-- WordPress Database Backup\n" );
		fwrite( $handle, "-- Generated: " . current_time( 'mysql' ) . "\n\n" );
		fwrite( $handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" );
		fwrite( $handle, "SET time_zone = \"+00:00\";\n\n" );

		// Get all tables.
		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		$total_tables = count($tables);
		$current_table = 0;

		$report_progress(__('Exporting database structure...', 'backupzen'), 5);

		// Structure export takes 5-20% of progress
		foreach ( $tables as $table ) {
			$table_name = $table[0];
			$current_table++;

			// Get table structure.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared, but is validated from $wpdb->prefix
			$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N );
			if ( $create_table ) {
				fwrite( $handle, "\n-- Table structure for `{$table_name}`\n" );
				fwrite( $handle, "DROP TABLE IF EXISTS `{$table_name}`;\n" );
				fwrite( $handle, $create_table[1] . ";\n\n" );
			}

			// Report progress for table structure (5-20%)
			$structure_progress = 5 + (($current_table / $total_tables) * 15);
			/* translators: 1: Table name, 2: Current table number, 3: Total number of tables */
			$report_progress(
				sprintf(__('Exporting table structure: %1$s (%2$d of %3$d)', 'backupzen'), $table_name, $current_table, $total_tables),
				min(20, (int)$structure_progress)
			);
		}

		// Data export takes 20-95% of progress
		$current_table = 0;
		foreach ( $tables as $table ) {
			$table_name = $table[0];
			$current_table++;

			// Get table data in chunks to avoid memory issues.
			$offset = 0;
			$limit = 100;
			$row_count = 0;
			
			// Get total rows for this table for accurate progress
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared, but is validated from $wpdb->prefix
			$total_rows_result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_name}`"));
			$total_rows = intval($total_rows_result);
			
			if ( 0 === $offset ) {
				fwrite( $handle, "-- Data for table `{$table_name}`\n" );
			}
			
			while ( true ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared, but is validated from $wpdb->prefix
				$rows = $wpdb->get_results( 
					$wpdb->prepare( "SELECT * FROM `{$table_name}` LIMIT %d OFFSET %d", $limit, $offset ),
					ARRAY_A
				);
				
				if ( empty( $rows ) ) {
					break;
				}
				
				foreach ( $rows as $row ) {
					$columns = array_keys( $row );
					$values = array();
					
					foreach ( $row as $value ) {
						if ( null === $value ) {
							$values[] = 'NULL';
						} else {
							$values[] = "'" . $wpdb->_real_escape( $value ) . "'";
						}
					}
					
					$columns_str = '`' . implode( '`, `', $columns ) . '`';
					$values_str = implode( ', ', $values );
					fwrite( $handle, "INSERT INTO `{$table_name}` ({$columns_str}) VALUES ({$values_str});\n" );
					$row_count++;
				}
				
				// Report progress for table data (20-95% range)
				// Each table gets (75% / total_tables) of the progress
				if ($total_rows > 0 && $total_tables > 0) {
					$table_base_progress = 20 + (($current_table - 1) / $total_tables * 75);
					$table_incremental = ($row_count / $total_rows) * (75 / $total_tables);
					$table_data_progress = $table_base_progress + $table_incremental;
					
					// Report every 100 rows or at completion for more frequent updates
					if ($row_count % 100 === 0 || $row_count === $total_rows) {
						/* translators: 1: Table name, 2: Current row count, 3: Total number of rows */
						$report_progress(
							sprintf(__('Exporting table data: %1$s (%2$d of %3$d rows)', 'backupzen'), $table_name, $row_count, $total_rows),
							min(95, (int)$table_data_progress)
						);
					}
				}
				
				$offset += $limit;
			}
			
			fwrite( $handle, "\n" );
		}

		$report_progress(__('Database export completed', 'backupzen'), 95);

		fclose( $handle );

		return array( 'success' => true );
	}

	/**
	 * Compress file and calculate checksum without loading into memory.
	 *
	 * @param string $input_file Input file path.
	 * @return array Result with checksum and compressed file path.
	 * @since 1.0.0
	 */
	private function compress_and_checksum_file( $input_file ) {
		$compressed_file = $input_file . '.gz';
		
		// Open input and output files.
		$input = @fopen( $input_file, 'rb' );
		$output = @gzopen( $compressed_file, 'wb6' ); // Level 6 compression.
		
		if ( ! $input || ! $output ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to open files for compression.', 'backupzen' ),
			);
		}
		
		// Stream and compress in chunks.
		$hash_ctx = hash_init( 'sha256' );
		$chunk_size = 1024 * 1024; // 1MB chunks.
		
		while ( ! feof( $input ) ) {
			$chunk = fread( $input, $chunk_size );
			if ( false === $chunk ) {
				break;
			}
			gzwrite( $output, $chunk );
		}
		
		fclose( $input );
		gzclose( $output );
		
		// Now calculate checksum of compressed file.
		$compressed_input = @fopen( $compressed_file, 'rb' );
		if ( ! $compressed_input ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to read compressed file.', 'backupzen' ),
			);
		}
		
		while ( ! feof( $compressed_input ) ) {
			$chunk = fread( $compressed_input, $chunk_size );
			if ( false === $chunk ) {
				break;
			}
			hash_update( $hash_ctx, $chunk );
		}
		
		fclose( $compressed_input );
		$checksum = hash_final( $hash_ctx );
		
		return array(
			'success' => true,
			'compressed_file' => $compressed_file,
			'checksum' => $checksum,
		);
	}

	/**
	 * Build BZEN file by streaming (memory efficient).
	 *
	 * @param string $output_path Output file path.
	 * @param array  $metadata Metadata array.
	 * @param string $compressed_file Compressed payload file.
	 * @param string $checksum SHA256 checksum.
	 * @return array Result array.
	 * @since 1.0.0
	 */
	private function build_bzen_file_streaming( $output_path, $metadata, $compressed_file, $checksum ) {
		$output = @fopen( $output_path, 'wb' );
		if ( ! $output ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create output file.', 'backupzen' ),
			);
		}
		
		// Write magic header.
		fwrite( $output, self::MAGIC_HEADER );
		
		// Write metadata.
		$metadata_json = wp_json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$metadata_length = strlen( $metadata_json );
		fwrite( $output, pack( 'V', $metadata_length ) );
		fwrite( $output, $metadata_json );
		
		// Write payload length.
		$payload_length = filesize( $compressed_file );
		$payload_low  = $payload_length & 0xFFFFFFFF;
		$payload_high = ( $payload_length >> 32 ) & 0xFFFFFFFF;
		fwrite( $output, pack( 'V2', $payload_low, $payload_high ) );
		
		// Stream compressed payload.
		$input = @fopen( $compressed_file, 'rb' );
		if ( ! $input ) {
			fclose( $output );
			return array(
				'success' => false,
				'message' => __( 'Failed to read compressed payload.', 'backupzen' ),
			);
		}
		
		$chunk_size = 1024 * 1024; // 1MB chunks.
		while ( ! feof( $input ) ) {
			$chunk = fread( $input, $chunk_size );
			if ( false === $chunk ) {
				break;
			}
			fwrite( $output, $chunk );
		}
		
		fclose( $input );
		
		// Write checksum.
		fwrite( $output, $checksum );
		
		$file_size = ftell( $output );
		fclose( $output );
		
		return array(
			'success' => true,
			'file_size' => $file_size,
		);
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return bool Success status.
	 * @since 1.0.0
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$files = @scandir( $dir );
		if ( false === $files ) {
			@rmdir( $dir );
			return true;
		}

		$files = array_diff( $files, array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = trailingslashit( $dir ) . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
		return true;
	}

	/**
	 * Read and parse a BZEN package file.
	 *
	 * @param string $file_path Full path to the .bzen file.
	 * @return array Result array with 'success' boolean and parsed data or error message.
	 * @since 1.0.0
	 */
	public function read( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'BZEN file not found.', 'backupzen' ),
			);
		}

		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to open BZEN file.', 'backupzen' ),
			);
		}

		// Read magic header.
		$magic = fread( $handle, self::MAGIC_HEADER_LENGTH );
		if ( $magic !== self::MAGIC_HEADER ) {
			fclose( $handle );
			return array(
				'success' => false,
				'message' => __( 'Invalid BZEN file format: magic header mismatch.', 'backupzen' ),
			);
		}

		// Read metadata length (4 bytes, little-endian).
		$metadata_length_bytes = fread( $handle, self::METADATA_LENGTH_SIZE );
		$metadata_length = unpack( 'V', $metadata_length_bytes )[1];

		// Read metadata JSON.
		$metadata_json = fread( $handle, $metadata_length );
		$metadata = json_decode( $metadata_json, true );

		if ( null === $metadata ) {
			fclose( $handle );
			return array(
				'success' => false,
				'message' => __( 'Failed to parse metadata JSON.', 'backupzen' ),
			);
		}

		// Read payload length (8 bytes, little-endian).
		$payload_length_bytes = fread( $handle, self::PAYLOAD_LENGTH_SIZE );
		$unpacked = unpack( 'V2', $payload_length_bytes );
		$payload_length = $unpacked[1] + ( $unpacked[2] * 0x100000000 );

		// Read payload.
		$compressed_payload = fread( $handle, $payload_length );

		// Read checksum.
		$stored_checksum = fread( $handle, self::CHECKSUM_LENGTH );

		fclose( $handle );

		// Verify checksum.
		$calculated_checksum = hash( 'sha256', $compressed_payload );
		if ( $stored_checksum !== $calculated_checksum ) {
			return array(
				'success' => false,
				'message' => __( 'Checksum verification failed. File may be corrupted.', 'backupzen' ),
			);
		}

		// Decompress payload.
		$payload = gzdecode( $compressed_payload );

		return array(
			'success'  => true,
			'metadata' => $metadata,
			'payload'  => $payload,
			'checksum' => $calculated_checksum,
		);
	}

	/**
	 * Read only metadata from a BZEN file (without reading full payload).
	 *
	 * @param string $file_path Full path to the .bzen file.
	 * @return array|false Metadata array on success, false on failure.
	 * @since 1.0.0
	 */
	public function read_metadata( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return false;
		}

		// Read magic header.
		$magic = fread( $handle, self::MAGIC_HEADER_LENGTH );
		if ( $magic !== self::MAGIC_HEADER ) {
			fclose( $handle );
			return false;
		}

		// Read metadata length (4 bytes, little-endian).
		$metadata_length_bytes = fread( $handle, self::METADATA_LENGTH_SIZE );
		$metadata_length = unpack( 'V', $metadata_length_bytes )[1];

		// Read metadata JSON.
		$metadata_json = fread( $handle, $metadata_length );
		$metadata = json_decode( $metadata_json, true );

		fclose( $handle );

		if ( null === $metadata ) {
			return false;
		}

		// Read checksum from end of file for preview.
		$file_size = filesize( $file_path );
		if ( $file_size > self::CHECKSUM_LENGTH ) {
			$handle = fopen( $file_path, 'rb' );
			fseek( $handle, -self::CHECKSUM_LENGTH, SEEK_END );
			$checksum = fread( $handle, self::CHECKSUM_LENGTH );
			fclose( $handle );
			$metadata['checksum'] = $checksum;
		}

		return $metadata;
	}

	/**
	 * Get payload from BZEN file (files + database SQL) with checksum verification.
	 *
	 * @param string $file_path Full path to the .bzen file.
	 * @return array Result array with 'success' boolean, metadata, and payload data.
	 * @since 1.0.0
	 */
	public function get_payload( $file_path ) {
		$verify_result = $this->verify_checksum( $file_path );
		if ( ! $verify_result['success'] ) {
			return $verify_result;
		}

		$read_result = $this->read( $file_path );
		if ( ! $read_result['success'] ) {
			return $read_result;
		}

		return array(
			'success'  => true,
			'metadata' => $read_result['metadata'],
			'payload'  => $read_result['payload'],
			'checksum' => $read_result['checksum'],
		);
	}

	/**
	 * Extract BZEN file to temporary directory.
	 *
	 * @param string $file_path Full path to the .bzen file.
	 * @param string $output_dir Directory where to extract files (must be writable).
	 * @return array Result array with 'success' boolean, metadata, and extracted file paths.
	 * @since 1.0.0
	 */
	public function extract( $file_path, $output_dir ) {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'BZEN file not found.', 'backupzen' ),
			);
		}

		if ( ! is_dir( $output_dir ) || ! is_writable( $output_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Output directory is not writable.', 'backupzen' ),
			);
		}

		// Read BZEN file.
		$read_result = $this->read( $file_path );
		if ( ! $read_result['success'] ) {
			return $read_result;
		}

		$metadata = $read_result['metadata'];
		$payload = $read_result['payload'];

		// Save payload as ZIP file.
		$zip_file = trailingslashit( $output_dir ) . 'backup.zip';
		file_put_contents( $zip_file, $payload );

		// Extract ZIP.
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_file ) ) {
			@unlink( $zip_file );
			return array(
				'success' => false,
				'message' => __( 'Failed to open ZIP archive.', 'backupzen' ),
			);
		}

		$zip->extractTo( $output_dir );
		$zip->close();

		// Delete ZIP file.
		@unlink( $zip_file );

		// Build result with paths to extracted content.
		$result = array(
			'success'  => true,
			'metadata' => $metadata,
			'db_path'  => '',
			'files_path' => '',
		);

		// Check for database file.
		$db_file = trailingslashit( $output_dir ) . 'database.sql';
		if ( file_exists( $db_file ) ) {
			$result['db_path'] = $db_file;
		}

		// Check for files directory.
		$files_dir = trailingslashit( $output_dir ) . 'files';
		if ( is_dir( $files_dir ) ) {
			$result['files_path'] = $files_dir;
		}

		return $result;
	}

	/**
	 * Verify checksum of a BZEN file.
	 *
	 * @param string $file_path Full path to the .bzen file.
	 * @return array Result array with 'success' boolean and verification status.
	 * @since 1.0.0
	 */
	public function verify_checksum( $file_path ) {
		$read_result = $this->read( $file_path );
		if ( ! $read_result['success'] ) {
			return $read_result;
		}

		return array(
			'success' => true,
			'valid'   => true,
			'checksum' => $read_result['checksum'],
		);
	}

	/**
	 * Prepare metadata array for BZEN file.
	 *
	 * @param array $options Backup options.
	 * @return array Metadata array.
	 * @since 1.0.0
	 */
	private function prepare_metadata( $options ) {
		global $wp_version;

		$metadata = array(
			'backupzen_version' => '1.0.0',
			'timestamp'         => current_time( 'mysql' ),
			'unix_timestamp'    => time(),
			'wp_version'        => $wp_version,
			'php_version'       => PHP_VERSION,
			'site_url'          => site_url(),
			'home_url'          => home_url(),
			'file_count'        => 0,
			'database_size'     => 0,
			'payload_hash'      => '',
			'original_sizes'    => array(
				'files'    => 0,
				'database' => 0,
			),
			'options'           => $options,
		);

		return $metadata;
	}

	/**
	 * Build BZEN file binary structure.
	 * Note: This method is deprecated - use build_bzen_file_streaming() instead.
	 *
	 * @param array  $metadata          Metadata array.
	 * @param string $compressed_payload Gzip-compressed payload.
	 * @param string $checksum          SHA256 checksum (hex).
	 * @return string Binary BZEN file content.
	 * @since 1.0.0
	 * @deprecated Use streaming method to avoid memory issues.
	 */
	private function build_bzen_file( $metadata, $compressed_payload, $checksum ) {
		$metadata_json = wp_json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$metadata_length = strlen( $metadata_json );

		$payload_length = strlen( $compressed_payload );
		$payload_low  = $payload_length & 0xFFFFFFFF;
		$payload_high = ( $payload_length >> 32 ) & 0xFFFFFFFF;

		$content = self::MAGIC_HEADER;
		$content .= pack( 'V', $metadata_length );
		$content .= $metadata_json;
		$content .= pack( 'V2', $payload_low, $payload_high );
		$content .= $compressed_payload;
		$content .= $checksum;

		return $content;
	}
	
	/**
 * Create a BZEN package file WITH progress callbacks.
 *
 * @param string   $output_path Full path where the .bzen file should be created.
 * @param array    $options     Backup options (files, database, etc.).
 * @param callable $progress_callback Optional callback function(string $step, int $percent).
 * @return array Result array with 'success' boolean and 'message' or 'file_path'.
 * @since 1.0.0
 */
public function create_with_progress($output_path, $options = array(), $progress_callback = null)
{
    // Increase limits for backup operation
    @set_time_limit(0);
    @ini_set('memory_limit', '768M');

    // Helper to call progress callback
    $report_progress = function($step, $percent) use ($progress_callback) {
        if (is_callable($progress_callback)) {
            call_user_func($progress_callback, $step, $percent);
        }
    };

    $report_progress(__('Validating backup path...', 'backupzen'), 5);

    // Validate output path
    if (empty($output_path)) {
        return array(
            'success' => false,
            'message' => __('Output path is required.', 'backupzen'),
        );
    }

    // Ensure directory exists
    $output_dir = dirname($output_path);
    if (!file_exists($output_dir)) {
        wp_mkdir_p($output_dir);
    }

    $report_progress(__('Creating temporary directory...', 'backupzen'), 8);

    // Create temporary directory
    $temp_dir = trailingslashit(WP_CONTENT_DIR) . 'backupzen_temp_' . uniqid();
    if (!wp_mkdir_p($temp_dir)) {
        return array(
            'success' => false,
            'message' => __('Failed to create temporary directory.', 'backupzen'),
        );
    }

    $report_progress(__('Preparing metadata...', 'backupzen'), 10);

    // Prepare metadata
    $metadata = $this->prepare_metadata($options);

    $report_progress(__('Starting backup collection...', 'backupzen'), 12);

    // Create the payload using ZIP format
    $payload_result = $this->create_zip_payload_with_progress($temp_dir, $options, function($step, $percent) use ($report_progress) {
        // Map internal progress (0-100) to overall progress (12-70)
        $mapped_percent = 12 + ($percent * 0.58);
        $report_progress($step, (int)$mapped_percent);
    });
    
    if (!$payload_result['success']) {
        $this->delete_directory($temp_dir);
        return $payload_result;
    }

    $zip_file = $payload_result['zip_file'];
    
    // Update metadata with actual stats
    $metadata['file_count'] = $payload_result['file_count'];
    $metadata['database_size'] = $payload_result['database_size'];
    $metadata['original_sizes'] = $payload_result['original_sizes'];

    $report_progress(__('Compressing backup data...', 'backupzen'), 72);

    // Calculate checksum and compress
    $compress_result = $this->compress_and_checksum_file($zip_file);
    
    if (!$compress_result['success']) {
        $this->delete_directory($temp_dir);
        return $compress_result;
    }

    $checksum = $compress_result['checksum'];
    $metadata['payload_hash'] = $checksum;

    $report_progress(__('Writing backup file...', 'backupzen'), 85);

    // Build BZEN file by streaming
    $written = $this->build_bzen_file_streaming( 
        $output_path, 
        $metadata, 
        $compress_result['compressed_file'], 
        $checksum 
    );
    
    if (!$written['success']) {
        $this->delete_directory($temp_dir);
        return $written;
    }

    $report_progress(__('Cleaning up temporary files...', 'backupzen'), 95);

    // Cleanup temp directory
    $this->delete_directory($temp_dir);

    $report_progress(__('Backup completed!', 'backupzen'), 100);

    return array(
        'success'  => true,
        'file_path' => $output_path,
        'file_size' => $written['file_size'],
        'checksum'  => $checksum,
    );
}

/**
 * Create ZIP payload with progress callbacks.
 *
 * @param string   $temp_dir Temporary directory for staging.
 * @param array    $options  Backup options.
 * @param callable $progress_callback Progress callback.
 * @return array Result array with payload data.
 * @since 1.0.0
 */
private function create_zip_payload_with_progress($temp_dir, $options, $progress_callback = null)
{
    $include_files = isset($options['include_files']) && $options['include_files'];
    $include_database = isset($options['include_database']) && $options['include_database'];

    $report_progress = function($step, $percent) use ($progress_callback) {
        if (is_callable($progress_callback)) {
            call_user_func($progress_callback, $step, $percent);
        }
    };

    $file_count = 0;
    $database_size = 0;
    $original_sizes = array(
        'files' => 0,
        'database' => 0,
    );

    $report_progress(__('Creating ZIP archive...', 'backupzen'), 0);

    // Create ZIP file
    $zip_file = trailingslashit($temp_dir) . 'backup.zip';
    $zip = new \ZipArchive();
    
    if (true !== $zip->open($zip_file, \ZipArchive::CREATE)) {
        return array(
            'success' => false,
            'message' => __('Failed to create ZIP archive.', 'backupzen'),
        );
    }

    // Add database if requested
    if ($include_database) {
        $report_progress(__('Starting database export...', 'backupzen'), 10);
        
        $db_file = trailingslashit($temp_dir) . 'database.sql';
        
        // Pass progress callback to database export (maps 0-100 to 10-30 range)
        $db_result = $this->export_database_to_file($db_file, function($step, $percent) use ($report_progress) {
            // Map database export progress (0-100) to overall progress (10-30)
            $mapped_percent = 10 + ($percent * 0.20);
            $report_progress($step, (int)$mapped_percent);
        });
        
        if ($db_result['success'] && file_exists($db_file)) {
            $zip->addFile($db_file, 'database.sql');
            $database_size = filesize($db_file);
            $original_sizes['database'] = $database_size;
            
            $report_progress(__('Database exported successfully', 'backupzen'), 30);
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to export database.', 'backupzen'),
            );
        }
    }

    // Add files if requested
    if ($include_files) {
        $report_progress(__('Collecting WordPress files...', 'backupzen'), 35);
        
        $files_result = $this->add_files_to_zip_with_progress($zip, function($current, $total) use ($report_progress) {
            // Map file progress (0-100) to database section (35-95)
            $percent = $total > 0 ? ($current / $total) * 100 : 0;
            $mapped_percent = 35 + ($percent * 0.60);
            /* translators: 1: Current file number, 2: Total number of files */
            $report_progress(
                sprintf(__('Adding files: %1$d of %2$d', 'backupzen'), $current, $total),
                (int)$mapped_percent
            );
        });
        
        $file_count = $files_result['file_count'];
        $original_sizes['files'] = $files_result['total_size'];
    }

    $report_progress(__('Finalizing ZIP archive...', 'backupzen'), 96);

    $zip->close();
    
    // Check if ZIP was created successfully
    if (!file_exists($zip_file) || filesize($zip_file) === 0) {
        return array(
            'success' => false,
            'message' => __('Failed to create ZIP archive. Check disk space and permissions.', 'backupzen'),
        );
    }

    // Clean up temp database file
    if ($include_database && file_exists(trailingslashit($temp_dir) . 'database.sql')) {
        @unlink(trailingslashit($temp_dir) . 'database.sql');
    }

    $report_progress(__('ZIP archive created successfully', 'backupzen'), 100);

    return array(
        'success' => true,
        'zip_file' => $zip_file,
        'file_count' => $file_count,
        'database_size' => $database_size,
        'original_sizes' => $original_sizes,
    );
}

    /**
     * Add wp-content files to ZIP archive with progress tracking.
     *
     * @param \ZipArchive $zip ZIP archive object.
     * @param callable    $progress_callback Progress callback(int $current, int $total).
     * @return array Result with file count and size.
     * @since 1.0.0
     */
    private function add_files_to_zip_with_progress($zip, $progress_callback = null)
    {
        $wp_content_dir = WP_CONTENT_DIR;
        $file_count = 0;
        $total_size = 0;
    
        // Normalize paths
        $wp_content_dir = wp_normalize_path($wp_content_dir);
    
        // Directories to exclude (relative to wp-content)
        $exclude_patterns = array(
            'cache',
            'upgrade',
            'backupzen',           // CRITICAL: Exclude backup directories
            'backupZen_backups',   // CRITICAL: Your actual backup directory!
            'backupZen_rollbacks',
            'backupzen_backups',
            'backupzen_temp_',
            'backup-db',
            'ai1wm-backups',
            'updraft',
            'wphb-cache',
            'w3tc-config',
            'et-cache',
        );
    
        // First, count total files for accurate progress
        $total_files = 0;
        try {
            $counter_iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($wp_content_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
    
            foreach ($counter_iterator as $item) {
                if ($item->isDir()) continue;
                
                $source_path = wp_normalize_path($item->getPathname());
                $relative_path = str_replace(trailingslashit($wp_content_dir), '', $source_path);
                
                $should_skip = false;
                foreach ($exclude_patterns as $pattern) {
                    if (0 === strpos($relative_path, $pattern . '/') || $relative_path === $pattern) {
                        $should_skip = true;
                        break;
                    }
                }
                
                if (!$should_skip) {
                    $total_files++;
                }
            }
        } catch (\Exception $e) {
            $total_files = 1000; // Fallback estimate
        }
    
        // Now add files with progress reporting
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($wp_content_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
    
            foreach ($iterator as $item) {
                if ($item->isDir()) continue;
    
                $source_path = wp_normalize_path($item->getPathname());
                $relative_path = str_replace(trailingslashit($wp_content_dir), '', $source_path);
                
                // Skip excluded directories
                $should_skip = false;
                foreach ($exclude_patterns as $pattern) {
                    if (0 === strpos($relative_path, $pattern . '/') || $relative_path === $pattern) {
                        $should_skip = true;
                        break;
                    }
                }
                
                if ($should_skip) continue;
    
                // Add file to zip
                if (@$zip->addFile($source_path, 'files/' . $relative_path)) {
                    $file_count++;
                    $total_size += $item->getSize();
                    
                    // Report progress every 10 files for more frequent updates
                    if ($file_count % 10 === 0 && is_callable($progress_callback)) {
                        call_user_func($progress_callback, $file_count, $total_files);
                    }
                }
    
                // Garbage collection every 100 files
                if ($file_count % 100 === 0) {
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }
            
            // Final progress report
            if (is_callable($progress_callback)) {
                call_user_func($progress_callback, $file_count, $total_files);
            }
            
        } catch (\Exception $e) {
            // Continue even if there are errors
        }
    
        return array(
            'file_count' => $file_count,
            'total_size' => $total_size,
        );
    }
	
}