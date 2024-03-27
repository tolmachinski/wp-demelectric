<?php
// Benchmark: https://gist.github.com/hawkidoki/388574a6e0eea3fa6220be565b0150a0

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Helpers;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Database as DatabaseS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Database as DatabaseR;
use DgoraWcas\Engines\TNTSearchMySQL\SearchQuery\AjaxQuery;
use DgoraWcas\Multilingual;

$isAlternativeSearchEndpointEnabled = defined( 'DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT_ENABLED' ) && DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT_ENABLED;

define( 'DGWT_SEARCH_START', microtime( true ) );
define( 'DGWT_WCAS_DOING_SEARCH', true );

if ( ! $isAlternativeSearchEndpointEnabled ) {
	if ( ! defined( 'SHORTINIT' ) ) {
		define( 'SHORTINIT', true );
	}
	define( 'DOING_AJAX', true );
}

// Simple test for Troubleshooting module
if ( isset( $_GET['dgwt_wcas_ping'] ) ) {
	echo 'pong';
	die();
}

if ( ! defined( 'ABSPATH' ) ) {
	$wpLoad   = '../../../../wp-load.php';
	$maxDepth = 8;

	while ( $maxDepth > 0 ) {

		if ( file_exists( $wpLoad ) ) {
			require_once $wpLoad;
			break;
		} else {

			$alternativePaths = array(
				'wp', // Support for Bedrock by Roots - https://roots.io/bedrock
				'.wordpress', // Support for Flywheel hosting - https://getflywheel.com
				'cms', // Support for Themosis Framefork - https://framework.themosis.com
				'wordpress',
				'wp-cms'
			);

			foreach ( $alternativePaths as $alternativePath ) {

				$bedrockAbsPath = str_replace( 'wp-load.php', $alternativePath . '/wp-load.php', $wpLoad );

				if ( file_exists( $bedrockAbsPath ) ) {
					require_once $bedrockAbsPath;
					break;
				}

			}

		}

		$wpLoad = '../' . $wpLoad;
		$maxDepth --;
	}

	// Support for Bitnami WordPress With NGINX And SSL - https://docs.bitnami.com/aws/apps/wordpress-pro/
	if ( ! defined( 'ABSPATH' ) ) {
		$wpLoad = '/opt/bitnami/wordpress/wp-load.php';
		if ( file_exists( $wpLoad ) ) {
			require_once $wpLoad;
		}
	}
}


if ( ! defined( 'ABSPATH' ) ) {
	exit( 'ABSPATH is not defined' );
}

if ( wp_installing() ) {
	exit( 'WordPress is in "installation" mode.' );
}

global $wpdb;
$charset    = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'blog_charset'" );
$charsetCom = ! empty( $charset ) ? '; charset=' . $charset : '';

if ( ! $isAlternativeSearchEndpointEnabled ) {
	require_once ABSPATH . WPINC . '/formatting.php';
	require_once ABSPATH . WPINC . '/http.php';
	if ( get_http_origin() === null || ! empty( get_http_origin() ) ) {
		require_once ABSPATH . WPINC . '/link-template.php';
	}

	define( 'DGWT_WCAS_PLUGIN_DIR', dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/' );

	require_once DGWT_WCAS_PLUGIN_DIR . 'vendor/autoload.php';
	require_once DGWT_WCAS_PLUGIN_DIR . 'fs/placeholder.php';

	if ( ! Config::isPluginActive( basename( DGWT_WCAS_PLUGIN_DIR ) . '/ajax-search-for-woocommerce.php' ) ) {
		exit( 'Plugin is disabled.' );
	}

	DatabaseS::registerTables();
	DatabaseR::registerTables();
}

send_origin_headers();
@header( 'Content-Type: application/json' . $charsetCom );
@header( 'X-Robots-Tag: noindex' );
send_nosniff_header();
nocache_headers();

$lang = ! empty( $_GET['l'] ) && Multilingual::isLangCode( strtolower( $_GET['l'] ) ) ? strtolower( $_GET['l'] ) : '';

// Send empty response if language is invalid
$languages = Builder::getInfo( 'languages' );
if ( ( ! empty( $languages ) && ! in_array( $lang, $languages ) ) || ( empty( $languages ) && ! empty( $lang ) ) ) {
	$l = isset( $languages[0] ) ? $languages[0] : '';
	if ( Builder::getInfo( 'status' ) !== 'completed' || ! Builder::isIndexValid( $l ) ) {
		AjaxQuery::sendEmptyResponse( 'free' );
	} else {
		AjaxQuery::sendEmptyResponse( 'pro' );
	}
}

// Fallback to native if tntsearchMySql engine is not ready
if ( Builder::getInfo( 'status' ) !== 'completed' || ! Builder::isIndexValid( $lang ) ) {

	if ( empty( $_GET ) || empty( $_GET['s'] ) ) {
		AjaxQuery::sendEmptyResponse('free');
	}

	if ( ! $isAlternativeSearchEndpointEnabled ) {
		require_once ABSPATH . WPINC . '/link-template.php';
		require_once ABSPATH . WPINC . '/general-template.php';
		if ( file_exists( ABSPATH . WPINC . '/class-wp-http.php' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-http.php';
		} else {
			// File class-http.php is deprecated since version 5.9.0.
			require_once ABSPATH . WPINC . '/class-http.php';
		}
		require_once ABSPATH . WPINC . '/class-wp-http-streams.php';
		require_once ABSPATH . WPINC . '/class-wp-http-curl.php';
		require_once ABSPATH . WPINC . '/class-wp-http-proxy.php';
		require_once ABSPATH . WPINC . '/class-wp-http-cookie.php';
		require_once ABSPATH . WPINC . '/class-wp-http-encoding.php';
		require_once ABSPATH . WPINC . '/class-wp-http-response.php';
		require_once ABSPATH . WPINC . '/class-wp-http-requests-response.php';
		require_once ABSPATH . WPINC . '/class-wp-http-requests-hooks.php';
	}

	$baseUrl = home_url( '?wc-ajax=dgwt_wcas_ajax_search' );

	$urlPhrase = str_replace( "\\'", "'", $_GET['s'] );
	$urlPhrase = str_replace( '\\"', '"', $urlPhrase );

	$args = array(
		's' => urlencode( $urlPhrase ),
	);

	if ( ! empty( $lang ) ) {
		$args['l'] = $lang;
	}

	$url = add_query_arg( $args, $baseUrl );

	$timeout       = 120;
	$headers       = array();
	$authorization = Helpers::getBasicAuthHeader();
	if ( $authorization ) {
		$headers['Authorization'] = $authorization;
	}

	$r = wp_remote_get( $url, compact( 'headers', 'timeout' ) );

	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) {
		AjaxQuery::sendEmptyResponse( 'free' );
	}

	echo wp_remote_retrieve_body( $r );

	die();
}

if ( ! Builder::searchableCacheExists( $lang ) ) {
	add_filter( 'dgwt/wcas/tnt/search_cache', '__return_false', PHP_INT_MAX - 5 );
}

$query = new AjaxQuery();

if ( empty( $_GET ) || empty( $_GET['s'] ) ) {
	AjaxQuery::sendEmptyResponse();
}

$query->setPhrase( $_GET['s'] );

if ( ! empty( $lang ) ) {
	$query->setLang( $lang );
}

$query->searchProducts();
$query->searchPosts();
$query->searchTaxonomy();
$query->searchVendors();

if ( ! $query->hasResults() ) {
	AjaxQuery::sendEmptyResponse();
}

$query->sendResults();
