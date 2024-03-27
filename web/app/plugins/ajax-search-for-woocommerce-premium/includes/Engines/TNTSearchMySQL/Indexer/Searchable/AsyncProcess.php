<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Logger;
use DgoraWcas\Engines\TNTSearchMySQL\Libs\WPBackgroundProcess;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AsyncProcess extends WPBackgroundProcess {

	/**
	 * @var string
	 */
	protected $prefix = 'wcas';

	/**
	 * @var string
	 */
	protected $action = 'build_searchable_index';

	/**
	 * @var string
	 */
	protected $name = '[Searchable index]';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	public function task( $itemsSet ) {
		if ( ! defined( 'DGWT_WCAS_SEARCHABLE_INDEX_TASK' ) ) {
			define( 'DGWT_WCAS_SEARCHABLE_INDEX_TASK', true );
		}

		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return false;
			}
			Builder::log( '[Searchable index] Breaking async task due to indexer status: ' . $status, 'debug', 'file', 'searchable' );
			exit();
		}

		Builder::log( '[Searchable index] Starting async task. Items set count: ' . count( $itemsSet ), 'debug', 'file', 'searchable' );

		do_action( 'dgwt/wcas/searchable_index/bg_processing/before_task' );

		register_shutdown_function( function () {
			Logger::maybeLogError( '[Searchable index] ' );
		} );

		try {
			$indexer = new Indexer;
			$indexer->indexSet( $itemsSet, array( $this, 'update_lock' ) );
		} catch ( \Error $e ) {
			Logger::handleThrowableError( $e, '[Searchable index] ', array( 'rollback' => true ) );
		} catch ( \Exception $e ) {
			Logger::handleThrowableError( $e, '[Searchable index] ', array( 'rollback' => true ) );
		}

		Builder::log( '[Searchable index] Finished processing items set', 'debug', 'file', 'searchable' );

		return false;
	}

	public function is_other_process() {
		return ! $this->is_queue_empty();
	}

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 *
	 * @return $this
	 */
	public function delete( $key ) {
		if ( delete_site_option( $key ) ) {
			Builder::log( sprintf( '[Searchable index] The queue <code>%s</code> was deleted ', $key ), 'debug', 'file' );
		};

		return $this;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			if ( wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier ) !== false ) {
				Builder::log( sprintf( '[Searchable index] Schedule <code>%s</code> was created ', $this->cron_hook_identifier ), 'debug', 'file' );
			}
		}
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
			Builder::log( sprintf( '[Searchable index] The queue <code>%s</code> was created', $key ), 'debug', 'file' );
		}

		return $this;
	}

	/**
	 * Dispatch job is queue is not empty
	 */
	public function maybe_dispatch() {
		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			Builder::log( '[Searchable index] Breaking async task dispatch due to indexer status: ' . $status, 'debug', 'file', 'searchable' );
			exit();
		}

		if ( $this->is_queue_empty() ) {
			$this->complete();
		} else {
			$this->data( array() );
			$this->dispatch();
		}
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	public function complete() {
		parent::complete();

		Builder::addInfo( 'end_searchable_ts', time() );
		Builder::log( '[Searchable index] Completed' );

		if ( Config::isIndexerMode( 'sync' ) ) {
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->maybe_dispatch();
		} else {
			sleep( 1 );
			Builder::maybeMarkAsCompleted();
		}
	}
}
