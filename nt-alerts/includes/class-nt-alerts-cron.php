<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Cron {

	const HOOK_EXPIRE_CHECK = 'nt_alerts_cron_expire_check';
	const HOOK_ARCHIVE      = 'nt_alerts_cron_archive';

	const SCHEDULE_5MIN = 'nt_5min';

	public static function register_hooks() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );

		add_action( self::HOOK_EXPIRE_CHECK, array( __CLASS__, 'cron_expire_check' ) );
		add_action( self::HOOK_ARCHIVE,      array( __CLASS__, 'cron_archive' ) );
	}

	public static function register_schedules( $schedules ) {
		if ( ! isset( $schedules[ self::SCHEDULE_5MIN ] ) ) {
			$schedules[ self::SCHEDULE_5MIN ] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes (NT Alerts)', 'nt-alerts' ),
			);
		}
		return $schedules;
	}

	/* -----------------------------------------------------------------
	 * Lifecycle (called from the activator)
	 * ----------------------------------------------------------------- */

	public static function schedule_all() {
		// register_schedules runs on the cron_schedules filter; on activate that
		// hook hasn't been added yet because boot() hasn't run, so call it.
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );

		// Clear any legacy schedules from earlier versions that ran the
		// expiry-warning and end-of-shift digest jobs.
		wp_clear_scheduled_hook( 'nt_alerts_cron_expiry_warning' );
		wp_clear_scheduled_hook( 'nt_alerts_cron_eod_digest' );

		if ( ! wp_next_scheduled( self::HOOK_EXPIRE_CHECK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE_5MIN, self::HOOK_EXPIRE_CHECK );
		}
		if ( ! wp_next_scheduled( self::HOOK_ARCHIVE ) ) {
			wp_schedule_event( self::next_local_time( '03:00' ), 'daily', self::HOOK_ARCHIVE );
		}
	}

	public static function unschedule_all() {
		foreach ( array(
			self::HOOK_EXPIRE_CHECK,
			self::HOOK_ARCHIVE,
			'nt_alerts_cron_expiry_warning',
			'nt_alerts_cron_eod_digest',
		) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/* -----------------------------------------------------------------
	 * Callbacks
	 * ----------------------------------------------------------------- */

	/**
	 * Marks active alerts whose end_time has passed as expired.
	 * Runs every 5 minutes.
	 */
	public static function cron_expire_check() {
		$now_iso = gmdate( 'c' );
		$posts   = get_posts( array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'active',
					'compare' => '=',
				),
				array(
					'key'     => 'end_time',
					'value'   => $now_iso,
					'compare' => '<',
					'type'    => 'CHAR',
				),
				array(
					'key'     => 'end_time',
					'value'   => '',
					'compare' => '!=',
				),
			),
		) );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $id ) {
			update_post_meta( $id, 'status',       'expired' );
			update_post_meta( $id, 'last_updated', $now_iso );
		}

		NT_Alerts_Cache::flush();
	}

	/**
	 * Archives expired alerts older than the configured threshold.
	 * Runs daily at 03:00 site-local.
	 */
	public static function cron_archive() {
		$days = (int) get_option( 'nt_alerts_archive_after_days', 30 );
		if ( $days < 1 ) {
			$days = 30;
		}

		$cutoff_iso = gmdate( 'c', time() - ( $days * DAY_IN_SECONDS ) );

		$ids = get_posts( array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'expired',
					'compare' => '=',
				),
				array(
					'key'     => 'end_time',
					'value'   => $cutoff_iso,
					'compare' => '<',
					'type'    => 'CHAR',
				),
			),
		) );

		foreach ( $ids as $id ) {
			update_post_meta( $id, 'status', 'archived' );
		}

		if ( ! empty( $ids ) ) {
			NT_Alerts_Cache::flush();
		}
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Returns the next Unix timestamp (UTC) corresponding to the given
	 * "HH:MM" time in the site's local timezone. If today's slot has
	 * already passed, returns tomorrow's.
	 */
	public static function next_local_time( $hhmm ) {
		try {
			$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}

		try {
			$now = new DateTimeImmutable( 'now', $tz );
			$target = $now->modify( $hhmm );
			if ( ! $target ) {
				return time() + HOUR_IN_SECONDS;
			}
			if ( $target <= $now ) {
				$target = $target->modify( '+1 day' );
			}
			return $target->getTimestamp();
		} catch ( Exception $e ) {
			return time() + HOUR_IN_SECONDS;
		}
	}
}
