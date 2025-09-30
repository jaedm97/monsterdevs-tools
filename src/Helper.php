<?php

namespace MonsterDevs\Tools;

class Helper {

	/**
	 * Add error log
	 *
	 * @param array|string $payload
	 * @param Throwable    $th
	 *
	 * @return void
	 */
	public static function add_error_log( $payload, $th = null ) {
		$log_name = 'monsterdevs_tools_helper_error_log';
		$log      = self::get_options( array(), $log_name );

		$log = ( empty( $log ) || ! is_array( $log ) ) ? array() : $log;

		if ( 150 < count( $log ) ) {
			// Remove first 50 entries
			$log = array_slice( $log, 50 );
		}

		$error         = is_array( $payload ) ? self::sanitize_data( $payload ) : array(
			'message' => sanitize_text_field( $payload ),
		);
		$error['time'] = date( 'Y-m-d H:i:s' );

		if ( ! empty( $th ) ) {
			$error = array_merge(
				$error,
				array(
					'error' => $th->getMessage(),
					'line'  => $th->getLine(),
					'file'  => $th->getFile(),
				)
			);
		}

		$log[] = $error;
		self::set_settings( $log, $log_name );
	}

	/**
	 * Get error log
	 *
	 * @return array
	 */
	public static function get_error_log() {
		$log = self::get_options( array(), 'monsterdevs_tools_helper_error_log' );
		$log = ( empty( $log ) || ! is_array( $log ) ) ? array() : $log;

		return $log;
	}

