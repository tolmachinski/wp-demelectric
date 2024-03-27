<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy;

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
	protected $action = 'build_taxonomy_index';

	/**
	 * @var string
	 */
	protected $name = '[Taxonomy index]';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $itemsSet Queue item to iterate over
	 *
	 * @return mixed
	 */
	public function task( $itemsSet ) {
		if ( ! defined( 'DGWT_WCAS_TAXONOMY_INDEX_TASK' ) ) {
			define( 'DGWT_WCAS_TAXONOMY_INDEX_TASK', true );
		}

		Builder::log( '[Taxonomy index] Starting async task. Items set count: ' . count( $itemsSet ), 'debug', 'file', 'taxonomy' );

		do_action( 'dgwt/wcas/taxonomy_index/bg_processing/before_task' );

		register_shutdown_function( function () {
			Logger::maybeLogError( '[Taxonomy index] ' );
		} );

		$startTime = microtime( true );

		$indexer = new Indexer;

		foreach ( $itemsSet as $item ) {
			$status = Builder::getInfo( 'status', Config::getIndexRole() );
			if ( $status !== 'building' ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					return false;
				}
				Builder::log( '[Taxonomy index] Breaking async task due to indexer status: ' . $status, 'debug', 'file', 'taxonomy' );
				exit();
			}

			try {
				$indexer->index( $item['term_id'], $item['taxonomy'] );
			} catch ( \Error $e ) {
				Logger::handleThrowableError( $e, '[Taxonomy index] ' );
			} catch ( \Exception $e ) {
				Logger::handleThrowableError( $e, '[Taxonomy index] ' );
			}
		}

		$termsProcessed = absint( Builder::getInfo( 'terms_processed', Config::getIndexRole() ) ) + count( $itemsSet );
		Builder::addInfo( 'terms_processed', $termsProcessed );

		$time = number_format( microtime( true ) - $startTime, 4, '.', '' ) . ' s';
		Builder::log( "[Taxonomy index] Processed $termsProcessed terms in $time" );

		Builder::log( '[Taxonomy index] Finished processing items set', 'debug', 'file', 'taxonomy' );

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
			Builder::log( sprintf( '[Taxonomy index] The queue <code>%s</code> was deleted ', $key ), 'debug', 'file' );
		};

		return $this;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			if ( wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier ) !== false ) {
				Builder::log( sprintf( '[Taxonomy index] Schedule <code>%s</code> was created ', $this->cron_hook_identifier ), 'debug', 'file' );
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
			Builder::log( sprintf( '[Taxonomy index] The queue <code>%s</code> was created', $key ), 'debug', 'file' );
		}

		return $this;
	}

	/**
	 * Dispatch job is queue is not empty
	 */
	public function maybe_dispatch() {
		$status = Builder::getInfo( 'status', Config::getIndexRole() );
		if ( $status !== 'building' ) {
			Builder::log( '[Taxonomy index] Breaking async task dispatch due to indexer status: ' . $status, 'debug', 'file', 'taxonomy' );
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

		Builder::addInfo( 'end_taxonomies_ts', time() );
		Builder::log( '[Taxonomy index] Completed' );

		sleep( 1 );
		Builder::maybeDispatchVariationAsyncProcess();

		sleep( 1 );
		Builder::maybeMarkAsCompleted();
	}
}
