<?php
/**
 * @dgwt_wcas_premium_only
 */

namespace DgoraWcas\Integrations\Plugins\B2BKing;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with B2BKing
 *
 * Plugin URL: https://webwizards.dev/
 * Author: WebWizards
 */
class B2BKing {
	public function init() {
		if ( ! dgoraAsfwFs()->is_premium() ) {
			return;
		}

		if ( ! defined( 'B2BKING_DIR' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'storeInSessionIncludedProducts' ), 20 );

		add_filter( 'dgwt/wcas/troubleshooting/renamed_plugins', array( $this, 'getFolderRenameInfo' ) );
	}

	/**
	 * Store visible product ids in session
	 */
	public function storeInSessionIncludedProducts() {
		if ( intval( get_option( 'b2bking_all_products_visible_all_users_setting', 1 ) ) !== 1 ) {
			if ( get_option( 'b2bking_plugin_status_setting', 'disabled' ) !== 'disabled' ) {
				$visible_ids = get_transient( 'b2bking_user_' . get_current_user_id() . '_ajax_visibility' );

				$newSession = false;
				if ( ! session_id() ) {
					session_start();
					$newSession = true;
				}

				$_SESSION['dgwt-wcas-b2bking-visible-products'] = empty( $visible_ids ) ? array() : $visible_ids;

				if ( $newSession && function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE ) {
					session_write_close();
				}
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

		$result = Helpers::getFolderRenameInfo__premium_only( 'B2BKing', $filters->plugin_names );
		if ( $result ) {
			$plugins[] = $result;
		}

		return $plugins;
	}
}
