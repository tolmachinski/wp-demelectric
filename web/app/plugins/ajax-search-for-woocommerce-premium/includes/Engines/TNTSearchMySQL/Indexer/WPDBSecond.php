<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

class WPDBSecond extends WPDB {
	public static function get_instance() {
		if ( isset( self::$selfSecond ) ) {
			return self::$selfSecond;
		}

		self::$selfSecond = new self();

		$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
		$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
		$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
		$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

		self::$selfSecond->db = new \wpdb( $dbuser, $dbpassword, $dbname, $dbhost );

		return self::$selfSecond;
	}
}