	/**
	 * Sanitize data
	 *
	 * @param array|string $data data
	 *
	 * @return array|string sanitized data
	 */
	public static function sanitize_data( $data ) {
		if ( empty( $data ) ) {
			return $data;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ $key ] = self::sanitize_data( $value );
				} else {
					$data[ $key ] = sanitize_text_field( $value );
				}
			}
		} elseif ( is_string( $data ) ) {
			$data = sanitize_text_field( $data );
		} else {
			$data = '';
		}
		return $data;
	}

	/**
	 * Get Connect Config
	 *
	 * @param array $config
	 * @return array
	 */
	public static function get_connect_config( $config = array() ) {
		$connect_body = array(
			'url'         => self::wp_site_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
			'title'       => get_bloginfo( 'name' ),
			'icon'        => get_site_icon_url(),
			'username'    => base64_encode( self::get_admin_username() ),
			'managed'     => is_bool( $config ) ? $config : true,
		);

		if ( is_array( $config ) ) {
			$connect_body = array_merge( $connect_body, $config );
		}

		return $connect_body;
	}

	public static function generate_jwt( $connect_id = '' ) {
		$connect_id = ! empty( $connect_id ) ? $connect_id : self::get_connect_id();
		if ( empty( $connect_id ) ) {
			return false;
		}

		$response = Curl::do_curl( "connects/{$connect_id}/generate-token", array(), array(), 'GET' );
		if ( ! empty( $response['success'] ) ) {
			$jwt = ! empty( $response['data']['token'] ) ? $response['data']['token'] : '';

			if ( ! empty( $jwt ) ) {
				self::set_jwt( $jwt );

				return true;
			}
		}

		self::add_error_log(
			array(
				'message'    => 'generate_jwt error, response from generate-token api',
				'response'   => $response,
				'connect_id' => $connect_id,
			)
		);
		return false;
	}

	public static function get_random_string( $length = 6 ) {
		try {
			$length        = (int) round( ceil( absint( $length ) / 2 ) );
			$bytes         = function_exists( 'random_bytes' ) ? random_bytes( $length ) : openssl_random_pseudo_bytes( $length );
			$random_string = bin2hex( $bytes );
		} catch ( \Exception $e ) {
			$random_string = substr( hash( 'sha256', wp_generate_uuid4() ), 0, absint( $length ) );
		}

		return $random_string;
	}

	public static function get_args_option( $key = '', $args = array(), $default = '' ) {
		$default = is_array( $default ) && empty( $default ) ? array() : $default;
		$value   = ! is_array( $default ) && ! is_bool( $default ) && empty( $default ) ? '' : $default;
		$key     = empty( $key ) ? '' : $key;

		if ( ! empty( $args[ $key ] ) ) {
			$value = $args[ $key ];
		}

		if ( isset( $args[ $key ] ) && is_bool( $default ) ) {
			$value = ! ( 0 == $args[ $key ] || '' == $args[ $key ] );
		}

		return $value;
	}

	public static function get_directory_info( $path ) {
		$bytes_total = 0;
		$files_total = 0;
		$path        = realpath( $path );

		try {
			if ( $path !== false && $path != '' && file_exists( $path ) ) {
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					try {
						$bytes_total += $object->getSize();
						++$files_total;
					} catch ( \Exception $e ) {
						continue;
					}
				}
			}
		} catch ( \Exception $e ) {
		}

		return array(
			'size'  => $bytes_total,
			'count' => $files_total,
		);
	}

	public static function is_on_wordpress_org( $slug, $type ) {
		$api_url  = 'https://api.wordpress.org/' . ( $type === 'plugin' ? 'plugins' : 'themes' ) . '/info/1.2/';
		$response = wp_remote_get(
			add_query_arg(
				array(
					'action'  => $type . '_information',
					'request' => array(
						'slug' => $slug,
					),
				),
				$api_url
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['name'] ) && ! empty( $data['slug'] ) && $data['slug'] === $slug ) {
			return true;
		}

		return false;
	}

	public static function get_admin_username() {
		if ( current_user_can( 'manage_options' ) ) {
			$current_user = wp_get_current_user();

			if ( ! empty( $current_user ) ) {
				return $current_user->user_login;
			}
		}

		$username = '';

		foreach (
			get_users(
				array(
					'role__in' => array( 'administrator' ),
					'fields'   => array( 'user_login' ),
				)
			) as $admin
		) {
			if ( empty( $username ) && isset( $admin->user_login ) ) {
				$username = $admin->user_login;
				break;
			}
		}

		return $username;
	}

	public static function get_options( $default = array(), $option_name = 'monsterdevs_api_options' ) {
		return Option::get_option( $option_name, $default );
	}

	public static function get_api_key( $return_hashed = false, $default_key = '' ) {
		$api_options = self::get_options();
		$api_key     = self::get_args_option( 'api_key', $api_options, $default_key );

		if ( ! $return_hashed ) {
			return $api_key;
		}

		if ( ! empty( $api_key ) && strpos( $api_key, '|' ) !== false ) {
			$exploded             = explode( '|', $api_key );
			$current_api_key_hash = hash( 'sha256', $exploded[1] );
		} else {
			$current_api_key_hash = ! empty( $api_key ) ? hash( 'sha256', $api_key ) : '';
		}

		return $current_api_key_hash;
	}

	public static function get_connect_id() {
		$api_options = self::get_options();

		return self::get_args_option( 'connect_id', $api_options );
	}

	public static function get_connect_uuid() {
		$api_options = self::get_options();

		return self::get_args_option( 'connect_uuid', $api_options );
	}

	public static function get_connect_origin() {
		$api_options = self::get_options();

		return self::get_args_option( 'origin', $api_options );
	}

	public static function get_jwt() {
		$api_options = self::get_options();

		return self::get_args_option( 'jwt', $api_options );
	}

	public static function get_response() {
		$api_options = self::get_options();

		return self::get_args_option( 'response', $api_options, array() );
	}


	public static function set_settings( $settings, $option_name = 'monsterdevs_api_options' ) {
		return Option::update_option( $option_name, $settings );
	}

	public static function set_api_key( $api_key ) {
		$api_options            = self::get_options();
		$api_options['api_key'] = $api_key;

		return self::set_settings( $api_options );
	}

	public static function set_connect_id( $connect_id ) {
		$api_options               = self::get_options();
		$api_options['connect_id'] = intval( $connect_id );

		return self::set_settings( $api_options );
	}

	public static function set_connect_uuid( $connect_uuid ) {
		$api_options                 = self::get_options();
		$api_options['connect_uuid'] = $connect_uuid;

		return self::set_settings( $api_options );
	}

	/**
	 * Set migration group id
	 */
	public static function set_mig_gid( $group_uuid ) {
		$api_options               = self::get_options();
		$api_options['group_uuid'] = $group_uuid;

		return self::set_settings( $api_options );
	}

	/**
	 * Get migration group id
	 */
	public static function get_mig_gid() {
		$api_options = self::get_options();

		return self::get_args_option( 'group_uuid', $api_options );
	}

	/**
	 * Has migration group id
	 */
	public static function has_mig_gid( $group_uuid ) {
		if ( empty( $group_uuid ) ) {
			return false;
		}
		return $group_uuid === self::get_mig_gid();
	}

	public static function set_connect_origin( $origin ) {
		$api_options           = self::get_options();
		$api_options['origin'] = $origin;

		return self::set_settings( $api_options );
	}

	public static function set_jwt( $jwt ) {
		$api_options        = self::get_options();
		$api_options['jwt'] = $jwt;

		return self::set_settings( $api_options );
	}

	public static function set_api_domain( $api_domain = '' ) {

		$api_options            = self::get_options();
		$api_options['api_url'] = $api_domain;

		return self::set_settings( $api_options );
	}

	public static function get_connect_plan() {
		$api_options = self::get_options();
		$plan_id     = self::get_args_option( 'plan_id', $api_options );

		if ( empty( $plan_id ) ) {
			return array();
		}

		return array(
			'plan_id'        => $plan_id,
			'plan_timestamp' => self::get_args_option( "plan_{$plan_id}_timestamp", $api_options ),
		);
	}

	public static function get_connect_plan_id() {
		$connect_plan = self::get_connect_plan();

		return self::get_args_option( 'plan_id', $connect_plan );
	}

	public static function set_connect_plan_id( $plan_id ) {
		$api_options = self::get_options();

		if ( ! empty( $plan_id ) ) {
			$key = "plan_{$plan_id}_timestamp";

			if ( ! isset( $api_options[ $key ] ) ) {
				$api_options[ $key ] = current_time( 'mysql' );
			}

			$api_options['plan_id'] = $plan_id;
		} else {
			unset( $api_options['plan_id'] );
		}

		return self::set_settings( $api_options );
	}

	public static function remove_connect_plan_id() {
		$api_options = self::get_options();
		$plan_id     = self::get_args_option( 'plan_id', $api_options );

		if ( empty( $plan_id ) ) {
			return false;
		}

		unset( $api_options['plan_id'] );
		unset( $api_options[ "plan_{$plan_id}_timestamp" ] );

		return self::set_settings( $api_options );
	}

	public static function wp_site_url( $path = '', $check_ssl = false ) {
		global $wpdb;

		$site_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );

		if ( empty( $site_url ) ) {
			return get_site_url( null, $path );
		}

		if ( $path && is_string( $path ) ) {
			$site_url .= '/' . ltrim( $path, '/' );
		}

		if ( $check_ssl ) {
			$parsed_url = parse_url( $site_url );
			$protocol   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : 'unknown';

			if ( $protocol !== 'https' ) {
				$site_url = site_url( $path );
			}
		}

		return $site_url;
	}
}
