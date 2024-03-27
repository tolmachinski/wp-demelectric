<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Vendor;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Cache;
use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	/**
	 * Add table names to the $wpdb object
	 *
	 * @return void
	 */
	public static function registerTables() {
		global $wpdb;

		$wpdb->dgwt_wcas_ven_index = $wpdb->prefix . Config::VENDORS_INDEX;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_ven_index ) ) {
			$wpdb->tables[] = Config::VENDORS_INDEX;
		}
	}

	/**
	 * Install DB table
	 *
	 * @return void
	 */
	private static function install( $indexRoleSuffix = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		$upFile = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( file_exists( $upFile ) ) {

			require_once( $upFile );

			$collate = Utils::getCollate( 'vendor/main' );

			$tableName = $wpdb->dgwt_wcas_ven_index . $indexRoleSuffix;
			$table = "CREATE TABLE $tableName (
				vendor_id         BIGINT(20) UNSIGNED NOT NULL,
		        shop_name         VARCHAR(100) NOT NULL,
				shop_city         VARCHAR(100) NOT NULL,
				shop_description  TEXT NOT NULL,
				shop_url          TEXT NOT NULL,
				shop_image        TEXT NOT NULL,
				PRIMARY KEY (vendor_id)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC $collate;";

			dbDelta( $table );
		}
	}

	/**
	 * Create database structure from the scratch
	 *
	 * @return void
	 */
	public static function create( $indexRoleSuffix = '' ) {
		self::install( $indexRoleSuffix );
	}

	/**
	 * Remove DB table
	 *
	 * @return void
	 */
	public static function remove( $indexRoleSuffix = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		$tableName = $wpdb->dgwt_wcas_ven_index . $indexRoleSuffix;

		$wpdb->query( "DROP TABLE IF EXISTS $tableName" );

		Cache::delete( 'table_exists', 'database' );
	}
}
