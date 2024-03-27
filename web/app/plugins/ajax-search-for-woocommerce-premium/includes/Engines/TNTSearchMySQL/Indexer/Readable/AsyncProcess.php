<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Logger;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Vendor;
use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Libs\WPBackgroundProcess;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AsyncProcess extends WPBackgroundProcess {

	const CANCELING_PROCESS_TRANSIENT = 'dgwt_wcas_canceling_r_index_build';

	/**
	 * @var string
	 */
	protected $prefix = 'wcas';

	/**
	 * @var string
	 */
	protected $action = 'build_readable_index';

	/**
	 * @var string
	 */
	protected $name = '[Readable index]';

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
		if ( ! defined( 'DGWT_WCAS_READABLE_INDEX_TASK' ) ) {
			define( 'DGWT_WCAS_READABLE_INDEX_TASK', true );
		}

		Builder::log( '[Readable index] Starting async task. Items set count: ' . count( $itemsSet ), 'debug', 'file', 'readable' );

		do_action( 'dgwt/wcas/readable_index/bg_processing/before_task' );

		register_shutdown_function( function () {
			Logger::maybeLogError( '[Readable index] ' );
		} );

		$updateLockTimer   = microtime( true );

		$indexer = new Indexer;

		foreach ( $itemsSet as $itemID ) {
			$status = Builder::getInfo( 'status', Config::getIndexRole() );
			if ( $status !== 'building' ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					return false;
				}
				Builder::log( '[Readable index] Breaking async task due to indexer status: ' . $status, 'debug', 'file', 'readable' );
				exit();
			}

			try {
				$indexer->insert( $itemID );
				// Update the background process lock timer no more frequently than every 5 seconds.
				if ( microtime( true ) - $updateLockTimer > 5 ) {
					$updateLockTimer = microtime( true );
					$this->update_lock();
				}
			} catch ( \Error $e ) {
				Logger::handleThrowableError( $e, '[Readable index] ' );
			} catch ( \Exception $e ) {
				Logger::handleThrowableError( $e, '[Readable index] ' );
			}
		}

		$readableProcessedPrev = absint( Builder::getInfo( 'readable_processed', Config::getIndexRole() ) );
		$readableProcessed     = $readableProcessedPrev + count( $itemsSet );
		Builder::addInfo( 'readable_processed', $readableProcessed );

		// Log only hundreds
		if ( $readableProcessedPrev > 0 && $readableProcessed > 0 && $readableProcessedPrev % 100 > $readableProcessed % 100 ) {
			Builder::log( "[Readable index] Processed $readableProcessed objects" );
		}

		// Save queue or trigger complete
		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_sku' ) === 'on' ) {
			if ( Config::isIndexerMode( 'direct' ) ) {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->complete();
			} else {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->save();
				// Clear data for another task of readable index
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->data( array() );
			}
		}

		Builder::log( '[Readable index] Finished processing items set', 'debug', 'file', 'readable' );

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
			Builder::log( sprintf( '[Readable index] The queue <code>%s</code> was deleted ', $key ), 'debug', 'file' );
		};

		return $this;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			if ( wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier ) !== false ) {
				Builder::log( sprintf( '[Readable index] Schedule <code>%s</code> was created ', $this->cron_hook_identifier ), 'debug', 'file' );
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
			Builder::log( sprintf( '[Readable index] The queue <code>%s</code> was created', $key ), 'debug', 'file' );
		}

		return $this;
	}

	/**
	 * Dispatch job is queue is not empty
	 */
	public function maybe_dispatch() {
		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			Builder::log( '[Readable index] Breaking async task dispatch due to indexer status: ' . $status, 'debug', 'file', 'readable' );
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

		$readableProcessed = absint( Builder::getInfo( 'readable_processed', Config::getIndexRole() ) );
		Builder::log( "[Readable index] Processed $readableProcessed objects" );

		Builder::addInfo( 'end_readable_ts', time() );
		Builder::log( '[Readable index] Completed' );

		// Build vendor index if necessary
		if ( Builder::canBuildVendorsIndex() ) {
			Vendor\Database::create( Config::getIndexRoleSuffix() );
			Vendor\Request::handle();
		}

		Builder::maybeDispatchTaxonomyAsyncProcess();

		sleep( 1 );
		Builder::maybeMarkAsCompleted();

	}
}
