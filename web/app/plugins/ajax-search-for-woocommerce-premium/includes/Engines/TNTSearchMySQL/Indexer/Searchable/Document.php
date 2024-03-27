<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\AbstractDocument;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\SynonymsHandler;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBSecond;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Stemmer\StemmerInterface;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\TokenizerInterface;
use DgoraWcas\Product;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Document extends AbstractDocument {

	/** @var StemmerInterface */
	private $stemmer;
	/** @var TokenizerInterface */
	private $tokenizer;
	/** @var SynonymsHandler */
	private $synonymsHandler;
	/** @var string */
	private $indexRole;

	/**
	 * @param int|array $data Post ID or array with it's data.
	 * @param array $config
	 */
	public function __construct( $data, $config = [] ) {
		parent::__construct( $data, $config );
	}

	/**
	 * @param SynonymsHandler $synonymsHandler
	 */
	public function setSynonymsHandler( SynonymsHandler $synonymsHandler ) {
		$this->synonymsHandler = $synonymsHandler;
	}

	/**
	 * @return SynonymsHandler
	 */
	public function getSynonymsHandler() {
		return $this->synonymsHandler;
	}

	/**
	 * @param TokenizerInterface $tokenizer
	 */
	public function setTokenizer( TokenizerInterface $tokenizer ) {
		$this->tokenizer = $tokenizer;
	}

	/**
	 * @return TokenizerInterface
	 */
	public function getTokenizer() {
		return $this->tokenizer;
	}

	/**
	 * @param StemmerInterface $stemmer
	 */
	public function setStemmer( StemmerInterface $stemmer ) {
		$this->stemmer = $stemmer;
	}

	/**
	 * @return StemmerInterface
	 */
	public function getStemmer() {
		return $this->stemmer;
	}

	/**
	 * @param string $indexRole
	 */
	public function setIndexRole( string $indexRole ) {
		$this->indexRole = $indexRole;
	}

	/**
	 * @throws WPDBException
	 */
	public function delete() {
		$doclistTable  = $this->getTableName( 'searchable_doclist' );
		$wordlistTable = $this->getTableName( 'searchable_wordlist' );

		WPDB::get_instance()->query( WPDB::get_instance()->prepare( "DELETE FROM $doclistTable WHERE doc_id = %d", $this->getID() ) );

		WPDB::get_instance()->query( "
			DELETE FROM $wordlistTable
			WHERE $wordlistTable.id NOT IN (
			    SELECT term_id FROM $doclistTable
		    )"
		);
	}

	public function getWordList() {
		global $wpdb;

		$doclistTable  = $this->getTableName( 'searchable_doclist' );
		$wordlistTable = $this->getTableName( 'searchable_wordlist' );

		$sql = "SELECT wordlist.term
             	FROM $doclistTable doclist
             	INNER JOIN $wordlistTable wordlist ON doclist.term_id = wordlist.id
            	WHERE doclist.doc_id = %d
                ORDER BY wordlist.term ASC";

		$query = $wpdb->prepare( $sql, $this->getID() );

		return (array) $wpdb->get_results( $query, ARRAY_A );
	}

	public function prepareDataToIndex() {
		$this->setDataToIndex( $this->getData() );

		$this->applyCustomAttributes();
		$this->processData();
	}

	/**
	 * @throws WPDBException
	 */
	protected function saveDataToIndex() {
		$termIds = $this->saveWordlist();
		$this->saveDoclist( $termIds );
	}

	/**
	 * Add custom attributes values to index data
	 *
	 * @return void
	 */
	private function applyCustomAttributes() {
		$config = $this->getConfig();

		if ( isset( $config['scope']['attributes'] ) && $config['scope']['attributes'] ) {
			$customAttributesValues = Product::getCustomAttributes( $this->getID() );
			if ( ! empty( $customAttributesValues ) ) {
				$sep = ' | ';
				if ( ! isset( $this->dataToIndex['custom_attributes'] ) ) {
					$this->dataToIndex['custom_attributes'] = '';
					$sep                                    = '';
				}

				$this->dataToIndex['custom_attributes'] .= $sep . implode( ' | ', $customAttributesValues );
			}
		}
	}

	/**
	 * Process data to index
	 *
	 * @return void
	 */
	private function processData() {
		if ( empty( $this->dataToIndex ) ) {
			return;
		}

		if ( empty( $this->tokenizer ) || empty( $this->stemmer ) || empty( $this->synonymsHandler ) ) {
			return;
		}

		$this->dataToIndex = array_map( function ( $text ) {
			return $this->processText( $text );
		}, $this->dataToIndex );
	}

	/**
	 * Process single line of text
	 *
	 * @param string $text
	 *
	 * @return array
	 */
	private function processText( $text ) {
		$text      = Utils::clearContent( $text );
		$text      = $this->synonymsHandler->applySynonyms( $text );
		$stopwords = apply_filters( 'dgwt/wcas/indexer/searchable/stopwords', array(), $this->getPostType(), $this->getID(), $this->getLang() );
		$words     = $this->tokenizer->tokenize( $text, $stopwords );
		$stems     = [];
		foreach ( $words as $word ) {
			if ( $word !== '' ) {
				$stems[] = $this->stemmer->stem( $word );
			}
		}

		return $stems;
	}

	/**
	 * Save words
	 *
	 * @return array
	 * @throws WPDBException
	 */
	private function saveWordlist() {
		$termIds  = [];
		$allWords = [];

		$table = $this->getTableName( 'searchable_wordlist' ) . ( $this->indexRole === 'tmp' ? '_tmp' : '' );

		// Counting hits for every word
		array_map( function ( $column ) use ( &$allWords ) {
			foreach ( $column as $word ) {
				if ( array_key_exists( $word, $allWords ) ) {
					$allWords[ $word ]['hits'] ++;
				} else {
					$allWords[ $word ] = [
						'hits' => 1,
					];
				}
			}
		}, $this->dataToIndex );

		foreach ( $allWords as $key => $word ) {
			$term = WPDBSecond::get_instance()->get_row( WPDBSecond::get_instance()->prepare( "
                    SELECT id, num_hits
                    FROM $table
                    WHERE term = %s",
				$key ), ARRAY_A );

			if ( empty( $term ) ) {
				WPDBSecond::get_instance()->insert(
					$table,
					array(
						'term'     => $key,
						'num_hits' => $word['hits'],
					),
					array(
						'%s',
						'%d',
					)
				);

				if ( ! empty( WPDBSecond::get_instance()->db->insert_id ) ) {
					$termIds[] = WPDBSecond::get_instance()->db->insert_id;
				}
			} else {
				$termIds[] = (int) $term['id'];

				WPDBSecond::get_instance()->update(
					$table,
					array(
						'num_hits' => $word['hits'] + (int) $term['num_hits'],
					),
					array(
						'id' => $term['id'],
					),
					array(
						'%d',
					),
					array(
						'%d',
					)
				);
			}
		}

		return $termIds;
	}

	/**
	 * Save docs
	 *
	 * @param int[] $termIds
	 *
	 * @return void
	 * @throws WPDBException
	 */
	private function saveDoclist( $termdIds ) {
		$table = $this->getTableName( 'searchable_doclist' ) . ( $this->indexRole === 'tmp' ? '_tmp' : '' );

		foreach ( $termdIds as $termId ) {
			$data = array(
				'term_id' => $termId,
				'doc_id'  => $this->getID(),
			);

			$format = array(
				'%d',
				'%d',
			);

			WPDBSecond::get_instance()->insert( $table, $data, $format );
		}
	}
}
