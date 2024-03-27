<?php

namespace DgoraWcas\Engines\TNTSearchMySQL;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	const READABLE_INDEX = 'dgwt_wcas_index';
	const READABLE_TAX_INDEX = 'dgwt_wcas_tax_index';
	const VARIATIONS_INDEX = 'dgwt_wcas_var_index';
	const VENDORS_INDEX = 'dgwt_wcas_ven_index';

	// Inverted index for searching purpose
	const SEARCHABLE_INDEX_WORDLIST = 'dgwt_wcas_invindex_wordlist';
	const SEARCHABLE_INDEX_DOCLIST = 'dgwt_wcas_invindex_doclist';
	const SEARCHABLE_INDEX_CACHE = 'dgwt_wcas_invindex_cache';

	private static $indexerMode = null;
	private static $indexRole = null;
	private static $indexRoleSuffix = null;

	/**
	 * Get indexer mode
	 *
	 * Modes: 'async', 'direct', 'sync'
	 *
	 * @return string
	 */
	public static function getIndexerMode() {
		$modes = array(
			'async', // default mode; indexes are built in parallel
			'direct', // indexes are built in one request with no background processes
			'sync', // indexes are built one by one
		);

		if ( in_array( self::$indexerMode, $modes ) ) {
			return self::$indexerMode;
		}

		self::$indexerMode = $modes[0];

		if ( defined( 'DGWT_WCAS_INDEXER_MODE' ) && in_array( DGWT_WCAS_INDEXER_MODE, $modes ) ) {
			self::$indexerMode = DGWT_WCAS_INDEXER_MODE;
		}

		$filteredMode = apply_filters( 'dgwt/wcas/tnt/indexer_mode', self::$indexerMode );
		if ( in_array( $filteredMode, $modes ) ) {
			self::$indexerMode = $filteredMode;
		}

		return self::$indexerMode;
	}

	/**
	 * Check if indexer is in specific mode
	 *
	 * Modes: 'async', 'direct', 'sync'
	 *
	 * @param string $mode
	 *
	 * @return bool
	 */
	public static function isIndexerMode( $mode ) {
		return self::getIndexerMode() === $mode;
	}

	public static function isParallelBuildingEnabled() {
		$parallel = true;

		if ( defined( 'DGWT_WCAS_PARALLEL_INDEX_BUILD' ) && ! DGWT_WCAS_PARALLEL_INDEX_BUILD ) {
			$parallel = false;
		}

		return $parallel;
	}

	public static function getIndexRole() {
		if ( in_array( self::$indexRole, array( 'tmp', 'main' ) ) ) {
			return self::$indexRole;
		}

		self::$indexRole = Config::isParallelBuildingEnabled() ? 'tmp' : 'main';

		return self::$indexRole;
	}

	public static function getIndexRoleSuffix() {
		if ( is_string( self::$indexRoleSuffix ) ) {
			return self::$indexRoleSuffix;
		}

		self::$indexRoleSuffix = Config::isParallelBuildingEnabled() ? '_tmp' : '';

		return self::$indexRoleSuffix;
	}

	/**
	 * Get path to the current theme
	 *
	 * @return string
	 */
	public static function getCurrentThemePath() {
		global $wpdb;

		$path = '';

		if ( ! function_exists( 'get_stylesheet_directory' ) ) {
			$stylesheet = $wpdb->get_var(
				"SELECT option_value
                   FROM $wpdb->options
                   WHERE option_name = 'stylesheet'
                   LIMIT 1"
			);

			$testPath = WP_CONTENT_DIR . '/themes/' . $stylesheet;

			if ( file_exists( $testPath ) ) {
				$path = $testPath . '/';
			}

		} else {
			$path = get_stylesheet_directory() . '/';
		}

		return $path;
	}

	/**
	 * Get all internal filter classes
	 *
	 * @return array
	 */
	public static function getInternalFilterClasses() {

		$classes          = array();
		$integrationsPath = dirname( dirname( dirname( __FILE__ ) ) ) . '/Integrations';

		$pluginsPath = $integrationsPath . '/Plugins/';

		if ( file_exists( $pluginsPath ) ) {
			$directories = glob( $pluginsPath . '*', GLOB_ONLYDIR );
			if ( ! empty( $directories ) ) {
				foreach ( $directories as $dir ) {

					$name     = str_replace( $pluginsPath, '', $dir );
					$filename = 'Filters.php';

					$file  = $dir . '/' . $filename;
					$class = '\\DgoraWcas\\Integrations\\Plugins\\' . $name . "\\Filters";

					if ( file_exists( $file ) && class_exists( $class ) ) {
						$classes[] = $class;
					}


				}
			}
		}

		return $classes;
	}

	/**
	 * Check if plugin is active
	 *
	 * @param string $pluginName
	 *
	 * @return bool
	 */
	public static function isPluginActive( $pluginName ) {
		$active = false;

		if ( is_multisite() ) {
			$plugins = get_site_option( 'active_sitewide_plugins' );
			$active  = isset( $plugins[ $pluginName ] );
		}

		if ( ! $active ) {
			$active = in_array( $pluginName, (array) get_option( 'active_plugins', array() ), true );
		}

		return $active;
	}
}
