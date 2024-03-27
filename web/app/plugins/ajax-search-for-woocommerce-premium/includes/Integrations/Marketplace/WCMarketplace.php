<?php

namespace DgoraWcas\Integrations\Marketplace;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCMarketplace
 * @package DgoraWcas\Integrations\Marketplace
 *
 * @dgwt_wcas_premium_only
 *
 * Integration for plugin WC Marketplace 3.5.4 by WC Marketplace (https://wc-marketplace.com)
 * Since version 4.0 plugin is rebranded to MultiVendorX (https://multivendorx.com/)
 */
class WCMarketplace {
	public function init() {
		if ( ! self::isWcMarketplaceActive() && ! self::isMultiVendorXActive() ) {
			return;
		}

		if ( DGWT_WCAS()->settings->getOption( 'marketplace_enable_search', 'off' ) === 'on' ) {
			add_filter( 'dgwt/wcas/search/vendors', '__return_true' );
		}

		add_filter( 'dgwt/wcas/vendors/list', function ( $vendors ) {
			return $this->getVendors();
		} );
	}

	public static function isWcMarketplaceActive() {
		if ( ! class_exists( 'WCMp' ) ) {
			return false;
		}
		if ( version_compare( WCMp_PLUGIN_VERSION, '3.5.5' ) < 0 ) {
			return false;
		}

		return true;
	}

	public static function isMultiVendorXActive() {
		return class_exists( 'MVX' );
	}

	/**
	 * Get vendor's formatted data
	 *
	 * @param int $imageID
	 *
	 * @return string
	 */
	public static function getVendorData( $vendorID ) {
		if ( is_numeric( $vendorID ) ) {
			$class  = self::isMultiVendorXActive() ? '\MVX_REST_API_Vendors_Controller' : '\WCMp_REST_API_Vendors_Controller';
			$vendor = self::makeOfflineApiRequest( $class, 'get_item', array( 'id' => $vendorID ) );
		}

		if ( is_array( $vendorID ) ) {
			$vendor = $vendorID;
		}

		$fields = array(
			'vendor_id'        => absint( $vendor['id'] ),
			'shop_name'        => sanitize_text_field( $vendor['shop']['title'] ),
			'shop_city'        => sanitize_text_field( $vendor['address']['city'] ),
			'shop_description' => Helpers::makeShortDescription( $vendor['shop']['description'], 20 ),
			'shop_url'         => esc_url( $vendor['shop']['url'] ),
			'shop_image'       => esc_url( self::getVendorLogo( $vendor['shop']['image'] ) ),
		);

		return (object) $fields;
	}

	/**
	 * Get formatted list of vendors
	 *
	 * @return array
	 */
	public function getVendors() {
		$vendors = array();
		$class   = self::isMultiVendorXActive() ? '\MVX_REST_API_Vendors_Controller' : '\WCMp_REST_API_Vendors_Controller';
		$rawData = self::makeOfflineApiRequest( $class, 'get_items' );

		foreach ( $rawData as $vendor ) {
			$vendors[] = self::getVendorData( $vendor );
		}

		return $vendors;
	}

	/**
	 * Get shop logo
	 *
	 * @param int $imageID
	 *
	 * @return string
	 */
	public static function getVendorLogo( $imageID ) {
		$src   = '';
		$image = wp_get_attachment_image_src( $imageID, DGWT_WCAS()->setup->getThumbnailSize() );
		if ( is_array( $image ) && count( $image ) ) {
			$src = $image[0];
		}

		if ( self::isMultiVendorXActive() ) {
			$src = apply_filters( 'mvx_vendor_get_image_src', $src );
		} else {
			$src = apply_filters( 'wcmp_vendor_get_image_src', $src );
		}

		return $src;
	}

	/**
	 * Make API request directly from PHP instead of using HTTP
	 *
	 * @param string $class
	 * @param string $method
	 * @param array $params
	 *
	 * @return array
	 */
	public static function makeOfflineApiRequest( $class, $method, $params = array() ) {

		$data = array();

		if ( self::isMultiVendorXActive() ) {
			$apiFile = WP_PLUGIN_DIR . '/dc-woocommerce-multi-vendor/api/class-mvx-rest-vendors-controller.php';
		} else {
			$apiFile = WP_PLUGIN_DIR . '/dc-woocommerce-multi-vendor/api/class-wcmp-rest-vendors-controller.php';
		}

		if ( file_exists( $apiFile ) ) {
			require_once $apiFile;

			$api     = new $class();
			$request = new \WP_REST_Request();
			$request->set_query_params( $params );

			$response = $api->$method( $request );

			if ( ! empty( $response ) && is_object( $response ) && is_a( $response, 'WP_REST_Response' ) && ! empty( $response->data ) ) {
				$data = $response->data;
			}
		}

		return $data;
	}

	/**
	 * Get vendor data directly from the DB
	 *
	 * @param $productID
	 *
	 * @return array
	 */
	public static function getVendorDataDirectly( $productID ) {
		global $wpdb;

		$data = array(
			'shop_name'  => '',
			'vendor_url' => ''
		);

		if ( empty( absint( $productID ) ) || ! is_numeric( $productID ) ) {
			return $data;
		}

		$sql = $wpdb->prepare( "SELECT GROUP_CONCAT( t.name SEPARATOR ', ')
                             FROM $wpdb->terms AS t
                             INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                             INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                             WHERE tt.taxonomy = %s
                             AND tr.object_id = %d", 'dc_vendor_shop', absint( $productID ) );

		$vendorName = $wpdb->get_var( $sql );

		if ( ! empty( $vendorName ) ) {
			$data['shop_name'] = $vendorName;
		}

		return $data;
	}
}
