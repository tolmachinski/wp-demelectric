<?php

namespace DgoraWcas\Admin;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Settings;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'addMenu' ), 20 );
	}

	/**
	 * Add meun items
	 *
	 * @return void
	 */
	public function addMenu() {

		$menuSuffix = '';
		if ( dgoraAsfwFs()->is__premium_only() ) {
			if ( Builder::getInfo( 'status', Config::getIndexRole() ) === 'error' || Builder::isIndexerWorkingTooLong() ) {
				$menuSuffix = '<span class="dgwt-wcas-menu-warning-icon">!</span>';
				add_action( 'admin_print_styles', function () {
					?>
					<style>
						.dgwt-wcas-menu-warning-icon {
							display: inline-block;
							vertical-align: top;
							box-sizing: border-box;
							margin: 1px 0 -1px 3px;
							padding: 0 5px;
							min-width: 18px;
							height: 18px;
							border-radius: 9px;
							background-color: #d63638;
							color: #fff;
							font-size: 11px;
							line-height: 1.6;
							text-align: center;
							z-index: 26;
						}
					</style>
					<?php
				} );
			}
		}

		if ( dgoraAsfwFs()->is_activation_mode() ) {
			add_action( 'admin_print_styles', function () {
				?>
				<style>
					#adminmenu > .toplevel_page_dgwt_wcas_settings {
						display: none;
					}
				</style>
				<?php
			} );
		}

		add_submenu_page(
			'woocommerce',
			__( 'FiboSearch', 'ajax-search-for-woocommerce' ),
			__( 'FiboSearch', 'ajax-search-for-woocommerce' ) . $menuSuffix,
			'manage_options',
			'dgwt_wcas_settings',
			array( $this, 'settingsPage' )
		);

		if ( ! dgoraAsfwFs()->is_activation_mode() ) {
			add_submenu_page(
				'dgwt_wcas_settings',
				'FiboSearch Debug',
				'FiboSearch [Hidden]',
				'manage_options',
				'dgwt_wcas_debug',
				array( $this, 'debugPage' )
			);
		}
	}

	/**
	 * Settings page
	 *
	 * @return void
	 */
	public function settingsPage() {
		Settings::output();
	}

	/**
	 * Debug page
	 *
	 * @return void
	 */
	public function debugPage() {
		include_once DGWT_WCAS_DIR . 'partials/admin/debug/debug.php';
	}
}
