<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\AbstractIndexer;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Logger;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\PostsSourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SourceQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SynonymsHandler;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Stemmer\NoStemmer;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Stemmer\StemmerInterface;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\Tokenizer;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\TokenizerInterface;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Indexer extends AbstractIndexer {

	/** @var null|StemmerInterface */
	private $stemmer = null;
	/** @var null|TokenizerInterface */
	private $tokenizer = null;
	/** @var null|SynonymsHandler */
	private $synonymsHandler = null;
	/** @var array */
	private $config = [];

	protected $type = 'searchable';

	public function __construct( $args = array() ) {
		$this->setConfig( $args );
	}

	public function prepareTools() {
		$this->setStemmer( new NoStemmer );
		$this->tokenizer = new Tokenizer;
		$this->tokenizer->setContext( 'indexer' );
		$this->synonymsHandler = new SynonymsHandler;
	}

	private function setConfig( $args = array() ) {
		$config = [
			'scope'      => array(
				//@TODO rest of scope for future use
				'attributes' => DGWT_WCAS()->settings->getOption( 'search_in_product_attributes' ) === 'on' ? true : false,
			),
			'index_role' => Config::getIndexRole(),
		];

		$config = wp_parse_args( $args, $config );

		if ( ! empty( $config['debug'] ) ) {
			$this->debug = true;
		}

		$this->config = $config;
	}

	private function setStemmer( StemmerInterface $stemmer ) {
		$this->stemmer = $stemmer;
		$class         = get_class( $stemmer );

		Builder::addInfo( 'stemmer', $class );
	}

	/**
	 * Get Document ready for indexing
	 *
	 * @param $row
	 *
	 * @return Document
	 */
	protected function getDocument( $row ) {
		if ( $this->stemmer === null ) {
			$this->prepareTools();
		}

		foreach ( $row as $key => $value ) {
			if ( strpos( $key, 'tax_' ) === 0 ) {
				$row[ $key ] = html_entity_decode( $value );
			}
		}

		$row = apply_filters( 'dgwt/wcas/indexer/items_row', $row );

		$document = new Document( $row, $this->config );
		$document->setStemmer( $this->stemmer );
		$document->setTokenizer( $this->tokenizer );
		$document->setSynonymsHandler( $this->synonymsHandler );
		$document->setIndexRole( $this->config['index_role'] );

		return $document;
	}

	/**
	 * Fetch data of products that should be indexed
	 *
	 * @param array $ids
	 *
	 * @return array
	 */
	protected function fetchProductsSetData( $ids = array() ) {
		$args = [];

		$postType = ! empty( $ids[0] ) ? get_post_type( $ids[0] ) : 'product';

		if ( ! empty( $ids ) ) {
			$args['package'] = $ids;
		}

		if ( $postType === 'product' ) {
			$source = new SourceQuery( $args );
		} else {
			$args['postType'] = $postType;
			$source           = new PostsSourceQuery( $args );
		}

		return $source->getData();
	}

	/**
	 * Insert item to the index
	 *
	 * @param int postID
	 *
	 * @return void
	 */
	public function insert( $postID ) {
		foreach ( $this->fetchProductsSetData( [ $postID ] ) as $row ) {
			try {
				$this->processItemRow( $row );
			} catch ( \Error $e ) {
				Logger::handleUpdaterThrowableError( $e, '[Searchable index] ' );
			} catch ( \Exception $e ) {
				Logger::handleUpdaterThrowableError( $e, '[Searchable index] ' );
			}
		}
	}

	/**
	 * Update item
	 *
	 * @param int postID
	 *
	 * @return void
	 */
	public function update( $postID ) {
		$this->delete( $postID );
		$this->insert( $postID );
	}

	/**
	 * Remove item from the index
	 *
	 * @param int postID
	 *
	 * @return void
	 */
	public function delete( $postID ) {
		try {
			$document = new Document( $postID );
			$document->delete();
		} catch ( \Error $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Searchable index] ' );
		} catch ( \Exception $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Searchable index] ' );
		}
	}

	/**
	 * Get document data prepared to index
	 *
	 * @param $postID
	 *
	 * @return array
	 */
	public function getDocumentDataBeforeIndex( $postID ) {
		$rows = $this->fetchProductsSetData( [ $postID ] );
		if ( empty( $rows ) ) {
			return [];
		}

		$document = $this->getDocument( $rows[0] );
		$document->prepareDataToIndex();

		return $document->getDataToIndex();
	}

	/**
	 * Get wordlist of indexed object
	 *
	 * @param int $postID Post ID
	 *
	 * @return array
	 */
	public function getWordList( $postID ) {
		$document = new Document( $postID );

		return $document->getWordList();
	}

	/**
	 * Wipe index
	 *
	 * @return bool
	 */
	public function wipe( $indexRoleSuffix ) {
		Database::remove( $indexRoleSuffix );
		Builder::log( '[Searchable index] Cleared' );

		return true;
	}
}
