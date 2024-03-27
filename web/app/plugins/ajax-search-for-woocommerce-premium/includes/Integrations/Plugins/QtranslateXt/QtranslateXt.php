<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\QtranslateXt;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\PostsSourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;
use DgoraWcas\Post;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with qTranslate-XT
 *
 * Plugin URL: https://github.com/qtranslate/qtranslate-xt/
 * Author: qTranslate Community
 *
 * Known issues:
 * - Untranslated attributes in variation title
 * - Untranslated attributes (qTranslate-XT doesn't support translations for attributes defined directly in product editor)
 */
class QtranslateXt {
	public function init() {
		if ( defined( 'DGWT_WCAS_DISABLE_MULTILINGUAL' ) && DGWT_WCAS_DISABLE_MULTILINGUAL ) {
			return;
		}
		if ( ! defined( 'QTX_VERSION' ) ) {
			return;
		}
		if ( version_compare( QTX_VERSION, '3.10' ) < 0 ) {
			return;
		}

		// Don't enable integration for only one language
		if ( count( qtranxf_getSortedLanguages() ) === 1 ) {
			add_action( 'dgwt/wcas/indexer/started', function () {
				Builder::log( 'Multilingual: No (because of just one language), Provider: qTranslate-XT' );
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

		add_filter( 'dgwt/wcas/readable_index/insert', array(
			$this,
			'clearReadableIndexData'
		), 10, 3 );

		add_filter( 'dgwt/wcas/indexer/readable_set_items_count', array( $this, 'readableSetItemsCount' ) );

		/*
		 * Variations index
		 */
		add_action( 'dgwt/wcas/variation_index/after_insert', array(
			$this,
			'insertTranslatedDataIntoVariationIndex'
		), 10, 6 );

		add_filter( 'dgwt/wcas/variation/insert', array( $this, 'variationInsertData' ), 10, 2 );
		add_filter( 'dgwt/wcas/indexer/variations_set_items_count', array( $this, 'variationsSetItemsCount' ) );

		/**
		 * Details Panel
		 */
		add_filter( 'dgwt/wcas/suggestion_details/post/vars', array( $this, 'detailsPanelVars' ), 10, 3 );
		add_filter( 'dgwt/wcas/suggestion_details/page/vars', array( $this, 'detailsPanelVars' ), 10, 3 );

		/*
		 * Other
		 */
		add_filter( 'dgwt/wcas/indexer/process_status/progress', array(
			$this,
			'correctProcessProgress'
		), 10, 5 );

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Set provider to qTranslate-XT
	 *
	 * @param string $provider
	 *
	 * @return string
	 */
	public function provider( $provider ) {
		$provider = 'qTranslate-XT';

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
		global $q_config;

		if ( ! empty( $q_config['default_language'] ) && Multilingual::isLangCode( $q_config['default_language'] ) ) {
			$defaultLang = $q_config['default_language'];
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
		$lang = qtranxf_getLanguage();
		if ( Multilingual::isLangCode( $lang ) ) {
			$currentLang = $lang;
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
		$codes = qtranxf_getSortedLanguages();
		if ( ! empty( $codes ) ) {
			$langs = array();
			foreach ( $codes as $code ) {
				if ( ! empty( $code ) && ( Multilingual::isLangCode( $code ) || $includeInvalid ) ) {
					$langs[] = $code;
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
				if ( isset( $term->i18n_config['name']['ts'][ $lang ] ) ) {
					$term->name = $term->i18n_config['name']['ts'][ $lang ];
				}
				if ( isset( $term->i18n_config['description']['ts'][ $lang ] ) ) {
					$term->description = $term->i18n_config['description']['ts'][ $lang ];
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

		if ( isset( $term->i18n_config['name']['ts'][ $lang ] ) ) {
			$term->name = $term->i18n_config['name']['ts'][ $lang ];
		}
		if ( isset( $term->i18n_config['description']['ts'][ $lang ] ) ) {
			$term->description = $term->i18n_config['description']['ts'][ $lang ];
		}

		return $term;
	}

	/**
	 * Prepare products data with translations for the indexer
	 *
	 * This filter:
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

		$langs          = Multilingual::getLanguages();
		$translatedData = array();

		foreach ( $langs as $lang ) {
			foreach ( $data as $row ) {
				$newRow               = $row;
				$newRow['name'] = $this->translate( $newRow['name'], $lang );
				if ( isset( $newRow['desc'] ) ) {
					$newRow['desc'] = $this->translate( $newRow['desc'], $lang );
				}
				if ( isset( $newRow['excerpt'] ) ) {
					$newRow['excerpt'] = $this->translate( $newRow['excerpt'], $lang );
				}
				if ( isset( $newRow['brand'] ) ) {
					$newRow['brand'] = $this->translateJoinded( $newRow['brand'], $lang, ' | ' );
				}
				if ( isset( $newRow['sku'] ) ) {
					$newRow['sku'] = $this->translate( $newRow['sku'], $lang );
				}
				if ( isset( $newRow['variations_skus'] ) ) {
					$newRow['variations_skus'] = $this->translateJoinded( $newRow['variations_skus'], $lang, ' | ' );
				}
				if ( isset( $newRow['variations_description'] ) ) {
					$newRow['variations_description'] = $this->translateJoinded( $newRow['variations_description'], $lang, ' | ' );
				}
				foreach ( $newRow as $key => $value ) {
					if ( strpos( $key, 'tax_pa_' ) === 0 ) {
						$newRow[ $key ] = $this->translateJoinded( $newRow[ $key ], $lang, ' | ' );
					}
				}
				foreach ( $newRow as $key => $value ) {
					if ( strpos( $key, 'cf_' ) === 0 ) {
						$newRow[ $key ] = $this->translate( $newRow[ $key ], $lang );
					}
				}
				if ( isset( $newRow['tax_product_tag'] ) ) {
					$newRow['tax_product_tag'] = $this->translateJoinded( $newRow['tax_product_tag'], $lang, ' | ' );
				}
				if ( isset( $newRow['tax_product_cat'] ) ) {
					$newRow['tax_product_cat'] = $this->translateJoinded( $newRow['tax_product_cat'], $lang, ' | ' );
				}
				$newRow['lang']   = $lang;
				$translatedData[] = $newRow;
			}
		}

		return $translatedData;
	}

	/**
	 * Prepare post/page data with translations for the indexer
	 *
	 * This filter:
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

		$langs          = Multilingual::getLanguages();
		$translatedData = array();

		foreach ( $langs as $lang ) {
			foreach ( $data as $row ) {
				$newRow               = $row;
				$newRow['name'] = $this->translate( $newRow['name'], $lang );
				$newRow['lang']       = $lang;
				$translatedData[]     = $newRow;
			}
		}

		return $translatedData;
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

		if ( Multilingual::getProvider() !== 'qTranslate-XT' ) {
			return;
		}

		$langs = Multilingual::getLanguages();

		foreach ( $langs as $lang ) {
			$translatedData                = $dataUnfiltered;
			$translatedData['description'] = $this->translate( $translatedData['description'], $lang );
			$translatedData['name']        = $this->translate( $translatedData['name'], $lang );
			$translatedData['lang']        = $lang;
			$translatedData['url']         = $this->convertUrl( $translatedData['url'], $lang );

			if ( isset( $translatedData['meta'] ) ) {
				$translatedData['meta'] = maybe_serialize( $translatedData['meta'] );
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
	 * Return empty data to skip add data to readable index
	 *
	 * We will add data from all languages in self::insertTranslatedDataIntoTaxonomyIndex()
	 *
	 * @param $data
	 * @param $postID
	 * @param $postType
	 *
	 * @return bool
	 */
	public function clearReadableIndexData( $data, $postID, $postType ) {
		return false;
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
		if ( Multilingual::getProvider() !== 'qTranslate-XT' ) {
			return;
		}

		$defaultLang     = Multilingual::getDefaultLanguage();
		$langs           = Multilingual::getLanguages();
		$additionalLangs = array_diff( $langs, array( $defaultLang ) );

		foreach ( $additionalLangs as $lang ) {
			$translatedData = $data;
			$term           = Multilingual::getTerm( $termID, $taxonomy, $lang );
			if ( isset( $term->i18n_config['name']['ts'][ $lang ] ) ) {
				$translatedData['term_name'] = $term->i18n_config['name']['ts'][ $lang ];
			}
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
	 * Return empty data to skip add data to variation index
	 *
	 * We will add data from all languages in self::insertTranslatedDataIntoVariationIndex()
	 *
	 * @param array $data
	 * @param \WC_Product_Variation $product
	 *
	 * @return bool
	 */
	public function variationInsertData( $data, $product ) {
		return false;
	}

	/**
	 * Insert translated taxonomy data into readable index
	 *
	 * @param $dataFiltered
	 * @param $parentProductID
	 * @param $parentProductSKU
	 * @param $lang
	 * @param $data
	 *
	 * @throws WPDBException
	 */
	public function insertTranslatedDataIntoVariationIndex( $dataFiltered, $parentProductID, $parentProductSKU, $lang, $data, $indexRole ) {
		global $wpdb;

		if ( Multilingual::getProvider() !== 'qTranslate-XT' ) {
			return;
		}

		$langs = Multilingual::getLanguages();

		foreach ( $langs as $lang ) {
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
	 * Translate title in Details Panel
	 *
	 * @param array $vars
	 * @param int $postID
	 * @param Post $post
	 *
	 * @return mixed
	 */
	public function detailsPanelVars( $vars, $postID, $post ) {

		$lang = Multilingual::getCurrentLanguage();

		$vars['title'] = $this->translate( $vars['title'], $lang );

		return $vars;
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
		return qtranxf_convertURL( $url, $lang, true );
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
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		return qtranxf_use_language( $lang, $content );
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
	 * Get info about renamed plugin folder
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function getFolderRenameInfo( $plugins ) {
		$result = Helpers::getFolderRenameInfo__premium_only( 'qTranslate-XT', [ Filters::PLUGIN_NAME ] );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
