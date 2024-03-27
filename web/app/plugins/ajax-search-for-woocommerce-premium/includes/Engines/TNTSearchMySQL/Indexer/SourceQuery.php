<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SourceQuery {

	protected $type = 'source_query';

	protected $args = array();

	protected $data = array();

	/** @var string */
	protected $query;

	protected $visibilityTermIds;

	public function __construct( $args = array() ) {
		$this->visibilityTermIds = wc_get_product_visibility_term_ids();

		$this->setArgs( $args );

		$this->buildQuery();
	}

	protected function setArgs( $args ) {

		$defaults = array(
			'ids'     => false, // return only IDS
			'package' => array()
		);

		$this->args = wp_parse_args( $args, $defaults );
	}

	/**
	 * Build SQL query which select products with all necessary fields
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

		// Select product name
		$select .= $this->selectTitle();

		// Select the full description of the product
		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_content' ) === 'on' ) {
			$select .= $this->selectDescription();
		}

		// Select the short description of the product
		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_excerpt' ) === 'on' ) {
			$select .= $this->selectShortDescription();
		}

		if ( Helpers::canSearchInVariableProducts() ) {
			$select .= $this->selectDescriptionsOfVariations();
		}

		// Select the SKU and variations SKUs of the product
		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_sku' ) === 'on' ) {
			$select .= $this->selectSku();
			$select .= $this->selectSkusOfVariations();
		}

		// Select the attributes of the product
		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_attributes' ) === 'on' ) {
			$attributesTaxonomies = Helpers::getAttributesTaxonomies();
			foreach ( $attributesTaxonomies as $taxonomy ) {
				$select .= $this->selectTerms( $taxonomy );
			}
		}

		// Select the custom fields values of the product.
		$customFields = DGWT_WCAS()->settings->getOption( 'search_in_custom_fields' );
		$customFields = empty( $customFields ) ? array() : explode( ',', $customFields );
		$customFields = apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/search_in_custom_fields', $customFields );
		if ( ! empty( $customFields ) ) {
			foreach ( $customFields as $customField ) {
				if ( empty( $customField ) ) {
					continue;
				}
				$select .= $this->selectCustomField( $customField );
			}
		}

		// Select the values of terms in selected taxonomies
		$activeTaxonomies = DGWT_WCAS()->tntsearchMySql->taxonomies->getActiveTaxonomies( 'search_related_products' );
		foreach ( $activeTaxonomies as $taxonomy ) {
			$select .= $this->selectTerms( $taxonomy );

			$indexDescOld = apply_filters( 'dgwt/wcas/tnt/' . $this->type . '/term_description', false ); // Deprecated filter name
			$indexDescNew = apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/term_description', false );

			if ( $indexDescOld || $indexDescNew ) {
				$select .= $this->selectTerms( $taxonomy, 'desc', 'tax_desc_' );
			}

		}

		// Select the language code
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

		// WooCommerce visibility - Exclude from search
		$where .= $this->whereExcludeFromSearch();

		// WooCommerce visibility - Exclude out of stock
		if ( DGWT_WCAS()->settings->getOption( 'exclude_out_of_stock' ) === 'on' ) {
			$where .= $this->whereExcludeOutOfStock();
		};

		// Exclude/include products matched by filters
		if ( ! empty( DGWT_WCAS()->settings->getOption( 'filter_products_rules' ) ) ) {
			$where .= $this->whereExcludeOrIncludeMatchedByFilters();
		};

		// Set range of products set
		if ( ! empty( $this->args['package'] ) ) {
			$where .= $this->whereNarrowDownToTheSet( $this->args['package'] );
		}

		// Narrow all posts to only products
		$where .= $this->wherePostTypes( array( 'product' ) );

		// Get only published posts
		$where .= $this->wherePublished();


		#-------------------------------
		# BUILD QUERY
		#-------------------------------

		$select = apply_filters( 'dgwt/wcas/tnt/source_query/select', $select, $this, $onlyIDs ); // deprecated
		$where  = apply_filters( 'dgwt/wcas/tnt/source_query/where', $where, $this, $onlyIDs ); // deprecated

		$select = apply_filters( 'dgwt/wcas/indexer/source_query/select', $select, $this, $onlyIDs );
		$join   = apply_filters( 'dgwt/wcas/indexer/source_query/join', '', $this, $onlyIDs );
		$where  = apply_filters( 'dgwt/wcas/indexer/source_query/where', $where, $this, $onlyIDs );


		$sql = "SELECT $select
                FROM $wpdb->posts posts
                $join
                WHERE  1=1
                $where
               ";

		$this->query = apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/query', $sql, $this, $onlyIDs );
	}


	/**
	 * Part of the SQL where statement which doesn't index products excluded from search via WooCommerce product settings
	 *
	 * @return string part of the SQL WHERE
	 */
	public function whereExcludeFromSearch() {
		global $wpdb;

		$where = $wpdb->prepare( " AND posts.ID NOT IN (
                                                   SELECT object_id
                                                   FROM $wpdb->term_relationships
                                                   WHERE term_taxonomy_id IN (%d)
				                                )",
			$this->visibilityTermIds['exclude-from-search']
		);

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/exclude_from_search', $where );
	}

	/**
	 * Part of the SQL where statement which doesn't index products with the stock status "outofstock"
	 *
	 * @return string part of the SQL WHERE
	 */
	public function whereExcludeOutOfStock() {

		global $wpdb;

		$where = $wpdb->prepare( " AND ( posts.ID NOT IN (
                                                   SELECT object_id
                                                   FROM $wpdb->term_relationships
                                                   WHERE term_taxonomy_id IN (%d)
				                                ))",
			$this->visibilityTermIds['outofstock']
		);

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/exclude_outofstock', $where );
	}

	/**
	 * Part of the SQL where statement.
	 * Do not index or index only products with a given category, tag or attribute
	 *
	 * @return string part of the SQL WHERE
	 */
	public function whereExcludeOrIncludeMatchedByFilters() {
		global $wpdb;
		$where = '';

		$rules = Helpers::getFilterProductsRules__premium_only();

		if ( empty( $rules ) ) {
			return $where;
		}

		$filterMode              = DGWT_WCAS()->settings->getOption( 'filter_products_mode', 'exclude' );
		$filteredTermTaxonomyIds = array();
		$langs                   = array();

		if ( Multilingual::isMultilingual() ) {
			// Others languages than default
			$langs = array_values( array_diff( Multilingual::getLanguages(), array( Multilingual::getDefaultLanguage() ) ) );
		}

		foreach ( $rules as $group => $values ) {
			$matchedTerms = Helpers::getFilterGroupTerms__premium_only( $group, $values );
			if ( ! empty( $matchedTerms ) ) {
				$filteredTermTaxonomyIds = array_merge( $filteredTermTaxonomyIds, wp_list_pluck( $matchedTerms, 'term_taxonomy_id' ) );

				// Get all related term's ids from all languages (except default)
				if ( ! empty( $langs ) ) {
					$taxonomy = Helpers::getTaxonomyFromFilterGroup__premium_only( $group );
					foreach ( $langs as $lang ) {
						foreach ( $matchedTerms as $term ) {
							$termTranslated = Multilingual::getTerm( $term->term_id, $taxonomy, $lang );
							if ( ! empty( $termTranslated ) ) {
								$filteredTermTaxonomyIds[] = $termTranslated->term_taxonomy_id;
							}
						}
					}
				}
			}
		}

		if ( empty( $filteredTermTaxonomyIds ) ) {
			return $where;
		}

		$placeholders = array_fill( 0, count( $filteredTermTaxonomyIds ), '%d' );
		$format       = implode( ', ', $placeholders );

		$where = $wpdb->prepare( " AND ( posts.ID " . ( $filterMode === 'exclude' ? 'NOT' : '' ) . " IN (
                                                   SELECT object_id
                                                   FROM $wpdb->term_relationships
                                                   WHERE term_taxonomy_id IN ($format)
				                                ))",
			$filteredTermTaxonomyIds
		);

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/exclude_include', $where );
	}

	/**
	 * Part of the SQL where statement which narrows down to the specific IDs set
	 *
	 * @param array $setIds
	 *
	 * @return string part of the SQL WHERE
	 */
	protected function whereNarrowDownToTheSet( $setIds = array() ) {

		global $wpdb;

		$placeholders = array_fill( 0, count( $setIds ), '%d' );
		$format       = implode( ', ', $placeholders );

		$where = $wpdb->prepare( " AND posts.ID IN ($format)", $setIds );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/narrow_set', $where );
	}

	/**
	 * Part of the SQL where statement which narrows down to the selected post types
	 *
	 * @param array $postTypes
	 *
	 * @return string part of the SQL WHERE
	 */
	protected function wherePostTypes( $postTypes = array() ) {
		global $wpdb;
		$where = '';

		if ( count( $postTypes ) === 1 ) {

			$where = $wpdb->prepare( " AND (post_type = %s) ", $postTypes[0] );

		} elseif ( count( $postTypes ) > 1 ) {

			$placeholders = array_fill( 0, count( $postTypes ), '%s' );
			$format       = implode( ', ', $placeholders );

			$where = $wpdb->prepare( " AND post_type IN ($format)", $postTypes );

		}

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/post_types', $where );
	}

	/**
	 * Part of the SQL where statement which narrows down to the published posts
	 *
	 * @return string part of the SQL WHERE
	 */
	protected function wherePublished() {
		$where = " AND post_status = 'publish' ";

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/where/published', $where );
	}


	/**
	 * Part of the SQL select statement which retrieves the product ID
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectID( string $groupName = 'ID' ): string {
		global $wpdb;

		$select = $wpdb->prepare( "posts.ID AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/id', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the product name
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectTitle( string $groupName = 'name' ): string {
		global $wpdb;

		$select = $wpdb->prepare( ", posts.post_title AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/name', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the product description
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectDescription( string $groupName = 'desc' ): string {
		global $wpdb;

		$select = $wpdb->prepare( ", posts.post_content AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/description', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the product short description
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectShortDescription( string $groupName = 'excerpt' ): string {
		global $wpdb;

		$select = $wpdb->prepare( ", posts.post_excerpt AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/excerpt', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the product SKU
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectSku( string $groupName = 'sku' ): string {
		global $wpdb;

		$select = $wpdb->prepare( ", (SELECT meta_value FROM $wpdb->postmeta WHERE post_id = posts.ID AND meta_key='_sku' LIMIT 1) AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/sku', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the SKUs of all product variations
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectSkusOfVariations( string $groupName = 'variations_skus' ): string {
		global $wpdb;

		$excludeOutOfStockSql = '';
		if ( DGWT_WCAS()->settings->getOption( 'exclude_out_of_stock' ) === 'on' ) {
			$excludeOutOfStockSql = "AND psv.ID NOT IN (
							            SELECT post_id FROM $wpdb->posts AS psv2
			                            JOIN $wpdb->postmeta AS pmsv2 ON psv2.ID = pmsv2.post_id
			                            WHERE psv2.post_type = 'product_variation'
			                            AND psv2.post_parent = posts.ID
			                            AND pmsv2.meta_key = '_stock_status'
			                            AND pmsv2.meta_value = 'outofstock'
			                         )";
		}

		$select = $wpdb->prepare( ", (SELECT GROUP_CONCAT( pmsv.meta_value SEPARATOR ' | ')
                             FROM $wpdb->posts AS psv
                             JOIN $wpdb->postmeta AS pmsv ON psv.ID = pmsv.post_id
                             WHERE psv.post_type = 'product_variation'
                             AND psv.post_parent = posts.ID
                             AND pmsv.meta_key='_sku'
                             AND pmsv.meta_value != ''
                             $excludeOutOfStockSql
                             ) AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/variations_skus', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the descriptions of all product variations
	 *
	 * @param string $groupName the name of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectDescriptionsOfVariations( string $groupName = 'variations_descriptions' ): string {

		global $wpdb;

		$excludeOutOfStockSql = '';
		if ( DGWT_WCAS()->settings->getOption( 'exclude_out_of_stock' ) === 'on' ) {
			$excludeOutOfStockSql = "AND pvd.ID NOT IN (
							            SELECT post_id FROM $wpdb->posts AS pvd2
			                            JOIN $wpdb->postmeta AS pmvd2 ON pvd2.ID = pmvd2.post_id
			                            WHERE pvd2.post_type = 'product_variation'
			                            AND pvd2.post_parent = posts.ID
			                            AND pmvd2.meta_key = '_stock_status'
			                            AND pmvd2.meta_value = 'outofstock'
			                         )";
		}

		$select = $wpdb->prepare( ", (SELECT GROUP_CONCAT(pmvd.meta_value SEPARATOR ' | ')
                             FROM $wpdb->posts AS pvd
                             JOIN $wpdb->postmeta AS pmvd ON pvd.ID = pmvd.post_id
                             WHERE pvd.post_type = 'product_variation'
                             AND pvd.post_parent = posts.ID
                             AND pmvd.meta_key='_variation_description'
                             AND pmvd.meta_value != ''
                             $excludeOutOfStockSql
                             ) AS %s", $groupName );

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/variations_descriptions', $select, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the values of selected custom field
	 *
	 * @param string $key key of custom field
	 * @param string $prefixGroupName the prefix of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectCustomField( string $key, string $prefixGroupName = 'cf_' ): string {
		global $wpdb;

		$groupName = $prefixGroupName . sanitize_key( $key );
		$select    = $wpdb->prepare(
			", (SELECT meta_value FROM $wpdb->postmeta WHERE post_id = posts.ID AND meta_key=%s LIMIT 1) AS %s",
			$key, $groupName
		);

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/custom_field', $select, $key, $groupName );
	}

	/**
	 * Part of the SQL select statement which retrieves the values of terms from selected taxonomy
	 *
	 * @param string $taxonomy
	 * @param string $typeOfData | value: values of the terms
	 *                           | desc: description of the terms
	 *                           | id_and_value: ids and values
	 *
	 * @param string $prefixGroupName the prefix of the group of data
	 *
	 * @return string part of the SQL SELECT
	 */
	public function selectTerms( string $taxonomy, string $typeOfData = 'value', string $prefixGroupName = 'tax_' ): string {
		global $wpdb;

		$select    = '';
		$groupName = '';

		if ( ! empty( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {

			$taxonomy  = sanitize_key( $taxonomy );
			$groupName = $prefixGroupName . $taxonomy;

			$groupConcat = "GROUP_CONCAT( t.name SEPARATOR ' | ')";

			if ( $typeOfData === 'desc' ) {
				$groupConcat = "GROUP_CONCAT( tt.description SEPARATOR ' | ')";
			}

			if ( $typeOfData === 'id_and_value' ) {
				$groupConcat = "GROUP_CONCAT( t.term_id, ' __ ', t.name SEPARATOR ' | ')";
			}

			$select = $wpdb->prepare( ", (SELECT $groupConcat
                             FROM $wpdb->terms AS t
                             INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                             INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                             WHERE tt.taxonomy = %s
                             AND tr.object_id = posts.ID
                             ) AS %s",
				$taxonomy, $groupName );

		}

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/terms', $select, $taxonomy, $groupName, $typeOfData );
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

			$select = $wpdb->prepare( ", (SELECT language_code
                                 FROM $translationsTable
                                 WHERE element_type = 'post_product'
                                 AND element_id = posts.ID
                                 AND language_code IN ('" . implode( "', '", esc_sql( $langs ) ) . "') LIMIT 1) AS %s",
				$groupName );
		}

		if ( Multilingual::isPolylang() ) {
			$select = $wpdb->prepare( ", (SELECT slug
                                 FROM $wpdb->terms
                                 WHERE term_id = (
                                     SELECT t.term_id
                                     FROM $wpdb->terms AS t
                                     INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                                     INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                     WHERE tt.taxonomy = 'language'
                                     AND tr.object_id = posts.ID
                                     LIMIT 1
                                 )) AS %s", $groupName );

		}

		return apply_filters( 'dgwt/wcas/indexer/' . $this->type . '/select/lang', $select );

	}

	/**
	 * Get SQL response
	 *
	 * @return array
	 */
	public function getData() {
		global $wpdb;

		$onlyIDs = $this->onlyIDs();

		do_action( 'dgwt/wcas/tnt/source_query/before_request', $this, $onlyIDs ); // Deprecated action name
		do_action( 'dgwt/wcas/indexer/' . $this->type . '/before_request', $this, $onlyIDs );

		$groupConcatMaxLen = apply_filters( 'dgwt/wcas/tnt/' . $this->type . '/group_concat_max_len', 100000 );

		if ( ! empty( $groupConcatMaxLen ) ) {
			$groupConcatMaxLen = absint( $groupConcatMaxLen );
			$wpdb->query( 'SET SESSION group_concat_max_len = ' . $groupConcatMaxLen . ';' );
		}

		if ( $onlyIDs ) {
			$rows = $wpdb->get_col( apply_filters( 'dgwt/wcas/tnt/' . $this->type . '/request', $this->query, $this, $onlyIDs ) );
		} else {
			$rows = $wpdb->get_results( apply_filters( 'dgwt/wcas/tnt/' . $this->type . '/request', $this->query, $this, $onlyIDs ), ARRAY_A );
		}


		$response = array();

		if ( ! empty( $rows ) && ! is_wp_error( $rows ) ) {
			$response = $rows;
		}

		return apply_filters( 'dgwt/wcas/tnt/' . $this->type . '/data', $response, $this, $onlyIDs );
	}

	/**
	 * Get SQL
	 *
	 * @return string
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * Check if query has return only ids instead of full data
	 *
	 * @return bool
	 */
	protected function onlyIDs() {
		return (bool) $this->args['ids'];
	}

}
