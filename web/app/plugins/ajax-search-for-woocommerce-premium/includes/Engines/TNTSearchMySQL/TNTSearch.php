<?php

//https://github.com/trilbymedia/grav-plugin-tntsearch/blob/develop/classes/GravTNTSearch.php

namespace DgoraWcas\Engines\TNTSearchMySQL;

use DgoraWcas\Admin\Troubleshooting;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\AsyncRebuildIndex;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\BackgroundProductUpdater;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\IndexPusher;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Scheduler;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomies;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Updater;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\FailureReports;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\AsyncProcess as AsyncProcessR;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\AsyncProcess as AsyncProcessS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\AsyncProcess as AsyncProcessT;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation\AsyncProcess as AsyncProcessV;
use DgoraWcas\Helpers;
use DgoraWcas\Product;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TNTSearch {

	/**
	 * Background processes for the readable index
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\AsyncProcess
	 *
	 */
	public $asynchBuildIndexR;

	/**
	 * Background processes for the searchable index
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\AsyncProcess
	 *
	 */
	public $asynchBuildIndexS;

	/**
	 * Background processes for the variation index
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation\AsyncProcess
	 *
	 */
	public $asynchBuildIndexV;

	/**
	 * Background processes for the taxonomies index
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\AsyncProcess
	 *
	 */
	public $asynchBuildIndexT;

	/**
	 * Async rebuild whole search index
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\AsyncRebuildIndex
	 *
	 */
	public $asyncRebuildIndex;

	/**
	 * Taxonomies
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomies
	 */
	public $taxonomies;

	/**
	 * Background product updater
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\BackgroundProductUpdater
	 */
	public $productUpdater;

	/**
	 * Failure Reports
	 *
	 * @var \DgoraWcas\Engines\TNTSearchMySQL\Indexer\FailureReports
	 */
	public $failureReports;

	/**
	 * TNTSearch constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * TNTSearch init
	 *
	 * @return void
	 */
	private function init() {
		require_once __DIR__ . '/Indexer/exceptions.php';

		$this->asynchBuildIndexR = new AsyncProcessR();
		$this->asynchBuildIndexS = new AsyncProcessS();
		$this->asynchBuildIndexT = new AsyncProcessT();
		$this->asynchBuildIndexV = new AsyncProcessV();

		$this->asyncRebuildIndex = new AsyncRebuildIndex();

		$this->taxonomies = new Taxonomies();

		$this->failureReports = new FailureReports();
		$this->failureReports->init();

		add_action( 'init', function () {

			if ( DGWT_WCAS()->engine === 'tntsearchMySql' && apply_filters( 'dgwt/wcas/override_search_results_page', true ) ) {
				$this->overrideSearchPage();
			}

			$this->initScheduler();
			$this->initUpdater();
			$this->initBackgroundProductUpdater();
			$this->initIndexPusher();
			$this->taxonomies->init();
		} );

		add_action( 'wp_ajax_dgwt_wcas_build_index', array( $this, 'ajaxBuildIndex' ) );
		add_action( 'wp_ajax_dgwt_wcas_stop_build_index', array( $this, 'ajaxStopBuildIndex' ) );
		add_action( 'wp_ajax_dgwt_wcas_build_index_heartbeat', array( $this, 'ajaxBuildIndexHeartbeat' ) );
		add_action( 'wp_ajax_dgwt_wcas_index_details_toggle', array( $this, 'ajaxBuildIndexDetailsToggle' ) );

		add_action( 'update_option_' . DGWT_WCAS_SETTINGS_KEY, array( $this, 'buildIndexOnchangeSettings' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'maintenanceOnInit' ), 5 );
		add_action( 'admin_init', array( $this, 'fixIndexStatus' ) );

		$this->wpBgProcessingBasicAuthBypass();
		$this->pushIndexAfterPluginUpdate();

		// Load dynamic prices
		add_action( 'wc_ajax_' . DGWT_WCAS_GET_PRICES_ACTION, array( $this, 'getDynamicPrices' ) );

		// Alternative search endpoint
		if (
			get_option( 'dgwt_wcas_alternative_endpoint_enabled' ) === '1' ||
			( defined( 'DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT' ) && DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT )
		) {
			add_action( 'wc_ajax_' . DGWT_WCAS_SEARCH_PRO_ACTION, array( $this, 'getSearchResults' ) );
		}

		add_action( 'dgwt/wcas/indexer/build', array( $this, 'buildIndex' ) );
		add_action( 'dgwt/wcas/indexer/delete', array( $this, 'deleteSingle' ) );
		add_action( 'dgwt/wcas/indexer/update', array( $this, 'updateSingle' ) );
	}


	/**
	 * Load background product updater
	 *
	 * @return void
	 */
	private function initBackgroundProductUpdater() {
		$this->productUpdater = new BackgroundProductUpdater();
		$this->productUpdater->init();
	}

	/**
	 * Load index pusher
	 *
	 * @return void
	 */
	private function initIndexPusher() {
		$pusher = new IndexPusher();
		$pusher->init();
	}

	/**
	 * Load scheduler
	 *
	 * @return void
	 */
	private function initScheduler() {
		$scheduler = new Scheduler();
		$scheduler->init();
	}

	/**
	 * Load updater
	 *
	 * Listens for changes in posts and taxonomies terms and update index
	 *
	 * @return void
	 */
	private function initUpdater() {
		$updater = new Updater;
		$updater->init();
	}

	/**
	 * Load search page logic
	 *
	 * The class interferes with WordPress search resutls page
	 *
	 * @return void
	 */
	private function overrideSearchPage() {
		$sp = new SearchPage();
		$sp->init();
	}

	/**
	 * Build index
	 *
	 * Admin ajax callback for action "dgwt_wcas_build_index"
	 *
	 * @return void
	 */
	public function ajaxBuildIndex() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( - 1, 403 );
		}

		check_ajax_referer( 'dgwt_wcas_build_index' );

		Builder::buildIndex();

		wp_send_json_success( array(
			'html' => Builder::renderIndexingStatus()
		) );
	}

	/**
	 * Start build an index after first vising a settings page
	 */
	public function maintenanceOnInit() {
		if ( ! Helpers::isSettingsPage() ) {
			return;
		}
		// Skip index rebuilding when plugin version changes, because that will be done in \DgoraWcas\Admin\Install::checkVersion.
		if ( Helpers::getPluginVersion__premium_only() !== DGWT_WCAS_VERSION ) {
			return;
		}

		$build  = false;
		$status = Builder::getInfo( 'status', 'main' );

		if ( Config::isParallelBuildingEnabled() ) {
			$statusTmp = Builder::getInfo( 'status', 'tmp' );
			if (
				( empty( $status ) || $status === 'not-exist' ) &&
				( empty( $statusTmp ) || $statusTmp === 'not-exist' )
			) {
				$build = true;
			}
		} else {
			if ( empty( $status ) || $status === 'not-exist' ) {
				$build = true;
			}
		}

		// Build index on start
		if ( $build ) {
			Builder::buildIndex();
		}
	}

	/**
	 * Make sure the index status has been copied from the "tmp" option to "main"
	 *
	 * @return void
	 */
	public function fixIndexStatus() {
		if ( ! Config::isParallelBuildingEnabled() ) {
			return;
		}

		$statusTmp = Builder::getInfo( 'status', 'tmp' );
		$status    = Builder::getInfo( 'status', 'main' );

		if ( $statusTmp === 'completed' && $status !== 'completed' ) {
			$buildIdTmp = Builder::getInfo( 'build_id', 'tmp' );
			$buildId    = Builder::getInfo( 'build_id', 'main' );
			if ( ! empty( $buildIdTmp ) && $buildIdTmp === $buildId ) {
				update_option( Builder::LAST_BUILD_OPTION_KEY . '_status', $statusTmp );
			}
		}
	}

	/**
	 * Stop building index
	 *
	 * Admin ajax callback for action "dgwt_wcas_stop_build_index"
	 *
	 * @return void
	 */
	public function ajaxStopBuildIndex() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( - 1, 403 );
		}

		check_ajax_referer( 'dgwt_wcas_stop_build_index' );

		Builder::addInfo( 'status', 'cancellation', Config::getIndexRole() );
		Builder::log( 'Stop building the index. Starting the cancellation process.' );

		Builder::cancelBuildIndex();

		wp_send_json_success( array(
			'html' => Builder::renderIndexingStatus( false )
		) );
	}

	/**
	 * Refresh index status
	 *
	 * Admin ajax callback for action "dgwt_wcas_build_index_heartbeat"
	 *
	 * @return void
	 */
	public function ajaxBuildIndexHeartbeat() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( - 1, 403 );
		}

		check_ajax_referer( 'dgwt_wcas_build_index_heartbeat' );

		$status       = Builder::getInfo( 'status', Config::getIndexRole() );
		$loop         = false;
		$lastActionTs = absint( Builder::getInfo( 'last_action_ts', Config::getIndexRole() ) );

		$diff = time() - $lastActionTs;

		if ( empty( $lastActionTs ) ) {
			$diff = 0;
		}

		if (
			$diff >= MINUTE_IN_SECONDS && $diff <= ( MINUTE_IN_SECONDS + 1 )
			|| $diff >= ( 3 * MINUTE_IN_SECONDS ) && $diff <= ( 3 * MINUTE_IN_SECONDS + 1 )
			|| $diff >= ( 5 * MINUTE_IN_SECONDS ) && $diff <= ( 5 * MINUTE_IN_SECONDS + 1 )
		) {
			Builder::log( sprintf( '[Indexer] %d minute(s) with no action', floor( $diff / 60 ) ), 'debug', 'file' );

			// Last chance to finish indexing.
			Builder::maybeMarkAsCompleted();
		}

		if ( Builder::isIndexerWorkingTooLong() ) {
			if ( Troubleshooting::hasWpCronMissedEvents() ) {
				Builder::log( sprintf( '[Indexer] [Error code: 001] The index build was stuck for %d minutes.', floor( $diff / 60 ) ), 'emergency', 'both' );
			} else {
				Builder::log( sprintf( '[Indexer] [Error code: 002] The index build was stuck for %d minutes.', floor( $diff / 60 ) ), 'emergency', 'both' );
			}

			Builder::addInfo( 'status', 'error', Config::getIndexRole() );
			Builder::log( 'Stop building the index. Starting the cancellation process.' );
			Builder::cancelBuildIndex();

			$loop = true;
		}

		if ( in_array( $status, array( 'preparing', 'building', 'cancellation' ) ) ) {
			$loop = true;
		}

		$refreshOnce = '';
		if ( $status === 'completed' && ! $loop && ! empty( Builder::getInfo( 'non_critical_errors', Config::getIndexRole() ) ) ) {
			$refreshOnce = Builder::getInfo( 'build_id', Config::getIndexRole() );
		}

		wp_send_json_success( array(
			'html'         => Builder::renderIndexingStatus(),
			'loop'         => $loop,
			'status'       => $status,
			'refresh_once' => $refreshOnce,
		) );
	}


	/**
	 * Show/hide indexer logs
	 *
	 * Admin ajax callback for action "dgwt_wcas_index_details_toggle"
	 *
	 * @return void
	 */
	public function ajaxBuildIndexDetailsToggle() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( - 1, 403 );
		}

		delete_transient( Builder::DETAILS_DISPLAY_KEY );

		if ( ! empty( $_REQUEST['display'] ) && $_REQUEST['display'] == 'true' ) {
			set_transient( Builder::DETAILS_DISPLAY_KEY, 1, 3600 );
		} else {
			set_transient( Builder::DETAILS_DISPLAY_KEY, 0, 3600 );
		}
	}

	/**
	 * Pushes the index-building process further after updating the plugin
	 *
	 * @return void
	 */
	public function pushIndexAfterPluginUpdate() {

		add_action( 'upgrader_process_complete', function ( $upgraderObject, $options ) {

			$properAction = ! empty( $options['action'] ) && in_array( $options['action'], array(
					'update',
					'install'
				) );
			$properType   = ! empty( $options['type'] ) && $options['type'] === 'plugin';
			$properPlugin = false;
			$res          = $upgraderObject->result;
			$files        = ! empty( $res['source_files'] ) && is_array( $res['source_files'] ) ? $res['source_files'] : array();
			$dest         = ! empty( $res['destination_name'] ) && is_string( $res['destination_name'] ) ? $res['destination_name'] : '';

			if ( in_array( 'ajax-search-for-woocommerce.php', $files )
			     && strpos( $dest, 'ajax-search-for-woocommerce' ) !== false ) {
				$properPlugin = true;
			}

			if ( $properAction && $properType && $properPlugin ) {
				/**
				 * To run an indexer after the plugin installation or updating we need call "admin_init" action.
				 *
				 * An "admin_init" action isn't called in the following cases:
				 *     1. Automatic updates
				 *     2. Updates by WP CLI
				 *     3. Updates by REST API
				 *     4. By WP Dashboard but without refreshing the page after updating
				 *
				 * Calling /wp-admin/admin-post.php fires do_action( 'admin_init' );. Login is not required.
				 */
				$code = wp_remote_retrieve_response_code( wp_remote_get( admin_url( 'admin-post.php' ) ) );

				// Add a task via Action Scheduler on failure
				if ( $code !== 200 ) {
					IndexPusher::schedule();
				}
			}
		}, 10, 2 );
	}

	/**
	 * Rebuild index after changing some options
	 *
	 * @return void
	 */
	public function buildIndexOnchangeSettings( $oldSettings, $newSettings ) {
		wp_cache_delete( 'alloptions', 'options' );

		if ( DGWT_WCAS()->engine !== 'tntsearchMySql' ) {
			return;
		};

		$listenKeys = array(
			'search_in_product_content',
			'search_in_product_excerpt',
			'search_in_product_sku',
			'search_in_product_attributes',
			'search_in_custom_fields',
			'exclude_out_of_stock',
			'filter_products_mode',
			'filter_products_rules',
			'show_matching_pages',
			'show_matching_posts',
			'search_synonyms',
		);

		$taxonomiesSlugs = DGWT_WCAS()->tntsearchMySql->taxonomies->getTaxonomiesSlugs();
		foreach ( $taxonomiesSlugs as $slug ) {
			$listenKeys[] = 'search_in_product_tax_' . $slug;
			$listenKeys[] = 'show_product_tax_' . $slug;
		}

		foreach ( $listenKeys as $key ) {
			if (
				(
					// Values are different
					is_array( $newSettings ) &&
					is_array( $oldSettings ) &&
					array_key_exists( $key, $newSettings ) &&
					array_key_exists( $key, $oldSettings ) &&
					$newSettings[ $key ] != $oldSettings[ $key ]
				) ||
				(
					// The key does not exist yet
					is_array( $newSettings ) &&
					is_array( $oldSettings ) &&
					array_key_exists( $key, $newSettings ) &&
					! array_key_exists( $key, $oldSettings )
				)
			) {
				Builder::buildIndex();
				break;
			}
		}

	}

	/**
	 * Bypass for WP Background Processing when BasicAuth is enabled
	 *
	 * @return void
	 */
	public function wpBgProcessingBasicAuthBypass() {

		$authorization = Helpers::getBasicAuthHeader();
		if ( $authorization ) {

			add_filter( 'http_request_args', function ( $r, $url ) {

				if ( strpos( $url, 'wp-cron.php' ) !== false
				     || strpos( $url, 'admin-ajax.php' ) !== false ) {

					$r['headers']['Authorization'] = Helpers::getBasicAuthHeader();

				}

				return $r;
			}, 10, 2 );
		}

	}

	/**
	 * Get prices for products
	 * AJAX callback
	 *
	 * @return void
	 */
	public function getDynamicPrices() {

		if ( ! defined( 'DGWT_WCAS_AJAX' ) ) {
			define( 'DGWT_WCAS_AJAX', true );
		}

		$prices = array();

		if ( ! empty( $_POST['items'] ) && array( $_POST['items'] ) ) {
			foreach ( $_POST['items'] as $postID ) {
				if ( ! empty( $postID ) && is_numeric( $postID ) ) {

					$postID = absint( $postID );

					$product = new Product( $postID );

					if ( $product->isCorrect() ) {
						$prices[] = (object) array(
							'id'    => $postID,
							'price' => $product->getPriceHTML()
						);
					}

				}
			}
		}

		wp_send_json_success( $prices );
	}

	/**
	 * Alternative search endpoint
	 */
	public function getSearchResults() {
		define( 'DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT_ENABLED', true );
		require_once DGWT_WCAS_DIR . 'includes/Engines/TNTSearchMySQL/Endpoints/search.php';
	}

	/**
	 * Alternative way to run indexing
	 *
	 * @return void
	 */
	public function buildIndex( $mode = 'async' ) {
		$async = ! ( $mode === 'direct' );

		Builder::buildIndex( $async );
	}

	/**
	 * Delete single object in index
	 *
	 * @param int $postID
	 *
	 * @return void
	 */
	public function deleteSingle( $postID ) {
		if ( is_null( $this->productUpdater ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'The deleting of objects in the search index should be performed at the earliest on the action "init" with priority 11.', 'ajax-search-for-woocommerce' ), '1.19' );

			return;
		}

		$this->productUpdater->handle( 'delete', $postID );
	}

	/**
	 * Update single object in index
	 *
	 * @param int $postID
	 *
	 * @return void
	 */
	public function updateSingle( $postID ) {
		if ( is_null( $this->productUpdater ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'The updating of objects in the search index should be performed at the earliest on the action "init" with priority 11.', 'ajax-search-for-woocommerce' ), '1.19' );

			return;
		}

		$this->productUpdater->handle( 'update', $postID );
	}
}
