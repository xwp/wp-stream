<?php
/**
 * Plugin Name: Stream
 * Plugin URI: https://xwp.co/work/stream/
 * Description: Stream tracks logged-in user activity so you can monitor every change made on your WordPress site in beautifully organized detail. All activity is organized by context, action and IP address for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 4.0.0
 * Author: XWP
 * Author URI: https://xwp.co
 * License: GPLv2+
 * Text Domain: stream
 * Domain Path: /languages
 *
 * @package WP_Stream
 */

/**
 * Copyright (c) 2015 XWP.Co Pty Ltd. (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'shutdown', 'wp_stream_fail_php_version' );
} else {
	require __DIR__ . '/classes/class-plugin.php';
	$plugin_class_name = 'WP_Stream\Plugin';
	if ( class_exists( $plugin_class_name ) ) {
		$GLOBALS['wp_stream'] = new $plugin_class_name();
	}
}

/**
 * Invoked when the PHP version check fails.
 *
 * Load up the translations and add the error message to the admin notices.
 */
function wp_stream_fail_php_version() {
	load_plugin_textdomain( 'stream', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	$message      = esc_html__( 'Stream requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'stream' );
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );

	echo wp_kses_post( $html_message );
}

/**
 * Helper for external plugins which wish to use Stream.
 *
 * @return WP_Stream\Plugin
 */
function wp_stream_get_instance() {
	return $GLOBALS['wp_stream'];
}
