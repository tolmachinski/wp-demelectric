<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FailureReports {
	const INDEXER_FAILURE_DATA_OPTION_KEY            = 'dgwt_wcas_indexer_last_failure_data';
	const AUTO_SEND_FAILURE_REPORTS_OPTION_KEY       = 'dgwt_wcas_auto_send_indexer_failure_reports';
	const DISMISS_INDEXER_FAILURE_NOTICES_OPTION_KEY = 'dgwt_wcas_dismiss_indexer_failure_notices';
	const EMAIL_PREVIEW_NONCE                        = 'failure-reports-email-preview';
	const EMAIL_ADDRESS                              = 'bugs@fibosearch.com';

	public function init() {
		add_action( 'wp_ajax_fibosearch_indexer_error_mail_preview', array( $this, 'getEmailPreview' ) );
		add_action( 'dgwt/wcas/indexer/status/error', array( $this, 'handleIndexerFailure' ) );
	}

	/**
	 * @return bool
	 */
	public function getAutoSend() {
		return get_option( self::AUTO_SEND_FAILURE_REPORTS_OPTION_KEY ) === '1';
	}

	/**
	 * @param bool $value
	 *
	 * @return void
	 */
	public function setAutoSend( $value ) {
		if ( $value ) {
			update_option( self::AUTO_SEND_FAILURE_REPORTS_OPTION_KEY, '1', 'no' );
		} else {
			delete_option( self::AUTO_SEND_FAILURE_REPORTS_OPTION_KEY );
		}
	}

	/**
	 * @return bool
	 */
	public function getDismissNotices() {
		return get_option( self::DISMISS_INDEXER_FAILURE_NOTICES_OPTION_KEY ) === '1';
	}

	/**
	 * @param bool $value
	 *
	 * @return void
	 */
	public function setDismissNotices( $value ) {
		if ( $value ) {
			update_option( self::DISMISS_INDEXER_FAILURE_NOTICES_OPTION_KEY, '1', 'no' );
		} else {
			delete_option( self::DISMISS_INDEXER_FAILURE_NOTICES_OPTION_KEY );
		}
	}

	public function getEmailPreview() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( - 1, 403 );
		}

		check_ajax_referer( self::EMAIL_PREVIEW_NONCE );

		echo $this->getEmailMessage();
		die();
	}

	/**
	 * Get indexer failure data
	 *
	 * @return array|bool
	 */
	public function getFailureData() {
		return get_option( self::INDEXER_FAILURE_DATA_OPTION_KEY );
	}

	/**
	 * Handle indexer failure
	 *
	 * @return void
	 */
	public function handleIndexerFailure() {
		if ( defined( 'DGWT_WCAS_DISABLE_INDEXER_FAILURE_REPORTS' ) && DGWT_WCAS_DISABLE_INDEXER_FAILURE_REPORTS ) {
			return;
		}
		if ( $this->getDismissNotices() ) {
			return;
		}

		Logger::copyLastLogs();
		$data = $this->prepareFailureData();
		update_option( self::INDEXER_FAILURE_DATA_OPTION_KEY, $data, 'no' );

		if ( $this->getAutoSend() ) {
			$this->sendReportViaEmail();
			$this->clearLastFailureData();
		}
	}

	/**
	 * Send report via e-mail
	 *
	 * @return bool
	 */
	public function sendReportViaEmail() {
		if ( defined( 'DGWT_WCAS_DISABLE_INDEXER_FAILURE_REPORTS' ) && DGWT_WCAS_DISABLE_INDEXER_FAILURE_REPORTS ) {
			return false;
		}

		$subject  = '[Bug] Index building failed - v' . DGWT_WCAS_VERSION;
		$message  = $this->getEmailMessage();
		$logFiles = Logger::getLogFilenames();

		return (bool) wp_mail( self::EMAIL_ADDRESS, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ), $logFiles );
	}

	/**
	 * @return void
	 */
	public function clearLastFailureData() {
		delete_option( self::INDEXER_FAILURE_DATA_OPTION_KEY );
		Logger::removeLogs( Logger::INDEXER_FAILURE_SOURCE );
	}

	/**
	 * @return string
	 */
	private function getEmailMessage() {
		$data = $this->getFailureData();

		if ( empty( $data ) ) {
			return '';
		}

		ob_start();
		require DGWT_WCAS_DIR . 'partials/emails/failure-report.php';

		return ob_get_clean();
	}

	/**
	 * Get data about last indexer failure
	 *
	 * @return array
	 */
	private function prepareFailureData() {
		list( $lastErrorCode, $lastErrorMessage ) = Logger::getLastEmergencyLog();

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}

		$info = \WP_Debug_Data::debug_data();

		$data = array(
			'last_error_message' => $lastErrorMessage,
			'log_files'          => Logger::getLogFilenames( Logger::INDEXER_FAILURE_SOURCE ),
			'tables'             => array(
				array(
					'header' => 'Plugin',
					'rows'   => array(
						array(
							'label' => 'Version',
							'value' => DGWT_WCAS_VERSION
						),
					)
				),
				array(
					'header' => 'WordPress',
					'rows'   => array(
						array(
							'label' => 'Version',
							'value' => isset( $info['wp-core']['fields']['version']['value'] ) ? $info['wp-core']['fields']['version']['value'] : ''
						),
						array(
							'label' => 'Multisite',
							'value' => isset( $info['wp-core']['fields']['multisite']['debug'] ) ? ( $info['wp-core']['fields']['multisite']['debug'] ? 'yes' : 'no' ) : ''
						),
					)
				),
				array(
					'header' => 'Server',
					'rows'   => array(
						array(
							'label' => 'Web server',
							'value' => isset( $info['wp-server']['fields']['httpd_software']['value'] ) ? $info['wp-server']['fields']['httpd_software']['value'] : ''
						),
						array(
							'label' => 'PHP version',
							'value' => isset( $info['wp-server']['fields']['php_version']['value'] ) ? $info['wp-server']['fields']['php_version']['value'] : ''
						),
						array(
							'label' => 'PHP time limit',
							'value' => isset( $info['wp-server']['fields']['time_limit']['value'] ) ? $info['wp-server']['fields']['time_limit']['value'] : ''
						),
						array(
							'label' => 'PHP memory limit',
							'value' => isset( $info['wp-server']['fields']['memory_limit']['value'] ) ? $info['wp-server']['fields']['memory_limit']['value'] : ''
						),
					)
				),
				array(
					'header' => 'Database',
					'rows'   => array(
						array(
							'label' => 'Server version',
							'value' => isset( $info['wp-database']['fields']['server_version']['value'] ) ? $info['wp-database']['fields']['server_version']['value'] : ''
						),
					)
				),
			),
		);

		return $data;
	}
}
