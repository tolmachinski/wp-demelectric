<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer\Vendor;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Logger;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDB;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\WPDBException;
use DgoraWcas\Helpers;
use DgoraWcas\Integrations\Marketplace\WCMarketplace;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Indexer {
	/**
	 * Insert vendor ID to the search index
	 *
	 * @param int vendor ID
	 *
	 * @return bool true on success
	 * @throws WPDBException
	 */
	public function insert( $vendorID ) {
		global $wpdb;

		$success = false;
		$vendor  = null;

		if ( ! Helpers::isTableExists( $wpdb->dgwt_wcas_ven_index . Config::getIndexRoleSuffix() ) ) {
			return $success;
		}

		if ( is_numeric( $vendorID ) ) {
			$vendor = WCMarketplace::getVendorData( $vendorID ); // @TODO add more 3rd party plugins or use filter
		}

		if ( is_object( $vendorID ) ) {
			$vendor = $vendorID;
		}

		if ( is_object( $vendor ) && ! empty( $vendor->vendor_id ) ) {
			$data = array(
				'vendor_id'        => $vendor->vendor_id,
				'shop_name'        => $vendor->shop_name,
				'shop_city'        => $vendor->shop_city,
				'shop_description' => $vendor->shop_description,
				'shop_url'         => $vendor->shop_url,
				'shop_image'       => $vendor->shop_image
			);

			$rows = WPDB::get_instance()->insert(
				$wpdb->dgwt_wcas_ven_index . Config::getIndexRoleSuffix(),
				$data,
				array(
					'%d',
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

		return $success;
	}

	/**
	 * Update vendor
	 *
	 * @param $vendorID
	 *
	 * @return bool true on success
	 */
	public function update( $vendorID ) {
		try {
			$this->delete( $vendorID );
			$this->insert( $vendorID );
		} catch ( \Error $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Vendors index] ' );
		} catch ( \Exception $e ) {
			Logger::handleUpdaterThrowableError( $e, '[Vendors index] ' );
		}
	}

	/**
	 * Remove vendor from the index
	 *
	 * @param int vendor ID
	 *
	 * @return bool true on success
	 * @throws WPDBException
	 */
	public function delete( $vendorID ) {
		global $wpdb;
		$success = false;

		if ( ! Helpers::isTableExists( $wpdb->dgwt_wcas_ven_index ) ) {
			return $success;
		}

		WPDB::get_instance()->delete(
			$wpdb->dgwt_wcas_ven_index,
			array( 'vendor_id' => $vendorID ),
			array( '%d' )
		);

		return $success;
	}

	/**
	 * Build whole vendor's index
	 *
	 * @return array
	 */
	public function build() {
		if ( ! defined( 'DGWT_WCAS_VENDOR_INDEX_TASK' ) ) {
			define( 'DGWT_WCAS_VENDOR_INDEX_TASK', true );
		}

		$status = array(
			'success' => 0,
			'error'   => 0
		);

		$vendors = apply_filters( 'dgwt/wcas/vendors/list', array() );

		if ( ! empty( $vendors ) && is_array( $vendors ) ) {
			foreach ( $vendors as $vendor ) {
				try {
					if ( $this->insert( $vendor ) ) {
						$status['success'] ++;
					} else {
						$status['error'] ++;
					}
				} catch ( \Error $e ) {
					Logger::handleThrowableError( $e, '[Vendors index] ' );
				} catch ( \Exception $e ) {
					Logger::handleThrowableError( $e, '[Vendors index] ' );
				}
			}
		}

		return $status;
	}

	/**
	 * Wipe index
	 *
	 * @return bool
	 */
	public function wipe( $indexRoleSuffix = '' ) {
		Database::remove( $indexRoleSuffix );
		Builder::log( '[Vendors index] Cleared' );

		return true;
	}

	/**
	 * Remove DB table
	 *
	 * @return void
	 */
	public static function remove() {
		global $wpdb;

		$wpdb->hide_errors();

		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->dgwt_wcas_ver_index" );
	}
}
