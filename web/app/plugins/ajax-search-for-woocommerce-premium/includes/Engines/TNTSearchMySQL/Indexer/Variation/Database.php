<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation;

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

		$wpdb->dgwt_wcas_var_index = $wpdb->prefix . Config::VARIATIONS_INDEX;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_var_index ) ) {
			$wpdb->tables[] = Config::VARIATIONS_INDEX;
		}
	}

	/**
	 * Install DB table
	 *
	 * @param bool $fromTheScratch
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

			$collate = Utils::getCollate( 'variations/main' );

			/**
			 * We use 'id' column because 'variation_id' is not always unique.
			 * This happens, for example, with the TranslatePress plugin, when records of different
			 * languages have the same 'variation_id'.
			 */
			$tableName = $wpdb->dgwt_wcas_var_index . $indexRoleSuffix;
			$table = "CREATE TABLE $tableName (
				id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				variation_id    BIGINT(20) UNSIGNED NOT NULL,
				product_id      BIGINT(20) UNSIGNED NOT NULL,
				sku             VARCHAR(255) NOT NULL,
				title           TEXT NOT NULL,
				description     TEXT NOT NULL,
				image           TEXT NOT NULL,
				url				TEXT NOT NULL,
				html_price      TEXT NOT NULL,
				lang            VARCHAR(7) NOT NULL,
				PRIMARY KEY    (id)
			) ENGINE=InnoDB ROW_FORMAT=DYNAMIC $collate;";

			dbDelta( $table );

			WPDB::get_instance()->query( "CREATE INDEX main_variation_id ON $tableName(variation_id);" );
			WPDB::get_instance()->query( "CREATE INDEX main_product_id ON $tableName(product_id);" );
			WPDB::get_instance()->query( "CREATE INDEX main_sku ON $tableName(sku);" );
			WPDB::get_instance()->query( "CREATE INDEX main_lang ON $tableName(lang);" );
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
	public static function remove( $indexRoleSuffix = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		$tableName = $wpdb->dgwt_wcas_var_index . $indexRoleSuffix;

		$wpdb->query( "DROP TABLE IF EXISTS $tableName" );

		Cache::delete( 'table_exists', 'database' );
	}
}
