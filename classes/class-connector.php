<?php
/**
 * Abstract class serving as the parent for all logger classes AKA "Connectors".
 * Common functionality for registering log events are defined here.
 *
 * @package WP_Stream;
 */

namespace WP_Stream;

/**
 * Class - Connector
 */
abstract class Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = null;

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array();

	/**
	 * Store delayed logs
	 *
	 * @var array
	 */
	public $delayed = array();

	/**
	 * Previous Stream entry in same request
	 *
	 * @var int
	 */
	public $prev_stream = null;

	/**
	 * Register connector in the WP Admin
	 *
	 * @var bool
	 */
	public $register_admin = true;

	/**
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = true;

	/**
	 * Holds connector registration status flag.
	 *
	 * @var bool
	 */
	private $is_registered = false;

	/**
	 * Is the connector currently registered?
	 *
	 * @return boolean
	 */
	public function is_registered() {
		return $this->is_registered;
	}

	/**
	 * Register all context hooks
	 */
	public function register() {
		if ( $this->is_registered ) {
			return;
		}

		foreach ( $this->actions as $action ) {
			add_action( $action, array( $this, 'callback' ), 10, 99 );
		}

		add_filter( 'wp_stream_action_links_' . $this->name, array( $this, 'action_links' ), 10, 2 );

		$this->is_registered = true;
	}

	/**
	 * Unregister all context hooks
	 */
	public function unregister() {
		if ( ! $this->is_registered ) {
			return;
		}

		foreach ( $this->actions as $action ) {
			remove_action( $action, array( $this, 'callback' ), 10, 99 );
		}

		remove_filter( 'wp_stream_action_links_' . $this->name, array( $this, 'action_links' ), 10, 2 );

		$this->is_registered = false;
	}

	/**
	 * Callback for all registered hooks throughout Stream
	 * Looks for a class method with the convention: "callback_{action name}"
	 */
	public function callback() {
		$action   = current_filter();
		$callback = array( $this, 'callback_' . preg_replace( '/[^a-z0-9_\-]/', '_', $action ) );

		// For the sake of testing, trigger an action with the name of the callback.
		if ( defined( 'WP_STREAM_TESTS' ) && WP_STREAM_TESTS ) {
			/**
			 * Action fires during testing to test the current callback
			 *
			 * @param  array  $callback  Callback name
			 */
			do_action( 'wp_stream_test_' . $callback[1] );
		}

		// Call the real function.
		if ( is_callable( $callback ) ) {
			return call_user_func_array( $callback, func_get_args() );
		}
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @param array  $links  Previous links registered.
	 * @param object $record Stream record.
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		unset( $record );
		return $links;
	}

	/**
	 * Log handler
	 *
	 * @param string $message   sprintf-ready error message string.
	 * @param array  $args      sprintf (and extra) arguments to use.
	 * @param int    $object_id Target object id.
	 * @param string $context   Context of the event.
	 * @param string $action    Action of the event.
	 * @param int    $user_id   User responsible for the event.
	 *
	 * @return bool
	 */
	public function log( $message, $args, $object_id, $context, $action, $user_id = null ) {
		$connector = $this->name;

		$data = apply_filters(
			'wp_stream_log_data',
			compact( 'connector', 'message', 'args', 'object_id', 'context', 'action', 'user_id' )
		);

		if ( ! $data ) {
			return false;
		} else {
			$connector = $data['connector'];
			$message   = $data['message'];
			$args      = $data['args'];
			$object_id = $data['object_id'];
			$context   = $data['context'];
			$action    = $data['action'];
			$user_id   = $data['user_id'];
		}

		return call_user_func_array( array( wp_stream_get_instance()->log, 'log' ), compact( 'connector', 'message', 'args', 'object_id', 'context', 'action', 'user_id' ) );
	}

	/**
	 * Save log data till shutdown, so other callbacks would be able to override
	 *
	 * @param string $handle Special slug to be shared with other actions.
	 * @note param mixed $arg1 Extra arguments to sent to log()
	 * @note param param mixed $arg2, etc..
	 */
	public function delayed_log( $handle ) {
		$args = func_get_args();

		array_shift( $args );

		$this->delayed[ $handle ] = $args;

		add_action( 'shutdown', array( $this, 'delayed_log_commit' ) );
	}

	/**
	 * Commit delayed logs saved by @delayed_log
	 */
	public function delayed_log_commit() {
		foreach ( $this->delayed as $handle => $args ) {
			call_user_func_array( array( $this, 'log' ), $args );
		}
	}

	/**
	 * Compare two values and return changed keys if they are arrays
	 *
	 * @param  mixed    $old_value Value before change.
	 * @param  mixed    $new_value Value after change.
	 * @param  bool|int $deep      Get array children changes keys as well, not just parents.
	 *
	 * @return array
	 */
	public function get_changed_keys( $old_value, $new_value, $deep = false ) {
		if ( ! is_array( $old_value ) && ! is_array( $new_value ) ) {
			return array();
		}

		if ( ! is_array( $old_value ) ) {
			return array_keys( $new_value );
		}

		if ( ! is_array( $new_value ) ) {
			return array_keys( $old_value );
		}

		$diff = array_udiff_assoc(
			$old_value,
			$new_value,
			function( $value1, $value2 ) {
				// Compare potentially complex nested arrays.
				return wp_json_encode( $value1 ) !== wp_json_encode( $value2 );
			}
		);

		$result = array_keys( $diff );

		// Find unexisting keys in old or new value.
		$common_keys     = array_keys( array_intersect_key( $old_value, $new_value ) );
		$unique_keys_old = array_values( array_diff( array_keys( $old_value ), $common_keys ) );
		$unique_keys_new = array_values( array_diff( array_keys( $new_value ), $common_keys ) );

		$result = array_merge( $result, $unique_keys_old, $unique_keys_new );

		// Remove numeric indexes.
		$result = array_filter(
			$result,
			function( $value ) {
				// @codingStandardsIgnoreStart
				// check if is not valid number (is_int, is_numeric and ctype_digit are not enough)
				return (string) (int) $value !== (string) $value;
				// @codingStandardsIgnoreEnd
			}
		);

		$result = array_values( array_unique( $result ) );

		if ( false === $deep ) {
			return $result; // Return an numerical based array with changed TOP PARENT keys only.
		}

		$result = array_fill_keys( $result, null );

		foreach ( $result as $key => $val ) {
			if ( in_array( $key, $unique_keys_old, true ) ) {
				$result[ $key ] = false; // Removed.
			} elseif ( in_array( $key, $unique_keys_new, true ) ) {
				$result[ $key ] = true; // Added.
			} elseif ( $deep ) { // Changed, find what changed, only if we're allowed to explore a new level.
				if ( is_array( $old_value[ $key ] ) && is_array( $new_value[ $key ] ) ) {
					$inner  = array();
					$parent = $key;
					$deep--;
					$changed = $this->get_changed_keys( $old_value[ $key ], $new_value[ $key ], $deep );
					foreach ( $changed as $child => $change ) {
						$inner[ $parent . '::' . $child ] = $change;
					}
					$result[ $key ] = 0; // Changed parent which has a changed children.
					$result         = array_merge( $result, $inner );
				}
			}
		}

		return $result;
	}

	/**
	 * Allow connectors to determine if their dependencies is satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		return true;
	}
}
