<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Support;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  Non-persistent object cache
 */
class Cache {

	/**
	 * Holds the cached objects.
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Adds data to the cache, if the cache key doesn't already exist.
	 *
	 * @param int|string $key What to call the contents in the cache.
	 * @param mixed $data The contents to store in the cache.
	 * @param string $group Optional. Where to group the cache contents. Default 'default'.
	 *
	 * @return bool False if cache key and group already exist, true on success
	 */
	public static function add( $key, $data, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( self::exists( $key, $group ) ) {
			return false;
		}

		return self::set( $key, $data, $group );
	}

	/**
	 * Sets the data contents into the cache.
	 *
	 * Differs from Cache/add() in that it will always write data.
	 * The cache contents are grouped by the $group parameter followed by the
	 * $key. This allows for duplicate ids in unique groups.
	 *
	 * @param int|string $key What to call the contents in the cache.
	 * @param mixed $data The contents to store in the cache.
	 * @param string $group Optional. Where to group the cache contents. Default 'default'.
	 *
	 * @return true Always returns true.
	 */
	public static function set( $key, $data, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		self::$cache[ $group ][ $key ] = $data;

		return true;
	}

	/**
	 * Retrieves the cache contents, if it exists.
	 *
	 * @param int|string $key What the contents in the cache are called.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 *
	 * @return false|mixed False on failure to retrieve contents or the cache contents on success.
	 */
	public static function get( $key, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( self::exists( $key, $group ) ) {

			if ( is_object( self::$cache[ $group ][ $key ] ) ) {
				return clone self::$cache[ $group ][ $key ];
			} else {
				return self::$cache[ $group ][ $key ];
			}
		}

		return false;
	}

	/**
	 * Removes the contents of the cache key in the group.
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @param int|string $key What the contents in the cache are called.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 *
	 * @return bool False if the contents weren't deleted and true on success.
	 */
	public static function delete( $key, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( ! self::exists( $key, $group ) ) {
			return false;
		}

		unset( self::$cache[ $group ][ $key ] );

		return true;
	}

	/**
	 * Clears the object cache of all data.
	 *
	 * @return true Always returns true.
	 */
	public static function flush() {
		self::$cache = array();

		return true;
	}

	/**
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @param int|string $key Cache key to check for existence.
	 * @param string $group Cache group for the key existence check.
	 *
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	private static function exists( $key, $group ) {
		return isset( self::$cache[ $group ] ) && ( isset( self::$cache[ $group ][ $key ] ) || array_key_exists( $key, self::$cache[ $group ] ) );
	}
}
