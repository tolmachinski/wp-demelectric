<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomPost {

	private $ids = array();
	private $documents = array();
	private $postType = '';
	private $phrase = '';
	private $lang = '';

	/**
	 * CustomPost constructor.
	 *
	 * @param $ids
	 */
	public function __construct( $ids, $postType, $phrase ) {
		$this->ids      = $ids;
		$this->postType = $postType;
		$this->phrase   = $phrase;
	}

	/**
	 * Get language code
	 *
	 * @return string
	 */
	public function getLang() {
		return $this->lang;
	}

	public function getResults() {
		$this->setResults();
		$this->orderByWeight();

		return $this->documents;
	}

	/**
	 * Set language
	 *
	 * @param string $lang
	 *
	 * @return void
	 */
	public function setLang( $lang ) {
		if ( Multilingual::isLangCode( $lang ) ) {
			$this->lang = $lang;
		}
	}

	/**
	 * Set post and page search results
	 *
	 * @return void
	 */
	public function setResults() {
		global $wpdb;

		$placeholders = array_fill( 0, count( $this->ids ), '%d' );
		$format       = implode( ', ', $placeholders );
		$documents    = array();

		$ids = $this->ids;
		foreach ( $this->ids as $id ) {
			$ids[] = $id;
		}

		$tableName = Utils::getTableName( 'readable' );

		$sql = $wpdb->prepare( "
                SELECT *
                FROM $tableName
                WHERE post_id IN ($format)
                AND name != ''
                ORDER BY FIELD(post_id, $format)
                ",
			$ids
		);

		$r = $wpdb->get_results( $sql );

		if ( ! empty( $r ) && is_array( $r ) && ! empty( $r[0] ) && ! empty( $r[0]->post_id ) ) {

			foreach ( $r as $item ) {
				$item->meta = maybe_unserialize( $item->meta );

				$score = Helpers::calcScore( $this->phrase, $item->name );

				$name = html_entity_decode( $item->name );

				$itemData = array(
					'post_id'   => $item->post_id,
					'value'     => $name,
					'name'      => $name,
					'url'       => $item->url,
					'type'      => 'post',
					'post_type' => $this->postType,
					'score'     => $score,
					'meta'      => $item->meta,
				);

				// Add the post language only if the query is multilingual
				if ( ! empty( $this->lang ) ) {
					$itemData['lang'] = $item->lang;
				}

				$documents[] = $itemData;
			}

		}

		$this->documents = apply_filters( 'dgwt/wcas/tnt/search_results/' . $this->postType, $documents, $this->phrase, $this->getLang() );
	}

	/**
	 * Order found items by weights
	 *
	 * @return void
	 */
	private function orderByWeight() {
		$i = 0;

		foreach ( $this->documents as $post ) {
			$score = 0;
			$score += Helpers::calcScore( $this->phrase, $post['name'] );

			$this->documents[ $i ]['score'] = apply_filters( "dgwt/wcas/tnt/{$this->postType}/score", (float) $score, $post['post_id'], $post, $this );

			$i ++;
		}

		usort( $this->documents, array( 'DgoraWcas\Helpers', 'cmpSimilarity' ) );
	}
}
