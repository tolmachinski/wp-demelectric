<?php

namespace DgoraWcas\Integrations\Marketplace;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Marketplace
 * @package DgoraWcas\Integrations\Marketplace
 *
 * @dgwt_wcas_premium_only
 *
 * Support for plugins:
 * 1. WC Marketplace 3.5.4 by WC Marketplace (https://wc-marketplace.com)
 */
class Marketplace {

	/**
	 * Marketplace plugin metadata
	 *
	 * @var array
	 */
	private $pluginInfo = array();

	/**
	 * Marketplace plugin slug
	 *
	 * @var string
	 */
	private $pluginSlug = '';

	/**
	 * Marketplace settings key
	 *
	 * @var string
	 */
	private $settingsSectionKey = 'dgwt_wcas_marketplace';

	public function __construct() {
	}

	public function init() {
		$this->initIntegration();
		$this->addSettings();
	}

	/**
	 * Init current marketplace integration
	 *
	 * @return void
	 */
	private function initIntegration() {
		foreach ( $this->getMarketplacePlugins() as $pluginInfo ) {
			if ( is_plugin_active( $pluginInfo['path'] ) ) {

				$file = WP_PLUGIN_DIR . '/' . $pluginInfo['path'];

				if ( file_exists( $file ) ) {
					$this->pluginInfo = get_plugin_data( $file );
					$this->pluginSlug = $pluginInfo['slug'];

					$this->loadClass( $pluginInfo['loadingClass'] );
				}

				break;
			}
		}
	}

	/**
	 * Init integration class
	 *
	 * @return void
	 */
	private function loadClass( $className ) {
		$class = '\\DgoraWcas\\Integrations\\Marketplace\\' . $className;

		if ( class_exists( $class ) ) {
			$integration = new $class;
			$integration->init();
		}
	}


	/**
	 * Get all supported brands plugins files
	 *
	 * @return array
	 */
	public function getMarketplacePlugins() {

		$plugins = array();

		// WC Marketplace (https://wc-marketplace.com)
		$plugins[] = array(
			'slug'         => 'dc-woocommerce-multi-vendor',
			'path'         => 'dc-woocommerce-multi-vendor/dc_product_vendor.php',
			'loadingClass' => 'WCMarketplace',
		);

		return $plugins;
	}

	/**
	 * Check if some brands plugin is enabled
	 *
	 * @return bool
	 */
	public function isMarketplaceEnabled() {
		return ! empty( $this->pluginInfo );
	}


	/**
	 * Get the name of the plugin vendor
	 *
	 * @return string
	 */
	public function getPluginName() {
		return ! empty( $this->pluginInfo['Name'] ) ? sanitize_text_field( $this->pluginInfo['Name'] ) : '';
	}

	/**
	 * Get the name of the plugin vendor
	 *
	 * @return string
	 */
	public function getPluginVersion() {
		return ! empty( $this->pluginInfo['Version'] ) ? sanitize_text_field( $this->pluginInfo['Version'] ) : '';
	}

	/**
	 * Get plugin logo
	 *
	 * @return string
	 */
	public function getLogo() {
		if ( $this->pluginSlug === 'dc-woocommerce-multi-vendor' ) {
			// Since version 4.0, plugin uses gif format for logo.
			return 'https://ps.w.org/' . $this->pluginSlug . '/assets/icon-128x128.gif';
		}

		return 'https://ps.w.org/' . $this->pluginSlug . '/assets/icon-128x128.png';
	}


	/**
	 * Register settings
	 *
	 * @return void
	 */
	private function addSettings() {
		if ( $this->isMarketplaceEnabled() ) {

			add_filter( 'dgwt/wcas/settings/sections', function ( $sections ) {
				$sections[27] = array(
					'id'    => $this->settingsSectionKey,
					'title' => __( 'Marketplace', 'ajax-search-for-woocommerce' )
				);

				return $sections;
			} );

			add_filter( 'dgwt/wcas/settings', function ( $settings ) {

				$settings[ $this->settingsSectionKey ][10] = array(
					'name'  => 'marketplace_main_head',
					'label' => __( 'Marketplace third-party integration', 'ajax-search-for-woocommerce' ),
					'type'  => 'head',
					'class' => 'dgwt-wcas-sgs-header'
				);

				$desc = sprintf( '<h2>' . __( 'You are using %s plugin version %s', 'ajax-search-for-woocommerce' ) . '<h2>', $this->getPluginName(), $this->getPluginVersion() );
				$desc .= '<p>' . __( 'We support this plugin.', 'ajax-search-for-woocommerce' ) . '</p>';

				$settings[ $this->settingsSectionKey ][20] = array(
					'name'  => 'marketplace_intro_head',
					'label' => '',
					'type'  => 'desc',
					'desc'  => $desc,
					'class' => 'dgwt-wcas-sgs-market-label',
				);

				$img = $this->getLogo();
				if ( ! empty( $img ) ) {
					$settings[ $this->settingsSectionKey ][20]['label'] = '<img src="' . $img . '">';
				}

				$settings[ $this->settingsSectionKey ][30] = array(
					'name'  => 'marketplace_settings_head',
					'label' => __( 'Settings', 'ajax-search-for-woocommerce' ),
					'type'  => 'head',
					'class' => 'dgwt-wcas-sgs-header'
				);

				$settings[ $this->settingsSectionKey ][40] = array(
					'name'  => 'marketplace_enable_search',
					'label' => __( 'Search in vendors', 'ajax-search-for-woocommerce' ),
					'type'  => 'checkbox',
					'default' => 'off',
				);

				$settings[ $this->settingsSectionKey ][50] = array(
					'name'  => 'marketplace_show_vendors_in_products',
					'label' => __( 'Show vendors next to products', 'ajax-search-for-woocommerce' ),
					'type'  => 'checkbox',
					'default' => 'off',
				);

				return $settings;
			} );


		}
	}

	/**
	 * Check if search in vendors is enabled
	 *
	 * @return bool
	 */
	public function canSearchInVendors() {
		$canSearch = false;
		if ( $this->isMarketplaceEnabled()
		     && DGWT_WCAS()->settings->getOption( 'marketplace_enable_search', 'off' ) === 'on' ) {
			$canSearch = true;
		}

		return $canSearch;
	}

	/**
	 * Check if can show vendors name in products's suggestion
	 *
	 * @return bool
	 */
	public function showProductVendor() {
		$show = false;
		if ( $this->canSearchInVendors()
		     && DGWT_WCAS()->settings->getOption( 'marketplace_show_vendors_in_products', 'off' ) === 'on' ) {
			$show = true;
		}

		return $show;
	}

}
