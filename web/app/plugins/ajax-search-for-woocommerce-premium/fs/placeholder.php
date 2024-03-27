<?php

/**
 * @dgwt_wcas_premium_only
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FreemiusPlaceholder {
	function is__premium_only() {
		return true;
	}
}

function dgoraAsfwFs() {
	global $dgoraAsfwFsPlaceholder;

	if ( ! isset( $dgoraAsfwFsPlaceholder ) ) {
		$dgoraAsfwFsPlaceholder = new FreemiusPlaceholder();
	}

	return $dgoraAsfwFsPlaceholder;
}

dgoraAsfwFs();
