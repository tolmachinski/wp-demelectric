<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\WPML;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\PostsSourceQuery;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with WPML Multilingual CMS
 *
 * Plugin URL: https://wpml.org/
 * Author: OnTheGoSystems
 */
class WPML {

	private static $showDefaultIfNoTranslationData = array();

	public function init() {
		if ( ! Multilingual::isWPML() ) {
			return;
		}

		/**
		 * Products
		 */
		if ( self::showDefaultIfNoTranslation( 'product' ) ) {
			// Searchable index
			add_filter( 'dgwt/wcas/tnt/source_query/data', array( $this, 'addLanguageData' ), 10, 3 );
		}

		/**
		 * Posts, pages
		 */
		if ( self::showDefaultIfNoTranslation( 'post' ) || self::showDefaultIfNoTranslation( 'page' ) ) {
			// Searchable index
			add_filter( 'dgwt/wcas/tnt/post_source_query/data', array( $this, 'addPostLanguageData' ), 10, 3 );
		}

		if (
			self::showDefaultIfNoTranslation( 'product' )
			|| self::showDefaultIfNoTranslation( 'post' )
			|| self::showDefaultIfNoTranslation( 'page' )
		) {
			// Readable index
			add_action( 'dgwt/wcas/readable_index/after_insert', array(
				$this,
				'insertTranslatedDataIntoReadableIndex'
			), 10, 6 );

			// Progress
			add_filter( 'dgwt/wcas/indexer/process_status/progress', array(
				$this,
				'correctProcessProgress'
			), 10, 5 );
		}


		add_filter( 'wcml_multi_currency_ajax_actions', array( $this, 'multiCurrencyAjaxActions' ), 10, 1 );

		add_filter( 'wcml_client_currency', array( $this, 'clientCurrency' ), 50, 1 );

		/**
		 * Other
		 */

		// Rebuild index after change active languages
		add_action( 'wpml_update_active_languages', array(
			'DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder',
			'buildIndex'
		) );

		// TODO Index taxonomies with fallback if WPML will support them (now it return 404 for term in another languages)

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Prepare a copy of the products from the default language when there are no translations
	 *
	 * @param array $data
	 * @param SourceQuery $sourceQuery
	 * @param boolean $onlyIDs
	 *
	 * @return array
	 */
	public function addLanguageData( $data, $sourceQuery, $onlyIDs ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $data;
		}

		if ( $onlyIDs ) {
			return $data;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );
		$additionalData  = array();

		foreach ( $additionalLangs as $lang ) {
			foreach ( $data as $row ) {
				// Skip translated products
				if ( $row['lang'] !== $defaultLang ) {
					continue;
				}

				$translatedObjectID = apply_filters( 'wpml_object_id', $row['ID'], 'product', false, $lang );
				// Add the translation as a copy of the original
				if ( is_null( $translatedObjectID ) ) {
					$newRow         = $row;
					$newRow['lang'] = $lang;
					// TODO Look for translations of product attributes, e.g. categories, tags, etc.
					$additionalData[] = $newRow;
				}
			}
		}

		$data = array_merge( $data, $additionalData );

		return $data;
	}

	/**
	 * Prepare a copy of the post/page from the default language when there are no translations
	 *
	 * @param array $data
	 * @param PostsSourceQuery $postsSourceQuery
	 * @param boolean $onlyIDs
	 *
	 * @return array
	 */
	public function addPostLanguageData( $data, $postsSourceQuery, $onlyIDs ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $data;
		}

		if ( $onlyIDs ) {
			return $data;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );
		$additionalData  = array();

		foreach ( $additionalLangs as $lang ) {
			foreach ( $data as $row ) {
				// Skip translated products
				if ( $row['lang'] !== $defaultLang ) {
					continue;
				}
				// Skip post types without option to use default if no translation
				if ( ! self::showDefaultIfNoTranslation( $row['post_type'] ) ) {
					continue;
				}

				$translatedObjectID = apply_filters( 'wpml_object_id', $row['ID'], $row['post_type'], false, $lang );
				// Add the translation as a copy of the original
				if ( is_null( $translatedObjectID ) ) {
					$newRow           = $row;
					$newRow['lang']   = $lang;
					$additionalData[] = $newRow;
				}
			}
		}

		$data = array_merge( $data, $additionalData );

