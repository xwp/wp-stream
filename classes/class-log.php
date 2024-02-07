<?php
/**
 * Handles top-level record keeping functionality.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Log
 */
class Log {

	/**
	 * Holds Instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Previous Stream record ID, used for chaining same-session records
	 *
	 * @var int
	 */
	private $prev_record;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Ensure function used in various methods is pre-loaded.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Log handler
	 *
	 * @param Connector $connector Connector responsible for logging the event.
	 * @param string    $message sprintf-ready error message string.
	 * @param array     $args sprintf (and extra) arguments to use.
	 * @param int       $object_id Target object id.
	 * @param string    $context Context of the event.
	 * @param string    $action Action of the event.
	 * @param int       $user_id User responsible for the event.
	 *
	 * @return mixed True if updated, otherwise false|WP_Error
	 */
	public function log( $connector, $message, $args, $object_id, $context, $action, $user_id = null ) {
		global $wp_roles;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( is_null( $object_id ) ) {
			$object_id = 0;
		}

		$wp_cron_tracking = isset( $this->plugin->settings->options['advanced_wp_cron_tracking'] ) ? $this->plugin->settings->options['advanced_wp_cron_tracking'] : false;
		$author           = new Author( $user_id );
		$agent            = $author->get_current_agent();

		// WP Cron tracking requires opt-in and WP Cron to be enabled.
		if ( ! $wp_cron_tracking && 'wp_cron' === $agent ) {
			return false;
		}

		$ip_address = $this->plugin->get_client_ip_address();

		$user = new \WP_User( $user_id );

		if ( $this->is_record_excluded( $connector, $context, $action, $user, $ip_address ) ) {
			return false;
		}

		$user_meta = array(
			'user_email'      => (string) ! empty( $user->user_email ) ? $user->user_email : '',
			'display_name'    => (string) $author->get_display_name(),
			'user_login'      => (string) ! empty( $user->user_login ) ? $user->user_login : '',
			'user_role_label' => (string) $author->get_role(),
			'agent'           => (string) $agent,
		);

		if ( 'wp_cli' === $agent && function_exists( 'posix_getuid' ) ) {
			$uid       = posix_getuid();
			$user_info = posix_getpwuid( $uid );

			$user_meta['system_user_id']   = (int) $uid;
			$user_meta['system_user_name'] = (string) $user_info['name'];
		}

		// Prevent any meta with null values from being logged.
		$stream_meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		// Add user meta to Stream meta.
		$stream_meta['user_meta'] = $user_meta;

		if ( ! empty( $user->roles ) ) {
			$roles = array_values( $user->roles );
			$role  = $roles[0];
		} elseif ( is_multisite() && is_super_admin() && $wp_roles->is_role( 'administrator' ) ) {
			$role = 'administrator';
		} else {
			$role = '';
		}

		$recordarr = array(
			'object_id' => (int) $object_id,
			'site_id'   => (int) is_multisite() ? get_current_site()->id : 1,
			'blog_id'   => (int) apply_filters( 'wp_stream_blog_id_logged', get_current_blog_id() ),
			'user_id'   => (int) $user_id,
			'user_role' => (string) $role,
			'created'   => (string) current_time( 'mysql', true ),
			'summary'   => (string) vsprintf( $message, $args ),
			'connector' => (string) $connector,
			'context'   => (string) $context,
			'action'    => (string) $action,
			'ip'        => (string) $ip_address,
			'meta'      => (array) $stream_meta,
		);

		if ( 0 === $recordarr['object_id'] ) {
			unset( $recordarr['object_id'] );
		}

		$result = $this->plugin->db->insert( $recordarr );

		// This is helpful in development environments:
		// error_log( $this->debug_backtrace( $recordarr ) );.

		return $result;
	}

	/**
	 * This function is use to check whether or not a record should be excluded from the log.
	 *
	 * @param string   $connector Name of the connector being logged.
	 * @param string   $context Name of the context being logged.
	 * @param string   $action Name of the action being logged.
	 * @param \WP_User $user The user being logged.
	 * @param string   $ip IP address being logged.
	 *
	 * @return bool
	 */
	public function is_record_excluded( $connector, $context, $action, $user = null, $ip = null ) {
		$exclude_record = false;

		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		if ( ! empty( $user->roles ) ) {
			$roles = array_values( $user->roles );
			$role  = $roles[0];
		} else {
			$role = '';
		}
		$record = array(
			'connector'  => $connector,
			'context'    => $context,
			'action'     => $action,
			'author'     => $user->ID,
			'role'       => $role,
			'ip_address' => $ip,
		);

		$exclude_settings = isset( $this->plugin->settings->options['exclude_rules'] ) ? $this->plugin->settings->options['exclude_rules'] : array();

		if ( is_multisite() && $this->plugin->is_network_activated() && ! is_network_admin() ) {
			$multisite_options = (array) get_site_option( 'wp_stream_network', array() );
			$exclude_settings  = isset( $multisite_options['exclude_rules'] ) ? $multisite_options['exclude_rules'] : array();
		}

		foreach ( $this->exclude_rules_by_rows( $exclude_settings ) as $exclude_rule ) {
			$exclude = array(
				'connector'  => ! empty( $exclude_rule['connector'] ) ? $exclude_rule['connector'] : null,
				'context'    => ! empty( $exclude_rule['context'] ) ? $exclude_rule['context'] : null,
				'action'     => ! empty( $exclude_rule['action'] ) ? $exclude_rule['action'] : null,
				'ip_address' => ! empty( $exclude_rule['ip_address'] ) ? $exclude_rule['ip_address'] : null,
				'author'     => is_numeric( $exclude_rule['author_or_role'] ) ? absint( $exclude_rule['author_or_role'] ) : null,
				'role'       => ( ! empty( $exclude_rule['author_or_role'] ) && ! is_numeric( $exclude_rule['author_or_role'] ) ) ? $exclude_rule['author_or_role'] : null,
			);

			$exclude_rules = array_filter( $exclude, 'strlen' );

			if ( $this->record_matches_rules( $record, $exclude_rules ) ) {
				$exclude_record = true;
				break;
			}
		}

		/**
		 * Filters whether or not a record should be excluded from the log.
		 *
		 * If true, the record is not logged.
		 *
		 * @param array $exclude_record Whether the record should excluded.
		 * @param array $recordarr The record to log.
		 *
		 * @return bool
		 */
		return apply_filters( 'wp_stream_is_record_excluded', $exclude_record, $record );
	}

