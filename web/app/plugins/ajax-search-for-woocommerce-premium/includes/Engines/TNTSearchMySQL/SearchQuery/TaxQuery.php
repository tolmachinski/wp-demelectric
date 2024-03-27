<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxQuery {

	/**
	 * @var array
	 */
	private $taxonomies = array();
	private $settings = array();
	private $taxTable = '';
	private $lang = '';

	public function __construct() {
		$this->setTable();
		$this->setSettings();
		$this->setTaxonomies();
	}

	/**
	 * Check if can search matching taxonomies
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return ! empty( $this->taxonomies );
	}

	/**
	 * Set taxonomy table name
	 *
	 * @return void
	 */
	private function setTable() {
		global $wpdb;

		$this->taxTable = $wpdb->prefix . Config::READABLE_TAX_INDEX;
	}

	/**
	 * Load settings
	 *
	 * @return void
	 */
	protected function setSettings() {
		$this->settings = Settings::getSettings();
	}

	/**
	 * Get option
	 *
	 * @param $option
	 *
	 * @return string
	 */
	private function getOption( $option ) {
		$value = '';
		if ( array_key_exists( $option, $this->settings ) ) {
			$value = $this->settings[ $option ];
		}

		return $value;
	}

	/**
	 * Set allowed product taxonomies
	 *
	 * @return void
	 */
	private function setTaxonomies() {
		global $wpdb;

		if ( ! Helpers::isTableExists( $this->taxTable ) ) {
			return;
		}

		$taxonomies = $wpdb->get_col( "SELECT DISTINCT(taxonomy) FROM $this->taxTable" );

		$this->taxonomies = apply_filters( 'dgwt/wcas/tnt/search_taxonomies', $taxonomies );
	}

	/**
	 * Get allowed product taxonomies
	 *
	 * @return array
	 */
	public function getActiveTaxonomies() {
		return $this->taxonomies;
	}

	/**
	 * Set language
	 *
	 * @param $lang
	 *
	 * @return void
	 */
	public function setLang( $lang ) {
		$this->lang = $lang;
	}

	public function search( $phrase ) {
		global $wpdb;

		$results = array();

		$term = $wpdb->esc_like( $phrase );

		$where = ' AND (';
		$i     = 0;
		foreach ( $this->getActiveTaxonomies() as $taxonomy ) {
			if ( $i === 0 ) {
				$where .= $wpdb->prepare( '  taxonomy = %s', $taxonomy );
			} else {
				$where .= $wpdb->prepare( ' OR taxonomy = %s', $taxonomy );
			}
			$i ++;
		}
		$where .= ')';

		if ( ! empty( $this->lang ) ) {
			$where .= $wpdb->prepare( ' AND lang = %s ', $this->lang );
		}

		$sql = $wpdb->prepare( "SELECT *
                                      FROM " . $this->taxTable . "
                                      WHERE 1 = 1
                                      $where
                                      AND term_name LIKE %s
                                      ORDER BY taxonomy, total_products DESC",
			'%' . $term . '%'
		);

		$r = $wpdb->get_results( $sql );

		$groups = array();

		if ( ! empty( $r ) && is_array( $r ) ) {
			foreach ( $r as $item ) {

				$score     = Helpers::calcScore( $phrase, $item->term_name );
				$showImage = $this->getOption( 'show_product_tax_' . $item->taxonomy . '_images' ) === 'on';

				$groups[ $item->taxonomy ][] = array(
					'term_id'     => $item->term_id,
					'taxonomy'    => $item->taxonomy,
					'value'       => html_entity_decode( $item->term_name ),
					'url'         => $item->term_link,
					'image_src'   => $showImage && ! empty( $item->image ) ? $item->image : '',
					'breadcrumbs' => $item->breadcrumbs,
					'count'       => $item->total_products,
					'type'        => 'taxonomy',
					'score'       => apply_filters( 'dgwt/wcas/search_results/term/score', $score, $phrase, $item )
				);
			}
		}

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $key => $group ) {
				usort( $groups[ $key ], array( 'DgoraWcas\Helpers', 'cmpSimilarity' ) );
				$results = array_merge( $results, $groups[ $key ] );
			}
		}

		return apply_filters( 'dgwt/wcas/tnt/search_results/taxonomies', $results, $phrase, $this->lang );
	}
}
