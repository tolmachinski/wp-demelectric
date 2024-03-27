<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Debug;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Debugger {
	const DEBUG_SEARCH_LOGS_KEY = 'dgwt_wcas_debug_search_logs';

	/**
	 * Save log for the search
	 *
	 * @param string $message
	 * @param string $context
	 *
	 * @return bool
	 */
	public static function log( $message, $context = 'common' ) {
		$added = false;

		$logs = self::getRawLogs();

		if ( ! array_key_exists( $context, $logs ) ) {
			$logs[ $context ] = array();
		}

		if ( is_array( $logs[ $context ] ) ) {
			$logs[ $context ][] = $message;

			$added = update_option( self::DEBUG_SEARCH_LOGS_KEY, $logs );
		}

		return $added;
	}

	/**
	 * Get raw logs
	 *
	 * @return array
	 */
	public static function getRawLogs() {
		global $wpdb;

		$data = array();

		$opt = $wpdb->get_var( $wpdb->prepare( "SELECT SQL_NO_CACHE option_value FROM $wpdb->options WHERE option_name = %s",
			self::DEBUG_SEARCH_LOGS_KEY ) );

		if ( ! empty( $opt ) ) {
			$opt = @unserialize( $opt );
			if ( is_array( $opt ) ) {
				$data = $opt;
			}
		}

		return $data;
	}

	/**
	 * Get all logs
	 *
	 * @param string $context
	 *
	 * @return array
	 */
	public static function getLogs( $context = 'common' ) {

		$logs   = array();
		$values = self::getRawLogs();

		if (
			! empty( $values )
			&& is_array( $values )
			&& array_key_exists( $context, $values )
			&& is_array( $values[ $context ] )
		) {
			$logs = $values[ $context ];
		}

		return $logs;
	}

	/**
	 * Print logs
	 *
	 * @param string $title
	 * @param string $context
	 *
	 * @return void
	 */
	public static function printLogs( $title = '', $context = 'common' ) {
		$title = ! empty( $title ) ? $title : $context;

		$html = '<div class="dgwt-wcas-indexer-logs">';
		$html .= '<h4>' . $title . '</h4>';

		$logs = self::getLogs( $context );

		if ( ! empty( $logs ) ) {
			foreach ( $logs as $log ) {
				$html .= '<span class="dgwt-wcas-indexer-log">';
				$html .= $log;
				$html .= '</span>';
			}
		}

		$html .= '</div>';

		echo $html;
	}

	/**
	 * Wipe logs
	 *
	 * @return void
	 */
	public static function wipeLogs( $context = 'common' ) {
		$logs = self::getRawLogs();

		if (
			! empty( $logs )
			&& is_array( $logs )
			&& array_key_exists( $context, $logs )
		) {
			unset( $logs[ $context ] );
			update_option( self::DEBUG_SEARCH_LOGS_KEY, $logs );
		}
	}

	/**
	 * Log search results
	 *
	 * @param $queryObj
	 *
	 * @return void
	 */
	public static function logSearchResults( $queryObj ) {
		if ( ! $queryObj->hasResults() ) {
			Debugger::log( 'no results', 'search-resutls' );

			return;
		}

		ob_start();
		$queryObj->sendResults( false );
		$results = ob_get_clean();
		ob_start();
		$response = json_decode( $results );

		$rows = array();
		$html = '';

		if ( ! empty( $response->suggestions ) ) {
			foreach ( $response->suggestions as $suggestion ) {
				if (
					! empty( $suggestion->type )
					&& in_array( $suggestion->type, array(
						'taxonomy',
						'product',
						'product_variation',
						'post',
						'page'
					) )
				) {
					$name = $suggestion->value . ' | ' . $suggestion->type;

					if ( ! empty( $suggestion->post_id ) ) {
						$name .= ' | ' . $suggestion->post_id;
					}

					if ( ! empty( $suggestion->taxonomy ) ) {
						$name .= ' | ' . $suggestion->term_id . ' | ' . $suggestion->taxonomy;
					}

					if ( ! empty( $suggestion->sku ) ) {
						$name .= ' | SKU ' . $suggestion->sku;
					}

					if ( ! empty( $suggestion->score ) ) {
						$name .= ' | Score ' . $suggestion->score;
					}

					$rows[] = $name;
				}
			}
		}

		if ( ! empty( $rows ) ) {
			$html = '<ol>';
			foreach ( $rows as $row ) {
				$html .= '<li>' . $row . '</li>';
			}
			$html .= '</ol>';
		}

		$html .= '<p>Total: ' . $response->total . ' | TNT Time: ' . $response->tntTime . ' | Time: ' . $response->time . '</p>';

		Debugger::log( $html, 'search-resutls' );
	}
}
