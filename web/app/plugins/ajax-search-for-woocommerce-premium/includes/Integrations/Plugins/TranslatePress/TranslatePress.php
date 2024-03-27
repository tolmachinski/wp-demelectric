<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\TranslatePress;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\PostsSourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with TranslatePress - Multilingual
 *
 * Plugin URL: https://translatepress.com/
 * Author: Cozmoslabs, Razvan Mocanu, Madalin Ungureanu, Cristophor Hurduban
 */
class TranslatePress {
	private $untranslatableFields = array();

	public function init() {
		if ( defined( 'DGWT_WCAS_DISABLE_MULTILINGUAL' ) && DGWT_WCAS_DISABLE_MULTILINGUAL ) {
			return;
		}
		if ( ! defined( 'TRP_PLUGIN_VERSION' ) && ! class_exists( 'TRP_Translate_Press' ) ) {
			return;
		}
		if ( version_compare( TRP_PLUGIN_VERSION, '1.9.4' ) < 0 ) {
			return;
		}
		// Don't enable integration for only one language
		if ( count( trp_get_languages() ) === 1 ) {
			add_action( 'dgwt/wcas/indexer/started', function () {
				Builder::log( 'Multilingual: No (because of just one language), Provider: TranslatePress' );
			} );

			return;
		}

		/*
		 * Multilingual
		 */
		add_filter( 'dgwt/wcas/multilingual/provider', array( $this, 'provider' ) );
		add_filter( 'dgwt/wcas/multilingual/default-language', array( $this, 'defaultLanguage' ) );
		add_filter( 'dgwt/wcas/multilingual/current-language', array( $this, 'currentLanguage' ) );
		add_filter( 'dgwt/wcas/multilingual/languages', array( $this, 'languages' ), 10, 2 );
		add_filter( 'dgwt/wcas/multilingual/terms-in-all-languages', array( $this, 'termsInAllLanguages' ), 10, 2 );
		add_filter( 'dgwt/wcas/multilingual/terms-in-language', array( $this, 'termsInLanguage' ), 10, 3 );
		add_filter( 'dgwt/wcas/multilingual/term', array( $this, 'term' ), 10, 4 );

		/*
		 * Searchable index
		 */
		add_filter( 'dgwt/wcas/tnt/source_query/data', array( $this, 'addLanguageData' ), 10, 3 );
		add_filter( 'dgwt/wcas/tnt/post_source_query/data', array( $this, 'addPostLanguageData' ), 10, 3 );
		add_filter( 'dgwt/wcas/indexer/searchable_set_items_count', array( $this, 'searchableSetItemsCount' ) );
		add_action( 'dgwt/wcas/searchable_index/bg_processing/before_task', array(
			$this,
			'prepareSearchableBgProcessingHooks'
		) );
		add_filter( 'dgwt/wcas/integrations/translatepress/untranslatable_fields/searchable', array(
			$this,
			'setUntranslatableFieldsSearchable'
		), 5 );

		/*
		 * Readable index
		 */
		add_action( 'dgwt/wcas/readable_index/after_insert', array(
			$this,
			'insertTranslatedDataIntoReadableIndex'
		), 10, 6 );
		add_action( 'dgwt/wcas/taxonomy_index/after_insert', array(
			$this,
			'insertTranslatedDataIntoTaxonomyIndex'
		), 10, 5 );
		add_action( 'dgwt/wcas/readable_index/bg_processing/before_task', array(
			$this,
			'prepareBgProcessingHooks'
		) );
		add_action( 'dgwt/wcas/tnt/background_product_update', array(
			$this,
			'prepareBgProcessingHooks'
		), 5 );
		add_action( 'dgwt/wcas/taxonomy_index/bg_processing/before_task', array(
			$this,
			'prepareBgProcessingHooks'
		) );

		add_filter( 'dgwt/wcas/indexer/readable_set_items_count', array( $this, 'readableSetItemsCount' ) );

		/*
		 * Variations index
		 */
		add_action( 'dgwt/wcas/variation_index/after_insert', array(
			$this,
			'insertTranslatedDataIntoVariationIndex'
		), 10, 6 );
		add_action( 'dgwt/wcas/variation_index/bg_processing/before_task', array(
			$this,
			'prepareBgProcessingHooks'
		) );

		add_filter( 'dgwt/wcas/indexer/variations_set_items_count', array( $this, 'variationsSetItemsCount' ) );

		/*
		 * Search page
		 */
		add_action( 'plugins_loaded', array( $this, 'removeHooksOnSearchPage' ), 20 );

		/*
		 * Other
		 */
		add_filter( 'trp_skip_selectors_from_dynamic_translation', array(
			$this,
			'skipSelectorsFromDynamicTranslation'
		) );
		add_filter( 'dgwt/wcas/indexer/process_status/progress', array(
			$this,
			'correctProcessProgress'
		), 10, 5 );

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Set provider to TranslatePress
	 *
	 * @param string $provider
	 *
	 * @return string
	 */
	public function provider( $provider ) {
		$provider = 'TranslatePress';

		return $provider;
	}

	/**
	 * Get default language
	 *
	 * @param string $defaultLang
	 *
	 * @return string
	 */
	public function defaultLanguage( $defaultLang ) {
		$trp          = \TRP_Translate_Press::get_trp_instance();
		$trp_settings = $trp->get_component( 'settings' );
		$settings     = $trp_settings->get_settings();

		$slug = self::getLanguageSlug( $settings['default-language'] );
		if ( ! empty( $slug ) ) {
			$defaultLang = $slug;
		}

		return $defaultLang;
	}

	/**
	 * Get current language
	 *
	 * @param string $currentLang
	 *
	 * @return string
	 */
	public function currentLanguage( $currentLang ) {
		global $TRP_LANGUAGE;

		$slug = self::getLanguageSlug( $TRP_LANGUAGE );
		if ( ! empty( $slug ) ) {
			$currentLang = $slug;
		}

		return $currentLang;
	}

	/**
	 * Get defined languages
	 *
	 * @param array $langs
	 *
	 * @return array
	 */
	public function languages( $langs, $includeInvalid ) {
		$codes = array_keys( trp_get_languages() );
		if ( ! empty( $codes ) ) {
			$langs = array();
			foreach ( $codes as $code ) {
				$slug = self::getLanguageSlug( $code );
				$slug = strtolower( $slug );
				if ( ! empty( $slug ) && ( Multilingual::isLangCode( $slug ) || $includeInvalid ) ) {
					$langs[] = $slug;
				}
			}
		}

		return $langs;
	}

	/**
	 * Get terms in all languages
	 *
	 * @param $terms
	 * @param $taxonomy
	 *
	 * @return int|\WP_Error|\WP_Term[]
	 */
	public function termsInAllLanguages( $terms, $taxonomy ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		);

		$terms = get_terms( apply_filters( 'dgwt/wcas/search/' . $taxonomy . '/args', $args ) );

		return $terms;
	}

