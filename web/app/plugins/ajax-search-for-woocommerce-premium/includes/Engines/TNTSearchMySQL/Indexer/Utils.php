<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\Tokenizer;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Utils {
	/**
	 * Clear content from HTML tags, comments, scripts and shortcodes
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public static function clearContent( $content ) {
		// Strip all tags with separating its text, eg. "<h1>Foo</h1>bar" >> "Foo bar" (not "Foobar")
		$content = str_replace( '  ', ' ', wp_strip_all_tags( str_replace( '<', ' <', (string) $content ) ) );

		// If we have shortcodes, remove all except allowed by `dgwt/wcas/indexer/allowed_shortcodes` filter
		if ( strpos( $content, '[' ) !== false ) {
			add_filter( 'strip_shortcodes_tagnames', array( __CLASS__, 'stripShortcodesTagnames' ), 10, 2 );
			$content = strip_shortcodes( $content );
			remove_filter( 'strip_shortcodes_tagnames', array( __CLASS__, 'stripShortcodesTagnames' ) );

			$content = do_shortcode( $content );
			$content = str_replace( '  ', ' ', wp_strip_all_tags( str_replace( '<', ' <', $content ) ) );
		}

		return trim( $content );
	}

	/**
	 * Filter shortcodes that will be stripped from content
	 *
	 * @param string[] $tags_to_remove
	 * @param string $content
	 *
	 * @return string[]
	 */
	public static function stripShortcodesTagnames( $tags_to_remove, $content ) {
		$allowedShortcodes = apply_filters( 'dgwt/wcas/indexer/allowed_shortcodes', array() );

		if ( is_array( $allowedShortcodes ) ) {
			$tags_to_remove = array_diff( $tags_to_remove, $allowedShortcodes );
		}

		return $tags_to_remove;
	}

	/**
	 * Get default collate
	 *
	 * @param string $context
	 *
	 * @return string
	 */
	public static function getCollate( $context = '' ) {
		return Helpers::getCollate( $context );
	}

	/**
	 * Get WooCommerce queue object WC_Queue
	 *
	 * @return null|\WC_Queue_Interface
	 */
	public static function getQueue() {
		$queue = null;
		$wcObj = WC();
		if ( method_exists( $wcObj, 'queue' ) ) {
			$wcQueue = $wcObj->queue();
			if ( is_object( $wcQueue ) && method_exists( $wcQueue, 'schedule_recurring' ) ) {
				$queue = $wcQueue;
			}
		}

		return $queue;
	}


	/**
	 * Get all DB tables belong to the plugin
	 *
	 * @param bool $networkScope delete tables in whole network
	 *
	 * @return array
	 */
	public static function getAllPluginTables( $networkScope = false ) {
		global $wpdb;

		$pluginTables = array();

		$tables = $wpdb->get_results( "SHOW TABLES" );

		if ( ! empty( $tables ) && is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				if ( ! empty( $table ) && is_object( $table ) ) {
					foreach ( $table as $tableName ) {

						if ( ! empty( $tableName ) && is_string( $tableName ) && strpos( $tableName, 'dgwt_wcas_' ) !== false ) {
							$pluginTables[] = $tableName;
						}
					}
				}
			}
		}

		if ( ! ( is_multisite() && $networkScope ) ) {
			$blogScopeTables = array();
			$prefix          = $wpdb->get_blog_prefix();

			foreach ( $pluginTables as $name ) {
				if ( strpos( $name, $prefix . 'dgwt_wcas_' ) === 0 ) {
					$blogScopeTables[] = $name;
				}
			}

			$pluginTables = $blogScopeTables;
		}

		return $pluginTables;
	}

	/**
	 * Get table name
	 *
	 * @param string $type
	 * @param string $lang
	 * @param string $postType
	 *
	 * TODO Every reference to a table should go through this method to make the refactor simpler later.
	 *
	 * @return string
	 */
	public static function getTableName( $type, $lang = '', $postType = '' ) {

		global $wpdb;

		$name   = '';
		$suffix = self::getTableSuffix( $lang, $postType );

		switch ( $type ) {
			case 'searchable_wordlist':
				$name = $wpdb->dgwt_wcas_si_wordlist . $suffix;
				break;
			case 'searchable_doclist':
				$name = $wpdb->dgwt_wcas_si_doclist . $suffix;
				break;
			case 'searchable_cache':
				$name = $wpdb->dgwt_wcas_si_cache . $suffix;
				break;
			case 'vendors':
				$name = $wpdb->dgwt_wcas_ven_index;
				break;
			case 'variations':
				$name = $wpdb->dgwt_wcas_var_index;
				break;
			case 'taxonomy':
				$name = $wpdb->dgwt_wcas_tax_index;
				break;
			case 'readable':
				$name = $wpdb->dgwt_wcas_index;
				break;
		}

		return $name;
	}

	/**
	 * Get table suffix
	 *
	 * @param $lang
	 * @param $postType
	 *
	 * @return string
	 */
	public static function getTableSuffix( $lang, $postType ) {
		$suffix = '';

		if ( ! empty( $postType ) && $postType !== 'product' ) {
			$suffix .= '_' . $postType;
			//@TODO DB tables don't support "-" in post type name. Better change "-" to "_" than add single quote to all SQL queries
		}

		if ( ! empty( $lang ) ) {
			$suffix .= '_' . str_replace( '-', '_', $lang );
		}

		return $suffix;
	}

	/**
	 * Get all tables variations suffixes
	 *
	 * @param array $ignore | lang, post_type
	 *
	 * @return array
	 */
	public static function getTablesSuffixes( $ignore = array() ) {
		$suffixes        = array();
		$langs           = Multilingual::getLanguages();
		$noProductsTypes = Helpers::getAllowedPostTypes( 'no-products' );

		if ( Multilingual::isMultilingual() && ! in_array( 'lang', $ignore ) ) {

			foreach ( $langs as $lang ) {

				$lang = str_replace( '-', '_', $lang );

				$suffixes[] = $lang;

				// Non-products indices
				if ( ! empty( $noProductsTypes ) && ! in_array( 'post_type', $ignore ) ) {
					foreach ( $noProductsTypes as $noProductsType ) {

						$suffixes[] = $noProductsType . '_' . $lang;
					}
				}

			}

		} else {

			// Regular table - non suffix
			$suffixes[] = '';

			// Non-products indices
			if ( ! empty( $noProductsTypes ) && ! in_array( 'post_type', $ignore ) ) {
				foreach ( $noProductsTypes as $noProductsType ) {

					$suffixes[] = $noProductsType;
				}
			}

		}

		return $suffixes;
	}
}
