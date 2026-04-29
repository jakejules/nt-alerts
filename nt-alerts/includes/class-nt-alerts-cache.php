<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Cache {

	const TRANSIENT_KEY_ACTIVE = 'nt_alerts_active_v1';

	private static $last_status = 'MISS';

	public static function register_hooks() {
		add_action( 'save_post_' . NT_ALERTS_CPT,   array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post',                 array( __CLASS__, 'flush_on_deleted_post' ), 10, 2 );
		add_action( 'updated_post_meta',            array( __CLASS__, 'flush_on_meta' ), 10, 4 );
		add_action( 'added_post_meta',              array( __CLASS__, 'flush_on_meta' ), 10, 4 );
		add_action( 'deleted_post_meta',            array( __CLASS__, 'flush_on_meta' ), 10, 4 );
	}

	public static function get_active() {
		$payload = get_transient( self::TRANSIENT_KEY_ACTIVE );
		self::$last_status = ( false === $payload ) ? 'MISS' : 'HIT';
		return $payload;
	}

	public static function set_active( $payload, $ttl = null ) {
		if ( null === $ttl ) {
			$ttl = (int) get_option( 'nt_alerts_cache_ttl', NT_ALERTS_CACHE_TTL_DEFAULT );
			if ( $ttl <= 0 ) {
				$ttl = NT_ALERTS_CACHE_TTL_DEFAULT;
			}
		}
		set_transient( self::TRANSIENT_KEY_ACTIVE, $payload, $ttl );
	}

	public static function flush() {
		delete_transient( self::TRANSIENT_KEY_ACTIVE );
	}

	public static function flush_on_deleted_post( $post_id, $post ) {
		if ( $post && NT_ALERTS_CPT === $post->post_type ) {
			self::flush();
		}
	}

	public static function flush_on_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		if ( NT_ALERTS_CPT === get_post_type( $object_id ) ) {
			self::flush();
		}
	}

	public static function last_status() {
		return self::$last_status;
	}
}
