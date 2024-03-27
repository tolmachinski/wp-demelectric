<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
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

		$wpdb->dgwt_wcas_index = $wpdb->prefix . Config::READABLE_INDEX;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_index ) ) {
			$wpdb->tables[] = Config::READABLE_INDEX;
		}
	}

	/**
	 * Install DB table
	 *
	 * @return void
	 * @throws WPDBException
	 */
	private static function install( $indexRoleSuffix = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		$upFile = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( file_exists( $upFile ) ) {

			require_once( $upFile );

			$collate = Utils::getCollate( 'readable/main' );

			/**
			 * We use 'id' column because 'post_id' is not always unique.
			 * This happens, for example, with the TranslatePress plugin, when records of different
			 * languages have the same 'post_id'.
			 */
			$tableName = $wpdb->dgwt_wcas_index . $indexRoleSuffix;
			$table = "CREATE TABLE $tableName (
				id         		BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id         BIGINT(20) UNSIGNED NOT NULL,
				created_date    DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
				name            TEXT NOT NULL,
				description     TEXT NOT NULL,
				sku             TEXT NOT NULL,
				sku_variations  TEXT NOT NULL,
				attributes      LONGTEXT NOT NULL,
				meta            LONGTEXT NOT NULL,
				image           TEXT NOT NULL,
				url				TEXT NOT NULL,
				html_price      TEXT NOT NULL,
				price           DECIMAL(10,2) NOT NULL,
				average_rating  DECIMAL(3,2) NOT NULL,
                review_count    SMALLINT(5) NOT NULL DEFAULT '0',
                total_sales     SMALLINT(5) NOT NULL DEFAULT '0',
                lang            VARCHAR(7) NOT NULL,
				PRIMARY KEY     (id)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC $collate;";

			dbDelta( $table );

			WPDB::get_instance()->query( "CREATE INDEX main_post_id ON $tableName(post_id);" );
		}
	}

	/**
	 * Create database structure from the scratch
	 *
	 * @return void
	 * @throws WPDBException
	 */
	public static function create( $indexRoleSuffix = '' ) {
		self::install( $indexRoleSuffix );
	}

	/**
	 * Remove DB table
	 *
	 * @return void
	 */
	public static function remove( $indexRoleSuffix ) {
		global $wpdb;

		$wpdb->hide_errors();

		$tableName = $wpdb->dgwt_wcas_index . $indexRoleSuffix;

		$wpdb->query( "DROP TABLE IF EXISTS $tableName" );

		Cache::delete( 'table_exists', 'database' );
	}
}
