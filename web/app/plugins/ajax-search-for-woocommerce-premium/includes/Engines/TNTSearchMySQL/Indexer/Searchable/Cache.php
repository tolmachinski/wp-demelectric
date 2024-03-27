<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable;

use DgoraWcas\Helpers;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cache {
	/** @var bool */
	private $enabled;
	/** @var string */
	private $lang = '';
	/** @var string */
	private $postType = '';

	/**
	 * Cache constructor
	 */
	public function __construct() {
		$this->setStatus();
	}

	/**
	 * Set cache status
	 */
	private function setStatus() {
		$enabled = true;

		if ( defined( 'DGWT_WCAS_SEARCH_CACHE' ) ) {
			$enabled = (bool) DGWT_WCAS_SEARCH_CACHE;
		}

		$enabled = apply_filters( 'dgwt/wcas/tnt/search_cache', $enabled );

		$this->enabled = Helpers::doesDbSupportJson__premium_only() && $enabled;
	}

	/**
	 * @param $lang
	 *
	 * @return void
	 */
	public function setLang( $lang ) {
		$this->lang = $lang;
	}

	/**
	 * @param $postType
	 *
	 * @return void
	 */
	public function setPostType( $postType ) {
		$this->postType = $postType;
	}

	/**
	 * Get cache status
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return (bool) $this->enabled;
	}

	/**
	 * Set value into cache
	 *
	 * @param string $key
	 * @param string $value JSON
	 *
	 * @return bool
	 */
	public function set( $key, $value ) {
		global $wpdb;

		if ( ! $this->enabled ) {
			return true;
		}

		$cacheTable = Utils::getTableName( 'searchable_cache', $this->lang, $this->postType );

		$query = "INSERT INTO $cacheTable (`cache_key`, `cache_value`) VALUES (%s, %s) ON DUPLICATE KEY UPDATE `cache_key` = VALUES(`cache_key`), `cache_value` = VALUES(`cache_value`)";
		$res   = $wpdb->query( $wpdb->prepare( $query, $key, $value ) );

		return $res !== false;
	}

	/**
	 * Get value from cache
	 *
	 * @param $key
	 *
	 * @return bool|mixed
	 */
	public function get( $key ) {
		global $wpdb;

		if ( ! $this->enabled ) {
			return false;
		}

		$cacheTable = Utils::getTableName( 'searchable_cache', $this->lang, $this->postType );

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $cacheTable WHERE cache_key = %s LIMIT 1", $key ), ARRAY_A );

		if ( ! empty( $result['cache_value'] ) ) {
			$value = json_decode( $result['cache_value'] );

			return json_last_error() === JSON_ERROR_NONE ? $value : false;
		}

		return false;
	}

	/**
	 * Delete cache entry by part of it's value
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function deleteByValue( $value ) {
		global $wpdb;

		if ( ! $this->enabled ) {
			return true;
		}

		$cacheTable = Utils::getTableName( 'searchable_cache', $this->lang, $this->postType );

		$query = "DELETE FROM $cacheTable WHERE JSON_CONTAINS(`cache_value`, %s) = 1";
		$res   = $wpdb->query( $wpdb->prepare( $query, $value ) );

		return $res !== false;
	}
}
