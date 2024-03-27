<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\WooCommerceMemberships;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with WooCommerce Memberships
 *
 * Plugin URL: https://www.woocommerce.com/products/woocommerce-memberships/
 * Author: SkyVerge
 */
class WooCommerceMemberships {
	public function init() {
		if ( ! dgoraAsfwFs()->is_premium() ) {
			return;
		}
		if ( ! class_exists( 'WC_Memberships' ) ) {
			return;
		}
		if ( version_compare( \WC_Memberships::VERSION, '1.22' ) < 0 ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'runIntegration' ) );

		add_action( 'woocommerce_update_product', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'edited_product_cat', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'save_post_wc_membership_plan', array( $this, 'clearProductIDsTransients' ) );

		add_action( 'wc_memberships_user_membership_created', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'wc_memberships_user_membership_saved', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'wc_memberships_user_membership_deleted', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'wc_memberships_user_membership_status_changed', array( $this, 'clearProductIDsTransients' ) );
		add_action( 'wc_memberships_member_user_role_updated', array( $this, 'clearProductIDsTransients' ) );

		// Save plugin settings
		add_action( 'woocommerce_settings_save_memberships', array( $this, 'clearProductIDsTransients' ) );

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Run integration
	 *
	 * Enable dynamic prizes in search results and store in transient
	 * and session IDs of visible products for current user.
	 */
	public function runIntegration() {
		// Enable dynamic prizes
		if ( wc_memberships()->get_member_discounts_instance()->applying_discounts() ) {
			add_filter( 'dgwt/wcas/tnt/dynamic_prices', '__return_true' );
		}

		// Prepare visible product IDs for filtering search results if option WooCommerce >> Settings >> Memberships
		// >> General >> Content restriction mode >> is set to "Hide completely"
		if ( wc_memberships()->get_restrictions_instance()->is_restriction_mode( 'hide' ) ) {
			$transientName = 'dgwt_wcas_woocommerce_memberships_pids_' . get_current_user_id();
			$productIDs    = get_transient( $transientName );

			if ( $productIDs === false ) {
				$query = new \WP_Query( array(
					'posts_per_page' => - 1,
					'post_type'      => 'product',
					'fields'         => 'ids',
				) );

				$productIDs = $query->posts;
				set_transient( $transientName, $productIDs, 6 * HOUR_IN_SECONDS );
			}

			if ( ! session_id() ) {
				session_start();
			}
			$_SESSION['dgwt-wcas-woocommerce-memberships-visible-products'] = $productIDs;
		}
	}

	/**
	 * Clear transients
	 */
	public function clearProductIDsTransients() {
		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_dgwt_wcas_woocommerce_memberships_pids_' );
		$sql    = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE %s";
		$keys   = $wpdb->get_results( $wpdb->prepare( $sql, $prefix . '%' ), ARRAY_A );
		if ( ! is_wp_error( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( ltrim( $key['option_name'], '_transient_' ) );
			}
		}
	}

	/**
	 * Get info about renamed plugin folder
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public function getFolderRenameInfo( $plugins ) {
		$filters = new Filters();

		$result = Helpers::getFolderRenameInfo__premium_only( 'WooCommerce Memberships', $filters->plugin_names );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
