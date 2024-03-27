<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation;

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
	protected $action = 'build_variation_index';

	/**
	 * @var string
	 */
	protected $name = '[Variation index]';

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
		if ( ! defined( 'DGWT_WCAS_VARIATIONS_INDEX_TASK' ) ) {
			define( 'DGWT_WCAS_VARIATIONS_INDEX_TASK', true );
		}

		Builder::log( '[Variation index] Starting async task. Items set count: ' . count( $itemsSet ), 'debug', 'file', 'variation' );

		do_action( 'dgwt/wcas/variation_index/bg_processing/before_task' );

		register_shutdown_function( function () {
			Logger::maybeLogError( '[Variation index] ' );
		} );

		$indexer = new Indexer;

		foreach ( $itemsSet as $itemID ) {
			$status = Builder::getInfo( 'status', Config::getIndexRole() );
			if ( $status !== 'building' ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					return false;
				}
				Builder::log( '[Variation index] Breaking async task due to indexer status: ' . $status, 'debug', 'file', 'variation' );
				exit();
			}

			try {
				$indexer->maybeIndex( $itemID );
			} catch ( \Error $e ) {
				Logger::handleThrowableError( $e, '[Variation index] ' );
			} catch ( \Exception $e ) {
				Logger::handleThrowableError( $e, '[Variation index] ' );
			}
		}

		$variationsProcessedPrev = absint( Builder::getInfo( 'variations_processed', Config::getIndexRole() ) );
		$variationsProcessed     = $variationsProcessedPrev + count( $itemsSet );
		Builder::addInfo( 'variations_processed', $variationsProcessed );

		// Log only hundreds
		if ( $variationsProcessedPrev > 0 && $variationsProcessed > 0 && $variationsProcessedPrev % 100 > $variationsProcessed % 100 ) {
			Builder::log( "[Variation index] Processed $variationsProcessed objects" );
		}

		Builder::log( '[Variation index] Finished processing items set', 'debug', 'file', 'variation' );

		return false;
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
			Builder::log( sprintf( '[Variation index] The queue <code>%s</code> was deleted ', $key ), 'debug', 'file' );
		};

		return $this;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			if ( wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier ) !== false ) {
				Builder::log( sprintf( '[Variation index] Schedule <code>%s</code> was created ', $this->cron_hook_identifier ), 'debug', 'file' );
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
			update_site_option( $key, $this->data );
			Builder::log( sprintf( '[Variation index] The queue <code>%s</code> was created', $key ), 'debug', 'file' );
		}

		return $this;
	}

	/**
	 * Dispatch job is queue is not empty
	 */
	public function maybe_dispatch() {
		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			Builder::log( '[Variation index] Breaking async task dispatch due to indexer status: ' . $status, 'debug', 'file', 'variation' );
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

		$variationsProcessed = absint( Builder::getInfo( 'variations_processed', Config::getIndexRole() ) );
		Builder::log( "[Variation index] Processed $variationsProcessed objects" );

		Builder::addInfo( 'end_variation_ts', time() );
		Builder::log( '[Variation index] Completed' );

		sleep( 1 );
		Builder::maybeMarkAsCompleted();
	}
}
