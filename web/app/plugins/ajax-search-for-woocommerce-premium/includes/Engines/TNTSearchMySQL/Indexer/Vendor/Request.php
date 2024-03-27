<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Vendor;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Request {

	public static function handle() {

		@set_time_limit( 3600 );

		self::build();

		return false;
	}

	private static function build() {
		Builder::log( '[Vendors index] Building...' );

		$indexer = new Indexer;
		$status  = $indexer->build();

		$level = 'info';
		if (
			( empty( $status['success'] ) && empty( $status['error'] ) )
			|| $status['error'] > 0
		) {
			$level = 'warning';
		}
		Builder::log( sprintf( '[Vendors index] Completed: %s, Not indexed: %s', $status['success'], $status['error'] ), $level );
	}

}