	/**
	 * Check if a record to stored matches certain rules.
	 *
	 * @param array $record List of record parameters.
	 * @param array $exclude_rules List of record exclude rules.
	 *
	 * @return boolean
	 */
	public function record_matches_rules( $record, $exclude_rules ) {
		$matches_needed = count( $exclude_rules );
		$matches_found  = 0;
		foreach ( $exclude_rules as $exclude_key => $exclude_value ) {
			if ( ! isset( $record[ $exclude_key ] ) || is_null( $exclude_value ) ) {
				continue;
			}

			if ( 'ip_address' === $exclude_key ) {
				$ip_addresses = explode( ',', $exclude_value );

				if ( in_array( $record['ip_address'], $ip_addresses, true ) ) {
					$matches_found++;
				}
			} elseif ( $record[ $exclude_key ] === $exclude_value ) {
				$matches_found++;
			}
		}

		return $matches_found === $matches_needed;
	}

	/**
	 * Get all exclude rules by row because we store them by rule instead.
	 *
	 * @param array $rules List of rules indexed by rule ID.
	 *
	 * @return array
	 */
	public function exclude_rules_by_rows( $rules ) {
		$excludes = array();

		// TODO: Move these to where the settings are generated to ensure they're in sync.
		$rule_keys = array(
			'exclude_row',
			'author_or_role',
			'connector',
			'context',
			'action',
			'ip_address',
		);

		if ( empty( $rules['exclude_row'] ) ) {
			return array();
		}

		foreach ( array_keys( $rules['exclude_row'] ) as $row_id ) {
			$excludes[ $row_id ] = array();

			foreach ( $rule_keys as $rule_key ) {
				if ( isset( $rules[ $rule_key ][ $row_id ] ) ) {
					$excludes[ $row_id ][ $rule_key ] = $rules[ $rule_key ][ $row_id ];
				} else {
					$excludes[ $row_id ][ $rule_key ] = null;
				}
			}
		}

		return $excludes;
	}

	/**
	 * Helper function to send a full backtrace of calls to the PHP error log for debugging
	 *
	 * @param array $recordarr Record argument array.
	 *
	 * @return string
	 */
	public function debug_backtrace( $recordarr ) {
		if ( version_compare( PHP_VERSION, '5.3.6', '<' ) ) {
			return __( 'Debug backtrace requires at least PHP 5.3.6', 'wp_stream' );
		}

		// Record details.
		$summary   = isset( $recordarr['summary'] ) ? $recordarr['summary'] : null;
		$author    = isset( $recordarr['author'] ) ? $recordarr['author'] : null;
		$connector = isset( $recordarr['connector'] ) ? $recordarr['connector'] : null;
		$context   = isset( $recordarr['context'] ) ? $recordarr['context'] : null;
		$action    = isset( $recordarr['action'] ) ? $recordarr['action'] : null;

		// Stream meta.
		$stream_meta = isset( $recordarr['meta'] ) ? $recordarr['meta'] : null;

		unset( $stream_meta['user_meta'] );

		if ( $stream_meta ) {
			array_walk(
				$stream_meta,
				function ( &$value, $key ) {
					$value = sprintf( '%s: %s', $key, ( '' === $value ) ? 'null' : $value );
				}
			);
			$stream_meta = implode( ', ', $stream_meta );
		}

		// User meta.
		$user_meta = isset( $recordarr['meta']['user_meta'] ) ? $recordarr['meta']['user_meta'] : null;

		if ( $user_meta ) {
			array_walk(
				$user_meta,
				function ( &$value, $key ) {
					$value = sprintf( '%s: %s', $key, ( '' === $value ) ? 'null' : $value );
				}
			);

			$user_meta = implode( ', ', $user_meta );
		}

		// Debug backtrace.
		ob_start();

		// @codingStandardsIgnoreStart
		debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // Option to ignore args requires PHP 5.3.6
		// @codingStandardsIgnoreEnd

		$backtrace = ob_get_clean();
		$backtrace = array_values( array_filter( explode( "\n", $backtrace ) ) );

		$output = sprintf(
			"WP Stream Debug Backtrace\n\n    Summary | %s\n     Author | %s\n  Connector | %s\n    Context | %s\n     Action | %s\nStream Meta | %s\nAuthor Meta | %s\n\n%s\n",
			$summary,
			$author,
			$connector,
			$context,
			$action,
			$stream_meta,
			$user_meta,
			implode( "\n", $backtrace )
		);

		return $output;
	}
}
