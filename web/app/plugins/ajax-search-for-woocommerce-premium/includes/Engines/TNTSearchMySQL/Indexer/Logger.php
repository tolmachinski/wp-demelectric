<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

// Exit if accessed directly
use DgoraWcas\Engines\TNTSearchMySQL\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {
	const INDEXER_SOURCE         = 'fibosearch-indexer';
	const INDEXER_FAILURE_SOURCE = 'fibosearch-fail-indexer';
	const UPDATER_SOURCE         = 'fibosearch-updater';

	/**
	 * @var \WC_Log_Handler_File
	 */
	private static $wcLogHandlerFile;

	public static $memory;

	/**
	 * Init WooCommerce log file handler
	 */
	private static function maybeInit() {
		if ( self::$wcLogHandlerFile === null ) {
			self::$wcLogHandlerFile = new \WC_Log_Handler_File();
		}
	}

	/**
	 * Log message to file
	 *
	 * @param string $message
	 * @param string $level
	 * @param string $scope
	 * @param array $context
	 *
	 * @return void
	 */
	public static function log( $message, $level, $scope = 'all', $context = array() ) {
		if ( ! \WC_Log_Levels::is_valid_level( $level ) ) {
			_doing_it_wrong( __METHOD__, sprintf( __( '%1$s was called with an invalid level "%2$s".', 'ajax-search-for-woocommerce' ), '<code>Logger::log</code>', $level ), '1.10' );
		}

		// Skip debug messages is debugging for indexer is disabled or scope is not enabled
		if (
			( $level === 'debug' && ! Builder::isDebug() )
			|| ( $level === 'debug' && ! Builder::isDebugScopeActive( $scope ) )
		) {
			return;
		}

		self::maybeInit();

		$context = wp_parse_args(
			$context,
			array(
				'source' => self::INDEXER_SOURCE
			)
		);

		$timestamp = current_time( 'timestamp' );
		$message   = strip_tags( $message );

		// Add process ID to every message
		if ( function_exists( 'ini_get' ) && strpos( ini_get( 'disable_functions' ), 'getmypid' ) === false ) {
			$message = '[' . getmypid() . '] ' . $message;
		}

		self::$wcLogHandlerFile->handle( $timestamp, $level, $message, $context );
	}

	/**
	 * Remove logs from /wc-logs
	 *
	 * @param string $source
	 */
	public static function removeLogs( $source = Logger::INDEXER_SOURCE ) {
		self::maybeInit();

		$files = self::$wcLogHandlerFile->get_log_files();
		foreach ( $files as $handle => $filename ) {
			if ( strpos( $handle, $source ) !== false ) {
				self::$wcLogHandlerFile->remove( $handle );
			}
		}
	}

	/**
	 * Logging of the most recent error
	 *
	 * Used in conjunction with register_shutdown_function() function
	 *
	 * @param string $prefix
	 */
	public static function maybeLogError( $prefix = '' ) {
		$error = error_get_last();
		if ( is_null( $error ) ) {
			return;
		}

		$errorCode = self::getErrorCode( $error['message'] );

		$errorString = $prefix . ( $errorCode ? $errorCode . ' ' : '' ) . $error['message'];
		$errorString .= ' | Type: ' . self::codeToString( $error['type'] );
		$errorString .= ' | File: ' . $error['file'] . ':' . $error['line'];

		if ( in_array(
			$error['type'],
			array(
				E_NOTICE,
				E_WARNING,
				E_CORE_WARNING,
				E_COMPILE_WARNING,
				E_USER_WARNING,
				E_USER_NOTICE,
				E_STRICT,
				E_DEPRECATED,
				E_USER_DEPRECATED,
			)
		) ) {

			$level = 'warning';
			switch ( $error['type'] ) {
				case E_NOTICE:
				case E_USER_NOTICE:
				case E_STRICT:
				case E_DEPRECATED:
				case E_USER_DEPRECATED:
					$level = 'notice';
					break;
				case E_WARNING:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_USER_WARNING:
					$level = 'warning';
					break;
			}

			Builder::log( $errorString, $level, 'file' );

		} else {

			Builder::log( $errorString, 'emergency', 'both' );

			Builder::addInfo( 'status', 'error' );
			Builder::log( 'Stop building the index. Starting the cancellation process.' );
			Builder::cancelBuildIndex();
			do_action( 'dgwt/wcas/indexer/status/error' );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return;
			}

			sleep( 3 );
			exit();
		}
	}

	public static function handleThrowableError( $e, $prefix = '', $args = array() ) {
		$errorCode = self::getErrorCode( $e->getMessage() );

		$args = wp_parse_args( $args, array(
			'rollback' => false,
		) );

		$errorString = $prefix . ( $errorCode ? $errorCode . ' ' : '' ) . $e->getMessage();
		$errorString .= ' | Type: ' . self::codeToString( $e->getCode() );
		$errorString .= ' | File: ' . $e->getFile();
		$errorString .= ' | Trace: ' . $e->getTraceAsString();

		$level     = 'emergency';
		$errorType = self::getNonCriticalErrorType( $e->getMessage() );

		if ( $errorType !== false ) {
			$level = 'warning';

			// Store a given error type only once in the database
			$nonCriticalErrors = Builder::getInfo( 'non_critical_errors', Config::getIndexRole() );

			if ( ! isset( $nonCriticalErrors[ $errorType ] ) ) {
				$nonCriticalErrors[ $errorType ] = $errorString;
				Builder::addInfo( 'non_critical_errors', $nonCriticalErrors );

				Builder::log( $errorString, $level, 'both' );
			} else {
				Builder::log( $errorString, $level, 'file' );
			}

		} else {
			if ( Builder::getInfo( 'status', Config::getIndexRole() ) !== 'building' ) {
				Builder::log( 'Critical error handling is omitted because the index is not being built', 'debug', 'file' );
				exit();
			}

			Builder::log( $errorString, $level, 'both' );

			if ( $args['rollback'] ) {
				WPDBSecond::get_instance()->rollback();
			}

			Builder::addInfo( 'status', 'error' );
			Builder::log( 'Stop building the index. Starting the cancellation process.' );
			Builder::cancelBuildIndex();
			do_action( 'dgwt/wcas/indexer/status/error' );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return;
			}

			sleep( 3 );
			exit();
		}
	}

	/**
	 * @param \Throwable $e
	 * @param string $prefix
	 */
	public static function handleUpdaterThrowableError( \Throwable $e, string $prefix = '' ) {
		$errorCode = self::getErrorCode( $e->getMessage() );

		$errorString = $prefix . ( $errorCode ? $errorCode . ' ' : '' ) . $e->getMessage();

		if ( $type = self::codeToString( $e->getCode() ) ) {
			$errorString .= ' | Type: ' . $type;
		}
		$errorString .= ' | File: ' . $e->getFile();
		$errorString .= ' | Trace: ' . $e->getTraceAsString();

		$level     = 'emergency';
		$errorType = self::getNonCriticalErrorType( $e->getMessage() );
		if ( $errorType !== false ) {
			$level = 'warning';
		}

		self::log( $errorString, $level, 'all', [ 'source' => self::UPDATER_SOURCE ] );
	}

	public static function codeToString( $type ) {
		switch ( $type ) {
			case E_ERROR: // 1 //
				return 'E_ERROR';
			case E_WARNING: // 2 //
				return 'E_WARNING';
			case E_PARSE: // 4 //
				return 'E_PARSE';
			case E_NOTICE: // 8 //
				return 'E_NOTICE';
			case E_CORE_ERROR: // 16 //
				return 'E_CORE_ERROR';
			case E_CORE_WARNING: // 32 //
				return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: // 64 //
				return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: // 128 //
				return 'E_COMPILE_WARNING';
			case E_USER_ERROR: // 256 //
				return 'E_USER_ERROR';
			case E_USER_WARNING: // 512 //
				return 'E_USER_WARNING';
			case E_USER_NOTICE: // 1024 //
				return 'E_USER_NOTICE';
			case E_STRICT: // 2048 //
				return 'E_STRICT';
			case E_RECOVERABLE_ERROR: // 4096 //
				return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: // 8192 //
				return 'E_DEPRECATED';
			case E_USER_DEPRECATED: // 16384 //
				return 'E_USER_DEPRECATED';
		}

		return $type;
	}

	/**
	 * Get log filenames
	 *
	 * @param string $source
	 *
	 * @return array Filenames
	 */
	public static function getLogFilenames( $source = Logger::INDEXER_SOURCE ) {
		self::maybeInit();

		$files = self::$wcLogHandlerFile->get_log_files();

		$filenames = array();

		foreach ( $files as $handle => $filename ) {
			if ( strpos( $handle, $source ) !== false ) {
				if ( file_exists( WC_LOG_DIR . $filename ) ) {
					$filenames[] = WC_LOG_DIR . $filename;
				}
			}
		}

		return $filenames;
	}

	public static function copyLastLogs( $source = Logger::INDEXER_SOURCE, $dest = Logger::INDEXER_FAILURE_SOURCE ) {
		$filenames = self::getLogFilenames( $source );

		self::removeLogs( $dest );

		foreach ( $filenames as $filename ) {
			if ( strpos( $filename, $source ) !== false ) {
				$filenameCopy = str_replace( $source, $dest, $filename );
				copy( $filename, $filenameCopy );
			}
		}
	}

	/**
	 * Get code and message of last emergency log from indexer logs
	 *
	 * @return array Code and message
	 */
	public static function getLastEmergencyLog() {
		self::maybeInit();

		$lastErrorCode    = '';
		$lastErrorMessage = '';

		foreach ( self::getLogFilenames() as $filename ) {
			$handle = fopen( $filename, "r" );
			if ( $handle ) {
				while ( ( $line = fgets( $handle ) ) !== false ) {
					if ( strpos( $line, 'EMERGENCY' ) === false ) {
						continue;
					}
					preg_match_all( '/\[Error [C|c]ode: (\d+)\]/m', $line, $matches, PREG_SET_ORDER, 0 );
					if ( isset( $matches[0][1] ) ) {
						$lastErrorCode = (string) $matches[0][1];
					}
					$lastErrorMessage = $line;
				}
				fclose( $handle );
			}
			if ( ! empty( $lastErrorMessage ) ) {
				break;
			}
		}

		return array( $lastErrorCode, $lastErrorMessage );
	}

	/**
	 * Get non critical error type
	 *
	 * @param $message
	 *
	 * @return bool|string False or error type
	 */
	public static function getNonCriticalErrorType( $message ) {
		preg_match_all( '/Call to a member function [a-zA-Z0-9-_\x80\xff]*\(\) on (null|string|bool|array|non-object)/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return 'call-on-non-object';
		}

		preg_match_all( '/Database error \"WordPress database error: Could not perform query because it contains invalid data.\"/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return 'wpdb-invalid-data';
		}

		// We check the translation of this error, because it sometimes appears in logs.
		preg_match_all( '/Database error \"' . __( 'WordPress database error: Could not perform query because it contains invalid data.' ) . '\"/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return 'wpdb-invalid-data';
		}

		return false;
	}

	/**
	 * Get error code by its message
	 *
	 * @param $message
	 *
	 * @return bool|string
	 */
	public static function getErrorCode( $message ) {
		preg_match_all( '/User (.*) already has more than \'max_user_connections\' active connections/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return '[Error code: 003]';
		}
		preg_match_all( '/Too many connections/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return '[Error code: 004]';
		}
		preg_match_all( '/MySQL server has gone away/mi', $message, $matches, PREG_SET_ORDER, 0 );
		if ( isset( $matches[0] ) ) {
			return '[Error code: 005]';
		}

		return false;
	}
}
