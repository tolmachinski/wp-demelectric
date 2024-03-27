<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Cache;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	/**
	 * Add tables names to the $wpdb object
	 * @return void
	 */
	public static function registerTables() {
		global $wpdb;

		$wpdb->dgwt_wcas_si_wordlist = $wpdb->prefix . Config::SEARCHABLE_INDEX_WORDLIST;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_si_wordlist ) ) {
			$wpdb->tables[] = Config::SEARCHABLE_INDEX_WORDLIST;
		}

		$wpdb->dgwt_wcas_si_doclist = $wpdb->prefix . Config::SEARCHABLE_INDEX_DOCLIST;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_si_doclist ) ) {
			$wpdb->tables[] = Config::SEARCHABLE_INDEX_DOCLIST;
		}

		$wpdb->dgwt_wcas_si_cache = $wpdb->prefix . Config::SEARCHABLE_INDEX_CACHE;
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_si_cache ) ) {
			$wpdb->tables[] = Config::SEARCHABLE_INDEX_CACHE;
		}
	}

	/**
	 * Install DB table
	 *
	 * @return void
	 * @throws WPDBException
	 */
	private static function install( $indexRoleSuffix ) {
		global $wpdb;

		$tables = array();

		$wpdb->hide_errors();

		$upFile = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( file_exists( $upFile ) ) {
			require_once( $upFile );

			$postTypes = array_merge( array( '' ), Helpers::getAllowedPostTypes( 'no-products' ) );
			$langs = Multilingual::isMultilingual() ? Multilingual::getLanguages() : array( '' );

			foreach ( $postTypes as $postType ) {
				foreach ( $langs as $lang ) {
					$tables[] = self::wordListTableStruct( $lang, $postType, $indexRoleSuffix );
					$tables[] = self::docListTableStruct( $lang, $postType, $indexRoleSuffix );
					if ( Helpers::doesDbSupportJson__premium_only() ) {
						$tables[] = self::cacheTableStruct( $lang, $postType, $indexRoleSuffix );
					}
				}
			}

			dbDelta( $tables );

			// MySQL Index
			foreach ( $postTypes as $postType ) {
				foreach ( $langs as $lang ) {
					$doclistTableName = Utils::getTableName( 'searchable_doclist', $lang, $postType ) . Config::getIndexRoleSuffix();
					WPDB::get_instance()->query( "CREATE INDEX main_term_id_index ON $doclistTableName(term_id);" );
					WPDB::get_instance()->query( "CREATE INDEX main_doc_id_index ON $doclistTableName(doc_id);" );

					if ( Helpers::doesDbSupportJson__premium_only() ) {
						$cacheTableName = Utils::getTableName( 'searchable_cache', $lang, $postType ) . Config::getIndexRoleSuffix();
						WPDB::get_instance()->query( "CREATE INDEX main_cache_key_index ON $cacheTableName(cache_key);" );
					}
				}
			}
		}
	}

	/**
	 * Get real tables belong to the searchable index
	 *
	 * @return array
	 */
	public static function getSearchableIndexTables( $indexRoleSuffix = '' ) {
		$searchableTables = array();

		$tables = Utils::getAllPluginTables();

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				if (
					(
						strpos( $table, 'dgwt_wcas_invindex_doclist' ) !== false
						|| strpos( $table, 'dgwt_wcas_invindex_wordlist' ) !== false
						|| strpos( $table, 'dgwt_wcas_invindex_cache' ) !== false
					)
					&& Helpers::endsWith( $table, $indexRoleSuffix )
				) {
					$searchableTables[] = $table;
				}
			}
		}

		return $searchableTables;
	}

	/**
	 *  DB structure for Wordlist table
	 *
	 * @param string $lang
	 * @param string $postType
	 *
	 * @return string
	 */
	public static function wordListTableStruct( $lang, $postType, $indexRoleSuffix = '' ) {
		$suffix         = trim( Utils::getTableSuffix( $lang, $postType ), '_' );
		$collateContext = empty( $suffix ) ? '' : '/' . $suffix;
		$tableName      = Utils::getTableName( 'searchable_wordlist', $lang, $postType ) . $indexRoleSuffix;
		$collate        = Utils::getCollate( 'searchable/wordlist' . $collateContext );

		$sql     = "CREATE TABLE $tableName (
				id           MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
				term         VARCHAR(127) NOT NULL UNIQUE,
				num_hits     MEDIUMINT NOT NULL DEFAULT 1,
				/* num_docs     MEDIUMINT NOT NULL DEFAULT 1, */
				PRIMARY KEY  (id)
			    ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC $collate;";

		return $sql;
	}

	/**
	 *  DB structure for Doclist table
	 *
	 * @param string $lang
	 * @param string $postType
	 *
	 * @return string
	 */
	public static function docListTableStruct( $lang, $postType, $indexRoleSuffix = '' ) {
		$tableName = Utils::getTableName( 'searchable_doclist', $lang, $postType ) . $indexRoleSuffix;

		$sql = "CREATE TABLE $tableName (
				id           MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
                term_id      MEDIUMINT UNSIGNED NOT NULL,
				doc_id       BIGINT NOT NULL,
				/* hit_count    MEDIUMINT NOT NULL DEFAULT 1, */
				PRIMARY KEY  (id)
			    ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC COLLATE ascii_bin";

		return $sql;
	}

	/**
	 *  DB structure for Cache table
	 *
	 * @param string $lang
	 * @param string $postType
	 *
	 * @return string
	 */
	public static function cacheTableStruct( $lang, $postType, $indexRoleSuffix = '' ) {
		$tableName = Utils::getTableName( 'searchable_cache', $lang, $postType ) . $indexRoleSuffix;

		$collate = Utils::getCollate( 'searchable/cache' );

		$sql = "CREATE TABLE $tableName (
				cache_id     MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
                cache_key    VARCHAR(255) NOT NULL UNIQUE,
				cache_value  JSON NOT NULL,
				PRIMARY KEY  (cache_id)
			    ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC $collate;";

		return $sql;
	}

	/**
	 * Create database structure from the scratch
	 *
	 * @return void
	 * @throws WPDBException
	 */
	public static function create( $indexRoleSuffix ) {
		self::install( $indexRoleSuffix );
	}

	/**
	 * Remove searchable index
	 *
	 * @return void
	 */
	public static function remove( $indexRoleSuffix = '' ) {
		global $wpdb;

		$wpdb->hide_errors();

		foreach ( self::getSearchableIndexTables( $indexRoleSuffix ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}

		Cache::delete( 'table_exists', 'database' );
	}
}
