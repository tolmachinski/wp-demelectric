<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable;

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Logger;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Helpers;
use DgoraWcas\Multilingual;
use DgoraWcas\Post;
use DgoraWcas\Product;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation\Indexer as IndexerVar;
use DgoraWcas\Engines\TNTSearchMySQL\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Indexer {

	/**
	 * @var Product
	 */
	private $product;
	/**
	 * @var Post
	 */
	private $post;

	/**
	 * Insert post to the index
	 *
	 * @param int $postID Post ID
	 * @param bool $directVariations Index variations directly
	 *
	 * @return bool true on success
	 * @throws WPDBException
	 */
	public function insert( $postID, $directVariations = false, $indexRole = '' ) {
		global $wpdb;

		if ( empty( $indexRole ) ) {
			$indexRole = Config::getIndexRole();
		}

		$success  = false;
		$postType = get_post_type( $postID );

		if ( $postType === 'product' ) {
			$this->product = new Product( $postID );

			// Support for multilingual
			if ( Multilingual::isMultilingual() ) {
				$lang = $this->product->getLanguage();
				// Abort if the product hasn't a language.
				if ( empty( $lang ) ) {
					return false;
				}
				// Abort if the product has a language that is not present in the settings.
				if ( ! in_array( $lang, Multilingual::getLanguages() ) ) {
					return false;
				}

				if ( $lang !== Multilingual::getCurrentLanguage() ) {
					Multilingual::switchLanguage( $lang );
				}

				if ( Multilingual::isMultiCurrency() ) {
					Multilingual::setCurrentCurrency( $this->product->getCurrency() );
				}
			}

			$data = $this->getProductData();

			if ( $this->product->isType( 'variable' ) && DGWT_WCAS()->settings->getOption( 'search_in_product_sku' ) === 'on' ) {
				$this->enqueueOrIndexVariations( $directVariations, $indexRole );
			}
		} else {
			$this->post = new Post( $postID );

			// Support for multilingual
			if ( Multilingual::isMultilingual() ) {
				$lang = $this->post->getLanguage();
				// Abort if the post hasn't a language.
				if ( empty( $lang ) ) {
					return false;
				}
				// Abort if the post has a language that is not present in the settings.
				if ( ! in_array( $lang, Multilingual::getLanguages() ) ) {
					return false;
				}

				if ( $lang !== Multilingual::getCurrentLanguage() ) {
					Multilingual::switchLanguage( $lang );
				}
			}

			$data = $this->getNonProductData();
		}

		$dataFiltered = apply_filters( 'dgwt/wcas/readable_index/insert', $data, $postID, $postType );

		if ( isset( $dataFiltered['meta'] ) ) {
			$dataFiltered['meta'] = maybe_serialize( $dataFiltered['meta'] );
		}

		$indexRoleSuffix = $indexRole === 'main' ? '' : '_tmp';

		if ( ! empty( $dataFiltered ) ) {
			$rows = WPDB::get_instance()->insert(
				$wpdb->dgwt_wcas_index . $indexRoleSuffix,
				$dataFiltered,
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

			if ( is_numeric( $rows ) ) {
				$success = true;
			}
		}

		do_action( 'dgwt/wcas/readable_index/after_insert', $dataFiltered, $postID, $postType, $success, $data, $indexRole );

		return $success;
	}

	/**
	 * Get product data to save
	 *
	 * @return array
	 */
	private function getProductData() {
		$data = array();

		if ( is_object( $this->product ) && $this->product->isValid() ) {

			$wordsLimit = - 1;
			if ( DGWT_WCAS()->settings->getOption( 'show_details_box' ) === 'on' ) {
				$wordsLimit = 15;
			}

			$data = array(
				'post_id'        => $this->product->getID(),
				'created_date'   => get_post_field( 'post_date', $this->product->getID(), 'raw' ),
				'name'           => $this->product->getName(),
				'description'    => $this->product->getDescription( 'suggestions', $wordsLimit ),
				'sku'            => $this->product->getSKU(),
				'sku_variations' => '',
				'attributes'     => '',
				'meta'           => array(),
				'image'          => $this->product->getThumbnailSrc(),
				'url'            => $this->product->getPermalink(),
				'html_price'     => $this->product->getPriceHTML(),
				'price'          => $this->product->getPrice(),
				'average_rating' => $this->product->getAverageRating(),
				'review_count'   => $this->product->getReviewCount(),
				'total_sales'    => $this->product->getTotalSales(),
				'lang'           => $this->product->getLanguage()
			);

			if ( apply_filters( 'dgwt/wcas/tnt/indexer/readable/process_sku_variations', true ) ) {
				$data['sku_variations'] = implode( '|', $this->product->getVariationsSKUs() );
			}

			if ( apply_filters( 'dgwt/wcas/tnt/indexer/readable/process_attributes', true ) ) {
				$data['attributes'] = implode( '|', $this->product->getAttributes( true ) );
			}

			$data = apply_filters( 'dgwt/wcas/tnt/indexer/readable/product/data', $data, $this->product->getID(), $this->product );
		}

		return $data;
	}


	/**
	 * Get post or pages data to save
	 *
	 * @return array
	 */
	private function getNonProductData() {
		$data = array();

		if ( is_object( $this->post ) && $this->post->isValid() ) {
			$data = array(
				'post_id'        => $this->post->getID(),
				'created_date'   => get_post_field( 'post_date', $this->post->getID(), 'raw' ),
				'name'           => $this->post->getTitle(),
				'description'    => $this->post->getDescription( 'short' ),
				'sku'            => '',
				'sku_variations' => '',
				'attributes'     => '',
				'meta'           => array(),
				'image'          => '',
				'url'            => $this->post->getPermalink(),
				'html_price'     => '',
				'price'          => '',
				'average_rating' => '',
				'review_count'   => '',
				'total_sales'    => '',
				'lang'           => $this->post->getLanguage()
			);

			$data = apply_filters( 'dgwt/wcas/tnt/indexer/readable/' . $this->post->getPostType() . '/data', $data, $this->post->getID(), $this->post );
		}

		return $data;
	}

	/**
	 * Enqueue variations for indexing in separate background process or index them instantly
	 *
	 * @param bool $directVariations Index variations directly
	 *
	 * @throws WPDBException
	 */
	private function enqueueOrIndexVariations( $directVariations = false, $indexRole = '' ) {
		// We could just fetch the ID, but this method also checks the visibility of variants
		$variations = $this->product->getAvailableVariations();
		if ( empty( $variations ) ) {
			return;
		}

		$variationsSetCount = apply_filters( 'dgwt/wcas/indexer/variations-set-items-count', Builder::VARIATIONS_SET_ITEMS_COUNT );
		$variationsSetCount = apply_filters( 'dgwt/wcas/indexer/variations_set_items_count', $variationsSetCount );

		if ( $directVariations ) {
			$variationsSet = wp_list_pluck( $variations, 'variation_id' );
			$indexerVar = new IndexerVar;
			$indexerVar->setIndexRole( $indexRole );
			foreach ( $variationsSet as $variationID ) {
				$indexerVar->maybeIndex( $variationID );
			}
		} elseif ( Config::isIndexerMode( 'direct' ) ) {
			$variationsSet = wp_list_pluck( $variations, 'variation_id' );
			DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->task( $variationsSet );
		} else {
			$i = 0;
			foreach ( $variations as $row ) {
				$variationsSet[] = $row['variation_id'];

				if ( count( $variationsSet ) === $variationsSetCount || $i + 1 === count( $variations ) ) {
					DGWT_WCAS()->tntsearchMySql->asynchBuildIndexV->push_to_queue( $variationsSet );
					$variationsSet = array();
				}

				$i ++;
			}

			$totalVariations = absint( Builder::getInfo( 'total_variations_for_indexing', Config::getIndexRole() ) );
			Builder::addInfo( 'total_variations_for_indexing', $totalVariations + count( $variations ) );
		}
	}

	/**
	 * Update product
	 *
	 * @param int $postID Post ID
	 * @param boolean $directVariations Index variations directly
	 *
	 * @return void
	 */
	public function update( $postID, $directVariations = false ) {
		try {
			$this->delete( $postID );
			// We always update the product in the "main" index.
			$this->insert( $postID, $directVariations, 'main' );
		} catch ( \Error $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Readable index] ' );
		} catch ( \Exception $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Readable index] ' );
		}
	}

	/**
	 * Get data of an indexed product
	 *
	 * @param int $postID Post ID
	 *
	 * @return array
	 */
	public function getSingle( $postID ) {
		global $wpdb;
		$data = array();

		$postID = absint( $postID );

		$sql = $wpdb->prepare( "
                SELECT *
                FROM $wpdb->dgwt_wcas_index
                WHERE post_id = %d
                ",
			$postID
		);

		$r = $wpdb->get_results( $sql );
		if ( ! empty( $r ) && is_array( $r ) && ! empty( $r[0] ) && ! empty( $r[0]->post_id ) ) {
			$data = $r[0];
		}

		return $data;
	}

	/**
	 * Remove record from the index
	 *
	 * @param int postID
	 *
	 * @return bool true on success
	 * @throws WPDBException
	 */
	public function delete( $postID ) {
		global $wpdb;

		$success = WPDB::get_instance()->delete(
			$wpdb->dgwt_wcas_index,
			array( 'post_id' => $postID ),
			array( '%d' )
		);

		// Variables if exist
		if ( Helpers::isTableExists( $wpdb->dgwt_wcas_var_index ) ) {
			$varDelete = WPDB::get_instance()->delete(
				$wpdb->dgwt_wcas_var_index,
				array( 'product_id' => $postID ),
				array( '%d' )
			);
			$success   = $success && $varDelete;
		}

		return (bool) $success;
	}

	/**
	 * Wipe index
	 *
	 * @return bool
	 */
	public function wipe( $indexRoleSuffix = '' ) {
		Database::remove( $indexRoleSuffix );
		Builder::log( '[Readable index] Cleared' );

		return true;
	}
}