		return $data;
	}

	/**
	 * Insert translated data into readable index (for products, posts, pages)
	 *
	 * @param $data
	 * @param $postID
	 * @param $postType
	 * @param $success
	 */
	public function insertTranslatedDataIntoReadableIndex( $data, $postID, $postType, $success, $dataUnfiltered, $indexRole ) {
		global $wpdb, $sitepress;

		if ( ! $success ) {
			return;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );

		// Skip translated products
		if ( $data['lang'] !== $defaultLang ) {
			return;
		}
		// Skip post types without option to use default if no translation
		if ( ! self::showDefaultIfNoTranslation( $postType ) ) {
			return;
		}

		foreach ( $additionalLangs as $lang ) {
			$translatedObjectID = apply_filters( 'wpml_object_id', $data['post_id'], 'product', false, $lang );
			// Add the translation as a copy of the original
			if ( is_null( $translatedObjectID ) ) {
				$translatedData         = $data;
				$translatedData['lang'] = $lang;

				// Multilingual::getPermalink() doesn't work for products that are not a translation
				// but a copy of the original, so we use the direct method to translate URL
				if ( is_callable( array( $sitepress, 'convert_url' ) ) ) {
					$translatedData['url'] = $sitepress->convert_url( $data['url'], $lang );
				}

				$rows = $wpdb->insert(
					$wpdb->dgwt_wcas_index . ( $indexRole === 'main' ? '' : '_tmp' ),
					$translatedData,
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%f',
						'%f',
						'%d',
						'%d',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Checks if can show post type in original language if there is no translation
	 *
	 * @return boolean
	 */
	public static function showDefaultIfNoTranslation( $postType ) {
		if ( isset( self::$showDefaultIfNoTranslationData[ $postType ] ) ) {
			return self::$showDefaultIfNoTranslationData[ $postType ];
		}

		$showDefault = false;

		if ( ! in_array( $postType, Helpers::getAllowedPostTypes() ) ) {
			return false;
		}

		if ( Multilingual::isWPML() && apply_filters( 'wpml_sub_setting', false, 'custom_posts_sync_option', $postType ) == 2 ) {
			$showDefault = true;
		}

		self::$showDefaultIfNoTranslationData[ $postType ] = $showDefault;

		return $showDefault;
	}

	/**
	 * Indexing progress correction
	 *
	 * Due to the addition of indexed data on the fly, the progress is poorly calculated and needs to be adjusted.
	 *
	 * @param $progress
	 * @param $percentR
	 * @param $percentS
	 * @param $percentV
	 * @param $percentT
	 *
	 * @return float|int
	 */
	public function correctProcessProgress( $progress, $percentR, $percentS, $percentV, $percentT ) {
		$count = count( Multilingual::getLanguages() );
		if ( $count > 1 ) {
			if ( Builder::canBuildVariationsIndex() && Builder::canBuildTaxonomyIndex() ) {
				$progress = $percentR * 0.4 + ( $percentS / $count ) * 0.4 + $percentV * 0.1 + $percentT * 0.1;
			} else if ( Builder::canBuildVariationsIndex() || Builder::canBuildTaxonomyIndex() ) {
				$progress = $percentR * 0.4 + ( $percentS / $count ) * 0.4 + $percentV * 0.2 + $percentT * 0.2;
			} else {
				$progress = $percentR * 0.5 + ( $percentS / $count ) * 0.5;
			}
		}

		return $progress;
	}

	/**
	 * Add currency ajax actions to WooCommerce Multilingual
	 *
	 * @param $ajaxActions
	 *
	 * @return mixed
	 */
	public function multiCurrencyAjaxActions( $ajaxActions ) {
		$ajaxActions[] = 'wcas_build_readable_index';
		$ajaxActions[] = 'dgwt_wcas_result_details';
		$ajaxActions[] = 'wcas_async_product_update';

		return $ajaxActions;
	}

	/**
	 * Currency
	 *
	 * @param $currency
	 *
	 * @return string
	 */
	public function clientCurrency( $currency ) {
		if (
			(
				defined( 'DGWT_WCAS_READABLE_INDEX_TASK' )
				|| defined( 'DGWT_WCAS_VARIATIONS_INDEX_TASK' )
				|| defined( 'DGWT_WCAS_UPDATER_TASK' )
			)
			&& ! empty( Multilingual::getCurrentCurrency() )
		) {
			$currency = Multilingual::getCurrentCurrency();
		}

		return $currency;
	}

	/**
	 * Get info about renamed plugin folder
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function getFolderRenameInfo( $plugins ) {
		$result = Helpers::getFolderRenameInfo__premium_only( 'WPML Multilingual CMS', [ Filters::PLUGIN_NAME ] );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
