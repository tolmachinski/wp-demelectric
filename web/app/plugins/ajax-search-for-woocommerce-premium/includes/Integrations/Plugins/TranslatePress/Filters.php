<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\TranslatePress;

use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filters {
	const PLUGIN_NAME = 'translatepress-multilingual/index.php';

	public function init() {
		if ( ! Config::isPluginActive( self::PLUGIN_NAME ) ) {
			return;
		}

		add_filter( 'dgwt/wcas/tnt/search_results/products', array( $this, 'filterResultsByLang' ), 10, 3 );
		add_filter( 'dgwt/wcas/tnt/search_results/post', array( $this, 'filterResultsByLang' ), 10, 3 );
		add_filter( 'dgwt/wcas/tnt/search_results/page', array( $this, 'filterResultsByLang' ), 10, 3 );
	}

	/**
	 * Filter search results by language
	 *
	 * @param $rows
	 * @param $phrase
	 * @param $lang
	 *
	 * @return array
	 */
	public function filterResultsByLang( $rows, $phrase, $lang ) {
		// If TranslatePress is active but only with one language then
		// $lang is empty and there is no need to filter the results
		if ( empty( $lang ) ) {
			return $rows;
		}

		$rows = array_filter( $rows, function ( $row ) use ( $lang ) {
			$rowLang = '';
			if ( isset( $row->lang ) ) {
				$rowLang = $row->lang;
			} else if ( isset( $row['lang'] ) ) {
				$rowLang = $row['lang'];
			}

			return $rowLang === $lang;
		} );

		$rows = array_values( $rows );

		return $rows;
	}
}
