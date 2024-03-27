<?php
/**
 * @dgwt_wcas_premium_only
 */
namespace DgoraWcas\Integrations\Plugins\WooCommerceProductsVisibility;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with WooCommerce Products Visibility
 *
 * Plugin URL: https://themeforest.net/user/codemine
 * Author: codemine
 */
class WooCommerceProductsVisibility {
	public function init() {
		if ( ! dgoraAsfwFs()->is_premium() ) {
			return;
		}

		/**
		 * This plugin is hooked on plugins_loaded action with priority 100000, so we need
		 * wait for it, and try to load this integration in next hook.
		 */
		add_action( 'sanitize_comment_cookies', array( $this, 'late_init' ) );
	}

	public function late_init() {
		if ( class_exists( 'WCPV_BACKEND' ) ) {
			add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
		}

		if ( ! class_exists( 'WCPV_FRONTEND' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'store_in_session_included_products' ), 20 );
	}

	/**
	 * Store visible product ids in session
	 */
	public function store_in_session_included_products() {
		$wcpv_frontend = \WCPV_FRONTEND::get_instance();
		if ( ! empty( $wcpv_frontend->include_products ) ) {
			if ( ! session_id() ) {
				session_start();
			}
			$_SESSION['dgwt-wcas-wcpv-visible-products'] = $wcpv_frontend->include_products;
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

		$result = Helpers::getFolderRenameInfo__premium_only( 'WooCommerce Products Visibility', $filters->plugin_names );

		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
