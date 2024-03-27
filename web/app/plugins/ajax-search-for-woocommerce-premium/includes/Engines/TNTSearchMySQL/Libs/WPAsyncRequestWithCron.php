<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Libs;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Async Request With Cron
 */

/**
 * Abstract WPAsyncRequestWithCron class.
 */
abstract class WPAsyncRequestWithCron extends WPAsyncRequest {

	/**
	 * Action
	 *
	 * (default value: 'async_request')
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'async_request_with_cron';

	/**
	 * Cron_hook_identifier
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $cron_hook_identifier;

	/**
	 * Initiate new async request
	 */
	public function __construct() {
		parent::__construct();

		$this->cron_hook_identifier = $this->identifier . '_cron';

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
	}

	/**
	 * Maybe handle
	 *
	 * Check for correct nonce and pass to handler.
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing
		session_write_close();

		check_ajax_referer( $this->identifier, 'nonce' );

		$rawPostData = @file_get_contents( 'php://input' );
		if ( $rawPostData !== false ) {
			parse_str( $rawPostData, $params );
			$this->data = $params;
		}

		// Clear running task via cron as fallback
		$this->clear_scheduled_event();

		$this->handle();

		wp_die();
	}

	/**
	 * Handle cron healthcheck
	 */
	public function handle_cron_healthcheck() {
		Builder::log( $this->name . ' Handle async request via cron healthcheck', 'debug', 'file', 'bg-process' );

		$this->handle();

		exit;
	}

	/**
	 * Schedule event
	 *
	 * @return $this
	 */
	public function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_single_event( time() + 60, $this->cron_hook_identifier );
		}

		return $this;
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
}
