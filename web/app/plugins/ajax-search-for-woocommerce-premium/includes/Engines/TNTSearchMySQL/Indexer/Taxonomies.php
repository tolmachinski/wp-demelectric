<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support product taxonomies in indexer
 */
class Taxonomies {
	private $taxonomies;

	public function init() {
		add_filter( 'dgwt/wcas/labels', array( $this, 'setTaxonomiesLabels' ), 5 );
		add_filter( 'dgwt/wcas/labels', array( $this, 'fixTaxonomiesLabels' ), PHP_INT_MAX - 5 );
		add_filter( 'dgwt/wcas/taxonomies_with_images', array( $this, 'taxonomiesWithImages' ) );

		add_filter( 'dgwt/wcas/settings/section=search', array( $this, 'addCustomTaxonomiesToSearchSettings' ) );
		add_filter( 'dgwt/wcas/settings/section=autocomplete', array(
			$this,
			'addCustomTaxonomiesToAutocompleteSettings'
		) );

		add_action( 'dgwt/wcas/settings/option_updated', array( $this, 'listenForSettingsChange' ), 10, 1 );
	}

	/**
	 * Get available taxonomies with its details
	 *
	 * @return array
	 */
	public function getTaxonomies() {
		if ( is_array( $this->taxonomies ) ) {
			return $this->taxonomies;
		}

		$this->registerAllTaxonomies();

		return $this->taxonomies;
	}

	/**
	 * Clear internal cache $this->taxonomies
	 *
	 * @return void
	 */
	public function clearCache() {
		$this->taxonomies = null;
	}

	/**
	 * Get a list of registered taxonomy slugs
	 *
	 * @return array
	 */
	public function getTaxonomiesSlugs() {
		$taxonomies = $this->getTaxonomies();

		if ( empty( $taxonomies ) ) {
			return array();
		}

		return wp_list_pluck( $taxonomies, 'taxonomy' );
	}

	/**
	 * Get details of selected taxonomy
	 *
	 * @param string $taxonomy Taxonomy slug
	 */
	public function getTaxonomyDetails( $taxonomy ) {
		$taxonomies = $this->getTaxonomies();

		if ( empty( $taxonomy ) ) {
			return array();
		}

		$index = array_search( $taxonomy, array_column( $taxonomies, 'taxonomy' ) );

		return $index === false ? array() : $taxonomies[ $index ];
	}

	/**
	 * Get active taxonomies
	 *
	 * @param string|array $contexts Search context. Accepts 'search_related_products', 'search_direct', 'image_support' and 'show_images' (or mix of them).
	 */
	public function getActiveTaxonomies( $contexts ) {
		if ( is_string( $contexts ) ) {
			$contexts = array( $contexts );
		}

		$result = array();

		foreach ( $contexts as $context ) {
			$taxonomies = array_filter( $this->getTaxonomies(), function ( $taxonomy ) use ( $context ) {
				return isset( $taxonomy[ $context ] ) && $taxonomy[ $context ];
			} );

			$result = array_merge( $result, wp_list_pluck( $taxonomies, 'taxonomy' ) );
		}

		return array_unique( $result );
	}

	/**
	 * Add taxonomies labels
	 *
	 * @param array $labels Labels used at frontend
	 *
	 * @return array
	 */
	public function setTaxonomiesLabels( $labels ) {
		$taxonomies = $this->getTaxonomies();

		if ( empty( $taxonomies ) ) {
			return $labels;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( isset( $taxonomy['labels']['name'] ) ) {
				$labels[ 'tax_' . $taxonomy['taxonomy'] . '_plu' ] = $taxonomy['labels']['name'];
			}
			if ( isset( $taxonomy['labels']['singular_name'] ) ) {
				$labels[ 'tax_' . $taxonomy['taxonomy'] ] = $taxonomy['labels']['singular_name'];
			}
		}

		return $labels;
	}

