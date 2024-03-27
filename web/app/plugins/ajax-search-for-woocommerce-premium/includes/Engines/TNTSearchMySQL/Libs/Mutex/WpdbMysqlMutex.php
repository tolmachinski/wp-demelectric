<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Libs\Mutex;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database driven mutex
 *
 * Attention: In some hosting environments, this mechanism does not work and should not be used for critical operations.
 */
class WpdbMysqlMutex implements Mutex {
	/**
	 * @var string Name of the lock
	 */
	private $name;

	/**
	 * @var int Wait for lock in seconds
	 */
	private $lockTimeout;

	public function __construct( $name = 'mutex', $lockTimeout = 5 ) {
		global $wpdb;

		$this->name        = $wpdb->_real_escape( $name );
		$this->lockTimeout = intval( $lockTimeout );
	}


	/**
	 * Try to set lock and return true on success
	 *
	 * @return bool
	 */
	public function acquire() {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT GET_LOCK(%s,%d) as set_lock', $this->name, $this->lockTimeout )
		);

		return intval( $row->set_lock ) === 1;
	}

	/**
	 * Release lock
	 *
	 * @return void
	 */
	public function release() {
		global $wpdb;

		$wpdb->get_row(
			$wpdb->prepare( 'SELECT RELEASE_LOCK(%s) as lock_released', $this->name )
		);
	}

	/**
	 * Check if mutex is locked
	 *
	 * @return bool
	 */
	public function is_locked() {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT IS_USED_LOCK(%s) as is_used_lock', $this->name )
		);

		return ! is_null( $row->is_used_lock );
	}
}
