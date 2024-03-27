<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Libs;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Helpers;
use \stdClass;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Background Process
 *
 * Based on WP Background Processing by Delicious Brains: https://github.com/deliciousbrains/wp-background-processing/blob/master/classes/wp-background-process.php
 */

/**
 * Abstract WPBackgroundProcess class.
 */
abstract class WPBackgroundProcess extends WPAsyncRequest {

	/**
	 * Action
	 *
	 * (default value: 'background_process')
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'background_process';

	/**
	 * Start time of current process.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 * @access protected
	 */
	protected $start_time = 0;

	/**
	 * Cron_hook_identifier
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $cron_hook_identifier;

	/**
	 * Cron_interval_identifier
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $cron_interval_identifier;

	/**
	 * Queue save result
	 *
	 * @var bool
	 */
	protected $save_result = true;

	/**
	 * Initiate new background process
	 */
	public function __construct() {
		parent::__construct();

		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );
	}

	/**
	 * Dispatch
	 *
	 * @access public
	 * @return void
	 */
	public function dispatch() {
		// Schedule the cron healthcheck.
		$this->schedule_event();

		// Perform remote post.
		parent::dispatch();

		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			Builder::log( $this->name . ' Breaking background process due to indexer status: ' . $status, 'debug', 'file', 'bg-process' );
			exit();
		}

		// Wait 15s and redispatch process if queue is not empty and process is not running
		$redispatchProcess = apply_filters( 'dgwt/wcas/indexer/redispatch-not-running-process', true );
		if ( $redispatchProcess ) {
			for ( $i = 1; $i <= 10; $i ++ ) {
				$sleep = $i <= 5 ? 1 : 2;
				sleep( $sleep );

				$isEmpty   = $this->is_queue_empty();
				$isRunning = $this->is_process_running();
				if ( $isEmpty || ( ! $isEmpty && $isRunning ) ) {
					Builder::log( $this->name . ' Breaking redispatch process | Loop: ' . $i . ' | Empty: ' . ( $isEmpty ? 'true' : 'false' ) . ' | Is running: ' . ( $isRunning ? 'true' : 'false' ), 'debug', 'file', 'bg-process' );

					return;
				}

				if ( ! in_array( Builder::getInfo( 'status', Config::getIndexRole() ), array( 'building' ) ) ) {
					return;
				}
			}

			if ( ! $this->is_queue_empty() && ! $this->is_process_running() ) {
				// Set default timeout and blocking, so we can get and check response
				add_filter( $this->identifier . '_post_args', function ( $args ) {
					if ( isset( $args['timeout'] ) ) {
						unset( $args['timeout'] );
					}
					if ( isset( $args['blocking'] ) ) {
						unset( $args['blocking'] );
					}
					// Prevent error: "400 Bad Request: Request Header Or Cookie Too Large"
					if ( isset( $args['cookies'] ) && is_array( $args['cookies'] ) ) {
						foreach ( $args['cookies'] as $index => $cookie ) {
							if ( strlen( $cookie ) > 250 ) {
								$args['cookies'][ $index ] = '';
							}
						}
					}

					return $args;
				} );

				Builder::log( $this->name . ' Redispatching process', 'debug', 'file', 'bg-process' );

				$response = parent::dispatch();
				if ( wp_remote_retrieve_response_code( $response ) !== 200 || is_wp_error( $response ) ) {
					if ( is_wp_error( $response ) ) {
						Builder::log( $this->name . ' Redispatch process error: ' . $response->get_error_message() . ' | Code: ' . $response->get_error_code(), 'debug', 'file', 'bg-process' );
					} else {
						Builder::log( $this->name . ' Redispatch process error response | Code: ' . wp_remote_retrieve_response_code( $response ) . ' | Message: ' . wp_remote_retrieve_response_message( $response ) . ' | Body: ' . substr( wp_remote_retrieve_body( $response ), 0, 1000 ) . '', 'debug', 'file', 'bg-process' );
					}
				}
			}
		}
	}

	/**
	 * Push to queue
	 *
	 * @param mixed $data Data.
	 *
	 * @return $this
	 */
	public function push_to_queue( $data ) {
		$this->data[] = $data;

		return $this;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		$key = $this->generate_key();

		if ( ! empty( $this->data ) ) {
			$this->save_result = update_site_option( $key, $this->data );
		}

		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string $key Key.
	 * @param array $data Data.
	 *
	 * @return $this
	 */
	public function update( $key, $data ) {
		if ( ! empty( $data ) ) {
			update_site_option( $key, $data );
		}

		return $this;
	}

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 *
	 * @return $this
	 */
	public function delete( $key ) {
		delete_site_option( $key );

		return $this;
	}

	/**
	 * Generate key
	 *
	 * Generates a unique key based on microtime. Queue items are
	 * given a unique key so that they can be merged upon save.
	 *
	 * @param int $length Length.
	 *
	 * @return string
	 */
	protected function generate_key( $length = 64 ) {
		$unique  = md5( microtime() . rand() );
		$prepend = $this->identifier . '_batch_';

		return substr( $prepend . $unique, 0, $length );
	}

	/**
	 * Maybe process queue
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing
		session_write_close();

		Builder::log( $this->name . ' Maybe handle process', 'debug', 'file', 'bg-process' );

		if ( $this->is_process_running() ) {
			Builder::log( $this->name . ' Maybe handle process. Stop: background process already running', 'debug', 'file', 'bg-process' );
			// Background process already running.
			wp_die();
		}

		if ( $this->is_queue_empty() ) {
			Builder::log( $this->name . ' Maybe handle process. Stop: no data to process', 'debug', 'file', 'bg-process' );
			// No data to process.
			wp_die();
		}

		$check_nonce = check_ajax_referer( $this->identifier, 'nonce', false );
		if ( ! $check_nonce ) {
			Builder::log( $this->name . ' Maybe handle process. Stop: invalid nonce', 'debug', 'file', 'bg-process' );

			if ( wp_doing_ajax() ) {
				wp_die( - 1, 403 );
			} else {
				die( '-1' );
			}
		}

		$this->handle();

		wp_die();
	}

	/**
	 * Get queue save result
	 *
	 * @return bool
	 */
	public function get_save_result() {
		return $this->save_result;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT SQL_NO_CACHE COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", $key ) );

		return ( $count > 0 ) ? false : true;
	}

	/**
	 * Is process running
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 *
	 * @return bool
	 */
	protected function is_process_running() {
		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

		if ( Helpers::isLockLocked__premium_only( $this->identifier . '_process_lock', $lock_duration ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 */
	public function lock_process() {
		$this->start_time = time(); // Set start time of current process.

		Helpers::setLock__premium_only( $this->identifier . '_process_lock' );
	}

	/**
	 * Update process lock
	 */
	public function update_lock() {
		Helpers::setLock__premium_only( $this->identifier . '_process_lock' );
	}

	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @return $this
	 */
	protected function unlock_process() {
		Helpers::unlockLock__premium_only( $this->identifier . '_process_lock' );

		return $this;
	}

	/**
	 * Get batch
	 *
	 * @return stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		global $wpdb;

		$table        = $wpdb->options;
		$column       = 'option_name';
		$key_column   = 'option_id';
		$value_column = 'option_value';

		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$key_column   = 'meta_id';
			$value_column = 'meta_value';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$query = $wpdb->get_row( $wpdb->prepare( "
			SELECT SQL_NO_CACHE *
			FROM {$table}
			WHERE {$column} LIKE %s
			ORDER BY {$key_column} ASC
			LIMIT 1
		", $key ) );

		$batch       = new stdClass();
		$batch->key  = $query->$column;
		$batch->data = maybe_unserialize( $query->$value_column );

		return $batch;
	}

	/**
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 */
	protected function handle() {
		$this->lock_process();

		Builder::log( $this->name . ' Locking and handling process', 'debug', 'file', 'bg-process' );

		do {
			$batch = $this->get_batch();

			foreach ( $batch->data as $key => $value ) {
				$task = $this->task( $value );

				if ( false !== $task ) {
					$batch->data[ $key ] = $task;
				} else {
					unset( $batch->data[ $key ] );
				}

				if ( $this->time_exceeded() || $this->memory_exceeded() ) {
					// Batch limits reached.
					break;
				}
			}

			// Update or delete current batch.
			if ( ! empty( $batch->data ) ) {
				$this->update( $batch->key, $batch->data );
			} else {
				$this->delete( $batch->key );
			}
		} while ( ! $this->time_exceeded( true ) && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

		$this->unlock_process();

		// Start next batch or complete process.
		if ( ! $this->is_queue_empty() ) {
			Builder::log( $this->name . ' Process handled and unlocked. Queue is not empty. Dispatching next process', 'debug', 'file', 'bg-process' );
			$this->dispatch();
		} else {
			Builder::log( $this->name . ' Process handled and unlocked. Queue is empty. Complete', 'debug', 'file', 'bg-process' );
			$this->complete();
		}

		wp_die();
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			Builder::log( $this->name . ' Batch memory exceeded: ' . size_format( $current_memory ) . '/' . size_format( $memory_limit ), 'debug', 'file', 'bg-process' );
			$return = true;
		}

		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @return bool
	 */
	protected function time_exceeded( $log = false ) {
		$finish = $this->start_time + apply_filters( $this->identifier . '_default_time_limit', 20 ); // 20 seconds
		$return = false;

		if ( time() >= $finish ) {
			if ( $log ) {
				Builder::log( $this->name . ' Batch time exceeded: ' . ( time() - $this->start_time ) . '/' . apply_filters( $this->identifier . '_default_time_limit', 20 ) . 's', 'debug', 'file', 'bg-process' );
			}
			$return = true;
		}

		return apply_filters( $this->identifier . '_time_exceeded', $return );
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		// Unschedule the cron healthcheck.
		$this->clear_scheduled_event();
	}

	/**
	 * Schedule cron healthcheck
	 *
	 * @access public
	 *
	 * @param mixed $schedules Schedules.
	 *
	 * @return mixed
	 */
	public function schedule_cron_healthcheck( $schedules ) {
		$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

		if ( property_exists( $this, 'cron_interval' ) ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval );
		}

		// Adds every 5 minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = array(
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display'  => sprintf( __( 'Every %d Minutes' ), $interval ),
		);

		return $schedules;
	}

	/**
	 * Handle cron healthcheck
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_healthcheck() {
		for ( $i = 0; $i < 2; $i ++ ) {
			if ( $this->is_process_running() ) {
				// Background process already running.
				exit;
			}
			sleep( 2 );
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			exit;
		}

		if ( Builder::isIndexerWorkingTooLong( 80 ) ) {
			Builder::log( $this->name . ' Handle process via cron healthcheck', 'debug', 'file', 'bg-process' );

			$this->handle();
		}

		exit;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
		}
	}

	/**
	 * Cancel Process
	 *
	 * Stop processing queue items, clear cronjob and delete batch.
	 *
	 */
	public function cancel_process() {
		if ( ! $this->is_queue_empty() ) {
			$batch = $this->get_batch();

			$this->delete( $batch->key );
		}

		wp_clear_scheduled_hook( $this->cron_hook_identifier );

		$this->unlock_process();
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	abstract protected function task( $item );

}