	/**
	 * Get term in specific language
	 *
	 * @param $terms
	 * @param $args
	 * @param $lang
	 *
	 * @return int|\WP_Error|\WP_Term[]
	 */
	public function termsInLanguage( $terms, $args, $lang ) {
		$terms = get_terms( array(
			'taxonomy'   => $args['taxonomy'],
			'hide_empty' => true,
		) );

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term->name ) ) {
					$term->name = $this->translate( $term->name, $lang );
				}
			}
		}

		return $terms;
	}

	/**
	 * Get translated term
	 *
	 * @param $term
	 * @param $termID
	 * @param $taxonomy
	 * @param $lang
	 *
	 * @return array|int|object|\WP_Error|\WP_Term|null
	 */
	public function term( $term, $termID, $taxonomy, $lang ) {
		$term = get_term( $termID, $taxonomy );
		if ( isset( $term->name ) ) {
			$term->name = $this->translate( $term->name, $lang );
		}

		return $term;
	}

	/**
	 * Prepare products data with translations for the indexer
	 *
	 * This filter:
	 * - adds language to received objects
	 * - for each language, it makes a copy of the object and translates all its attributes
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

		$defaultLang = Multilingual::getDefaultLanguage();

		// Set default language to products
		foreach ( $data as $index => $row ) {
			$data[ $index ]['lang'] = $defaultLang;
		}

		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );
		$additionalData  = array();

		foreach ( $additionalLangs as $lang ) {
			foreach ( $data as $row ) {
				$newRow = $row;
				if ( $this->isFieldTranslatable( 'name', 'searchable' ) ) {
					$newRow['name'] = $this->translate( $newRow['name'], $lang );
				}
				if ( isset( $newRow['desc'] ) && $this->isFieldTranslatable( 'desc', 'searchable' ) ) {
					$newRow['desc'] = $this->translate( $newRow['desc'], $lang );
				}
				if ( isset( $newRow['excerpt'] ) && $this->isFieldTranslatable( 'excerpt', 'searchable' ) ) {
					$newRow['excerpt'] = $this->translate( $newRow['excerpt'], $lang );
				}
				if ( isset( $newRow['brand'] ) && $this->isFieldTranslatable( 'brand', 'searchable' ) ) {
					$newRow['brand'] = $this->translateJoinded( $newRow['brand'], $lang, ' | ' );
				}
				if ( isset( $newRow['sku'] ) && $this->isFieldTranslatable( 'sku', 'searchable' ) ) {
					$newRow['sku'] = $this->translate( $newRow['sku'], $lang );
				}
				if ( isset( $newRow['variations_skus'] ) && $this->isFieldTranslatable( 'variations_skus', 'searchable' ) ) {
					$newRow['variations_skus'] = $this->translateJoinded( $newRow['variations_skus'], $lang, ' | ' );
				}
				if ( isset( $newRow['variations_description'] ) && $this->isFieldTranslatable( 'variations_description', 'searchable' ) ) {
					$newRow['variations_description'] = $this->translateJoinded( $newRow['variations_description'], $lang, ' | ' );
				}
				foreach ( $newRow as $key => $value ) {
					if ( strpos( $key, 'tax_pa_' ) === 0 && $this->isFieldTranslatable( $key, 'searchable' ) ) {
						$newRow[ $key ] = $this->translateJoinded( $newRow[ $key ], $lang, ' | ' );
					}
				}
				foreach ( $newRow as $key => $value ) {
					if ( strpos( $key, 'cf_' ) === 0 && $this->isFieldTranslatable( $key, 'searchable' ) ) {
						$newRow[ $key ] = $this->translate( $newRow[ $key ], $lang );
					}
				}
				if ( isset( $newRow['tax_product_tag'] ) && $this->isFieldTranslatable( 'tax_product_tag', 'searchable' ) ) {
					$newRow['tax_product_tag'] = $this->translateJoinded( $newRow['tax_product_tag'], $lang, ' | ' );
				}
				if ( isset( $newRow['tax_product_cat'] ) && $this->isFieldTranslatable( 'tax_product_cat', 'searchable' ) ) {
					$newRow['tax_product_cat'] = $this->translateJoinded( $newRow['tax_product_cat'], $lang, ' | ' );
				}
				$newRow['lang']   = $lang;
				$additionalData[] = $newRow;
			}
		}

		return array_merge( $data, $additionalData );
	}

	/**
	 * Prepare post/page data with translations for the indexer
	 *
	 * This filter:
	 * - adds language to received objects
	 * - for each language, it makes a copy of the object and translates all its attributes
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

		$defaultLang = Multilingual::getDefaultLanguage();

		// Set default language to products
		foreach ( $data as $index => $row ) {
			$data[ $index ]['lang'] = $defaultLang;
		}

		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );
		$additionalData  = array();

		foreach ( $additionalLangs as $lang ) {
			foreach ( $data as $row ) {
				$newRow               = $row;
				$newRow['name'] = $this->translate( $newRow['name'], $lang );
				$newRow['lang']       = $lang;
				$additionalData[]     = $newRow;
			}
		}

		$data = array_merge( $data, $additionalData );

		return $data;
	}

	/**
	 * Prepare to run the searchable indexing process
	 */
	public function prepareSearchableBgProcessingHooks() {
		// Loading a TranslatePress component that normally does not load during AJAX queries
		$trp = \TRP_Translate_Press::get_trp_instance();
		$trp->init_machine_translation();
	}

	/**
	 * Set default untranslatable fields for "searchable" context
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function setUntranslatableFieldsSearchable( $fields ) {
		$fields[] = 'variations_skus';

		return $fields;
	}

	/**
	 * Adjusting the size of the queue to the number of languages
	 *
	 * @param $count
	 *
	 * @return int
	 */
	public function searchableSetItemsCount( $count ) {
		$langs = Multilingual::getLanguages();
		if ( count( $langs ) > 1 ) {
			$count = (int) floor( Builder::SEARCHABLE_SET_ITEMS_COUNT / count( $langs ) );
		}

		return $count;
	}


	/**
	 * Insert translated data into readable index (for products, posts, pages)
	 *
	 * @param $data
	 * @param $postID
	 * @param $postType
	 * @param $success
	 *
	 * @throws WPDBException
	 */
	public function insertTranslatedDataIntoReadableIndex( $data, $postID, $postType, $success, $dataUnfiltered, $indexRole ) {
		global $wpdb;

		if ( ! $success ) {
			return;
		}
		if ( Multilingual::getProvider() !== 'TranslatePress' ) {
			return;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );

		foreach ( $additionalLangs as $lang ) {
			$translatedData = $data;
			if ( $this->isFieldTranslatable( 'description', 'readable' ) ) {
				$translatedData['description'] = $this->translate( $translatedData['description'], $lang );
			}
			if ( $this->isFieldTranslatable( 'name', 'readable' ) ) {
				$translatedData['name'] = $this->translate( $translatedData['name'], $lang );
			}
			$translatedData['lang'] = $lang;
			if ( $this->isFieldTranslatable( 'url', 'readable' ) ) {
				$translatedData['url'] = $this->convertUrl( $translatedData['url'], $lang );
			}

			$rows = WPDB::get_instance()->insert(
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

	/**
	 * Insert translated taxonomy data into readable index
	 *
	 * @param $data
	 * @param $termID
	 * @param $taxonomy
	 * @param $success
	 *
	 * @throws WPDBException
	 */
	public function insertTranslatedDataIntoTaxonomyIndex( $data, $termID, $taxonomy, $success, $indexRole ) {
		global $wpdb;

		if ( ! $success ) {
			return;
		}
		if ( Multilingual::getProvider() !== 'TranslatePress' ) {
			return;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );

		foreach ( $additionalLangs as $lang ) {
			$translatedData              = $data;
			$translatedData['term_name'] = $this->translate( $translatedData['term_name'], $lang );
			$translatedData['term_link'] = $this->convertUrl( $translatedData['term_link'], $lang );
			$translatedData['lang']      = $lang;

			$rows = WPDB::get_instance()->insert(
				$wpdb->dgwt_wcas_tax_index . ( $indexRole === 'main' ? '' : '_tmp' ),
				$translatedData,
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Insert translated taxonomy data into readable index
	 *
	 * @param $data
	 * @param $parentProductID
	 * @param $parentProductSKU
	 * @param $lang
	 *
	 * @throws WPDBException
	 */
	public function insertTranslatedDataIntoVariationIndex( $data, $parentProductID, $parentProductSKU, $lang, $dataUnfiltered, $indexRole ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return;
		}
		if ( Multilingual::getProvider() !== 'TranslatePress' ) {
			return;
		}

		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $lang ) );

		foreach ( $additionalLangs as $lang ) {
			$translatedData = $data;
			// Warning: title may has different delimiter
			$translatedData['title']       = $this->translateJoinded( $translatedData['title'], $lang, ', ' );
			$translatedData['description'] = $this->translate( $translatedData['description'], $lang );
			$translatedData['url']         = $this->convertUrl( $translatedData['url'], $lang );
			$translatedData['sku']         = $this->translate( $translatedData['sku'], $lang );
			$translatedData['lang']        = $lang;

			$rows = WPDB::get_instance()->insert(
				$wpdb->dgwt_wcas_var_index . ( $indexRole === 'main' ? '' : '_tmp' ),
				$translatedData,
				array(
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Adjusting the size of the queue to the number of languages
	 *
	 * @param $count
	 *
	 * @return int
	 */
	public function variationsSetItemsCount( $count ) {
		$langs = Multilingual::getLanguages();
		if ( count( $langs ) > 1 ) {
			$count = (int) floor( Builder::VARIATIONS_SET_ITEMS_COUNT / count( $langs ) );
		}

		return $count;
	}

	/**
	 * Get lang slug from code
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function getLanguageSlug( $code ) {
		if ( Multilingual::getProvider() !== 'TranslatePress' ) {
			return '';
		}

		$trp          = \TRP_Translate_Press::get_trp_instance();
		$trp_settings = $trp->get_component( 'settings' );
		$settings     = $trp_settings->get_settings();

		if ( isset( $settings['url-slugs'][ $code ] ) ) {
			return $settings['url-slugs'][ $code ];
		}

		return '';
	}

	/**
	 * Disable TranslatePress hooks on serach page
	 */
	public function removeHooksOnSearchPage() {
		$trp    = \TRP_Translate_Press::get_trp_instance();
		$search = $trp->get_component( 'search' );
		if ( version_compare( TRP_PLUGIN_VERSION, '1.9.8' ) < 0 ) {
			remove_filter( 'pre_get_posts', array( $search, 'trp_search_filter' ), 10 );
		} else {
			remove_filter( 'pre_get_posts', array( $search, 'trp_search_filter' ), 99999999 );
		}
		remove_filter( 'get_search_query', array( $search, 'trp_search_query' ), 10 );
	}

	/**
	 * Adjusting the size of the queue to the number of languages
	 *
	 * @param $count
	 *
	 * @return int
	 */
	public function readableSetItemsCount( $count ) {
		$langs = Multilingual::getLanguages();
		if ( count( $langs ) > 1 ) {
			$count = (int) floor( Builder::READABLE_SET_ITEMS_COUNT / count( $langs ) );
		}

		return $count;
	}

	/**
	 * Prepare to run the background indexing process
	 */
	public function prepareBgProcessingHooks() {
		/**
		 * When we get product, post and page permalinks, the TRP_Url_Converter::add_language_to_home_url()
		 * method is used, but in its inside the is_admin_request() method is called,
		 * which when it returns "true", causes that we do not get the correct permalinks
		 * in other languages. These permalinks are needed to build the readable index.
		 * This filter is the only method to prevent this problem.
		 */
		add_filter( 'admin_url', function ( $url, $path, $blog_id ) {
			if ( Helpers::is_running_inside_class( 'TRP_Url_Converter' ) ) {
				$url = '-';
			}

			return $url;
		}, 10, 3 );

		// Newer version of TranslatePress has below hook, but we stay above code for backward compatibility
		add_filter( 'trp_add_language_to_home_url_check_for_admin', function ( $result, $url, $path ) {
			return false;
		}, 10, 3 );

		/**
		 * Init machine translation component
		 */
		$trp = \TRP_Translate_Press::get_trp_instance();
		$trp->init_machine_translation();

		/**
		 * During cron request, TranslatePress doesn't set hooks for frontend,
		 * but we need a filter for 'home_url', so we need set it manually.
		 */
		if ( isset( $_REQUEST['doing_wp_cron'] ) ) {
			add_filter( 'home_url', array( $trp->get_component( 'url_converter' ), 'add_language_to_home_url' ), 1, 4 );
		}
	}

	/**
	 * Prevent to dynamic translate search results
	 *
	 * @param array $selectors
	 *
	 * @return array
	 */
	public function skipSelectorsFromDynamicTranslation( $selectors ) {
		$selectors[] = '.dgwt-wcas-suggestions-wrapp';
		$selectors[] = '.dgwt-wcas-details-wrapp';

		return $selectors;
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
	 * Convert URL to given language
	 *
	 * @param $url
	 * @param $lang
	 *
	 * @return string
	 */
	private function convertUrl( $url, $lang ) {
		$output = '';
		$trp    = \TRP_Translate_Press::get_trp_instance();
		/**
		 * @var $urlConverter \TRP_Url_Converter
		 */
		$urlConverter = $trp->get_component( 'url_converter' );

		$code = $this->getLanguageCode( $lang );
		if ( $code ) {
			$output = $urlConverter->get_url_for_language( $code, $url, '' );
		}

		return $output;
	}

	/**
	 * Get language code from slug (en >> en_US)
	 *
	 * @param $slug
	 *
	 * @return false|int|string
	 */
	private function getLanguageCode( $slug ) {
		$trp = \TRP_Translate_Press::get_trp_instance();
		/**
		 * @var \TRP_Settings $trpSettings
		 */
		$trpSettings = $trp->get_component( 'settings' );
		$settings    = $trpSettings->get_settings();

		return array_search( $slug, $settings['url-slugs'] );
	}

	/**
	 * Translate content to given language
	 *
	 * @param $content
	 * @param $lang
	 *
	 * @return string
	 */
	private function translate( $content, $lang ) {
		global $TRP_LANGUAGE;

		$output = '';
		$trp    = \TRP_Translate_Press::get_trp_instance();
		/**
		 * @var \TRP_Translation_Render $translationRender
		 */
		$translationRender = $trp->get_component( 'translation_render' );

		$trpLanguageBackup = $TRP_LANGUAGE;
		$code              = $this->getLanguageCode( $lang );
		if ( $code ) {
			$TRP_LANGUAGE = $code;
			$output       = $translationRender->translate_page( $content );
			$TRP_LANGUAGE = $trpLanguageBackup;
		}

		return $output;
	}

	/**
	 * Translate content divided by delimiter
	 *
	 * Eg. "Black | Blue | Green" - we have 3 strings to translate separated by " | " delimiter
	 *
	 * @param $content
	 * @param $lang
	 * @param $delimiter
	 *
	 * @return string
	 */
	private function translateJoinded( $content, $lang, $delimiter ) {
		$arr           = explode( $delimiter, $content );
		$arrTranslated = array();
		if ( ! empty( $arr ) ) {
			foreach ( $arr as $item ) {
				$arrTranslated[] = $this->translate( $item, $lang );
			}
		}

		return join( $delimiter, $arrTranslated );
	}

	/**
	 * Check if field is translatable
	 *
	 * @param $field
	 * @param $context
	 *
	 * @return bool
	 */
	private function isFieldTranslatable( $field, $context ) {
		$excluded = $this->getUntranslatableFields( $context );

		return ! in_array( $field, $excluded );
	}

	/**
	 * @param string $context Context: 'searchable', 'readable'.
	 *
	 * @return array
	 */
	private function getUntranslatableFields( $context = '' ) {
		if ( empty( $context ) ) {
			return array();
		}

		if ( isset( $this->untranslatableFields[ $context ] ) && is_array( $this->untranslatableFields[ $context ] ) ) {
			return $this->untranslatableFields[ $context ];
		}

		$this->untranslatableFields[ $context ] = apply_filters( 'dgwt/wcas/integrations/translatepress/untranslatable_fields/' . $context, array() );

		if ( ! is_array( $this->untranslatableFields[ $context ] ) ) {
			$this->untranslatableFields[ $context ] = array();
		}

		return $this->untranslatableFields[ $context ];
	}

	/**
	 * Get info about renamed plugin folder
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function getFolderRenameInfo( $plugins ) {
		$result = Helpers::getFolderRenameInfo__premium_only( 'TranslatePress - Multilingual', [ Filters::PLUGIN_NAME ] );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
