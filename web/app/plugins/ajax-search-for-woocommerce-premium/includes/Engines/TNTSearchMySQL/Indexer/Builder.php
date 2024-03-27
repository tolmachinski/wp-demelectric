<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Indexer as IndexerR;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Indexer as IndexerS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\Indexer as IndexerTax;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Vendor\Indexer as IndexerVendors;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation\Indexer as IndexerVar;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Database as DatabaseS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Database as DatabaseR;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\Database as DatabaseT;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\Request as RequestT;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation\Database as DatabaseVar;
use DgoraWcas\Engines\TNTSearchMySQL\Libs\Mutex\WpdbMysqlMutex;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Cache;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Builder {

	const LAST_BUILD_OPTION_KEY              = 'dgwt_wcas_indexer_last_build';
	const DETAILS_DISPLAY_KEY                = 'dgwt_wcas_indexer_details_display';
	const INDEXING_PREPARE_PROCESS_EXIST_KEY = 'dgwt_wcas_indexer_prepare_process_exist';
	const INDEXER_DEBUG_TRANSIENT_KEY        = 'dgwt_wcas_indexer_debug';
	const INDEXER_DEBUG_SCOPE_TRANSIENT_KEY  = 'dgwt_wcas_indexer_debug_scope';
	const SEARCHABLE_SET_ITEMS_COUNT         = 50;
	const READABLE_SET_ITEMS_COUNT           = 25;
	const VARIATIONS_SET_ITEMS_COUNT         = 25;
	const TAXONOMY_SET_ITEMS_COUNT           = 100;

	/**
	 * @var WpdbMysqlMutex
	 */
	private static $addInfoMutex;

	public static $indexerDebugScopes = array(
		'all',
		'readable',
		'searchable',
		'taxonomy',
		'variation',
		'bg-process',
	);

	/**
	 * Structure of indexer data
	 *
	 * @return array
	 */
	private static function getIndexInfoStruct() {
		return array(
			'build_id'                        => uniqid(),
			'db'                              => 'MySQL',
			'status'                          => '',
			'start_ts'                        => time(),
			'start_searchable_ts'             => 0,
			'start_readable_ts'               => 0,
			'start_taxonomies_ts'             => 0,
			'start_variation_ts'              => 0,
			'end_ts'                          => 0,
			'end_searchable_ts'               => 0,
			'end_readable_ts'                 => 0,
			'end_taxonomies_ts'               => 0,
			'end_variation_ts'                => 0,
			'last_action_ts'                  => time(),
			'readable_processed'              => 0,
			'searchable_processed'            => 0,
			'variations_processed'            => 0,
			'terms_processed'                 => 0,
			'total_terms_for_indexing'        => 0,
			'total_variations_for_indexing'   => 0,
			'total_products_for_indexing'     => 0,
			'total_non_products_for_indexing' => 0,
			'logs'                            => array(),
			'non_critical_errors'             => array(),
			'languages'                       => array(),
			'plugin_version'                  => '',
			'stemmer'                         => '',
		);
	}

	/**
	 * @return void
	 */
	private static function createInfoStruct() {
		foreach ( self::getIndexInfoStruct() as $key => $value ) {
			update_option( self::LAST_BUILD_OPTION_KEY . '_' . $key . Config::getIndexRoleSuffix(), $value, 'no' );
		}
	}

	/**
	 * Add specific info about the last index build
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param string $indexRole Index role
	 *
	 * @return bool
	 */
	public static function addInfo( $key, $value, $indexRole = '' ) {
		if ( self::$addInfoMutex === null ) {
			self::$addInfoMutex = new WpdbMysqlMutex( 'builder_add_info_' . $key, 5 );
		}

		if ( ! self::$addInfoMutex->acquire() ) {
			self::log( "[Builder] Unable to get lock when adding indexer information with key: $key", 'warning', 'file' );

			return false;
		}

		// If empty, get "writable" index.
		if ( empty( $indexRole ) ) {
			$indexRole = Config::getIndexRole();
		}
		$indexRoleSuffix = $indexRole === 'main' ? '' : '_tmp';

		if ( $key === 'logs' ) {
			$logs   = self::getInfo( 'logs', $indexRole );
			if ( ! is_array( $logs ) ) {
				$logs = array();
			}
			$logs[] = $value;
			$value  = $logs;
		}

		$added = update_option( self::LAST_BUILD_OPTION_KEY . '_' . $key . $indexRoleSuffix, $value );
		update_option( self::LAST_BUILD_OPTION_KEY . '_last_action_ts' . $indexRoleSuffix, time() );
		Cache::set( $key . '_' . $indexRole, $value, 'indexer' );

		self::$addInfoMutex->release();

		return $added;
	}

	public static function getInfo( $key, $indexRole = 'main' ) {
		global $wpdb;

		if ( ! Helpers::isIndexing__premium_only() ) {
			$value = Cache::get( $key . '_' . $indexRole, 'indexer' );
			if ( $value !== false ) {
				return $value;
			}
		}

		$value           = '';
		$indexRoleSuffix = $indexRole === 'main' ? '' : '_tmp';
		$infoKey         = self::LAST_BUILD_OPTION_KEY . '_' . $key . $indexRoleSuffix;
		$rawValue        = $wpdb->get_var( $wpdb->prepare( "SELECT SQL_NO_CACHE option_value FROM $wpdb->options WHERE option_name = %s", $infoKey ) );

		if ( ! empty( $rawValue ) ) {
			$value = maybe_unserialize( $rawValue );
		}

		Cache::set( $key . '_' . $indexRole, $value, 'indexer' );

		return $value;
	}

	/**
	 * Is indexer debug enabled
	 *
	 * @return bool
	 */
	public static function isDebug() {
		if ( defined( 'DGWT_WCAS_INDEXER_DEBUG' ) ) {
			return (bool) DGWT_WCAS_INDEXER_DEBUG;
		}

		return (bool) get_transient( self::INDEXER_DEBUG_TRANSIENT_KEY );
	}

	/**
	 * Get indexer debug scope
	 *
	 * @return array
	 */
	public static function getDebugScopes() {
		if ( defined( 'DGWT_WCAS_INDEXER_DEBUG_SCOPE' ) ) {
			$scope = explode( ',', DGWT_WCAS_INDEXER_DEBUG_SCOPE );

			return array_map( 'trim', $scope );
		}

		$scope = get_transient( self::INDEXER_DEBUG_SCOPE_TRANSIENT_KEY );

		return is_array( $scope ) ? $scope : array( 'all' );
	}

	/**
	 * Check if scope of debug is enabled
	 *
	 * @param string $scope
	 *
	 * @return bool
	 */
	public static function isDebugScopeActive( $scope ) {
		if ( $scope === 'all' || in_array( 'all', self::getDebugScopes() ) ) {
			return true;
		}

		return in_array( $scope, self::getDebugScopes() );
	}

	/**
	 * Log indexer message
	 *
	 * @param string $message
	 * @param string $level One of the following:
	 *     'emergency': The indexer has stopped due to a fatal error.
	 *     'warning': PHP warnings.
	 *     'notice': PHP notices.
	 *     'info': Informational messages about indexer.
	 *     'debug': Debug-level messages.
	 * @param string $destination Destination. Choices: 'db', 'file' or 'both'
	 * @param string $scope Scope of log. Choices: look at self::$indexerDebugScopes
	 *
	 * @return void
	 * @TODO Może tutaj trzeba przekazać jakiego indeksu to dotyczy? 'main' lub 'tmp'
	 */
	public static function log( $message, $level = 'info', $destination = 'both', $scope = 'all', $indexRole = '' ) {
		if ( defined( 'DGWT_WCAS_DISABLE_INDEXER_LOGS' ) && DGWT_WCAS_DISABLE_INDEXER_LOGS ) {
			return;
		}

		if ( $destination === 'file' || $destination === 'both' ) {
			Logger::log( $message, $level, $scope );
		}

		if ( $destination === 'db' || $destination === 'both' ) {
			self::addInfo( 'logs', array(
				'time'    => current_time( 'timestamp' ),
				'error'   => in_array( $level, array( 'emergency', 'warning', 'notice' ) ),
				'message' => $message
			) );
		}
	}

	/**
	 * Get all logs
	 *
	 * @return array
	 */
	public static function getLogs( $indexRole = '' ) {
		$logs = self::getInfo( 'logs', $indexRole );

		return empty( $logs ) ? array() : $logs;
	}

	/**
	 * Get all plugin's option and delete cache
	 *
	 * @return void
	 */
	public static function flushOptionsCache() {
		foreach ( Helpers::getAllOptionNames() as $optionKey ) {
			wp_cache_delete( $optionKey, 'options' );
		}
	}

	public static function buildIndex( $async = true ) {

		self::flushOptionsCache();

		$result = self::prepareBuildIndex();

		if ( ! $result ) {
			return;
		}

		if ( Config::isIndexerMode( 'direct' ) ) {
			$async = false;
		}

		if ( $async ) {
			DGWT_WCAS()->tntsearchMySql->asyncRebuildIndex->data( array( 'force' => true ) )->schedule_event()->dispatch();
		} else {
			self::buildIndexProcess();
		}
	}

	public static function prepareBuildIndex() {
		if ( ! defined( 'DGWT_WCAS_PREPARE_BUILD_INDEX' ) ) {
			define( 'DGWT_WCAS_PREPARE_BUILD_INDEX', true );
		}

		if ( Helpers::isLockLocked__premium_only( self::INDEXING_PREPARE_PROCESS_EXIST_KEY, 5 ) ) {
			self::log( 'Indexer already preparing building the index' );

			return false;
		}

		Logger::removeLogs();
		Logger::removeLogs( Logger::UPDATER_SOURCE );

		Helpers::setLock__premium_only( self::INDEXING_PREPARE_PROCESS_EXIST_KEY );

		self::createInfoStruct();

		self::log( sprintf( 'Build ID: %s', self::getInfo( 'build_id', Config::getIndexRole() ) ), 'info', 'file' );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			self::log( 'Build via: WP-CLI', 'info', 'file' );
		}
		self::log( sprintf( 'Indexer mode: %s', Config::getIndexerMode() ) );
		self::log( sprintf( 'Parallel build: %s', Config::isParallelBuildingEnabled() ? 'yes' : 'no' ) );

		self::cancelBuildIndex( false );

		self::addInfo( 'status', 'preparing' );

		self::wipeActionScheduler();

		self::log( sprintf( 'Plugin version: %s', DGWT_WCAS_VERSION ) );
		self::addInfo( 'plugin_version', DGWT_WCAS_VERSION );

		if ( Multilingual::isMultilingual() ) {
			self::log( sprintf( 'Multilingual: Yes, Provider: %s, Default: %s, Langs: %s', Multilingual::getProvider(), Multilingual::getDefaultLanguage(),
				implode( ',', Multilingual::getLanguages() ) ) );
			self::addInfo( 'languages', Multilingual::getLanguages() );
		}

		self::log( 'Indexer prepared for building the index' );
		do_action( 'dgwt/wcas/indexer/prepared' );

		return true;
	}

	public static function buildIndexProcess() {
		if ( ! defined( 'DGWT_WCAS_BUILD_INDEX_PROCESS' ) ) {
			define( 'DGWT_WCAS_BUILD_INDEX_PROCESS', true );
		}

		$status = self::getInfo( 'status', Config::getIndexRole() );

		if ( $status === 'building' ) {
			self::log( 'Indexer already running' );

			return;
		} elseif ( $status !== 'preparing' ) {
			self::log( 'Indexer is not prepared for running' );

			return;
		}

		self::addInfo( 'status', 'building' );

		self::log( 'Indexer started building the index' );
		do_action( 'dgwt/wcas/indexer/started' );

		// Readable
		try {
			DatabaseR::create( Config::getIndexRoleSuffix() );
		} catch ( \Error $e ) {
			Logger::handleThrowableError( $e, '[Readable index] ' );
		} catch ( \Exception $e ) {
			Logger::handleThrowableError( $e, '[Readable index] ' );
		}
		self::addInfo( 'start_readable_ts', time() );
		self::log( '[Readable index] Building...' );

		// Variations
		if ( self::canBuildVariationsIndex() ) {
			try {
				DatabaseVar::create( Config::getIndexRoleSuffix() );
			} catch ( \Error $e ) {
				Logger::handleThrowableError( $e, '[Variations index] ' );
			} catch ( \Exception $e ) {
				Logger::handleThrowableError( $e, '[Variations index] ' );
			}
		}

		// Taxonomies
		if ( self::canBuildTaxonomyIndex() ) {
			try {
				DatabaseT::create( Config::getIndexRoleSuffix() );
			} catch ( \Error $e ) {
				Logger::handleThrowableError( $e, '[Taxonomy index] ' );
			} catch ( \Exception $e ) {
				Logger::handleThrowableError( $e, '[Taxonomy index] ' );
			}
		}

		$toTheQueue = array(
			'searchable' => array(),
			'readable'   => array(),
		);

		#-------------------------------
		# SEARCHABLE AND READABLE INDEX
		#-------------------------------
		try {
			DatabaseS::create( Config::getIndexRoleSuffix() );
		} catch ( \Error $e ) {
			Logger::handleThrowableError( $e, '[Searchable index] ' );
		} catch ( \Exception $e ) {
			Logger::handleThrowableError( $e, '[Searchable index] ' );
		}

		$source      = new SourceQuery( array( 'ids' => true ) );
		$productsIds = $source->getData();

		self::addInfo( 'total_products_for_indexing', count( $productsIds ) );

		self::addInfo( 'start_searchable_ts', time() );
		self::log( '[Searchable index] Building...' );

		// The searchable indexer expects arrays of IDs of the same type.
		$toTheQueue['searchable'][] = $productsIds;
		$toTheQueue['readable']     = $productsIds;


		#-----------------------------------
		# NON-PRODUCTS INDEX - POST TYPES
		#-----------------------------------
		$types = Helpers::getAllowedPostTypes( 'no-products' );
		if ( ! empty( $types ) ) {
			$totalNonProducts = 0;
			foreach ( $types as $type ) {

				$npSource = new PostsSourceQuery( array(
					'ids'      => true,
					'postType' => $type
				) );
				$postIds  = $npSource->getData();

				$totalNonProducts = $totalNonProducts + count( $postIds );

				$toTheQueue['searchable'][] = $postIds;
				$toTheQueue['readable']     = array_merge( $toTheQueue['readable'], $postIds );

			}

			self::addInfo( 'total_non_products_for_indexing', $totalNonProducts );
		}

		#-------------------------------------------
		# PUSH TO QUEUE (for async and sync mode)
		# PROCESS (for direct mode)
		#-------------------------------------------
		if ( Config::isIndexerMode( 'direct' ) ) {
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->task( $toTheQueue['readable'] );
			foreach ( $toTheQueue['searchable'] as $ids ) {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->task( $ids );
			}
		} else {
			foreach ( $toTheQueue as $indexer => $data ) {
				if ( $indexer === 'searchable' ) {
					foreach ( $data as $subdata ) {
						self::pushToQueue( $subdata, $indexer );
					}
				} else {
					self::pushToQueue( $data, $indexer );
				}
			}
		}

		#----------------------------------------
		# DISPATCH (for async and sync mode)
		# COMPLETE (for direct mode)
		#----------------------------------------
		if ( Config::isIndexerMode( 'direct' ) ) {
			// This condition prevents the build completion from starting when an error previously occurred.
			if ( self::getInfo( 'status', Config::getIndexRole() ) === 'building' ) {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->complete();
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->complete();
			}
		} elseif ( Config::isIndexerMode( 'sync' ) ) {
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->save();
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->save();

			if ( DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->get_save_result() && DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->get_save_result() ) {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->maybe_dispatch();
			} else {
				Builder::log( '[Indexer] [Error code: 006] Failed to save indexer queue to the database.', 'emergency', 'both' );
				Builder::addInfo( 'status', 'error' );
				Builder::log( 'Stop building the index. Starting the cancellation process.' );
				Builder::cancelBuildIndex();
				do_action( 'dgwt/wcas/indexer/status/error' );
			}
		} else {
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->save();
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->save();

			if ( DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->get_save_result()
			     && DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->get_save_result()
			) {
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->maybe_dispatch();
				sleep( 1 );
				DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->maybe_dispatch();
			} else {
				Builder::log( '[Indexer] [Error code: 006] Failed to save indexer queue to the database.', 'emergency', 'both' );
				Builder::addInfo( 'status', 'error' );
				Builder::log( 'Stop building the index. Starting the cancellation process.' );
				Builder::cancelBuildIndex();
				do_action( 'dgwt/wcas/indexer/status/error' );
			}
		}
	}

	/**
	 * Push data to the correct queue
	 *
	 * @TODO SourceQuery should return IDs directly, rather than associative arrays with the key 'ID'.
	 *
	 * @param array $ids
	 * @param string $indexer
	 *
	 * @return void
	 */
	public static function pushToQueue( $ids, $indexer ) {

		$limit  = 50;
		$object = null;
		$set    = array();

		switch ( $indexer ) {
			case 'searchable':
				$object = DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS;
				$limit  = apply_filters( 'dgwt/wcas/indexer/searchable_set_items_count', self::SEARCHABLE_SET_ITEMS_COUNT );
				break;
			case 'readable':
				$object = DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR;
				$limit  = apply_filters( 'dgwt/wcas/indexer/readable_set_items_count', self::READABLE_SET_ITEMS_COUNT );
				break;
		}

		// Stop early
		if ( empty( $ids ) || ! is_object( $object ) ) {
			return;
		}

		$i = 0;
		foreach ( $ids as $id ) {
			$set[] = $id;

			if ( count( $set ) === $limit || $i + 1 === count( $ids ) ) {
				$object->push_to_queue( $set );
				$set = array();
			}

			$i ++;
		}
	}

	/**
	 * Stops build index and wipes all processes and data
	 *
	 * @param bool $clearInfo clear info (start time of processes)
	 *
	 * @return void
	 */
	public static function cancelBuildIndex( $clearInfo = true ) {
		DGWT_WCAS()->tntsearchMySql->asynchBuildIndexR->cancel_process();
		DGWT_WCAS()->tntsearchMySql->asynchBuildIndexS->cancel_process();
		DGWT_WCAS()->tntsearchMySql->asynchBuildIndexT->cancel_process();
		DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->cancel_process();

		self::wipeIndexTables();

		Helpers::removeBatchOptions__premium_only();

		if ( $clearInfo ) {
			self::addInfo( 'start_searchable_ts', '' );
			self::addInfo( 'start_readable_ts', '' );
			self::addInfo( 'start_variation_ts', '' );
			self::addInfo( 'start_taxonomies_ts', '' );

			self::flushOptionsCache();
		}
	}

	public static function wipeIndexTables( $indexRole = '' ) {
		if ( empty( $indexRole ) ) {
			$indexRole = Config::getIndexRole();
		}
		$indexRoleSuffix = $indexRole === 'main' ? '' : '_tmp';

		Cache::delete( 'table_exists', 'database' );

		if ( self::searchableIndexExists( '', $indexRoleSuffix ) ) {
			$indexerS = new IndexerS;
			$indexerS->wipe( $indexRoleSuffix );
		}

		if ( self::readableIndexExists( $indexRoleSuffix ) ) {
			$indexerR = new IndexerR;
			$indexerR->wipe( $indexRoleSuffix );
		}

		if ( self::taxIndexExists( $indexRoleSuffix ) ) {
			$taxIndexer = new IndexerTax();
			$taxIndexer->wipe( $indexRoleSuffix );
		}

		if ( self::vendorsIndexExists( $indexRoleSuffix ) ) {
			$vendorsIndexer = new IndexerVendors();
			$vendorsIndexer->wipe( $indexRoleSuffix );
		}

		if ( self::variationsIndexExists( $indexRoleSuffix ) ) {
			$varIndexer = new IndexerVar();
			$varIndexer->wipe( $indexRoleSuffix );
		}
	}

	public static function getReadableProgress() {
		global $wpdb;

		$percent    = 0;
		$totalItems = absint( self::getInfo( 'total_products_for_indexing', Config::getIndexRole() ) );

		if ( ! empty( Helpers::getAllowedPostTypes( 'no-products' ) ) ) {
			$npTotalItems = absint( self::getInfo( 'total_non_products_for_indexing', Config::getIndexRole() ) );
			if ( is_numeric( $totalItems ) && is_numeric( $npTotalItems ) && ! empty( $npTotalItems ) ) {
				$totalItems += $npTotalItems;
			}
		}

		if ( self::readableIndexExists( Config::getIndexRoleSuffix() ) ) {
			$tableName = $wpdb->dgwt_wcas_index . Config::getIndexRoleSuffix();
			$totalIndexed = $wpdb->get_var( 'SELECT COUNT(DISTINCT post_id) FROM ' . $tableName );
		}

		if (
			! empty( $totalItems )
			&& is_numeric( $totalItems )
			&& ! empty( $totalIndexed )
			&& is_numeric( $totalIndexed )
		) {
			$percent = $totalIndexed * 100 / $totalItems;
		}

		return absint( $percent );
	}

	public static function getSearchableProgress() {

		$percent    = 0;
		$totalItems = absint( self::getInfo( 'total_products_for_indexing', Config::getIndexRole() ) );

		if ( ! empty( Helpers::getAllowedPostTypes( 'no-products' ) ) ) {
			$npTotalItems = absint( self::getInfo( 'total_non_products_for_indexing', Config::getIndexRole() ) );
			if ( is_numeric( $totalItems ) && is_numeric( $npTotalItems ) && ! empty( $npTotalItems ) ) {
				$totalItems += $npTotalItems;
			}
		}

		$processed = absint( self::getInfo( 'searchable_processed', Config::getIndexRole() ) );

		if (
			! empty( $totalItems )
			&& is_numeric( $totalItems )
			&& ! empty( $processed )
			&& is_numeric( $processed )
		) {
			$percent = $processed * 100 / $totalItems;
		}

		return absint( $percent );
	}

	public static function getVariationsProgress() {

		$percent    = 0;
		$totalItems = absint( self::getInfo( 'total_variations_for_indexing', Config::getIndexRole() ) );
		$processed  = absint( self::getInfo( 'variations_processed', Config::getIndexRole() ) );

		if (
			! empty( $totalItems )
			&& is_numeric( $totalItems )
			&& ! empty( $processed )
			&& is_numeric( $processed )
		) {
			$percent = $processed * 100 / $totalItems;
		}

		return absint( $percent );
	}

	public static function getTaxonomiesProgress() {

		$percent    = 0;
		$totalItems = absint( self::getInfo( 'total_terms_for_indexing', Config::getIndexRole() ) );
		$processed  = absint( self::getInfo( 'terms_processed', Config::getIndexRole() ) );

		if (
			! empty( $totalItems )
			&& is_numeric( $totalItems )
			&& ! empty( $processed )
			&& is_numeric( $processed )
		) {
			$percent = $processed * 100 / $totalItems;
		}

		return absint( $percent );
	}

	public static function getProgressBarValue() {

		if ( self::getInfo( 'status', Config::getIndexRole() ) === 'completed' ) {
			return 100;
		}

		$percentR = self::getReadableProgress();
		$percentS = self::getSearchableProgress();
		$percentV = self::getVariationsProgress();
		$percentT = self::getTaxonomiesProgress();

		if ( self::canBuildVariationsIndex() && self::canBuildTaxonomyIndex() ) {
			$progress = $percentR * 0.4 + $percentS * 0.4 + $percentV * 0.1 + $percentT * 0.1;
		} elseif ( self::canBuildVariationsIndex() || self::canBuildTaxonomyIndex() ) {
			$progress = $percentR * 0.4 + $percentS * 0.4 + $percentV * 0.2 + $percentT * 0.2;
		} else {
			$progress = ( $percentR + $percentS ) / 2;
		}

		$progress = apply_filters( 'dgwt/wcas/indexer/process_status/progress', $progress, $percentR, $percentS, $percentV, $percentT );

		return $progress > 100 ? 99 : $progress;
	}

	public static function renderIndexingStatus( $refreshStatus = true ) {
		if ( $refreshStatus ) {
			self::refreshStatus();
		}

		$html = '<div class="js-dgwt-wcas-indexing-wrapper">';
		$html .= self::getIndexHeader();
		$html .= self::getProcessStatus();
		$html .= '</div>';

		return $html;
	}

	public static function refreshStatus() {
		global $wpdb;

		$status = self::getInfo( 'status', Config::getIndexRole() );

		$startTs    = absint( self::getInfo( 'start_ts', Config::getIndexRole() ) );
		$sStartTs   = absint( self::getInfo( 'start_searchable_ts', Config::getIndexRole() ) );
		$rStartTs   = absint( self::getInfo( 'start_readable_ts', Config::getIndexRole() ) );
		$taxStartTs = absint( self::getInfo( 'start_taxonomies_ts', Config::getIndexRole() ) );
		$sEndTs     = absint( self::getInfo( 'end_searchable_ts', Config::getIndexRole() ) );
		$rEndTs     = absint( self::getInfo( 'end_readable_ts', Config::getIndexRole() ) );
		$taxEndTs   = absint( self::getInfo( 'end_taxonomies_ts', Config::getIndexRole() ) );

		switch ( $status ) {
			case 'cancellation':

				self::addInfo( 'status', 'not-exist' );
				self::log( 'Canceling completed' );

				break;
			case 'error':

				self::cancelBuildIndex();

				break;
		}

	}

	public static function getIndexHeader( $indexRole = '' ) {
		if ( empty( $indexRole ) ) {
			$indexRole = Config::getIndexRole();
		}
		$text              = '';
		$statusColor       = '';
		$statusText        = '';
		$status            = self::getInfo( 'status', $indexRole );
		$endTs             = absint( self::getInfo( 'end_ts', $indexRole) );
		$totalProducts     = absint( self::getInfo( 'total_products_for_indexing', $indexRole ) );
		$totalNonProducts  = absint( self::getInfo( 'total_non_products_for_indexing', $indexRole ) );
		$nonCriticalErrors = self::getInfo( 'non_critical_errors', $indexRole);
		$lastErrorCode     = '';
		$lastErrorMessage  = '';

		switch ( $status ) {
			case 'preparing':
				$text        = __( 'Wait... Preparing indexing in progress', 'ajax-search-for-woocommerce' );
				$statusText  = __( 'This process will continue in the background. You can leave this page!',
					'ajax-search-for-woocommerce' );
				$statusColor = '#e6a51d';
				break;
			case 'building':
				$text        = __( 'Wait... Indexing in progress', 'ajax-search-for-woocommerce' );
				$statusText  = __( 'This process will continue in the background. You can leave this page!',
					'ajax-search-for-woocommerce' );
				$statusColor = '#e6a51d';
				break;
			case 'cancellation':
				$text        = __( 'Wait... The index build process is canceling', 'ajax-search-for-woocommerce' );
				$statusText  = __( 'Canceling...', 'ajax-search-for-woocommerce' );
				$statusColor = '#7293b0';
				break;
			case 'completed':
				$lastDate = ! empty( $endTs ) ? Helpers::localDate( $endTs ) : '-';
				if ( empty( $nonCriticalErrors ) ) {
					$text = __( 'The search index was built successfully.', 'ajax-search-for-woocommerce' );
				} else {
					$text = __( 'The search index was built successfully, but some non-critical errors occurred.', 'ajax-search-for-woocommerce' );
				}
				$statusText  = __( 'Completed. Works.', 'ajax-search-for-woocommerce' );
				$statusColor = '#4caf50';
				break;
			case 'error':
				$text        = __( 'The search index could not be built.', 'ajax-search-for-woocommerce' );
				$statusText  = __( 'Errors', 'ajax-search-for-woocommerce' );
				$statusColor = '#d75f5f';
				list( $lastErrorCode, $lastErrorMessage ) = Logger::getLastEmergencyLog();
				break;
			default:
				$text        = __( 'The search index does not exist yet. Build it now.', 'ajax-search-for-woocommerce' );
				$statusText  = __( 'Not exist', 'ajax-search-for-woocommerce' );
				$statusColor = '#aaaaaa';
				break;
		}

		$actionButton    = self::getIndexButton();
		$isDetails       = get_transient( self::DETAILS_DISPLAY_KEY );
		$status          = self::getInfo( 'status', $indexRole );
		$oldIndexEndTime = Helpers::localDate( absint( self::getInfo( 'end_ts', 'main' ) ), 'Y-m-d H:i:s' );
		$notices         = array();

		$infoLimitedEngine = '<b>' . __( 'Search engine status:', 'ajax-search-for-woocommerce' ) . '</b> ';
		$infoLimitedEngine .= __( "Your search engine does not work optimally because the index hasn't been built yet.", 'ajax-search-for-woocommerce' );
		$infoLimitedEngine .= ' ' . __( 'The best speed and all pro features will be available after the index has been built.', 'ajax-search-for-woocommerce' );

		if ( Config::isParallelBuildingEnabled() && self::getInfo( 'status', 'tmp' ) !== 'completed' ) {
			if ( self::getInfo( 'status', 'main' ) === 'completed' ) {
				$notices['info'] = '<b>' . __( 'Search engine status:', 'ajax-search-for-woocommerce' ) . '</b> ';
				$notices['info'] .= __( 'We have some good news!', 'ajax-search-for-woocommerce' );
				if ( in_array( self::getInfo( 'status', 'tmp' ), array( 'preparing', 'building' ) ) ) {
					$notices['info'] .= ' ' . __( "Even though the current index is being built, it doesn't affect your search engine.", 'ajax-search-for-woocommerce' );
				} else {
					$notices['info'] .= ' ' . __( "Even though the current index wasn't built, it doesn't affect your search engine.", 'ajax-search-for-woocommerce' );
				}
				$notices['info'] .= ' ' . sprintf( __( "It works well based on the last properly built index from %s", 'ajax-search-for-woocommerce' ), $oldIndexEndTime );
			} else {
				$notices['info'] = $infoLimitedEngine;
			}
		}

		if ( ! Config::isParallelBuildingEnabled() && self::getInfo( 'status', 'main' ) !== 'completed' ) {
			$notices['info'] = $infoLimitedEngine;
		}

		ob_start();
		include DGWT_WCAS_DIR . 'partials/admin/indexer-notices.php';
		include DGWT_WCAS_DIR . 'partials/admin/indexer-header.php';
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public static function getIndexButton() {
		$status = self::getInfo( 'status', Config::getIndexRole());

		if ( in_array( $status, array( 'building', 'preparing' ) ) ) {
			$html = '<button class="button js-ajax-stop-build-index">' . __( 'Stop process',
					'ajax-search-for-woocommerce' ) . '</button>';
		} elseif ( in_array( $status, array( 'completed' ) ) ) {
			$html = '<button class="button js-ajax-build-index">' . __( 'Rebuild index',
					'ajax-search-for-woocommerce' ) . '</button>';
		} elseif ( in_array( $status, array( 'error' ) ) ) {
			$html = '<button class="button js-ajax-build-index">' . __( 'Try to rebuild the index',
					'ajax-search-for-woocommerce' ) . '</button>';
		} elseif ( in_array( $status, array( 'cancellation' ) ) ) {
			$html = '<button class="button js-ajax-build-index" disabled="disabled">' . __( 'Stop process',
					'ajax-search-for-woocommerce' ) . '</button>';
		} else {
			$html = '<button class="button js-ajax-build-index ajax-build-index-primary">' . __( 'Build index',
					'ajax-search-for-woocommerce' ) . '</button>';
		}

		return $html;
	}

	/**
	 * Check if readable products table exist
	 *
	 * @return bool
	 */
	public static function readableIndexExists( $indexRoleSuffix = '' ) {
		global $wpdb;

		return Helpers::isTableExists( $wpdb->dgwt_wcas_index . $indexRoleSuffix );
	}

	/**
	 * Check if readable taxonmies table exist
	 *
	 * @return bool
	 */
	public static function taxIndexExists( $indexRoleSuffix = '' ) {
		global $wpdb;

		return Helpers::isTableExists( $wpdb->dgwt_wcas_tax_index . $indexRoleSuffix );
	}

	/**
	 * Check if variations table exist
	 *
	 * @return bool
	 */
	public static function variationsIndexExists( $indexRoleSuffix = '' ) {
		global $wpdb;

		return Helpers::isTableExists( $wpdb->dgwt_wcas_var_index . $indexRoleSuffix );
	}

	/**
	 * Check if vendors table exist
	 *
	 * @return bool
	 */
	public static function vendorsIndexExists( $indexRoleSuffix = '' ) {
		global $wpdb;

		return Helpers::isTableExists( $wpdb->dgwt_wcas_ven_index . $indexRoleSuffix );
	}

	/**
	 * Check if searchable tables exist
	 *
	 * @param string $currentLang
	 * @param string $source
	 *
	 * @return bool
	 */
	public static function searchableIndexExists( $currentLang = '', $indexRoleSuffix = '' ) {
		global $wpdb;
		$isShortInit    = defined( 'SHORTINIT' ) && SHORTINIT;
		$wordlistExists = false;
		$doclistExists  = false;

		$currentLang = Multilingual::isLangCode( $currentLang ) ? $currentLang : '';

		$wpdb->hide_errors();

		ob_start();

		if ( ! empty( $currentLang ) || ( ! $isShortInit && Multilingual::isMultilingual() ) ) {
			$wordlistInstances = 0;
			$doclistInstances  = 0;

			if ( ! empty( $currentLang ) ) {
				$langs = array( $currentLang );
			} else {
				$langs = Multilingual::getLanguages();
			}

			foreach ( $langs as $lang ) {

				$lang = str_replace( '-', '_', $lang );

				$wordlistTable = $wpdb->dgwt_wcas_si_wordlist . '_' . $lang . $indexRoleSuffix;
				$doclistTable  = $wpdb->dgwt_wcas_si_doclist . '_' . $lang . $indexRoleSuffix;

				if ( Helpers::isTableExists( $wordlistTable ) ) {
					$wordlistInstances ++;
				}

				if ( Helpers::isTableExists( $doclistTable ) ) {
					$doclistInstances ++;
				}
			}

			if ( $wordlistInstances === count( $langs ) ) {
				$wordlistExists = true;
			}

			if ( $doclistInstances === count( $langs ) ) {
				$doclistExists = true;
			}

		} else {
			$wordlistExists = Helpers::isTableExists( $wpdb->dgwt_wcas_si_wordlist . $indexRoleSuffix );
			$doclistExists  = Helpers::isTableExists( $wpdb->dgwt_wcas_si_doclist . $indexRoleSuffix );
		}

		ob_end_clean();

		return $wordlistExists && $doclistExists;
	}

	/**
	 * Check if cache table exists
	 *
	 * @param string $lang Language
	 * @param string $postType Post type. Leave empty to check 'product' table
	 *
	 * @return bool
	 */
	public static function searchableCacheExists( $lang = '', $postType = '' ) {
		global $wpdb;

		$lang = Multilingual::isLangCode( $lang ) ? $lang : '';
		$lang = str_replace( '-', '_', $lang );

		$cacheTable = $wpdb->dgwt_wcas_si_cache;
		if ( ! empty( $postType ) ) {
			$cacheTable .= '_' . $postType;
		}
		if ( ! empty( $lang ) ) {
			$cacheTable .= '_' . $lang;
		}

		return Helpers::isTableExists( $cacheTable );
	}


	public static function getReadableTotalIndexed() {
		global $wpdb;
		$count = 0;

		if ( self::readableIndexExists( Config::getIndexRoleSuffix() ) ) {
			$r = $wpdb->get_var( 'SELECT COUNT(DISTINCT post_id) FROM ' . $wpdb->dgwt_wcas_index . Config::getIndexRoleSuffix() );
			if ( ! empty( $r ) && is_numeric( $r ) ) {
				$count = absint( $r );
			}
		}


		return $count;
	}

	public static function getProcessStatus() {

		$info = array();
		foreach ( self::getIndexInfoStruct() as $key => $field ) {
			$offset = get_option( 'gmt_offset' );
			$value  = self::getInfo( $key, Config::getIndexRole() );
			if ( strpos( $key, '_ts' ) !== false && ! empty( $value ) && ! empty( $offset ) ) {
				$info[ $key ] = absint( $value ) + ( $offset * 3600 );
			} else {
				$info[ $key ] = $value;
			}
		}

		$progressPercent       = self::getProgressBarValue();
		$logs                  = self::getLogs( Config::getIndexRole() );
		$isDetails             = get_transient( self::DETAILS_DISPLAY_KEY );
		$canBuildTaxonomyIndex = self::canBuildTaxonomyIndex();
		$canBuildVendorsIndex  = self::canBuildVendorsIndex();

		ob_start();
		include DGWT_WCAS_DIR . 'partials/admin/indexer-body.php';
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Check if can index taxonomies
	 *
	 * @return bool
	 */
	public static function canBuildTaxonomyIndex() {
		return ! empty( DGWT_WCAS()->tntsearchMySql->taxonomies->getActiveTaxonomies( 'search_direct' ) );
	}

	/**
	 * Check if can index vendors
	 *
	 * @return bool
	 */
	public static function canBuildVendorsIndex() {
		return apply_filters( 'dgwt/wcas/search/vendors', false );
	}

	/**
	 * Check if can build variations index
	 *
	 * @return bool
	 */
	public static function canBuildVariationsIndex() {
		$canBuild = false;

		if ( ! empty( $_POST['dgwt_wcas_settings'] ) ) {

			$settings = $_POST['dgwt_wcas_settings'];

			if ( ! empty( $settings['search_in_product_sku'] ) && $settings['search_in_product_sku'] === 'on' ) {
				$canBuild = true;
			}
		}

		if ( ! $canBuild ) {
			$canBuild = DGWT_WCAS()->settings->getOption( 'search_in_product_sku' ) === 'on';
		}

		return $canBuild;
	}

	/**
	 * Check if index is completed and valid
	 *
	 * @param string $lang
	 *
	 * @return bool
	 */
	public static function isIndexValid( $lang = '', $indexRole = 'main' ) {
		$valid = false;

		$indexRoleSuffix = $indexRole === 'main' ? '' : '_tmp';
		if (
			self::getInfo( 'status', $indexRole ) === 'completed' &&
			self::searchableIndexExists( $lang, $indexRoleSuffix ) &&
			self::readableIndexExists( $indexRoleSuffix )
		) {
			$valid = true;
		}

		return $valid;
	}

	/**
	 * Wipe all data of deprecated SQLite driver
	 *
	 * @return void
	 */
	public static function wipeSQLiteAfterEffects() {

		$uploadDir = wp_upload_dir();
		if ( ! empty( $uploadDir['basedir'] ) ) {


			$directory = $uploadDir['basedir'] . '/wcas-search';
			$file      = $uploadDir['basedir'] . '/wcas-search/products.index';


			if ( file_exists( $file ) && is_writable( $file ) ) {
				@unlink( $file );
			}

			if ( file_exists( $directory ) && is_writable( $directory ) ) {
				@rmdir( $directory );
			}

		}

	}

	/**
	 * Complete the search index
	 *
	 * @return void
	 */
	public static function maybeMarkAsCompleted() {
		$status = self::getInfo( 'status', Config::getIndexRole() );
		$sEndTs = absint( self::getInfo( 'end_searchable_ts', Config::getIndexRole() ) );
		$rEndTs = absint( self::getInfo( 'end_readable_ts', Config::getIndexRole() ) );
		$tEndTs = self::canBuildTaxonomyIndex() ? absint( self::getInfo( 'end_taxonomies_ts', Config::getIndexRole() ) ) : 1;
		$vEndTs = self::canBuildVariationsIndex() ? absint( self::getInfo( 'end_variation_ts', Config::getIndexRole() ) ) : 1;

		if ( 'building' === $status && ! empty( $sEndTs ) && ! empty( $rEndTs ) && ! empty( $vEndTs ) && ! empty( $tEndTs ) ) {
			self::addInfo( 'status', 'completed' );
			self::addInfo( 'end_ts', time() );
			self::log( 'Indexing completed' );
			self::copyTmpIndexToMain();
			self::flushOptionsCache();
			do_action( 'dgwt/wcas/indexer/status/completed' );
		}
	}

	public static function copyTmpIndexToMain() {
		global $wpdb;

		// Break early if parallel index building is disabled.
		if ( ! Config::isParallelBuildingEnabled() ) {
			Builder::log( '[Indexer] Skipping copying of "tmp" tables - parallel building is disabled', 'debug', 'file' );

			return;
		}

		if ( ! Builder::isIndexValid( '', 'tmp' ) ) {
			Builder::log( '[Indexer] Skipping copying of "tmp" tables - "tmp" index is invalid', 'debug', 'file' );

			return;
		}

		$tables = Utils::getAllPluginTables();
		foreach ( $tables as $table ) {
			if ( strpos( $table, 'dgwt_wcas_stats' ) ) {
				continue;
			}
			// Remove all not "main" index tables (even those that don't have "tmp" version).
			if ( strpos( $table, '_tmp' ) === false ) {
				$wpdb->query( "DROP TABLE IF EXISTS $table" );
			}
			if ( strpos( $table, '_tmp' ) !== false ) {
				$mainTable = str_replace( '_tmp', '', $table );
				// Make sure the table at index "main" does not exist.
				$wpdb->query( "DROP TABLE IF EXISTS $mainTable" );
				$wpdb->query( "RENAME TABLE $table TO $mainTable" );
			}
		}

		foreach ( self::getIndexInfoStruct() as $key => $value ) {
			$value = get_option( self::LAST_BUILD_OPTION_KEY . '_' . $key . '_tmp', $value );
			delete_option( self::LAST_BUILD_OPTION_KEY . '_' . $key );

			/*
			 * Make sure the options are copied.
			 * Sometimes it happened that some options were not copied, and we have to be sure that everything went OK.
			 */
			$count = 0;
			do {
				$count ++;
				if ( $count > 1 ) {
					usleep( 500000 );
				}
				if ( $count === 1 ) {
					$addResult  = add_option( self::LAST_BUILD_OPTION_KEY . '_' . $key, $value, '', 'no' );
					$savedValue = get_option( self::LAST_BUILD_OPTION_KEY . '_' . $key );
					$continue   = ! $addResult || $value !== $savedValue;
				} else {
					delete_option( self::LAST_BUILD_OPTION_KEY . '_' . $key );
					$wpdb->query( $wpdb->prepare( "INSERT INTO $wpdb->options (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')", self::LAST_BUILD_OPTION_KEY . '_' . $key, maybe_serialize( $value ) ) );
					$continue = false;
				}
				if ( $key === 'logs' ) {
					break;
				}
			} while ( $continue && $count < 3 );
		}
	}

	/**
	 * Remove all options created by this plugin
	 *
	 * @param bool $networkScope delete tables in whole network
	 *
	 * @return void
	 */
	public static function deleteIndexOptions( $networkScope = false ) {
		global $wpdb;

		$prefix = $wpdb->prefix;

		if ( is_multisite() && $networkScope ) {
			$prefix = $wpdb->base_prefix;
		}

		$lastBuildOptionKey = $wpdb->esc_like( self::LAST_BUILD_OPTION_KEY ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $lastBuildOptionKey ) );
		delete_transient( self::DETAILS_DISPLAY_KEY );

		if ( is_multisite() && $networkScope ) {
			foreach ( get_sites() as $site ) {
				if ( is_numeric( $site->blog_id ) ) {

					$blogID = $site->blog_id == 1 ? '' : $site->blog_id . '_';

					$table = $prefix . $blogID . 'options';

					$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name LIKE %s", $lastBuildOptionKey ) );

					$wpdb->delete( $table, array( 'option_name' => '_transient_timeout_' . self::DETAILS_DISPLAY_KEY ) );
					$wpdb->delete( $table, array( 'option_name' => '_transient_' . self::DETAILS_DISPLAY_KEY ) );

				}
			}
		}

	}

	/**
	 * Remove all database tables created by this plugin
	 *
	 * @param bool $networkScope delete tables in whole network
	 *
	 * @return void
	 */
	public static function deleteDatabaseTables( $networkScope = false ) {
		global $wpdb;

		// DB tables
		$tables = Utils::getAllPluginTables( $networkScope );

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS $table" );
			}
		}
	}

	/**
	 * Removal of planned actions that will update products in the index
	 */
	public static function wipeActionScheduler() {
		$queue = Utils::getQueue();
		if ( empty( $queue ) ) {
			return;
		}

		try {
			$queue->cancel_all( 'dgwt/wcas/tnt/background_product_update' );
		} catch ( Exception $e ) {

		}
	}

	/**
	 * Dispatch building variation index
	 */
	public static function maybeDispatchVariationAsyncProcess() {
		if ( ! self::canBuildVariationsIndex() ) {
			return;
		}

		$status = self::getInfo( 'status', Config::getIndexRole() );
		$sEndTs = absint( self::getInfo( 'end_searchable_ts', Config::getIndexRole() ) );
		$rEndTs = absint( self::getInfo( 'end_readable_ts', Config::getIndexRole() ) );

		if (
			( Config::isIndexerMode( 'async' ) && $status === 'building' && ! empty( $rEndTs ) )
			|| ( Config::isIndexerMode( 'sync' ) && $status === 'building' && ! empty( $sEndTs ) && ! empty( $rEndTs ) )
			|| ( Config::isIndexerMode( 'direct' ) && $status === 'building' )
		) {
			self::addInfo( 'start_variation_ts', time() );
			// Reset end time because this process may end several times
			self::addInfo( 'end_variation_ts', 0 );
			self::log( '[Variation index] Building...' );

			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->maybe_dispatch();
		}
	}

	/**
	 * Dispatch building taxonomies index
	 */
	public static function maybeDispatchTaxonomyAsyncProcess() {
		if ( ! self::canBuildTaxonomyIndex() ) {
			self::maybeDispatchVariationAsyncProcess();

			return;
		}

		$status = self::getInfo( 'status', Config::getIndexRole() );
		$rEndTs = absint( self::getInfo( 'end_readable_ts', Config::getIndexRole() ) );

		if (
			( Config::isIndexerMode( 'async' ) && $status === 'building' && ! empty( $rEndTs ) )
			|| ( Config::isIndexerMode( 'sync' ) && $status === 'building' && ! empty( $rEndTs ) )
			|| ( Config::isIndexerMode( 'direct' ) && $status === 'building' )
		) {
			RequestT::handle();
		}
	}

	/**
	 * Check if the indexer working too long without any action
	 *
	 * @return bool
	 */
	public static function isIndexerWorkingTooLong( $forceMaxNoActionTime = 0 ) {
		$status = Builder::getInfo( 'status', Config::getIndexRole() );

		// Return early if indexer is not working
		if ( ! in_array( $status, array( 'building', 'preparing' ) ) ) {
			return false;
		}

		$lastActionTs = absint( Builder::getInfo( 'last_action_ts', Config::getIndexRole() ) );

		// Return early if the indexer info hasn't been created yet
		if ( empty( $lastActionTs ) ) {
			return false;
		}

		$diff = time() - $lastActionTs;

		$maxNoActionTime = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 61 * MINUTE_IN_SECONDS : 16 * MINUTE_IN_SECONDS;
		/**
		 * Filters maximum no action time of indexer.
		 *
		 * @param int $maxNoActionTime Max time in seconds. 16 min if WP-Cron is enabled or 61 min if not
		 */
		$maxNoActionTime = apply_filters( 'dgwt/wcas/indexer/max_no_action_time', $maxNoActionTime );

		if ( $forceMaxNoActionTime > 0 ) {
			$maxNoActionTime = $forceMaxNoActionTime;
		}

		return in_array( $status, array( 'building', 'preparing' ) ) && $diff >= $maxNoActionTime;
	}
}
