<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Admin\Install;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IndexPusher {

	const HOOK = 'dgwt/wcas/indexer/index_pusher';

	public function init() {
		add_action( self::HOOK, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Try to rebuild the search index if it is necessary
	 *
	 * @return void
	 */
	public static function handle() {
		Install::checkVersion();
	}

	/**
	 * Schedule to index pusher
	 *
	 * @return void
	 */
	public static function schedule() {
		$queue = Utils::getQueue();
		if ( empty( $queue ) ) {
			return;
		}

		// Check if there is task scheduled for this product
		$isScheduled = $queue->search( array(
			'hook'   => self::HOOK,
			'status' => 'pending',
		) );

		if ( empty( $isScheduled ) ) {
			$queue->add( self::HOOK );
		}
	}
}
