<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Libs\WPAsyncRequestWithCron;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AsyncRebuildIndex extends WPAsyncRequestWithCron {
	/**
	 * Prefix
	 * @var string
	 */
	protected $prefix = 'wcas';

	/**
	 * @var string
	 */
	protected $action = 'async_rebuild_index';

	/**
	 * @var string
	 */
	protected $name = '[Indexer]';

	/**
	 * Handle
	 */
	protected function handle() {
		if ( Builder::getInfo( 'status', Config::getIndexRole() ) === 'completed' || isset( $this->data['force'] ) ) {
			Builder::buildIndexProcess();
		}
	}

	/**
	 * Handle cron healthcheck
	 */
	public function handle_cron_healthcheck() {
		Builder::log( $this->name . ' Handle async request via cron healthcheck', 'debug', 'file', 'bg-process' );

		// The data is not stored in the database, so we have to pass it in again in cron
		$this->data( array( 'force' => true ) );

		$this->handle();

		exit;
	}
}
