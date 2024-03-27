<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostsSourceQuery extends SourceQuery {

	protected $type = 'post_source_query';

	private $postType = 'post';

	public function __construct( $args = array() ) {
		$this->setArgs( $args );
		$this->setPostType();

		$this->buildQuery();
	}

	protected function setArgs( $args ) {
		$defaults = array(
			'postType' => 'post',
			'ids'      => false, // return only IDS
			'package'  => array()
		);

		$this->args = wp_parse_args( $args, $defaults );
	}

	/**
	 * Build SQL query which select posts with all necessary fields
	 *
	 * @return void
	 */
	protected function buildQuery() {
		global $wpdb;

		#-------------------------------
		# SELECT
		#-------------------------------
		$select = '';

		// Select product ID
		$select .= $this->selectID();

		// Select post title
		$select .= $this->selectTitle();

		// Select post content
		if ( apply_filters( 'dgwt/wcas/tnt/post_source_query/description', false ) ) {
			$select .= $this->selectDescription();
		}

		// Select post type
		$select .= $this->selectPostType();

		// Select post language
		if ( Multilingual::isMultilingual() ) {
			$select .= $this->selectLang();
		}

		// Select only IDs
		$onlyIDs = $this->onlyIDs();
		if ( $onlyIDs ) {
			$select = 'posts.ID';
		}

		#-------------------------------
		# WHERE
		#-------------------------------
		$where = '';

		// Set range of products set
		if ( ! empty( $this->args['package'] ) ) {
			$where .= $this->whereNarrowDownToTheSet( $this->args['package'] );
		}

		// Narrow all posts to only selected post type
		$where .= $this->wherePostTypes( array( $this->getPostType() ) );

		// Get only published posts
		$where .= $this->wherePublished();

		// Excluded ID-s
		$where .= $this->whereExcludeIDsFromSearch();

		$select = apply_filters( 'dgwt/wcas/tnt/post_source_query/select', $select, $this, $onlyIDs ); // deprecated
		$where  = apply_filters( 'dgwt/wcas/tnt/post_source_query/where', $where, $this, $onlyIDs ); // deprecated

		$select = apply_filters( 'dgwt/wcas/indexer/post_source_query/select', $select, $this, $onlyIDs );
		$join   = apply_filters( 'dgwt/wcas/indexer/post_source_query/join', '', $this, $onlyIDs );
		$where  = apply_filters( 'dgwt/wcas/indexer/post_source_query/where', $where, $this, $onlyIDs );

		$sql = "SELECT $select
                FROM $wpdb->posts posts
                $join
                WHERE  1=1
                $where
               ";

		$this->query = apply_filters( 'dgwt/wcas/indexer/post_source_query/query', $sql, $this, $onlyIDs );
	}

	/**
	 * Part of the SQL where we could exclude posts with specific IDs
	 *
	 * @return string part of the SQL WHERE
	 */
	public function whereExcludeIDsFromSearch() {
		global $wpdb;

		$where       = '';
		$excludedIds = array();

		if ( $this->postType === 'page' ) {
			$wooPages = array(
				'woocommerce_shop_page_id',
				'woocommerce_cart_page_id',
				'woocommerce_checkout_page_id',
				'woocommerce_myaccount_page_id',
				'woocommerce_edit_address_page_id',
				'woocommerce_view_order_page_id',
				'woocommerce_change_password_page_id',
				'woocommerce_logout_page_id',
			);
			foreach ( $wooPages as $page ) {
				$pageID = get_option( $page );
				if ( ! empty( $pageID ) && intval( $pageID ) > 0 ) {
					$excludedIds[] = intval( $pageID );
				}
			}
		}

		$excludedIds = apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/excluded_ids', $excludedIds, $this->postType );

		if ( ! empty( $excludedIds ) ) {
			$excludedIds  = array_map( 'intval', $excludedIds );
			$placeholders = array_fill( 0, count( $excludedIds ), '%d' );
			$format       = implode( ', ', $placeholders );
			$where        = $wpdb->prepare( " AND posts.ID NOT IN ($format)", $excludedIds );
		}

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/exclude_ids_from_search', $where, $this->postType );
	}

	/**
	 * Part of the SQL select statement which retrieves post type
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectPostType( $groupName = 'post_type' ) {
		global $wpdb;

		$select = $wpdb->prepare( ", (SELECT post_type
                             FROM $wpdb->posts
                             WHERE posts.ID = ID) AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/posts_source_query/select/post_type', $select );
	}

	/**
	 * Part of the SQL select statement which retrieves the language code
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectLang( $groupName = 'lang' ) {
		global $wpdb;

		$langs  = Multilingual::getLanguages();
		$select = '';

		if ( Multilingual::isWPML() ) {
			$translationsTable = $wpdb->prefix . 'icl_translations';

			$postType       = $this->getPostType();
			$wpmlObjectTypes = array();
			$wpmlObjectTypes[] = 'post_' . $postType;

			$select = $wpdb->prepare( ", (SELECT language_code
                                 FROM $translationsTable
                                 WHERE element_type IN ('" . implode( "', '", esc_sql( $wpmlObjectTypes ) ) . "')
                                 AND element_id = posts.ID
                                 AND language_code IN ('" . implode( "', '", esc_sql( $langs ) ) . "') LIMIT 1) AS %s",
				$groupName );
		}

		if ( Multilingual::isPolylang() ) {
			$select = ", (SELECT slug
                                 FROM $wpdb->terms
                                 WHERE term_id = (
                                     SELECT t.term_id
                                     FROM $wpdb->terms AS t
                                     INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                                     INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                     WHERE tt.taxonomy = 'language'
                                     AND tr.object_id = posts.ID
                                     LIMIT 1
                                 )) AS lang";
		}

		return apply_filters( 'dgwt/wcas/indexer/posts_source_query/select/lang', $select );
	}

	/**
	 * Set post type
	 *
	 * @return void
	 */
	private function setPostType() {
		if ( ! empty( $this->args['postType'] ) ) {
			$output = sanitize_key( $this->args['postType'] );
			if ( ! empty( $output ) ) {
				$this->postType = $output;
			}
		}
	}

	/**
	 * Get post type
	 *
	 * @return string
	 */
	public function getPostType() {
		return $this->postType;
	}
}
