<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductVariationQuery {
	/**
	 * Variation Db table name
	 * @var string
	 */
	private $tableName = '';

	/**
	 * Search phrase
	 *
	 * @var string
	 */
	private $phrase = '';

	/**
	 * All relevant products IDs
	 * @var array
	 */
	private $ids = array();

	/**
	 * Raw response from DB
	 *
	 * @var
	 */
	private $result;

	/**
	 * lang
	 *
	 * @var
	 */
	private $lang = '';

	/**
	 * ProductVariationQuery constructor.
	 *
	 * @param string $phrase
	 * @param array $ids
	 * @param string $lang
	 */
	public function __construct( $phrase, $ids, $lang = '' ) {

		if ( ! empty( $lang ) ) {
			$this->lang = $lang;
		}

		$this->setTable();
		$this->setPhrase( $phrase );
		$this->setIds( $ids );
		$this->search();
	}

	/**
	 * Set index table name
	 *
	 * @return void
	 */
	private function setTable() {
		global $wpdb;

		$this->tableName = $wpdb->prefix . Config::VARIATIONS_INDEX;
	}

	private function setPhrase( $phrase ) {
		$this->phrase = $phrase;
	}

	/**
	 * Set product IDs
	 *
	 * @param $ids
	 */
	private function setIds( $ids ) {
		$productIds = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$productIds[] = absint( $id );
			}
		}
		$this->ids = $productIds;
	}

	/**
	 * Search variation
	 *
	 * @return void
	 *
	 */
	private function search() {
		global $wpdb;

		if ( ! array( $this->ids ) || empty( $this->ids ) || empty( $this->phrase ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $this->ids ), '%d' );
		$format       = implode( ', ', $placeholders );

		$pieces   = $this->ids;
		$pieces[] = $this->phrase;

		if ( ! empty( $this->lang ) ) {
			$pieces[] = $this->lang;
		}

		$rawSql = "SELECT *
                   FROM $this->tableName
                   WHERE product_id IN ($format)
                   AND sku = %s";

		if ( ! empty( $this->lang ) ) {
			$rawSql .= ' AND lang = %s';
		}

		$sql = $wpdb->prepare( $rawSql, $pieces );

		$data = $wpdb->get_results( $sql );

		if ( ! empty( $data ) && is_array( $data ) && ! empty( $data[0] ) ) {

			$this->result = $data[0];

		}

	}

	/**
	 * Check if SKU exact match exist
	 *
	 * @return bool
	 */
	public function hasResults() {
		return ! empty( $this->result );
	}

	/**
	 * Get suggestion body
	 *
	 * @return array
	 */
	public function getSuggestionBody() {

		$body = array();

		if ( $this->hasResults() ) {

			$body = array(
				'post_id'      => $this->result->product_id,
				'variation_id' => $this->result->variation_id,
				'value'        => $this->result->title,
				'url'          => $this->result->url,
				'thumb_html'   => '<img src="' . $this->result->image . '">',
				'price'        => $this->result->html_price,
				'desc'         => $this->result->description,
				'sku'          => $this->result->sku,
				'on_sale'      => false,
				'featured'     => false,
				'type'         => 'product_variation'
			);
		}


		return $body;

	}

}
