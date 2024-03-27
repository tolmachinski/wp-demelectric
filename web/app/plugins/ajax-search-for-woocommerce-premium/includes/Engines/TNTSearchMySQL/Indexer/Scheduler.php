<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scheduler {
	const HOOK = 'dgwt_wcas_recurring_indexing';
	const LAST_DATA_SETTINGS_KEY = 'dgwt_wcas_schedule_last_data';
	const SINGLE_SCHEDULE_ENABLED_KEY = 'dgwt_wcas_schedule_single';

	/**
	 * @var \WC_Queue_Interface
	 */
	private $queue;
	private $settings;

	public function init() {

		$this->queue = Utils::getQueue();

		add_action( self::HOOK, array( $this, 'recurringTask' ) );

		if ( is_admin() ) {
			if ( ! empty( $this->queue ) ) {
				$this->setOptions();
				$this->registerSchedule();
			}
		}

	}

	/**
	 * Schedule a recurring indexing
	 *
	 * @param bool $forceReschedule Force to reschedule task
	 *
	 * @return  void
	 */
	public function registerSchedule( $forceReschedule = false ) {

		$interval        = $this->getInterval();
		$initHour        = $this->getInitHour();
		$oldInterval     = $this->getLatestInterval();
		$oldInitHour     = $this->getLatestInitHour();
		$scheduleEnabled = $this->isEnabled();
		$isRunning       = $this->isRunning();
		$firstRunTs      = $this->getFirstRunTimestamp();

		// One-time removal of a recurring task
		if ( ! $this->isSingleEnabled() ) {
			$this->queue->cancel_all( self::HOOK );
			update_option( self::SINGLE_SCHEDULE_ENABLED_KEY, true );
		}

		if ( $forceReschedule ) {
			$this->queue->cancel_all( self::HOOK );
		}

		// If scheduler was enabled
		if ( $scheduleEnabled && ! $isRunning ) {

			$this->queue->schedule_single( $firstRunTs, self::HOOK );

			$this->updateData( 'lastInterval', $interval );
			$this->updateData( 'lastInitHour', $initHour );

		}

		// When the interval or init hour was changed
		if ( $scheduleEnabled
		     && (
			     ( is_numeric( $interval ) && $interval > 0 && $interval !== $oldInterval )
			     || ( is_numeric( $initHour ) && $initHour >= 0 && $initHour !== $oldInitHour )
		     )
		) {

			$this->queue->cancel_all( self::HOOK );
			$this->queue->schedule_single( $firstRunTs, self::HOOK );

			$this->updateData( 'lastInterval', $interval );
			$this->updateData( 'lastInitHour', $initHour );
		}

		// When scheduler was disabled
		if ( ! $scheduleEnabled && $isRunning ) {
			$this->queue->cancel_all( self::HOOK );
		}

	}

	/**
	 * Check if single-mode scheduler has been enabled
	 *
	 * @return bool|mixed
	 */
	public function isSingleEnabled() {
		return get_option( self::SINGLE_SCHEDULE_ENABLED_KEY );
	}

	/**
	 * Check if scheduler is enabled
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return DGWT_WCAS()->settings->getOption( 'indexer_schedule' ) === 'on' ? true : false;
	}

	/**
	 * Check if scheduler is running
	 *
	 * @return bool
	 */
	public function isRunning() {
		$running = false;
		if ( ! empty( $this->getNextTask() ) ) {
			$running = true;
		}

		return $running;
	}

	/**
	 * Get the date of next task
	 */
	public function getNextTask() {
		return $this->queue->get_next( self::HOOK );
	}

	/**
	 * Get scheduler interval in seconds
	 *
	 * @return int
	 */
	public function getInterval() {
		$interval = DAY_IN_SECONDS * 7;

		$opt = DGWT_WCAS()->settings->getOption( 'indexer_schedule_interval', 'weekly' );

		switch ( $opt ) {
			case 'daily':
				$interval = DAY_IN_SECONDS;
				break;
			case 'weekly':
				$interval = DAY_IN_SECONDS * 7;
				break;
		}

		return $interval;
	}

	/**
	 * Get latest scheduler interval in seconds
	 *
	 * @return int
	 */
	public function getLatestInterval() {
		$interval = 0;

		if ( ! empty( $this->settings['lastInterval'] ) && is_numeric( $this->settings['lastInterval'] ) && $this->settings['lastInterval'] > 0 ) {
			$interval = (int) $this->settings['lastInterval'];
		}

		return $interval;
	}


	/**
	 * Get latest init hour
	 *
	 * @return int
	 */
	public function getLatestInitHour() {
		$hour = 3;

		if ( ! empty( $this->settings['lastInitHour'] ) && is_numeric( $this->settings['lastInitHour'] ) && $this->settings['lastInitHour'] > 0 ) {
			$hour = (int) $this->settings['lastInitHour'];
		}

		return $hour;
	}

	/**
	 * Update latest scheduler interval
	 *
	 * @param string $key
	 * @param int $value
	 *
	 * @return bool
	 */
	public function updateData( $key, $value ) {
		$updated = false;

		if ( ! empty( $value ) && is_numeric( $value ) && $value > 0 ) {
			$this->settings[ $key ] = (int) $value;
			$updated                = update_option( self::LAST_DATA_SETTINGS_KEY, $this->settings );
		}

		return $updated;
	}

	/**
	 * Get hour to start the task
	 *
	 * @return int
	 */
	public function getInitHour() {
		$initHour = 3;

		$hour = DGWT_WCAS()->settings->getOption( 'indexer_schedule_start_time', 3 );
		if ( $hour !== '' && is_numeric( $hour ) && $hour >= 0 && $hour < 24 ) {
			$initHour = (int) $hour;
		}

		return $initHour;
	}

	/**
	 * Get timestampt for first task run
	 *
	 * @return int
	 */
	public function getFirstRunTimestamp() {
		$hoursOffset = $this->getInitHour() * 60 * 60;
		$today       = new \WC_DateTime( 'today' );
		$now         = new \WC_DateTime();
		$tmpTime     = new \WC_DateTime( 'today', new \DateTimeZone( wc_timezone_string() ) );
		$hoursOffset = $hoursOffset - $tmpTime->getOffset();

		$ts = $today->getTimestamp() + $this->getInterval() + $hoursOffset;
		if ( $ts < $now->getTimestamp() ) {
			$ts = $ts + DAY_IN_SECONDS;
		}

		return $ts;
	}

	/**
	 * Print data for debuging
	 *
	 * @return void
	 */
	public function debug() {

		echo 'Scheduler enabled: ' . var_export( $this->isEnabled(), true ) . '<br />';
		echo 'Is running: ' . var_export( $this->isRunning(), true ) . '<br /><br />';
		echo 'Interval: ' . var_export( $this->getInterval(), true ) . '<br />';
		echo 'Old interval: ' . var_export( $this->getLatestInterval(), true ) . '<br /><br />';
		echo 'Init hour: ' . var_export( $this->getInitHour(), true ) . '<br />';
		echo 'Old init hour: ' . var_export( $this->getLatestInitHour(), true ) . '<br /><br />';
		echo 'First run date: ' . var_export( $this->getFirstRunTimestamp(), true ) . '<br /><br />';
		echo 'Next Task: ' . var_export( $this->getNextTask(), true ) . '<br />';
	}

	/**
	 * Get options
	 */
	public function setOptions() {
		$opt            = get_option( self::LAST_DATA_SETTINGS_KEY );
		$this->settings = $opt;
	}

	/**
	 * Get next task description
	 *
	 * @return string
	 */
	public static function nextTaskDescription() {
		$queue = Utils::getQueue();
		$date  = null;
		$text  = '';
		if ( ! empty( $queue ) ) {
			$nextTask = $queue->get_next( self::HOOK );
			if ( ! empty( $nextTask ) && is_object( $nextTask ) && is_a( $nextTask, 'WC_DateTime' ) ) {
				$date = Helpers::localDate( $nextTask->getTimestamp() );
			}
		}
		if ( $date ) {
			$text = sprintf( __( 'the next index rebuild: %s', 'ajax-search-for-woocommerce' ), $date );

		}

		return $text;
	}

	/**
	 * Rebuild index in recurring task
	 *
	 * @return void
	 */
	public function recurringTask() {
		$build = false;

		if ( Builder::getInfo( 'status', Config::getIndexRole() ) === 'completed' ) {
			$build = true;
		} else if ( Builder::getInfo( 'status', Config::getIndexRole() ) === 'error' ) {
			list( $lastErrorCode, $lastErrorMessage ) = Logger::getLastEmergencyLog();
			if ( in_array( $lastErrorCode, array( '001', '002' ) ) ) {
				$build = true;
			}
		}

		if ( $build ) {
			Builder::buildIndex( false );
		}

		$this->registerSchedule( true );
	}
}
