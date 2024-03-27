<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Debug;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Indexer as IndexerR;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Indexer as IndexerS;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product {

	private $productID;
	public $product;
	private $indexerR;
	private $indexerS;

	public function __construct( $productID ) {

		$productID = absint( $productID );

		$this->product   = new \DgoraWcas\Product( $productID );
		$this->productID = $productID;
		$this->indexerR  = new IndexerR();
		$this->indexerS  = new IndexerS();
	}

	/**
	 * Get data that are saved in a readable index
	 *
	 * @return array
	 */
	public function getReadableIndexData() {
		return $this->indexerR->getSingle( $this->productID );
	}

	/**
	 * Get searchable index terms that belong to product
	 *
	 * @return array
	 */
	public function getSearchableIndexData() {
		$terms = array();
		foreach ( $this->indexerS->getWordList( $this->productID ) as $term ) {
			$terms[] = $term['term'];
		}

		return $terms;
	}

	/**
	 * Get data before saving in searchable index database using "source" method (raw SQL)
	 *
	 * @return array
	 */
	public function getDataForIndexingBySource() {
		$terms = [];

		$stems = $this->indexerS->getDocumentDataBeforeIndex( $this->productID );

		if ( ! empty( $stems ) ) {
			foreach ( $stems as $key => $group ) {
				foreach ( $group as $term ) {
					$terms[] = $term;
				}
			}

			$terms = array_unique( $terms );
			sort( $terms, SORT_STRING );
		}

		return $terms;
	}
}
