<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Variation;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Multilingual;
use DgoraWcas\ProductVariation;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Indexer {
	/** @var string */
	private $indexRole;

	/**
	 * @var ProductVariation
	 */
	private $product;

	public function __construct() {
		$this->setIndexRole( Config::getIndexRole() );
	}

	/**
	 * @param string $indexRole
	 */
	public function setIndexRole( string $indexRole ) {
		$this->indexRole = $indexRole;
	}

	/**
	 * Index variation info if necessary
	 *
	 * @param int|\WC_Product_Variation $postID Variation ID or object
	 *
	 * @return boolean|void
	 * @throws WPDBException
	 */
	public function maybeIndex( $postID ) {
		global $wpdb;

		$success = false;

		$this->product = new ProductVariation( $postID );

		if ( ! $this->product->isValid() ) {
			return;
		}

		$lang = $this->product->getLanguage();
		$lang = Multilingual::isLangCode( $lang ) ? $lang : Multilingual::getDefaultLanguage();

		if ( empty( $this->product->getSKU() ) ) {
			return;
		}

		if ( ! $this->product->canIndex__premium_only() ) {
			return;
		}

		// Empty variation SKU? return
		if ( ! empty( $this->product->getParentSKU() ) && $this->product->getParentSKU() === $this->product->getSKU() ) {
			return;
		}

		// Permalink
		$url = $this->product->getPermalink();

		// Support for multilingual
		if ( Multilingual::isMultilingual() ) {
			if ( ! empty( $lang ) && $lang !== Multilingual::getCurrentLanguage() ) {
				Multilingual::switchLanguage( $lang );
			}

			if ( Multilingual::isMultiCurrency() ) {
				Multilingual::setCurrentCurrency( $this->product->getCurrency() );
			}

			$url = Multilingual::getPermalink( $this->product->getParentID(), $this->product->getPermalink(), $lang );
		}

		// Title of variation = name of it's parent
		$title          = (string) $this->product->getWooObject()->get_title();
		$variationAttrs = (string) wc_get_formatted_variation( $this->product->getWooObject(), true, false, false );
		if ( ! empty( $variationAttrs ) ) {
			$title .= ', ' . $variationAttrs;
		}

		$data = array(
			'variation_id' => $this->product->getID(),
			'product_id'   => $this->product->getParentID(),
			'sku'          => (string) $this->product->getSKU(),
			'title'        => apply_filters( 'dgwt/wcas/variation/title', $title, $this->product->getWooObject() ),
			'description'  => (string) $this->product->getDescription(),
			'image'        => $this->prepareImageSrc( $this->product->getWooObject()->get_image_id(), $this->product->getID() ),
			'url'          => apply_filters( 'dgwt/wcas/variation/permalink', $url, $this->product->getWooObject() ),
			'html_price'   => $this->product->getPriceHTML(),
			'lang'         => $lang
		);

		$dataFiltered = apply_filters( 'dgwt/wcas/variation/insert', $data, $this->product->getWooObject() );

		if ( ! empty( $dataFiltered ) ) {
			$rows = WPDB::get_instance()->insert(
				$wpdb->dgwt_wcas_var_index . ( $this->indexRole === 'tmp' ? '_tmp' : '' ),
				$dataFiltered,
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

			if ( is_numeric( $rows ) ) {
				$success = true;
			}
		}

		do_action( 'dgwt/wcas/variation_index/after_insert', $dataFiltered, $this->product->getParentID(), $this->product->getParentSKU(), $lang, $data, $this->indexRole );

		return $success;
	}

	/**
	 * Prepare variation Image SCR
	 *
	 * @param int $imageID
	 * @param int $variationID
	 *
	 * @return string
	 */
	public function prepareImageSrc( $imageID, $variationID ) {

		$src = '';

		if ( ! empty( $imageID ) ) {
			$imageSrc = wp_get_attachment_image_src( $imageID, DGWT_WCAS()->setup->getThumbnailSize() );

			if ( is_array( $imageSrc ) && ! empty( $imageSrc[0] ) ) {
				$src = $imageSrc[0];
			}
		}

		if ( empty( $src ) ) {
			$src = wc_placeholder_img_src();
		}

		return apply_filters( 'dgwt/wcas/variation/thumbnail_src', $src, $this->product->getParentID(), $variationID );
	}

	/**
	 * Wipe index
	 *
	 * @return bool
	 */
	public function wipe( $indexRoleSuffix = '' ) {
		Database::remove( $indexRoleSuffix );
		Builder::log( '[Variations index] Cleared' );

		return true;
	}
}