	/**
	 * Backward compatibility for labels
	 *
	 * Full taxonomy names for categories and tags. Brand is just another taxonomy. All with prefix 'tax_'.
	 *
	 * @param array $labels Labels used at frontend
	 *
	 * @return array
	 */
	public function fixTaxonomiesLabels( $labels ) {
		// Product category. Old: 'category', 'product_cat_plu'.
		if ( isset( $labels['category'] ) ) {
			$labels['tax_product_cat'] = $labels['category'];
			unset( $labels['category'] );
		}
		if ( isset( $labels['product_cat_plu'] ) ) {
			$labels['tax_product_cat_plu'] = $labels['product_cat_plu'];
			unset( $labels['product_cat_plu'] );
		}

		// Product tag. Old: 'tag', 'product_tag_plu'.
		if ( isset( $labels['tag'] ) ) {
			$labels['tax_product_tag'] = $labels['tag'];
			unset( $labels['tag'] );
		}
		if ( isset( $labels['product_tag_plu'] ) ) {
			$labels['tax_product_tag_plu'] = $labels['product_tag_plu'];
			unset( $labels['product_tag_plu'] );
		}

		// Brand. Old: 'brand', 'brand_plu'.
		if ( isset( $labels['brand'] ) ) {
			$labels[ 'tax_' . DGWT_WCAS()->brands->getBrandTaxonomy() ] = $labels['brand'];
			unset( $labels['brand'] );
		}
		if ( isset( $labels['brand_plu'] ) ) {
			$labels[ 'tax_' . DGWT_WCAS()->brands->getBrandTaxonomy() . '_plu' ] = $labels['brand_plu'];
			unset( $labels['brand_plu'] );
		}

		return $labels;
	}

	/**
	 * Populate list of taxonomies that has image support
	 *
	 * @param array $taxonomies
	 *
	 * @return array
	 */
	public function taxonomiesWithImages( $taxonomies ) {
		return array_merge( $taxonomies, $this->getActiveTaxonomies( 'image_support' ) );
	}

	/**
	 * Add search options for custom taxonomies
	 *
	 * @param array $settingsScope
	 *
	 * @return array
	 */
	public function addCustomTaxonomiesToSearchSettings( $settingsScope ) {

		$basePosition = 280;

		$skippedTaxonomies = array( 'product_cat', 'product_tag' );
		if ( DGWT_WCAS()->brands->hasBrands() ) {
			$skippedTaxonomies[] = DGWT_WCAS()->brands->getBrandTaxonomy();
		}

		foreach ( $this->getTaxonomies() as $taxonomy ) {
			if ( in_array( $taxonomy['taxonomy'], $skippedTaxonomies ) ) {
				continue;
			}

			$settingsScope[ $basePosition ] = array(
				'name'    => 'search_in_product_tax_' . $taxonomy['taxonomy'],
				'label'   => sprintf( __( 'Search in %s', 'ajax-search-for-woocommerce' ), mb_strtolower( $taxonomy['labels']['name'] ) ),
				'class'   => 'dgwt-wcas-premium-only',
				'type'    => 'checkbox',
				'default' => 'off',
			);

			$basePosition += 2;
		}

		if ( in_array( 'product_tag', $this->getActiveTaxonomies( 'image_support' ) ) ) {
			$settingsScope[1350] = array(
				'name'      => 'show_product_tax_product_tag_images',
				'label'     => __( 'show images', 'ajax-search-for-woocommerce' ),
				'type'      => 'checkbox',
				'class'     => 'js-dgwt-wcas-adv-settings dgwt-wcas-premium-only',
				'default'   => 'off',
				'desc'      => __( 'show images', 'ajax-search-for-woocommerce' ),
				'move_dest' => 'show_product_tax_product_tag',
			);
		}

		return $settingsScope;
	}

	/**
	 * Add autocomplete options for custom taxonomies
	 *
	 * @param array $settingsScope
	 *
	 * @return array
	 */
	public function addCustomTaxonomiesToAutocompleteSettings( $settingsScope ) {
		$basePosition = 1350;

		$skippedTaxonomies = array( 'product_cat', 'product_tag' );
		if ( DGWT_WCAS()->brands->hasBrands() ) {
			$skippedTaxonomies[] = DGWT_WCAS()->brands->getBrandTaxonomy();
		}

		foreach ( $this->getTaxonomies() as $taxonomy ) {
			if ( in_array( $taxonomy['taxonomy'], $skippedTaxonomies ) ) {
				continue;
			}

			$settingsScope[ $basePosition ] = array(
				'name'    => 'show_product_tax_' . $taxonomy['taxonomy'],
				'label'   => sprintf( __( 'Show %s', 'ajax-search-for-woocommerce' ), mb_strtolower( $taxonomy['labels']['name'] ) ),
				'class'   => 'dgwt-wcas-premium-only' . ( $taxonomy['image_support'] ? ' js-dgwt-wcas-options-toggle-sibling' : '' ),
				'type'    => 'checkbox',
				'default' => 'off',
			);

			$basePosition += 2;

			if ( $taxonomy['image_support'] ) {
				$settingsScope[ $basePosition ] = array(
					'name'      => 'show_product_tax_' . $taxonomy['taxonomy'] . '_images',
					'label'     => __( 'show images', 'ajax-search-for-woocommerce' ),
					'class'     => 'dgwt-wcas-premium-only',
					'type'      => 'checkbox',
					'default'   => 'off',
					'desc'      => __( 'show images', 'ajax-search-for-woocommerce' ),
					'move_dest' => 'show_product_tax_' . $taxonomy['taxonomy'],
				);

				$basePosition += 2;
			}
		}

		return $settingsScope;
	}

