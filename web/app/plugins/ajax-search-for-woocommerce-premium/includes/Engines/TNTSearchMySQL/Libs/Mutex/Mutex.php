<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Libs\Mutex;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Mutex {
	/**
	 * Try to set lock and return true on success
	 *
	 * @return bool
	 */
	public function acquire();

	/**
	 * Release lock
	 *
	 * @return void
	 */
	public function release();
}
