<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Helpers;
use DgoraWcas\Integrations\Marketplace\WCMarketplace;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxQuery extends MainQuery {

	/**
	 * Total autocomplete limit
	 * int
	 */
	private $totalLimit;

	/**
	 * Autocomplete groups
	 * array
	 */
	private $groups = array();

	/**
	 * Flexible lmits
	 * bool
	 */
	private $flexibleLimits = true;

	/**
	 * Show heading in autocomplete
	 * bool
	 */
	private $showHeadings = false;

	/**
	 * Final Sugestions
	 * array
	 */
	private $suggestions = array();

	/**
	 * AjaxQuery constructor.
	 *
	 * @param bool $debug
	 */
	public function __construct( $debug = false ) {

		parent::__construct( $debug );

		// Add "No results" suggestion if all results have been removed in earlier filters.
		add_filter( 'dgwt/wcas/search_results/output', array(
			'DgoraWcas\Helpers',
			'noResultsSuggestion'
		), PHP_INT_MAX - 10 );
	}

	/**
	 * Send empty response in correct JSON format.
	 * Equivalent "No Results"
	 *
	 * @param string $engine
	 *
	 * @return void
	 */
	public static function sendEmptyResponse( $engine = 'pro' ) {
		$output  = array();
		$results = array();

		$results[] = array(
			'value' => '',
			'type'  => 'no-results'
		);

		$output['suggestions'] = $results;

		$output['total'] = 0;

		if ( $engine === 'pro' ) {
			$output['tntTime'] = '0.0000 s';
		}
		$output['time'] = number_format( microtime( true ) - DGWT_SEARCH_START, 4, '.', '' );

		$output['engine'] = $engine;
		$output['v']      = Helpers::getPluginVersion__premium_only();

		echo json_encode( apply_filters( 'dgwt/wcas/search_results/output', $output ) );
		exit();
	}

	/**
	 * Send search results as a JSON response
	 *
	 * @param bool $exit
	 *
	 * @return void
	 */
	public function sendResults( $exit = true ) {
		$output               = array();
		$this->groups         = $this->searchResultsGroups();
		$this->flexibleLimits = apply_filters( 'dgwt/wcas/flexible_limits', true );
		$this->showHeadings   = $this->getOption( 'show_grouped_results', 'string', 'off' ) === 'on';
		$totalProducts        = 0;

		if ( $this->flexibleLimits ) {
			$totalLimit       = $this->getOption( 'suggestions_limit', 'int', 7 );
			$this->totalLimit = $totalLimit === - 1 ? $this->calcFreeSlots() : $totalLimit;
		}

		// Taxonomies
		foreach ( $this->foundTax as $suggestion ) {

			$key = ! empty( $suggestion['taxonomy'] ) ? 'tax_' . $suggestion['taxonomy'] : '';

			if ( array_key_exists( $key, $this->groups ) && empty( $this->groups[ $key ]['full'] ) ) {
				$limit = $this->flexibleLimits ? $this->totalLimit : $this->groups[ $key ]['limit'];

				$this->groups[ $key ]['results'][] = $suggestion;

				if ( count( $this->groups[ $key ]['results'] ) === $limit ) {
					$this->groups[ $key ]['full'] = true;
				}
			}

		}

		// Vendors
		foreach ( $this->foundVendors as $suggestion ) {

			$key = 'vendor';

			if ( empty( $this->groups[ $key ]['full'] ) ) {
				$limit = $this->flexibleLimits ? $this->totalLimit : $this->groups[ $key ]['limit'];

				$this->groups[ $key ]['results'][] = $suggestion;

				if ( count( $this->groups[ $key ]['results'] ) === $limit ) {
					$this->groups[ $key ]['full'] = true;
				}
			}

		}

		// Posts and pages
		foreach ( $this->foundPosts as $postType => $items ) {

			foreach ( $items as $suggestion ) {

				if ( array_key_exists( $postType, $this->groups ) && empty( $this->groups[ $postType ]['full'] ) ) {
					$limit = $this->flexibleLimits ? $this->totalLimit : $this->groups[ $postType ]['limit'];

					if ( isset( $suggestion['meta'] ) ) {
						unset( $suggestion['meta'] );
					}

					$this->groups[ $postType ]['results'][] = $suggestion;

					if ( count( $this->groups[ $postType ]['results'] ) - 1 === $limit ) {
						$this->groups[ $postType ]['full'] = true;
					}
				}
			}
		}

		// Products
		if ( apply_filters( 'dgwt/wcas/search_in_products', true ) ) {
			$produts       = $this->getProducts();
			$productsSlots = $this->flexibleLimits ? $this->totalLimit : $this->groups['product']['limit'];

			$ids = array();

			foreach ( $produts as $suggestion ) {

				$p = array(
					'post_id'    => $suggestion->post_id,
					'value'      => html_entity_decode( $suggestion->name ),
					'url'        => $suggestion->url,
					'thumb_html' => '<img src="' . $suggestion->image . '">',
					'price'      => $suggestion->html_price,
					'desc'       => $suggestion->description,
					'sku'        => $suggestion->sku,
					'on_sale'    => false,
					'featured'   => false,
					'type'       => 'product'
				);

				if ( $this->ShowVendorInProduct() ) {
					$vendor          = $this->getProductVendorData( $suggestion->post_id );
					$p['vendor']     = $vendor['shop_name'];
					$p['vendor_url'] = $vendor['vendor_url'];
				}

				$p = apply_filters( 'dgwt/wcas/tnt/search_results/suggestion/product', $p, $suggestion );

				$this->groups['product']['results'][] = $p;

				$ids[] = $suggestion->post_id;

				$productsSlots --;
				if ( $productsSlots === 0 ) {
					break;
				}
			}

			// SKU exact match
			if ( $this->getOption( 'search_in_product_sku', 'string', 'off' ) === 'on' ) {
				$varQuery = new ProductVariationQuery( $this->getPhrase(), $ids, $this->getLang() );

				if ( $varQuery->hasResults() ) {
					array_unshift( $this->groups['product']['results'], $varQuery->getSuggestionBody() );
				}
			}

			$totalProducts = count( $produts );
		}


		$this->orderGroups();

		if ( $this->flexibleLimits ) {
			$this->applyFlexibleLimits();
		}

		$this->convertGroupsToSuggestions();

		$this->maybeApplyMoreProductsLink( $totalProducts );

		$output['suggestions'] = $this->suggestions;
		$output['total']       = $totalProducts;

		$output['tntTime'] = number_format( $this->tntTime / 1000, 4, '.', '' ) . ' s';
		$output['time']    = number_format( microtime( true ) - DGWT_SEARCH_START, 4, '.', '' ) . ' s';

		$output['engine'] = 'pro';
		$output['v']      = Helpers::getPluginVersion__premium_only();

		$output = apply_filters( 'dgwt/wcas/search_results/output', $output );
		$output = apply_filters( 'dgwt/wcas/tnt/search_results/output', $output );

		echo json_encode( $output );

		if ( $exit ) {
			exit();
		}
	}

	/**
	 * Headline output structure
	 *
	 * @return array
	 */
	private function headlineBody( $headline ) {
		return array(
			'value' => $headline,
			'type'  => 'headline'
		);
	}

	/**
	 * Sort group by order
	 *
	 * @return void
	 */
	private function orderGroups() {
		uasort( $this->groups, array( 'DgoraWcas\Helpers', 'sortAjaxResutlsGroups' ) );
	}

	/**
	 * Add more products link to the suggestion if neccessary
	 *
	 * @param int $totalProducts
	 *
	 * @return void
	 */
	private function maybeApplyMoreProductsLink( $totalProducts ) {
		if ( ! empty( $this->groups['product']['results'] ) && count( $this->groups['product']['results'] ) < $totalProducts ) {
			$this->suggestions[] = array(
				'total' => $totalProducts,
				'type'  => 'more_products'
			);
		}
	}

	/**
	 * Prepare suggestions based on groups
	 *
	 * @return void
	 */
	private function convertGroupsToSuggestions() {
		$this->suggestions = array();

		$totalHeadlines = 0;

		foreach ( $this->groups as $key => $group ) {

			if ( ! empty( $group['results'] ) ) {

				if ( $this->showHeadings ) {
					$this->suggestions[] = $this->headlineBody( $key );
					$totalHeadlines ++;
				}

				foreach ( $group['results'] as $result ) {
					$this->suggestions[] = $result;
				}
			}
		}

		// Remove products headline when there are only product type suggestion
		if ( $totalHeadlines === 1 ) {
			$i     = 0;
			$unset = false;
			foreach ( $this->suggestions as $key => $suggestion ) {
				if (
					! empty( $suggestion['type'] )
					&& $suggestion['type'] === 'headline'
					&& $suggestion['value'] === 'product'
				) {
					unset( $this->suggestions[ $i ] );
					$unset = true;
					break;
				}

				$i ++;
			}

			if ( $unset ) {
				$this->suggestions = array_values( $this->suggestions );
			}
		}

	}

	/**
	 * Apply flexible limits
	 *
	 * @return void
	 */
	private function applyFlexibleLimits() {

		$slots  = $this->totalLimit;
		$total  = 0;
		$groups = 0;

		foreach ( $this->groups as $key => $group ) {
			if ( ! empty( $this->groups[ $key ]['results'] ) ) {
				$total = $total + count( $this->groups[ $key ]['results'] );
				$groups ++;
			}
		}

		$toRemove = $total >= $slots ? $total - $slots : 0;

		if ( $toRemove > 0 ) {
			for ( $i = 0; $i < $toRemove; $i ++ ) {

				$largestGroupCount = 0;
				$largestGroupKey   = 'product';

				foreach ( $this->groups as $key => $group ) {
					if ( ! empty( $this->groups[ $key ]['results'] ) ) {

						$thisGroupTotal = count( $this->groups[ $key ]['results'] );
						if ( $thisGroupTotal > $largestGroupCount ) {
							$largestGroupCount = $thisGroupTotal;
							$largestGroupKey   = $key;
						}
					}
				}


				$last = count( $this->groups[ $largestGroupKey ]['results'] ) - 1;
				if ( isset( $this->groups[ $largestGroupKey ]['results'][ $last ] ) ) {
					unset( $this->groups[ $largestGroupKey ]['results'][ $last ] );
				}

			}
		}

	}

	/**
	 * Calc total free slots
	 *
	 * @return int
	 */
	public function calcFreeSlots() {
		$slots = 0;

		foreach ( $this->groups as $key => $group ) {
			if ( ! empty( $group['limit'] ) ) {
				$slots = $slots + absint( $group['limit'] );
			}
		}

		return $slots;
	}

	/**
	 * Can show vendors next to products
	 *
	 * @return int
	 */
	private function showVendorInProduct() {
		$show = false;

		// WC Marketplace
		if (
			Config::isPluginActive( 'dc-woocommerce-multi-vendor/dc_product_vendor.php' )
			&& is_array( $this->settings )
			&& ! empty( $this->settings['marketplace_show_vendors_in_products'] )
			&& $this->settings['marketplace_show_vendors_in_products'] === 'on'
		) {
			$show = true;
		}

		return $show;
	}

	/**
	 * Get Vendor data
	 *
	 * @param int $productID
	 *
	 * @return array
	 */
	private function getProductVendorData( $productID ) {

		$data = array(
			'shop_name'  => '',
			'vendor_url' => ''
		);

		// WC Marketplace
		if ( Config::isPluginActive( 'dc-woocommerce-multi-vendor/dc_product_vendor.php' ) ) {
			$data = array_intersect_key( WCMarketplace::getVendorDataDirectly( $productID ), $data );

		}

		return $data;
	}

	/**
	 * Order of the search resutls groups
	 *
	 * @return array
	 */
	private function searchResultsGroups() {

		$groups = array(
			'product' => array(
				'limit' => 7,
				'order' => 100
			)
		);

		// Taxonomies
		$taxonomies = $this->taxQuery->getActiveTaxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			switch ( $taxonomy ) {
				case 'product_cat':
					$order = 10;
					break;
				case 'product_tag':
					$order = 20;
					break;
				default:
					$order = 5;
					break;
			}

			$groups[ 'tax_' . $taxonomy ] = array(
				'limit' => 3,
				'order' => $order,
			);
		}

		// Posts
		if ( $this->getOption( 'show_matching_posts', 'string', 'off' ) === 'on' ) {
			$groups['post'] = array(
				'limit' => 5,
				'order' => 40,
			);
		}

		// Pages
		if ( $this->getOption( 'show_matching_pages', 'string', 'off' ) === 'on' ) {
			$groups['page'] = array(
				'limit' => 5,
				'order' => 50,
			);
		}

		// Vendors
		if ( $this->getOption( 'marketplace_enable_search', 'string', 'off' ) === 'on' ) {
			$groups['vendor'] = array(
				'limit' => 5,
				'order' => 15,
			);
		}

		return apply_filters( 'dgwt/wcas/search_groups', $groups );
	}
}
