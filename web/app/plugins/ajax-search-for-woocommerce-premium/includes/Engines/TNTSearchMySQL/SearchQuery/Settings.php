<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\SearchQuery;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public static function getSettings() {

		$settings = array();

		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			global $wpdb;

			$record = $wpdb->get_var(
				"SELECT option_value
                   FROM $wpdb->options
                   WHERE option_name = 'dgwt_wcas_settings'
                   LIMIT 1"
			);

			$s = @unserialize( $record );
			if ( $record === 'b:0;' || $s !== false ) {
				$settings = $s;
			}
		} else {
			$s = get_option( 'dgwt_wcas_settings' );
			if ( ! empty( $s ) ) {
				$settings = $s;
			}

		}

		return $settings;
	}

}