	/**
	 * Register all taxonomies
	 *
	 * @return void
	 */
	private function registerAllTaxonomies() {
		$this->taxonomies = array();

		$this->registerTaxonomy( array(
			'taxonomy'      => 'product_cat',
			'labels'        => array(
				'name'          => __( 'Categories', 'woocommerce' ),
				'singular_name' => __( 'Category', 'woocommerce' ),
			),
			'image_support' => true,
		) );

		$this->registerTaxonomy( array(
			'taxonomy'      => 'product_tag',
			'labels'        => array(
				'name'          => __( 'Tags' ),
				'singular_name' => __( 'Tag' ),
			),
			'image_support' => false,
		) );

		$taxonomies = apply_filters( 'dgwt/wcas/indexer/taxonomies', array() );

		if ( is_array( $taxonomies ) && ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$this->registerTaxonomy( $taxonomy );
			}
		}
	}

	/**
	 * Register taxonomy
	 *
	 * @param string|array $taxonomy
	 */
	private function registerTaxonomy( $taxonomy ) {
		// Prepare default data if taxonomy is passed just as string
		if ( is_string( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
			$taxonomyObj = get_taxonomy( $taxonomy );
			$taxonomy    = array(
				'taxonomy'      => $taxonomy,
				'labels'        => array(
					'name'          => $taxonomyObj->labels->name,
					'singular_name' => $taxonomyObj->labels->singular_name,
				),
				'image_support' => false,
			);
		}

		if ( ! is_array( $taxonomy ) ) {
			return;
		}

		$taxonomyData = array(
			'taxonomy'                => '',
			'labels'                  => array(
				'name'          => '',
				'singular_name' => '',
			),
			'image_support'           => false,
			'search_direct'           => false,
			'search_related_products' => false,
			'show_images'             => false,
		);

		$taxonomy = apply_filters( 'dgwt/wcas/indexer/taxonomies/register-taxonomy', $taxonomy );

		// Taxonomy slug
		if ( empty( $taxonomy['taxonomy'] ) || ! taxonomy_exists( $taxonomy['taxonomy'] ) ) {
			return;
		}
		$taxonomyData['taxonomy'] = $taxonomy['taxonomy'];

		// Name
		if ( isset( $taxonomy['labels']['name'] ) && is_string( $taxonomy['labels']['name'] ) ) {
			$taxonomyData['labels']['name'] = $taxonomy['labels']['name'];
		} else {
			$taxonomyObj                    = get_taxonomy( $taxonomyData['taxonomy'] );
			$taxonomyData['labels']['name'] = $taxonomyObj->labels->name;
		}

		// Singular name
		if ( isset( $taxonomy['labels']['singular_name'] ) && is_string( $taxonomy['labels']['singular_name'] ) ) {
			$taxonomyData['labels']['singular_name'] = $taxonomy['labels']['singular_name'];
		} else {
			$taxonomyObj                             = get_taxonomy( $taxonomyData['taxonomy'] );
			$taxonomyData['labels']['singular_name'] = $taxonomyObj->labels->singular_name;
		}

		// Image support
		if ( isset( $taxonomy['image_support'] ) && is_bool( $taxonomy['image_support'] ) ) {
			$taxonomyData['image_support'] = $taxonomy['image_support'];
		}

		$taxonomyData['search_direct']           = DGWT_WCAS()->settings->getOption( 'show_product_tax_' . $taxonomy['taxonomy'] ) === 'on';
		$taxonomyData['search_related_products'] = DGWT_WCAS()->settings->getOption( 'search_in_product_tax_' . $taxonomy['taxonomy'] ) === 'on';
		$taxonomyData['show_images']             = DGWT_WCAS()->settings->getOption( 'show_product_tax_' . $taxonomy['taxonomy'] . '_images' ) === 'on';

		// Ensure we have proper container for taxonomies
		if ( $this->taxonomies === null ) {
			$this->taxonomies = array();
		}

		// Prevent to register same taxonomy twice
		foreach ( $this->taxonomies as $registeredTaxonomy ) {
			if ( $registeredTaxonomy['taxonomy'] === $taxonomyData['taxonomy'] ) {
				return;
			}
		}

		$this->taxonomies[] = $taxonomyData;
	}

	/**
	 * Clear internal cache after changing values of options related to taxonomies
	 *
	 * @param string $optionKey
	 *
	 * @return void
	 */
	public function listenForSettingsChange( $optionKey ) {
		if ( strpos( $optionKey, 'search_in_product_tax_' ) !== false ) {
			$this->clearCache();
		}
	}
}
