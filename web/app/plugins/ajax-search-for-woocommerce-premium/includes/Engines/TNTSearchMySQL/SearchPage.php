<?php

namespace DgoraWcas\Engines\TNTSearchMySQL;

use \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use \DgoraWcas\Engines\TNTSearchMySQL\SearchQuery\SearchResultsPageQuery;
use \DgoraWcas\Helpers;
use \DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search results on the WordPress search page
 *
 * Class SearchPage
 * @package DgoraWcas\Engines\TNTSearchMySQL
 */
class SearchPage {

	/**
	 * Buffer for post IDs uses for search results page
	 * @var null
	 */
	private $postsIDsBuffer = null;

	public function init() {
		// Override default search
		add_action( 'pre_get_posts', array( $this, 'searchProducts' ), 900001 );

		// Clear default search query
		add_filter( 'posts_search', array( 'DgoraWcas\Helpers', 'clearSearchQuery' ), 1000, 2 );

		// Restore search phrase
		add_filter( 'the_posts', array( 'DgoraWcas\Helpers', 'rollbackSearchPhrase' ), 1000, 2 );

		// Return product IDs that have been found
		add_filter( 'dgwt/wcas/search_page/result_post_ids', array( $this, 'getProductIds' ), 10, 2 );
	}

	/**
	 * Disable cache results and narrowing search results to those from our engine
	 *
	 * @param \WP_Query $query
	 */
	public function searchProducts( $query ) {
		if ( ! Helpers::isSearchQuery( $query ) ) {
			return;
		}

		/**
		 * Disable cache: `cache_results` defaults to false but can be enabled
		 */
		$query->set( 'cache_results', false );
		if ( ! empty( $query->query['cache_results'] ) ) {
			$query->set( 'cache_results', true );
		}

		$query->set( 'dgwt_wcas', $query->query_vars['s'] );

		$phrase = $query->query_vars['s'];

		$orderby = 'relevance';
		$order   = 'desc';

		if ( ! empty( $query->query_vars['orderby'] ) ) {
			$orderby = $query->query_vars['orderby'];
		}

		if ( ! empty( $query->query_vars['order'] ) ) {
			$order = strtolower( $query->query_vars['order'] );
		}

		$lang = Multilingual::isMultilingual() ? Multilingual::getCurrentLanguage() : '';
		if ( ! Builder::searchableCacheExists( $lang ) ) {
			add_filter( 'dgwt/wcas/tnt/search_cache', '__return_false', PHP_INT_MAX - 5 );
		}

		$search = new SearchResultsPageQuery();
		$search->setPhrase( $phrase );

		if ( Multilingual::isMultilingual() ) {
			$search->setLang( Multilingual::getCurrentLanguage() );
		}

		$search->searchProducts();

		$results = $search->getProducts( $orderby, $order );

		$postIn = array_map( 'intval', wp_list_pluck( $results, 'post_id' ) );

		// Force WP_Query not to find results
		if ( empty( $postIn ) ) {
			$postIn = array( - 1 );
		}

		// Save for later use
		$this->postsIDsBuffer = $postIn;

		$query->set( 'post__in', $postIn );
		$query->set( 'orderby', 'post__in' );
		// Resetting the key 's' to disable the default search logic.
		$query->set( 's', '' );
	}

	/**
	 * Allow to get the ID of products that have been found
	 *
	 * @param integer[] $postsIDs
	 *
	 * @return mixed
	 */
	public function getProductIds( $postsIDs ) {
		if ( $this->postsIDsBuffer !== null ) {
			return $this->postsIDsBuffer;
		}

		return $postsIDs;
	}

}
