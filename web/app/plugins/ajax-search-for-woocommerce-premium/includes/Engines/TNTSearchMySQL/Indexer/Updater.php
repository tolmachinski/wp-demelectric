<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Helpers;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Taxonomy\Indexer as IndexerTax;
use DgoraWcas\Product;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {
	/**
	 * Temporary buffor to store already processed IDs
	 * to prevent double actions
	 * @var array
	 */
	private $alreadyProcessed = array();

	/**
	 * Allowed taxonomies
	 *
	 * @var array
	 */
	private $allowedTaxonomies;

	public function init() {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		// Products
		add_action( 'woocommerce_new_product', array( $this, 'onProductUpdate' ), 20, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'onProductUpdate' ), 20, 2 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'onProductUpdate' ), 20, 2 );

		add_action( 'woocommerce_delete_product', array( $this, 'onProductDelete' ), 20 );
		add_action( 'woocommerce_delete_product_variation', array( $this, 'onProductDelete' ), 20 );
		add_action( 'woocommerce_trash_product', array( $this, 'onProductDelete' ), 20 );
		add_action( 'woocommerce_trash_product_variation', array( $this, 'onProductDelete' ), 20 );

		add_action( 'save_post', array( $this, 'onProductUpdate' ), 10000, 2 );
		add_action( 'deleted_post', array( $this, 'onProductDelete' ), 10000 );

		// Posts, pages and sometimes Products
		add_action( 'save_post', array( $this, 'onPostSave' ), 10000 );
		add_action( 'deleted_post', array( $this, 'onPostDelete' ), 10000 );

		// Terms
		add_action( 'edited_term', array( $this, 'onTermSave' ), 10000, 3 );
		add_action( 'created_term', array( $this, 'onTermSave' ), 10000, 3 );

		add_action( 'delete_term', array( $this, 'onTermDelete' ), 10000, 5 );
	}

	/**
	 * Set allowed taxonomies
	 *
	 * @return void
	 */
	public function setAllowedTaxonomies() {
		if ( is_array( $this->allowedTaxonomies ) ) {
			return;
		}

		$this->allowedTaxonomies = DGWT_WCAS()->tntsearchMySql->taxonomies->getActiveTaxonomies( array(
			'search_direct',
			'search_related_products'
		) );

		if ( DGWT_WCAS()->settings->getOption( 'search_in_product_attributes' ) === 'on' ) {
			$attributesTaxonomies = Helpers::getAttributesTaxonomies();
			if ( ! empty( $attributesTaxonomies ) ) {
				$this->allowedTaxonomies = array_merge( $this->allowedTaxonomies, $attributesTaxonomies );
			}
		}
	}

	/**
	 * Update the search index if the product was changed or created
	 *
	 * @param int $productID
	 * @param object $product
	 *
	 * @return void
	 */
	public function onProductUpdate( $productID, $product = null ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		$this->setAllowedTaxonomies();

		$productObj = new Product( $productID );

		if ( ! $productObj->isValid() ) {
			return;
		}

		if ( $this->isAlreadyProcessed( $productObj->getID(), 'update' ) || ! Builder::isIndexValid() ) {
			return;
		}

		// Variation ID? Get parent product
		if ( $productObj->getWooObject()->get_type() === 'variation' ) {
			$productObj = new Product( $productObj->getWooObject()->get_parent_id() );
			if ( ! $productObj->isValid() || $this->isAlreadyProcessed( $productObj->getID(), 'update' ) ) {
				return;
			}
		}

		$canIndex = $productObj->isPublishedAndVisible() && apply_filters( 'dgwt/wcas/indexer/updater/can_index', $productObj->canIndex__premium_only(), $productObj->getID(), $productObj->getWooObject() );

		if ( $canIndex ) {
			$this->doAsyncRequest( 'update', $productObj->getID() );
		} else {
			$this->doAsyncRequest( 'delete', $productObj->getID() );
		}
	}

	/**
	 * Remove a product from the search index
	 *
	 * @param int/object $productID
	 *
	 * @return void
	 */
	public function onProductDelete( $productID ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		$product = new Product( $productID );

		if ( ! $product->isValid() ) {
			return;
		}

		// Variation ID? Get parent product
		if ( $product->getWooObject()->get_type() === 'variation' ) {
			$product = new Product( $product->getWooObject()->get_parent_id() );
			if ( ! $product->isValid() ) {
				return;
			}
		}

		if ( ! $this->isAlreadyProcessed( $product->getID(), 'delete' ) && Builder::isIndexValid() ) {
			$this->doAsyncRequest( 'delete', $product->getID() );
		}
	}

	/**
	 * Update the search index if the post or page was changed
	 *
	 * @param int $postID
	 *
	 * @return void
	 */
	public function onPostSave( $postID = 0 ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		if (
			( defined( 'DOING_AUTOSAVE' )
			  && DOING_AUTOSAVE
			  || wp_is_post_revision( $postID )
			  || wp_is_post_autosave( $postID )
			)
		) {
		} else {
			if (
				! $this->isAlreadyProcessed( $postID, 'update' )
				&& in_array( get_post_type( $postID ), Helpers::getAllowedPostTypes( 'no-products' ) )
				&& Builder::isIndexValid()
			) {
				if ( 'publish' === get_post_status( $postID ) && apply_filters( 'dgwt/wcas/indexer/updater/post/can_index', true, $postID ) ) {
					$this->doAsyncRequest( 'update', $postID );
				} else {
					$this->doAsyncRequest( 'delete', $postID );
				}
			}
		}
	}

	/**
	 * Remove a post or page from the search index
	 *
	 * @param int $postID
	 *
	 * @return void
	 */
	public function onPostDelete( $postID = 0 ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		if (
			! $this->isAlreadyProcessed( $postID, 'delete' )
			&& in_array( get_post_type( $postID ), Helpers::getAllowedPostTypes( 'no-products' ) )
			&& Builder::isIndexValid()
		) {
			$this->doAsyncRequest( 'delete', $postID );
		}
	}

	/**
	 * Remove a term from the search index
	 *
	 * @param int $termID Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param mixed $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 * @param array $object_ids List of term object IDs.
	 *
	 * @return void
	 */
	public function onTermDelete( $termID, $tt_id, $taxonomy, $deleted_term, $objectIDs ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		$this->setAllowedTaxonomies();

		if ( in_array( $taxonomy, $this->allowedTaxonomies ) && Builder::getInfo( 'status' ) === 'completed' ) {

			$indexer = new IndexerTax;
			try {
				$indexer->delete( $termID, $taxonomy );
			} catch ( \Error $e ) {
				Logger::handleUpdaterThrowableError( $e, '[Taxonomy index] ' );
			} catch ( \Exception $e ) {
				Logger::handleUpdaterThrowableError( $e, '[Taxonomy index] ' );
			}

			// Maybe reindex related products
			$this->maybeUpdateProducts( $objectIDs, $taxonomy );
		}
	}

	/**
	 * Update the search index if a term is changed.
	 *
	 * @param int $termID Term ID.
	 * @param int $ttID Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function onTermSave( $termID, $ttID, $taxonomy ) {
		// Disable updater
		if ( apply_filters( 'dgwt/wcas/tnt/disable_updater', false ) ) {
			return;
		}

		$this->setAllowedTaxonomies();

		if ( in_array( $taxonomy, $this->allowedTaxonomies ) && Builder::getInfo( 'status' ) === 'completed' ) {
			$currentLang = '';
			if ( Multilingual::isMultilingual() ) {
				$currentLang = Multilingual::getCurrentLanguage();
			}

			// Check if a given term is associated with any object
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'include'    => array( $termID ),
			) );

			$indexer = new IndexerTax;
			// We always update the product in the "main" index.
			$indexer->setIndexRole( 'main' );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				try {
					$indexer->delete( $termID, $taxonomy );
				} catch ( \Error $e ) {
					Logger::handleUpdaterThrowableError( $e, '[Taxonomy index] ' );
				} catch ( \Exception $e ) {
					Logger::handleUpdaterThrowableError( $e, '[Taxonomy index] ' );
				}
			} else {
				$indexer->update( $termID, $taxonomy );

				// Maybe reindex related products
				$postIDs = get_objects_in_term( $termID, $taxonomy );
				$this->maybeUpdateProducts( $postIDs, $taxonomy );
			}

			if ( ! empty( $currentLang ) ) {
				Multilingual::switchLanguage( $currentLang );
			}
		}

	}

	/**
	 * Update products in index if user can search in related taxonomy
	 *
	 * @param int[]|\WP_Error $postsIDs
	 * @param string $taxonomy
	 */
	private function maybeUpdateProducts( $postsIDs, $taxonomy ) {
		if ( is_wp_error( $postsIDs ) || empty( $postsIDs ) ) {
			return;
		}

		$activeTaxonomies = DGWT_WCAS()->tntsearchMySql->taxonomies->getActiveTaxonomies( 'search_related_products' );
		if (
			in_array( $taxonomy, $activeTaxonomies ) ||
			( in_array( $taxonomy, Helpers::getAttributesTaxonomies() ) && DGWT_WCAS()->settings->getOption( 'search_in_product_attributes' ) === 'on' )
		) {
			// TODO Maybe rebuild index if there are more than 30 products?
			foreach ( $postsIDs as $postID ) {
				$postType = get_post_type( $postID );
				if ( $postType === 'product' ) {
					$this->onProductUpdate( $postID );
				}
			}
		}
	}

	/**
	 * Realize async request
	 *
	 * @param string $action
	 * @param int $postID
	 *
	 * @return void
	 */
	public function doAsyncRequest( $action, $postID ) {
		// Whitelist of actions
		if ( ! in_array( $action, array( 'update', 'delete' ) ) ) {
			return;
		}

		if ( $this->isAlreadyProcessed( $postID, $action ) ) {
			return;
		}

		if ( ! empty( $postID ) && is_numeric( $postID ) ) {

			if ( Utils::getQueue() ) {
				BackgroundProductUpdater::schedule( $action, $postID );
				$this->markAsProcessed( $postID, $action );
			}
		}
	}

	/**
	 * Check if product or post was already processed
	 * to prevent process it twice
	 *
	 * @param $id
	 * @param $action
	 *
	 * @return bool
	 */
	private function isAlreadyProcessed( $id, $action ) {
		$processed = false;

		if (
			! empty( $id ) && is_numeric( $id )
			&& isset( $this->alreadyProcessed[ $action ] )
			&& in_array( $id, $this->alreadyProcessed[ $action ] )
		) {
			$processed = true;
		}

		return $processed;
	}

	/**
	 * Mark product or post ID as processed
	 *
	 * @param $postID
	 * @param $action
	 */
	private function markAsProcessed( $postID, $action ) {
		if ( is_numeric( $postID ) && ! empty( $action ) ) {
			$this->alreadyProcessed[ $action ][] = $postID;
		}
	}
}
