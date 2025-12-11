<?php
/**
 * Backup Scanner
 *
 * Scans backup directory and extracts metadata from backup files.
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
 * BackupScanner class.
 *
 * @since 1.0.0
 */
class BackupScanner {
	/**
	 * Backup directory path.
	 *
	 * @var string
	 */
	private $backup_dir;

	/**
	 * Constructor.
	 *
	 * @param string $backup_dir Backup directory path.
	 */
	public function __construct( $backup_dir ) {
		$this->backup_dir = trailingslashit( $backup_dir );
	}

	/**
	 * Get all backup files with metadata.
	 *
	 * @return array Array of backup file entries.
	 * @since 1.0.0
	 */
	public function get_backups() {
		$backups = array();

		if ( ! file_exists( $this->backup_dir ) || ! is_dir( $this->backup_dir ) ) {
			return $backups;
		}

		$files = scandir( $this->backup_dir );
		if ( false === $files ) {
			return $backups;
		}

		foreach ( $files as $file ) {
			// Skip hidden files and directories.
			if ( '.' === $file[0] || is_dir( $this->backup_dir . $file ) ) {
				continue;
			}

			// Check if it's a valid backup file.
			$type = $this->detect_file_type( $file );
			if ( 'unknown' === $type ) {
				continue;
			}

			$filepath = $this->backup_dir . $file;

			// Get file metadata.
			$backup_entry = array(
				'filename' => $file,
				'filepath' => $filepath,
				'type'     => $type,
				'size'     => filesize( $filepath ),
				'created'  => filemtime( $filepath ),
				'checksum' => '',
			);

			// For BZEN files, try to extract checksum preview.
			if ( 'bzen' === $type ) {
				$checksum_preview = $this->get_bzen_checksum_preview( $filepath );
				if ( $checksum_preview ) {
					$backup_entry['checksum'] = $checksum_preview;
				}
			}

			$backups[] = $backup_entry;
		}

		// Sort by creation date (newest first).
		usort( $backups, function( $a, $b ) {
			return $b['created'] - $a['created'];
		} );

		return $backups;
	}

	/**
	 * Detect backup file type based on extension.
	 *
	 * @param string $filename Filename.
	 * @return string File type: 'bzen', 'zip', 'sql', 'sqlgz', or 'unknown'.
	 * @since 1.0.0
	 */
	public function detect_file_type( $filename ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		switch ( $extension ) {
			case 'bzen':
				return 'bzen';
			case 'zip':
				return 'zip';
			case 'sql':
				return 'sql';
			case 'gz':
				// Check if it's .sql.gz.
				$basename = pathinfo( $filename, PATHINFO_FILENAME );
				if ( 'sql' === strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) ) ) {
					return 'sqlgz';
				}
				return 'unknown';
			case 'tar':
				// Check if it's .tar.gz.
				$basename = pathinfo( $filename, PATHINFO_FILENAME );
				if ( 'tar' === strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) ) ) {
					return 'targz';
				}
				return 'unknown';
			default:
				return 'unknown';
		}
	}

	/**
	 * Get checksum preview from BZEN file (first 16 characters).
	 *
	 * @param string $filepath Full path to .bzen file.
	 * @return string|false Checksum preview (16 chars) or false on failure.
	 * @since 1.0.0
	 */
	private function get_bzen_checksum_preview( $filepath ) {
		$packager = new BzenPackager();
		$metadata = $packager->read_metadata( $filepath );

		if ( $metadata && isset( $metadata['checksum'] ) ) {
			return substr( $metadata['checksum'], 0, 16 );
		}

		return false;
	}

	/**
	 * Get human-readable file type label.
	 *
	 * @param string $type File type.
	 * @return string Human-readable label.
	 * @since 1.0.0
	 */
	public static function get_type_label( $type ) {
		$labels = array(
			'bzen'   => __( 'BZEN', 'backupzen' ),
			'zip'    => __( 'ZIP', 'backupzen' ),
			'sql'    => __( 'SQL', 'backupzen' ),
			'sqlgz'  => __( 'SQL.GZ', 'backupzen' ),
			'targz'  => __( 'TAR.GZ', 'backupzen' ),
			'unknown' => __( 'Unknown', 'backupzen' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['unknown'];
	}

	/**
	 * Get dashicon class for file type.
	 *
	 * @param string $type File type.
	 * @return string Dashicon class.
	 * @since 1.0.0
	 */
	public static function get_type_icon( $type ) {
		$icons = array(
			'bzen'   => 'admin-tools',
			'zip'    => 'media-archive',
			'sql'    => 'database',
			'sqlgz'  => 'database',
			'targz'  => 'media-archive',
			'unknown' => 'media-default',
		);

		return isset( $icons[ $type ] ) ? $icons[ $type ] : $icons['unknown'];
	}
}

