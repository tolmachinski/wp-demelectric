<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

// Exit if accessed directly
use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractIndexer {
	/**
	 * @var string Indexer type
	 */
	protected $type = '';

	/**
	 * Get indexer type
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	public function getTypeName() {
		return ucfirst( $this->getType() );
	}

	/**
	 * Index the entire set in one go
	 *
	 * @param array $itemsSet
	 * @param callable $updateLock
	 *
	 * @return void
	 * @throws WPDBException
	 */
	public function indexSet( $itemsSet, $updateLock ) {
		$productProcessed = Builder::getInfo( $this->getType() . '_processed', Config::getIndexRole() );

		$time = microtime( true );

		$counter = ! empty( $productProcessed ) && is_numeric( $productProcessed ) ? intval( $productProcessed ) : 0;

		$defaultItemsCount = constant( '\DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder::' . strtoupper( $this->getType() ) . '_SET_ITEMS_COUNT' );
		$productsSetCount  = apply_filters( "dgwt/wcas/indexer/{$this->getType()}_set_items_count", $defaultItemsCount );
		$longTimeCounter   = 0;
		$longTimeLimit     = 20;
		$longTimeWarning   = false;
		$updateLockTimer   = microtime( true );

		$indexerMode = Config::getIndexerMode();

		WPDBSecond::get_instance()->start_transaction();

		foreach ( $this->fetchProductsSetData( $itemsSet ) as $row ) {
			$counter ++;
			$longTimeCounter ++;

			$this->processItemRow( $row );

			if ( $indexerMode !== 'direct' && $longTimeWarning === false && microtime( true ) - $time > $longTimeLimit ) {
				$longTimeWarning = true;
				Builder::log( "[{$this->getTypeName()} index] Timeout warning: " . round( ( $longTimeCounter / $productsSetCount ) * 100 ) . "% of the objects in the set were indexed in " . number_format( microtime( true ) - $time,
						4, '.', '' ) . " seconds", 'debug', 'file', $this->getType() );
			}

			// Update the background process lock timer no more frequently than every 5 seconds.
			if ( microtime( true ) - $updateLockTimer > 5 && is_callable( $updateLock ) ) {
				$updateLockTimer = microtime( true );
				call_user_func( $updateLock );
			}
		}

		// Any errors? Kill the process.
		if ( Builder::getInfo( 'status', Config::getIndexRole() ) !== 'building' ) {
			WPDBSecond::get_instance()->rollback();
			Builder::log( "[{$this->getTypeName()} index] Process killed" );
			exit();
		}

		WPDBSecond::get_instance()->commit();

		$ntime = number_format( microtime( true ) - $time, 4, '.', '' ) . ' s';
		Builder::log( "[{$this->getTypeName()} index] Processed $counter objects in $ntime" );

		Builder::addInfo( "{$this->getType()}_processed", $counter );
	}

	/**
	 * Process single row from set of items to index
	 *
	 * @param array $row
	 *
	 * @return void
	 * @throws WPDBException
	 */
	protected function processItemRow( $row ) {
		$document = $this->getDocument( $row );

		if ( Multilingual::isMultilingual() ) {
			$lang = $document->getLang();
			// Abort if the object hasn't the language.
			if ( empty( $lang ) ) {
				return;
			}
			// Abort if the object has a language that is not present in the settings.
			if ( ! in_array( $lang, Multilingual::getLanguages() ) ) {
				return;
			}
		}

		$document->save();
	}

	/**
	 * Fetch data of products that should be indexed
	 *
	 * @param $ids
	 *
	 * @return array
	 */
	abstract protected function fetchProductsSetData( $ids );

	/**
	 * Get single document
	 *
	 * @param array $row
	 *
	 * @return AbstractDocument
	 */
	abstract protected function getDocument( $row );
}
