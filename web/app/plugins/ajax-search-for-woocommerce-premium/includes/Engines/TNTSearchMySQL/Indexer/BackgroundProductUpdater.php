<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Indexer as IndexerS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Indexer as IndexerR;
use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BackgroundProductUpdater {

	public function init() {
		add_action( 'dgwt/wcas/tnt/background_product_update', array( __CLASS__, 'handle' ), 10, 2 );
	}

	/**
	 * Update product in index
	 *
	 * @param string $action Action
	 * @param int $postID Product ID
	 */
	public static function handle( $action, $postID ) {
		if ( empty( $postID ) ) {
			return;
		}
		if ( intval( $postID ) <= 0 ) {
			return;
		}

		$indexerR = new IndexerR;
		// We always update the product in the "main" index.
		$indexerS = new IndexerS( array( 'index_role' => 'main' ) );

		switch ( $action ) {
			case 'update':
				$indexerR->update( $postID, true );
				$indexerS->update( $postID );
				break;
			case 'delete':
				try {
					$indexerR->delete( $postID );
				} catch ( \Error $e ) {
					Logger::handleUpdaterThrowableError( $e, '[Readable index] ' );
				} catch ( \Exception $e ) {
					Logger::handleUpdaterThrowableError( $e, '[Readable index] ' );
				}
				$indexerS->delete( $postID );
				break;
		}

		sleep( 1 );
	}

	/**
	 * Schedule to update or delete product in background
	 *
	 * @param string $action Action
	 * @param int $postID Product ID
	 */
	public static function schedule( $action, $postID ) {
		$queue = Utils::getQueue();
		if ( empty( $queue ) ) {
			return;
		}
		// Skip if index isn't yet completed
		if ( Builder::getInfo( 'status' ) !== 'completed' ) {
			$queue->cancel_all( 'dgwt/wcas/tnt/background_product_update' );

			return;
		}
		// Skip if triggered from order
		if ( Helpers::is_running_inside_class( 'WC_Order', 20 ) && $action !== 'delete' ) {
			return;
		}

		// Check if there is task scheduled for this product
		$scheduledUpdates = $queue->search( array(
			'hook'   => 'dgwt/wcas/tnt/background_product_update',
			'args'   => array( 'action' => $action, 'postID' => $postID ),
			'status' => 'pending',
		) );

		if ( empty( $scheduledUpdates ) ) {
			// Preventing creation of too large queue of products to update in the index
			$allScheduledUpdates = $queue->search( array(
				'hook'     => 'dgwt/wcas/tnt/background_product_update',
				'status'   => 'pending',
				'per_page' => - 1
			) );
			$maxScheduledUpdates = apply_filters( 'dgwt/wcas/tnt/max_scheduled_updates', 30 );
			if ( count( $allScheduledUpdates ) < $maxScheduledUpdates ) {
				$queue->add( 'dgwt/wcas/tnt/background_product_update', array(
					'action' => $action,
					'postID' => $postID
				) );
			}
		}
	}
}
