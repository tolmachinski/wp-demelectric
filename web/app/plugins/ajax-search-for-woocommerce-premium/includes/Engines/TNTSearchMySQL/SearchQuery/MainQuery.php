<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

use DgoraWcas\Analytics\Recorder;
use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Debug\Debugger;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Cache;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\Tokenizer;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\TokenizerInterface;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Stemmer\NoStemmer;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Stemmer\StemmerInterface;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Collection;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Expression;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class MainQuery {
	/** @var Cache */
	private $cache;
	/** @var array */
	private $config = array();
	/** @var string */
	protected $postType = '';
	/** @var string */
	private $s = '';
	/** @var null|StemmerInterface */
	private $stemmer = null;
	/** @var null|Tokenizer */
	private $tokenizer = null;
	private $searchableLimit = PHP_INT_MAX;
	protected $searchStart = 0;
	/** @var TaxQuery */
	protected $taxQuery;
	protected $tntTime = 0;
	protected $settings = array();
	protected $slots;
	private $foundProductsIds = array();
	protected $foundProducts = array();
	protected $foundTax = array();
	protected $foundVendors = array();
	protected $foundPosts = array();
	/** @var string */
	protected $lang = '';
	public $debug = false;

	##########
	# Config #
	##########
	/** @var bool */
	public $fuzziness = false;
	/** @var int */
	public $fuzzyPrefixLength = 2;
	/** @var int */
	public $fuzzyMaxExpansions = 50;
	/** @var int */
	public $fuzzyDistance = 2;
	/** @var int */
	public $maxDocs = 0;
	/** @var int */
	public $wordlistByKeywordLimit = 0;

	/**
	 * MainQuery constructor.
	 *
	 * @param bool $debug
	 */
	public function __construct( $debug = false ) {
		$this->debug = $debug;

		$this->setSettings();
		$this->loadFilters();

		$this->slots = $this->getOption( 'suggestions_limit', 'int', 7 );

		$this->initSearchEngine();

		// Init Search Analytics
		if ( Builder::getInfo( 'status' ) === 'completed'
		     && $this->getOption( 'analytics_enabled', 'string', 'off' ) === 'on'
		) {
			$stats = new Recorder();
			$stats->listen();
		}
	}

	/**
	 * Include filters directly from themes, child themes of wp-content directory
	 *
	 * @return void
	 */
	private function loadFilters() {
		$theme = Config::getCurrentThemePath();
		$files = array(
			'fibosearch.php',
			'asfw-filters.php',
			'ajax-search-for-woocommerce.php',
			'ajax-search-filters.php',
		);

		if ( file_exists( $theme ) ) {
			foreach ( $files as $file ) {
				if ( file_exists( $theme . $file ) ) {
					require_once $theme . $file;

					break;
				}
			}
		}

		foreach ( $files as $file ) {
			if ( file_exists( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $file ) ) {
				require_once WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $file;

				break;
			}
		}

		// Internal plugins integrations
		foreach ( Config::getInternalFilterClasses() as $class ) {
			if ( class_exists( $class ) ) {
				$obj = new $class;
				$obj->init();
			}
		}

	}

	/**
	 * Set searched phrase
	 *
	 * @param string $phrase
	 *
	 * @return void
	 */
	public function setPhrase( $phrase ) {
		$charLimit = apply_filters( 'dgwt/wcas/search/input_chars_limit', 200 );

		if ( mb_strlen( $phrase ) > $charLimit ) {
			// Limit the number of characters
			$phrase = mb_substr( $phrase, 0, $charLimit );

			// Trim last word if needed
			if ( mb_substr( $phrase, - 1 ) !== ' ' ) {
				$phrase = mb_substr( $phrase, 0, mb_strrpos( $phrase, ' ' ) );
			}
		}

		$phrase  = apply_filters( 'dgwt/wcas/phrase/initial', $phrase );
		$phrase  = $this->replacePhrase( $phrase );
		$phrase  = $this->removeInPhrase( $phrase );
		$this->s = apply_filters( 'dgwt/wcas/phrase/final', $phrase );
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
			$this->cache->setLang( $lang );
		}
	}

	/**
	 * Load settings
	 *
	 * @return void
	 */
	protected function setSettings() {
		$this->settings = Settings::getSettings();
	}

	/**
	 * Get searched phrase
	 *
	 * @param string mode - searchable or readable
	 *
	 * @return string
	 */
	public function getPhrase( $mode = 'readable' ) {
		$phrase = trim( $this->s );
		$phrase = str_replace( '  ', ' ', $phrase );

		return apply_filters( 'dgwt/wcas/search_phrase', $phrase );
	}

	/**
	 * Get language code
	 *
	 * @return string
	 */
	public function getLang() {
		return $this->lang;
	}

	/**
	 * Get option from the plugin settings
	 *
	 * @param $key
	 * @param string $type
	 * @param string $default
	 *
	 * @return bool|int|string
	 */
	protected function getOption( $key, $type = 'string', $default = '' ) {

		if ( isset( $this->settings[ $key ] ) ) {
			$value = filter_var( $this->settings[ $key ], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		} else {
			$value = $default;
		}

		switch ( $type ) {
			case 'int':
				$value = intval( $value );
				break;
			case 'bool':
				$value = boolval( $value );
				break;
		}

		return $value;
	}

	/**
	 * Init serach engine
	 *
	 * @return void
	 */
	private function initSearchEngine() {
		$fuzzines = $this->getFuzzinessSettings();

		$config = apply_filters( 'dgwt/wcas/tnt/query/config', array(
			'debug'                  => $this->debug,
			'wordlistByKeywordLimit' => apply_filters( 'dgwt/wcas/tnt/wordlist_by_keyword_limit', 5000 ),
			'maxDocs'                => 50000,
			'fuzziness'              => $fuzzines['fuzziness'],
			'fuzzy'                  => $fuzzines['fuzzy'],
		) );

		if ( defined( 'DGWT_WCAS_TNT_WORDLIST_LIMIT' ) ) {
			$config['wordlistByKeywordLimit'] = absint( DGWT_WCAS_TNT_WORDLIST_LIMIT );
		}

		if ( defined( 'DGWT_WCAS_TNT_DOCS_LIMIT' ) ) {
			$config['maxDocs'] = absint( DGWT_WCAS_TNT_DOCS_LIMIT );
		}

		$this->setConfig( $config );

		$this->setStemmer();

		$this->setCache( new Cache() );

		$this->setTokenizer( new Tokenizer() );

		$this->taxQuery = new TaxQuery();
	}

	/**
	 * Get fuzziness options
	 *
	 * @return array
	 */
	private function getFuzzinessSettings() {
		$fuzziness = array(
			'fuzziness' => true,
			'fuzzy'     => array(
				'fuzzy_prefix_length'  => 2,
				'fuzzy_max_expansions' => 200,
				'fuzzy_distance'       => 2
			)
		);

		$option = $this->getOption( 'fuzziness_enabled', 'string', 'normal' );

		switch ( $option ) {
			case 'soft':
				$fuzziness['fuzzy']['fuzzy_prefix_length']  = 2;
				$fuzziness['fuzzy']['fuzzy_max_expansions'] = 50;
				$fuzziness['fuzzy']['fuzzy_distance']       = 1;
				break;
			case 'normal':
				break;
			case 'hard':
				$fuzziness['fuzzy']['fuzzy_prefix_length']  = 2;
				$fuzziness['fuzzy']['fuzzy_max_expansions'] = 400;
				$fuzziness['fuzzy']['fuzzy_distance']       = 3;
				break;
			default:
				$fuzziness['fuzziness'] = false;
				$fuzziness['fuzzy']     = array();
				break;
		}

		return $fuzziness;
	}

	/**
	 * Set config fot TNT Search
	 *
	 * @return void
	 */
	public function setConfig( $config = array() ) {

		if ( isset( $config['fuzziness'] ) ) {
			$fuzziness       = boolval( $config['fuzziness'] );
			$this->fuzziness = $fuzziness;
		}

		if ( ! empty( $config['fuzzy'] ) && is_array( $config['fuzzy'] ) ) {
			$this->fuzziness = true;

			if ( isset( $config['fuzzy']['fuzzy_prefix_length'] ) ) {
				$this->fuzzyPrefixLength = intval( $config['fuzzy']['fuzzy_prefix_length'] );
			}

			if ( isset( $config['fuzzy']['fuzzy_max_expansions'] ) ) {
				$this->fuzzyMaxExpansions = intval( $config['fuzzy']['fuzzy_max_expansions'] );
			}

			if ( isset( $config['fuzzy']['fuzzy_distance'] ) ) {
				$this->fuzzyDistance = intval( $config['fuzzy']['fuzzy_distance'] );
			}
		}

		if ( isset( $config['maxDocs'] ) ) {
			$this->maxDocs = absint( $config['maxDocs'] );
		}

		if ( isset( $config['wordlistByKeywordLimit'] ) ) {
			$this->wordlistByKeywordLimit = absint( $config['wordlistByKeywordLimit'] );
		}

		$this->config = $config;
	}

	/**
	 * Set stemmer
	 *
	 * @return void
	 */
	protected function setStemmer() {
		$stemmer = Builder::getInfo( 'stemmer' );

		if ( ! empty( $stemmer ) && class_exists( $stemmer ) ) {
			$this->stemmer = new $stemmer;
		} else {
			$this->stemmer = isset( $this->config['stemmer'] ) ? new $this->config['stemmer'] : new NoStemmer;
		}
	}

	/**
	 * @param Cache $cache
	 */
	private function setCache( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Set post type
	 *
	 * @param string $postType
	 */
	public function setPostType( $postType ) {
		if (
			! empty( $postType )
			&& is_string( $postType )
			&& preg_match( '/^[a-z_\-]{1,50}$/', $postType )
		) {
			$this->postType = $postType;
			$this->cache->setPostType( $postType );
		}
	}

	/**
	 * @param TokenizerInterface $tokenizer
	 */
	public function setTokenizer( TokenizerInterface $tokenizer ) {
		$this->tokenizer = $tokenizer;
	}

	/**
	 * Search products
	 *
	 * @param int $limit
	 *
	 * @return bool true if something was found
	 */
	public function searchProducts( $limit = null ) {
		$found = false;

		$max    = $limit ?? $this->searchableLimit;
		$phrase = $this->getPhrase( 'searchable' );
		$result = $this->searchFibo( $phrase, $max );

		if ( ! empty( $result['ids'] ) && is_array( $result['ids'] ) ) {
			$this->foundProductsIds = apply_filters( 'dgwt/wcas/tnt/search_results/ids', $result['ids'], $phrase );

			$this->tntTime = $this->tntTime + (float) ( str_replace( ' ms', '', $result['execution_time'] ) );
			$found         = true;
		}

		do_action( 'dgwt/wcas/after_searching/products', $phrase, count( $this->foundProductsIds ), $this->getLang() );

		return $found;
	}

	/**
	 * Search no-products items (taxonomies)
	 *
	 * @return bool true if something was found
	 */
	public function searchTaxonomy() {
		$found = false;

		if ( $this->taxQuery->isEnabled() ) {
			if ( ! empty( $this->lang ) ) {
				$this->taxQuery->setLang( $this->lang );
			}

			$this->foundTax = $this->taxQuery->search( $this->getPhrase() );

			if ( ! empty( $this->foundTax ) ) {
				$found = true;
			}
		}

		return $found;
	}

	/**
	 * Search no-products items (vendors)
	 *
	 * @return bool true if something was found
	 */
	public function searchVendors() {
		$found = false;

		$vendorQuery = new VendorQuery();
		$vendorQuery->init();

		if ( $vendorQuery->isEnabled() ) {
			$this->foundVendors = $vendorQuery->search( $this->getPhrase() );

			if ( ! empty( $this->foundVendors ) ) {
				$found = true;
			}
		}

		return $found;
	}

	/**
	 * Search in Posts
	 *
	 * @return bool true if something was found
	 */
	public function searchPosts() {
		$found = false;

		$postTypes = $this->getExtraPostTypes();
		if ( ! empty( $postTypes ) ) {

			foreach ( $postTypes as $postType ) {

				$max    = $limit ?? $this->searchableLimit;
				$phrase = $this->getPhrase( 'searchable' );
				$this->setPostType( $postType );
				$result = $this->searchFibo( $phrase, $max );

				if ( ! empty( $result['ids'] ) && is_array( $result['ids'] ) ) {
					$ids = apply_filters( 'dgwt/wcas/tnt/search_results' . $postType . '/ids', $result['ids'], $phrase );

					$cp = new CustomPost( $ids, $postType, $this->getPhrase() );
					if ( ! empty( $this->getLang() ) ) {
						$cp->setLang( $this->getLang() );
					}
					$this->foundPosts[ $postType ] = $cp->getResults();

					$this->tntTime = $this->tntTime + (float) ( str_replace( ' ms', '', $result['execution_time'] ) );
					$found         = true;
				}
			}
		}

		return $found;
	}

	/**
	 * Check if current query has relevant IDs
	 *
	 * @return  bool
	 */
	public function hasResults() {
		return ! empty( $this->foundProductsIds ) || ! empty( $this->foundTax ) || ! empty( $this->foundPosts ) || ! empty( $this->foundVendors );
	}

	/**
	 *
	 * Set found posts
	 * @return void
	 */
	private function setFoundProducts() {
		global $wpdb;

		if ( ! empty( $this->foundProductsIds ) ) {

			$placeholders = array_fill( 0, count( $this->foundProductsIds ), '%d' );
			$format       = implode( ', ', $placeholders );

			$tableName = Utils::getTableName( 'readable' );

			$sql = $wpdb->prepare( "
                SELECT *
                FROM $tableName
                WHERE post_id IN ($format)
                AND name != ''
                ",
				$this->foundProductsIds
			);

			$r = $wpdb->get_results( $sql );

			if ( ! empty( $r ) && is_array( $r ) && ! empty( $r[0] ) && ! empty( $r[0]->post_id ) ) {
				foreach ( $r as $index => $value ) {
					$r[ $index ]->meta = maybe_unserialize( $value->meta );
				}
				$this->foundProducts = apply_filters( 'dgwt/wcas/tnt/search_results/products', $r, $this->getPhrase(), $this->getLang() );
			}
		}
	}

	/**
	 * Get products from IDs
	 *
	 * @param string $orderBy
	 * @param string $order
	 *
	 * @return array
	 */
	public function getProducts( $orderBy = 'relevance', $order = '' ) {
		if ( empty( $this->foundProducts ) ) {
			$this->setFoundProducts();
		}

		if ( ! empty( $this->foundProducts ) ) {
			$this->sortResults( $orderBy, $order );
		}

		return $this->foundProducts;
	}

	/**
	 * Sort products
	 *
	 * @param string $orderBy
	 * @param string $order
	 *
	 * @param string $orderBy
	 */
	private function sortResults( $orderBy, $order = '' ) {
		// Something wrong with the query vars? Try to read order from the URL
		if ( empty( $orderBy ) || ! is_string( $orderBy ) ) {

			if ( ! empty( $_GET['orderby'] ) ) {
				$orderBy = sanitize_title( $_GET['orderby'] );

				if ( strpos( $orderBy, '-asc' ) !== false ) {
					$order = 'asc';
				}
				if ( strpos( $orderBy, '-desc' ) !== false ) {
					$order = 'desc';
				}
			} else {
				return;
			}

		}

		$orderBy = str_replace( array( '-asc', '-desc' ), '', $orderBy );

		if ( in_array( $order, array( 'asc', 'desc' ) ) ) {
			if ( $orderBy === 'date' ) {
				$orderBy = 'date-' . $order;
			}
			if ( $orderBy === 'price' ) {
				$orderBy = 'price-' . $order;
			}
		}

		$orderBy = apply_filters( 'dgwt/wcas/tnt/sort_products/order_by', $orderBy );

		switch ( $orderBy ) {
			case 'relevance':
				$this->orderProductsByWeight();
				break;
			case 'date ID':
			case 'date-desc':

				usort( $this->foundProducts, function ( $a, $b ) {
					$a = strtotime( $a->created_date );
					$b = strtotime( $b->created_date );
					if ( $a == $b ) {
						return 0;
					}

					return ( $a < $b ) ? 1 : - 1;
				} );

				break;
			case 'price-asc':
				usort( $this->foundProducts, function ( $a, $b ) {
					if ( $a->price == $b->price ) {
						return 0;
					}

					return ( $a->price < $b->price ) ? - 1 : 1;
				} );

				break;

			case 'price-desc':
				usort( $this->foundProducts, function ( $a, $b ) {
					if ( $a->price == $b->price ) {
						return 0;
					}

					return ( $a->price < $b->price ) ? 1 : - 1;
				} );

				break;

			case 'rating':
				usort( $this->foundProducts, function ( $a, $b ) {
					if ( $a->average_rating == $b->average_rating ) {
						return 0;
					}

					return ( $a->average_rating < $b->average_rating ) ? 1 : - 1;
				} );

				break;

			case 'popularity':
			case 'popularity-desc':

				usort( $this->foundProducts, function ( $a, $b ) {
					if ( $a->total_sales == $b->total_sales ) {
						return 0;
					}

					return ( $a->total_sales < $b->total_sales ) ? 1 : - 1;
				} );

				break;

		}

		$this->foundProducts = apply_filters( 'dgwt/wcas/tnt/sort_products', $this->foundProducts, $orderBy );
	}

	/**
	 * Order found products by weights
	 *
	 * @return void
	 */
	private function orderProductsByWeight() {
		$i = 0;

		foreach ( $this->foundProducts as $product ) {

			$score = 0;

			$score += Helpers::calcScore( $this->getPhrase(), $product->name );

			// SKU
			if ( $this->searchIn( 'sku' ) ) {

				$score += Helpers::calcScore( $this->getPhrase(), $product->sku, array(
					'check_similarity' => false
				) );
				$score += Helpers::calcScore( $this->getPhrase(), $product->sku_variations, array(
					'check_similarity' => false,
					'check_position'   => false,
					'score_containing' => 80
				) );
			}

			// Attributes
			if ( $this->searchIn( 'attributes' ) ) {
				$score += Helpers::calcScore( $this->getPhrase(), $product->attributes, array(
					'check_similarity' => false,
					'check_position'   => false,
					'score_containing' => 80
				) );
			}

			$this->foundProducts[ $i ]->score = apply_filters( 'dgwt/wcas/tnt/product/score', (float) $score, $product->post_id, $product, $this );

			$i ++;
		}

		usort( $this->foundProducts, array( 'DgoraWcas\Helpers', 'cmpSimilarity' ) );
	}

	/**
	 * Count total results
	 *
	 * @return int
	 */
	public function getTotalFound() {
		return count( $this->foundProductsIds );
	}

	/**
	 * Get extra post types to search
	 *
	 * @return array
	 */
	public function getExtraPostTypes() {
		$postTypes = array();

		if ( array_key_exists( 'show_matching_posts', $this->settings )
		     && $this->settings['show_matching_posts'] === 'on' ) {

			$postTypes[] = 'post';
		}

		if ( array_key_exists( 'show_matching_pages', $this->settings )
		     && $this->settings['show_matching_pages'] === 'on' ) {

			$postTypes[] = 'page';
		}

		return apply_filters( 'dgwt/wcas/tnt/search_post_types', $postTypes );
	}

	/**
	 * Check the search scope
	 *
	 * @param $scope
	 *
	 * @return bool
	 */
	public function searchIn( $scope ) {
		$inScope = false;

		switch ( $scope ) {
			case 'content':
			case 'description':
				if ( array_key_exists( 'search_in_product_content', $this->settings ) ) {
					$inScope = $this->settings['search_in_product_content'] === 'on' ? true : false;
				}
				break;
			case 'excerpt':
				if ( array_key_exists( 'search_in_product_excerpt', $this->settings ) ) {
					$inScope = $this->settings['search_in_product_excerpt'] === 'on' ? true : false;
				}
				break;
			case 'sku':

				if ( array_key_exists( 'search_in_product_sku', $this->settings ) ) {
					$inScope = $this->settings['search_in_product_sku'] === 'on' ? true : false;
				}
				break;
			case 'attributes':
				if ( array_key_exists( 'search_in_product_attributes', $this->settings ) ) {
					$inScope = $this->settings['search_in_product_attributes'] === 'on' ? true : false;
				}
				break;
		}

		return $inScope;
	}

	/**
	 * Replace phrase to another
	 *
	 * @param string $phrase
	 *
	 * @return string
	 */
	private function replacePhrase( $phrase ) {
		$phrase    = trim( mb_strtolower( $phrase ) );
		$toReplace = apply_filters( 'dgwt/wcas/phrase/replace', array() );
		if ( empty( $toReplace ) || ! is_array( $toReplace ) ) {
			return $phrase;
		}
		if ( isset( $toReplace[ $phrase ] ) ) {
			return $toReplace[ $phrase ];
		}

		// Replace substrings in phrase
		foreach ( $toReplace as $keyword => $replace ) {
			if ( strpos( $phrase, $keyword ) !== false ) {
				$phrase = str_replace( $keyword, $replace, $phrase );
			}
		}

		return $phrase;
	}

	/**
	 * Remove words in phrase
	 *
	 * @param string $phrase
	 *
	 * @return string
	 */
	private function removeInPhrase( $phrase ) {
		$phrase   = trim( mb_strtolower( $phrase ) );
		$toRemove = apply_filters( 'dgwt/wcas/phrase/remove', array() );
		if ( empty( $toRemove ) || ! is_array( $toRemove ) ) {
			return $phrase;
		}
		foreach ( $toRemove as $word ) {
			$phrase = str_replace( $word, '', $phrase );
		}

		return trim( $phrase );
	}

	/**
	 * Search documents
	 *
	 * Assumptions:
	 * - at the beginning, we sort the keywords by length (from longest)
	 * - when searching for documents for the next keywords, we take into account the previous results
	 * - we stop searching when there are no more results for any of the keywords
	 *
	 * @param string $phrase
	 * @param int $numOfResults
	 *
	 * @return array
	 */
	public function searchFibo( string $phrase, int $numOfResults = 100 ): array {
		$startTimer = microtime( true );
		$keywords   = $this->breakIntoTokens( $phrase );
		$keywords   = new Collection( $keywords );

		if ( $this->debug ) {
			Debugger::log( '<b>Phrase:</b> ' . var_export( $phrase, true ) . '<br /><br />', 'product-search-flow' );
			Debugger::log( '<b>Keywords after tokenization:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
		}

		$keywords = $keywords->map( function ( $keyword ) {
			return $this->stemmer->stem( $keyword );
		} );

		$last = $keywords->last();

		if ( $this->debug ) {
			Debugger::log( '<b>Keywords after stemmer and before sorting:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
			Debugger::log( '<b>Last keyword:</b> ' . var_export( $last, true ) . '<br /><br />', 'product-search-flow' );
		}

		$keywords->sortWith( 'usort', 'DgoraWcas\Helpers::sortFromLongest' );

		if ( $this->debug ) {
			Debugger::log( '<b>Keywords after sorting:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
		}

		$documentsIds = null;

		foreach ( $keywords as $index => $term ) {
			$isLastKeyword = $term === $last;
			// Break if there are no results for previous keywords
			if ( is_array( $documentsIds ) && empty( $documentsIds ) ) {
				break;
			}
			$result = $this->getAllDocumentIdsForKeyword( $term, $isLastKeyword, $documentsIds );
			if ( $this->debug ) {
				$keywordLike = $this->getKeywordLikeFormat( $term, $isLastKeyword );
				if ( count( $result ) < 5000 ) {
					Debugger::log( 'Partial results for: <b>' . $term . '</b> | SQL LIKE statement ' . $keywordLike . ' | isLastKeyword: ' . ( $isLastKeyword ? 'true' : 'false' ) . ' | total: ' . count( $result ) . ' | ids: ' . implode( ',', $result ) . '<br /><br />', 'product-search-flow' );
				} else {
					Debugger::log( 'Partial results for: <b>' . $term . '</b> | SQL LIKE statement ' . $keywordLike . ' | isLastKeyword: ' . ( $isLastKeyword ? 'true' : 'false' ) . ' | total: ' . count( $result ) . '<br /><br />', 'product-search-flow' );
				}
			}
			if ( $index === 0 ) {
				$documentsIds = $result;
			} else {
				$documentsIds = array_intersect( $documentsIds, $result );
			}
		}

		// Allow following code to run properly when there is no results.
		$documentsIds = is_null( $documentsIds ) ? array() : $documentsIds;

		$docs = new Collection( $documentsIds );

		$totalHits = $docs->count();
		$docs      = $docs->take( $numOfResults );
		$stopTimer = microtime( true );

		return [
			'ids'            => array_values( $docs->toArray() ),
			'hits'           => $totalHits,
			'execution_time' => round( $stopTimer - $startTimer, 7 ) * 1000 . " ms"
		];
	}

	/**
	 * @param string $phrase
	 * @param int $numOfResults
	 *
	 * @return array
	 */
	public function searchBoolean( $phrase, $numOfResults = 100 ) {
		$keywords    = $this->breakIntoTokens( $phrase );
		$lastKeyword = end( $keywords );

		$stack      = [];
		$startTimer = microtime( true );

		$expression = new Expression;

		$postfix = ! empty( $keywords ) ? $expression->toPostfix( $this->prepareExpression( $keywords ) ) : array();

		if ( $this->debug ) {
			Debugger::log( '<b>Phrase:</b> ' . var_export( $phrase, true ), 'product-search-flow' );
			Debugger::log( '<b>Keywords:</b> <pre>' . var_export( $keywords, true ) . '</pre>', 'product-search-flow' );
			Debugger::log( '<b>Tokens:</b> <pre>' . var_export( $postfix, true ) . '</pre>', 'product-search-flow' );
		}

		foreach ( $postfix as $token ) {
			if ( $token == '&' ) {

				$right = array_pop( $stack );
				$left  = array_pop( $stack );

				if ( $this->debug ) {
					$debugOutput = 'INTERSECT START <br />';
				}

				if ( is_string( $right ) ) {
					$rightWord     = $right;
					$isLastKeyword = $right == $lastKeyword;
					$right         = $this->getAllDocumentsForKeyword( $this->stemmer->stem( $right ), $isLastKeyword )
					                      ->pluck( 'doc_id' );

					if ( $this->debug ) {
						$debugOutput .= 'INTERSECT right keyword: ' . $rightWord . ' | total: ' . count( $right ) . ' | ids: ' . implode( ',', $right ) . '<br />';
					}
				}

				if ( is_string( $left ) ) {
					$leftWord      = $left;
					$isLastKeyword = $left == $lastKeyword;
					$left          = $this->getAllDocumentsForKeyword( $this->stemmer->stem( $left ), $isLastKeyword )
					                      ->pluck( 'doc_id' );

					if ( $this->debug ) {
						$debugOutput .= 'INTERSECT left keyword: ' . $leftWord . ' | total: ' . count( $left ) . ' | ids: ' . implode( ',', $left ) . '<br />';
					}
				}

				if ( is_null( $right ) ) {
					$right = [];
				}

				if ( is_null( $left ) ) {
					$left = [];
				}

				$stack[] = array_values( array_intersect( $right, $left ) );

				if ( $this->debug ) {
					if ( is_array( $stack ) && isset( $stack[0] ) ) {
						$debugOutput .= 'INTERSECT ' . ' common: ' . implode( ',', array_unique( $stack[0] ) ) . '<br />';
					}

					$debugOutput .= 'INTERSECT END<br /><br />';
					Debugger::log( $debugOutput, 'product-search-flow' );
				}

			} elseif ( $token == '|' ) {
				$right = array_pop( $stack );
				$left  = array_pop( $stack );

				if ( $this->debug ) {
					$debugOutput = 'MERGE START <br />';
				}

				if ( is_string( $right ) ) {
					$rightWord     = $right;
					$isLastKeyword = $right == $lastKeyword;
					$right         = $this->getAllDocumentsForKeyword( $this->stemmer->stem( $right ), $isLastKeyword )
					                      ->pluck( 'doc_id' );

					if ( $this->debug ) {
						$debugOutput .= 'MERGE right keyword: ' . $rightWord . ' | total: ' . count( $right ) . ' | ids: ' . implode( ',', $right ) . '<br />';
					}
				}

				if ( is_string( $left ) ) {
					$leftWord      = $left;
					$isLastKeyword = $left == $lastKeyword;
					$left          = $this->getAllDocumentsForKeyword( $this->stemmer->stem( $left ), $isLastKeyword )
					                      ->pluck( 'doc_id' );

					if ( $this->debug ) {
						$debugOutput .= 'MERGE left keyword: ' . $leftWord . ' | total: ' . count( $left ) . ' | ids: ' . implode( ',', $left ) . '<br />';
					}
				}

				if ( is_null( $right ) ) {
					$right = [];
				}

				if ( is_null( $left ) ) {
					$left = [];
				}

				$stack[] = array_unique( array_merge( $right, $left ) );

				if ( $this->debug ) {
					if ( is_array( $stack ) && isset( $stack[0] ) ) {
						$debugOutput .= 'MERGE ' . ' sum: ' . implode( ',', $stack[0] ) . '<br />';
					}

					$debugOutput .= 'MERGE END<br /><br />';
					Debugger::log( $debugOutput, 'product-search-flow' );
				}

			} elseif ( $token == '~' ) {
				$left = array_pop( $stack );
				if ( is_string( $left ) ) {
					$left = $this->getAllDocumentsForWhereKeywordNot( $this->stemmer->stem( $left ), true )
					             ->pluck( 'doc_id' );
				}
				if ( is_null( $left ) ) {
					$left = [];
				}
				$stack[] = $left;
			} else {
				$stack[] = $token;
			}
		}
		if ( count( $stack ) ) {
			$docs = new Collection( $stack[0] );
		} else {
			$docs = new Collection;
		}

		$docs = $docs->take( $numOfResults );

		$stopTimer = microtime( true );

		return [
			'ids'            => $docs->toArray(),
			'hits'           => $docs->count(),
			'execution_time' => round( $stopTimer - $startTimer, 7 ) * 1000 . " ms"
		];
	}

	/**
	 * Search documents with sorting by BM25 algorithm
	 *
	 * @param string $phrase
	 * @param int $numOfResults
	 *
	 * @return array
	 */
	public function searchFiboBM25( $phrase, $numOfResults = 100 ) {
		$startTimer = microtime( true );
		$keywords   = $this->breakIntoTokens( $phrase );
		$keywords   = new Collection( $keywords );

		if ( $this->debug ) {
			Debugger::log( '<b>Phrase:</b> ' . var_export( $phrase, true ), 'product-search-flow' );
			Debugger::log( '<b>Keywords before tokenization:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
		}

		$keywords = $keywords->map( function ( $keyword ) {
			return $this->stemmer->stem( $keyword );
		} );

		if ( $this->debug ) {
			Debugger::log( '<b>Keywords after stemmer and before sorting:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
		}

		$last = $keywords->last();
		$keywords->sortWith( 'usort', 'DgoraWcas\Helpers::sortFromLongest' );

		if ( $this->debug ) {
			Debugger::log( '<b>Keywords after sorting:</b> <pre>' . var_export( $keywords->toArray(), true ) . '</pre>', 'product-search-flow' );
		}

		$tfWeight     = 1;
		$dlWeight     = 0.5;
		$docScores    = [];
		$count        = $this->totalDocumentsInCollection();
		$documentsIds = null;

		foreach ( $keywords as $index => $term ) {
			$isLastKeyword = $term === $last;
			// Break if there are no results for previous keywords
			if ( is_array( $documentsIds ) && empty( $documentsIds ) ) {
				break;
			}
			$df        = $this->totalMatchingDocuments( $term, $isLastKeyword );
			$idf       = log( $count / max( 1, $df ) );
			$documents = $this->getAllDocumentsForKeyword( $term, $isLastKeyword, $documentsIds );
			if ( $this->debug ) {
				if ( $documents->count() < 5000 ) {
					Debugger::log( 'Partial results for: ' . $term . ' | total: ' . count( $documents->pluck( 'doc_id' ) ) . ' | ids: ' . implode( ',', $documents->pluck( 'doc_id' ) ) . '<br /><br />', 'product-search-flow' );
				} else {
					Debugger::log( 'Partial results for: ' . $term . ' | total: ' . count( $documents->pluck( 'doc_id' ) ) . '<br /><br />', 'product-search-flow' );
				}
			}

			foreach ( $documents as $document ) {
				$docID               = $document['doc_id'];
				$tf                  = $document['hit_count'];
				$num                 = ( $tfWeight + 1 ) * $tf;
				$denom               = $tfWeight
				                       * ( ( 1 - $dlWeight ) + $dlWeight )
				                       + $tf;
				$score               = $idf * ( $num / $denom );
				$docScores[ $docID ] = isset( $docScores[ $docID ] ) ?
					$docScores[ $docID ] + $score : $score;
			}

			$resultIds = $documents->pluck( 'doc_id' );
			$resultIds = array_unique( $resultIds );
			$resultArr = array();
			foreach ( $resultIds as $id ) {
				$resultArr[ $id ] = $id;
			}
			$docScores    = array_intersect_key( $docScores, $resultArr );
			$documentsIds = array_keys( $docScores );
		}

		arsort( $docScores );

		$docs = new Collection( $docScores );

		$totalHits = $docs->count();
		$docs      = $docs->map( function ( $doc, $key ) {
			return $key;
		} )->take( $numOfResults );
		$stopTimer = microtime( true );

		return [
			'ids'            => array_keys( $docs->toArray() ),
			'hits'           => $totalHits,
			'execution_time' => round( $stopTimer - $startTimer, 7 ) * 1000 . " ms"
		];
	}

	public function totalDocumentsInCollection() {
		return absint( Builder::getInfo( 'searchable_processed' ) );
	}

	/**
	 * @param      $keyword
	 * @param bool $isLastWord
	 *
	 * @return int
	 */
	public function totalMatchingDocuments( $keyword, $isLastWord = false ) {
		$occurance = $this->getWordlistByKeyword( $keyword, $isLastWord );
		if ( isset( $occurance[0] ) ) {
			return $occurance[0]['num_docs'];
		}

		return 0;
	}

	/**
	 * Prepare search expression for postfix
	 *
	 * @param array $keywords
	 *
	 * @return string
	 */
	public function prepareExpression( $keywords ) {
		if ( count( $keywords ) < 2 || ! empty( $chars ) ) {
			return '|' . implode( ' ', $keywords );
		}

		$chars = method_exists( $this->tokenizer, 'getSpecialChars' ) ? $this->tokenizer->getSpecialChars() : array();

		$pieces = array();

		foreach ( $keywords as $keyword ) {
			$hasSpecialChar = false;
			foreach ( $chars as $char ) {
				if ( strpos( $keyword, $char ) !== false ) {
					$hasSpecialChar = true;
					break;
				}
			}

			if ( ! $hasSpecialChar ) {
				$pieces[] = $keyword;
			}
		}

		$exp = '|';
		if ( ! empty( $pieces ) ) {
			$exp .= implode( ' ', $pieces );
		}

		return $exp;
	}

	/**
	 * @param      $keyword
	 * @param bool $isLastKeyword
	 * @param array $docsIn
	 *
	 * @return array
	 */
	public function getAllDocumentIdsForKeyword( $keyword, $isLastKeyword = false, $docsIn = null ) {
		$keywordLike = $this->getKeywordLikeFormat( $keyword, $isLastKeyword );

		$result = $this->cache->get( $keywordLike );
		if ( $result !== false ) {
			if ( is_array( $docsIn ) && ! empty( $docsIn ) ) {
				$result = array_intersect( $docsIn, $result );
			}

			return $result;
		}

		$startTime = microtime( true );
		$words     = $this->getWordlistByKeyword( $keyword, $isLastKeyword );

		if ( ! isset( $words[0] ) ) {
			return array();
		}

		$result = $this->getAllDocumentsForWordlist( $words, $docsIn )->pluck( 'doc_id' );
		$result = array_values( array_unique( $result ) );

		$stopTime = microtime( true );

		// Re-run self (without $docsIn) for slow search, to allow cache result
		if ( ! empty( $docsIn ) && $stopTime - $startTime > 0.5 && $this->cache->isEnabled() ) {
			$this->getAllDocumentIdsForKeyword( $keyword, $isLastKeyword );
		}

		if ( ! empty( $result ) && empty( $docsIn ) && ( $stopTime - $startTime > 0.5 ) ) {
			$this->cache->set( $keywordLike, json_encode( array_map( 'absint', $result ) ) );
		}

		return $result;
	}

	/**
	 * Get format of keyword LIKE statement depending on context
	 *
	 * @param $keyword
	 * @param $isLastKeyword
	 *
	 * @return string
	 */
	public function getKeywordLikeFormat( $keyword, $isLastKeyword ) {
		global $wpdb;

		$keywordLike = "%" . $wpdb->esc_like( $keyword ) . "%";
		if ( strlen( $keyword ) <= 1 && ! $isLastKeyword ) {
			$keywordLike = $wpdb->esc_like( $keyword );
		} elseif ( strlen( $keyword ) <= 1 && $isLastKeyword ) {
			$keywordLike = $wpdb->esc_like( $keyword ) . "%";
		}

		return apply_filters( 'dgwt/wcas/tnt/keyword_like_format', $keywordLike, $keyword, $isLastKeyword, true );
	}

	/**
	 * @param string $keyword
	 * @param bool $isLastWord
	 *
	 * @return array
	 */
	public function getWordlistByKeyword( $keyword, $isLastWord = false ) {
		global $wpdb;

		$keyword       = mb_strtolower( $keyword );
		$keywordLike   = $this->getKeywordLikeFormat( $keyword, $isLastWord );
		$limit         = $this->getLimits( 'wordlist' );
		$wordlistTable = Utils::getTableName( 'searchable_wordlist', $this->lang, $this->postType );

		$sql = $wpdb->prepare( "SELECT * FROM $wordlistTable WHERE term LIKE %s ORDER BY length(term) ASC, num_hits DESC LIMIT %d", $keywordLike, $limit );

		$result = $wpdb->get_results( $sql, ARRAY_A );

		if ( $this->fuzziness && ! isset( $result[0] ) ) {
			return $this->fuzzySearch( $keyword );
		}

		return $result;
	}

	/**
	 * @param array $words
	 * @param null|array $docsIn
	 *
	 * @return Collection
	 */
	private function getAllDocumentsForWordlist( $words, $docsIn = null ) {
		global $wpdb;

		$limit        = $this->getLimits( 'doclist' );
		$doclistTable = Utils::getTableName( 'searchable_doclist', $this->lang, $this->postType );

		$format = implode( ',', array_fill( 0, count( $words ), '%d' ) );

		if ( is_array( $docsIn ) && ! empty( $docsIn ) ) {
			$in    = join( ",", $docsIn );
			$query = "SELECT * FROM $doclistTable WHERE term_id in ($format) AND doc_id IN($in) ORDER BY CASE term_id";
		} else {
			$query = "SELECT * FROM $doclistTable WHERE term_id in ($format) ORDER BY CASE term_id";
		}
		$order_counter = 1;

		foreach ( $words as $word ) {
			$query .= " WHEN " . $word['id'] . " THEN " . $order_counter ++;
		}

		$query .= " END";

		if ( ! empty( $limit ) ) {
			$query .= " LIMIT {$limit}";
		}

		$ids    = wp_list_pluck( $words, 'id' );
		$sql    = $wpdb->prepare( $query, $ids );
		$result = $wpdb->get_results( $sql, ARRAY_A );

		return new Collection( $result );
	}

	/**
	 * @param      $keyword
	 * @param bool $isLastKeyword
	 * @param array $docsIn
	 *
	 * @return Collection
	 */
	public function getAllDocumentsForKeyword( $keyword, $isLastKeyword = false, $docsIn = null ) {
		$words = $this->getWordlistByKeyword( $keyword, $isLastKeyword );

		if ( ! isset( $words[0] ) ) {
			return new Collection( [] );
		}

		return $this->getAllDocumentsForWordlist( $words, $docsIn );
	}

	/**
	 * @param      $keyword
	 *
	 * @return Collection
	 */
	public function getAllDocumentsForWhereKeywordNot( $keyword ) {
		global $wpdb;

		$limit        = $this->getLimits( 'doclist' );
		$doclistTable = Utils::getTableName( 'searchable_doclist', $this->lang, $this->postType );

		$word = $this->getWordlistByKeyword( $keyword );
		if ( ! isset( $word[0] ) ) {
			return new Collection( [] );
		}
		$query = "SELECT * FROM $doclistTable WHERE doc_id NOT IN (SELECT doc_id FROM $doclistTable WHERE term_id = %d) GROUP BY doc_id ORDER BY hit_count DESC LIMIT {$limit}";

		if ( empty( $limit ) ) {
			$query = "SELECT * FROM $doclistTable WHERE doc_id NOT IN (SELECT doc_id FROM $doclistTable WHERE term_id = %d) GROUP BY doc_id ORDER BY hit_count DESC";
		}

		$sql = $wpdb->prepare( $query, $word[0]['id'] );

		return new Collection( $wpdb->get_results( $sql, ARRAY_A ) );
	}

	/**
	 * @param string $keyword
	 *
	 * @return array
	 */
	public function fuzzySearch( $keyword ) {
		global $wpdb;

		$wordlistTable = Utils::getTableName( 'searchable_wordlist', $this->lang );

		$prefix      = substr( $keyword, 0, $this->fuzzyPrefixLength );
		$keywordLike = $wpdb->esc_like( mb_strtolower( $prefix ) ) . "%";
		$sql         = $wpdb->prepare( "SELECT * FROM $wordlistTable WHERE term LIKE %s ORDER BY num_hits DESC LIMIT %d", $keywordLike, $this->fuzzyMaxExpansions );
		$matches     = $wpdb->get_results( $sql, ARRAY_A );

		$resultSet = [];
		foreach ( $matches as $match ) {
			$distance = levenshtein( $match['term'], $keyword );
			if ( $distance <= $this->fuzzyDistance ) {
				$match['distance'] = $distance;
				$resultSet[]       = $match;
			}
		}

		// Sort the data by distance, and than by num_hits
		$distance = [];
		$hits     = [];
		foreach ( $resultSet as $key => $row ) {
			$distance[ $key ] = $row['distance'];
			$hits[ $key ]     = $row['num_hits'];
		}
		array_multisort( $distance, SORT_ASC, $hits, SORT_DESC, $resultSet );

		return $resultSet;
	}

	/**
	 * Wrapper for tokenizer
	 *
	 * @param string $phrase
	 *
	 * @return array
	 */
	public function breakIntoTokens( $phrase ) {
		$stopwords = apply_filters( 'dgwt/wcas/search/stopwords', array(), $this->getLang() );

		return $this->tokenizer->tokenize( $phrase, $stopwords );
	}

	/**
	 * Get all limitations
	 *
	 * @return int
	 */
	private function getLimits( $target ) {
		switch ( $target ) {
			case 'wordlist':
				return $this->wordlistByKeywordLimit;
			case 'doclist':
				return $this->maxDocs;
		}

		return 0;
	}
}
