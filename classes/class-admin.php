<?php
/**
 * Centralized manager for WordPress backend functionality.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use DateTime;
use DateTimeZone;
use DateInterval;
use \WP_CLI;
use \WP_Roles;

/**
 * Class - Admin
 */
class Admin {

	/**
	 * Holds Instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Holds Network class
	 *
	 * @var Network
	 */
	public $network;

	/**
	 * Holds Live Update class
	 *
	 * @var Live_Update
	 */
	public $live_update;

	/**
	 * Holds Export class
	 *
	 * @var Export
	 */
	public $export;

	/**
	 * Menu page screen id
	 *
	 * @var string
	 */
	public $screen_id = array();

	/**
	 * List table object
	 *
	 * @var List_Table
	 */
	public $list_table = null;

	/**
	 * Option to disable access to Stream
	 *
	 * @var bool
	 */
	public $disable_access = false;

	/**
	 * Class applied to the body of the admin screen
	 *
	 * @var string
	 */
	public $admin_body_class = 'wp_stream_screen';

	/**
	 * Slug of the records page
	 *
	 * @var string
	 */
	public $records_page_slug = 'wp_stream';

	/**
	 * Slug of the settings page
	 *
	 * @var string
	 */
	public $settings_page_slug = 'wp_stream_settings';

	/**
	 * Parent page of the records and settings pages
	 *
	 * @var string
	 */
	public $admin_parent_page = 'admin.php';

	/**
	 * Capability name for viewing records
	 *
	 * @var string
	 */
	public $view_cap = 'view_stream';

	/**
	 * Capability name for viewing settings
	 *
	 * @var string
	 */
	public $settings_cap = 'manage_options';

	/**
	 * Total amount of authors to pre-load
	 *
	 * @var int
	 */
	public $preload_users_max = 50;

	/**
	 * Admin notices, collected and displayed on proper action
	 *
	 * @var array
	 */
	public $notices = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'init' ) );

		// Ensure function used in various methods is pre-loaded.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// User and role caps.
		add_filter( 'user_has_cap', array( $this, 'filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( $this, 'filter_role_caps' ), 10, 3 );

		if ( is_multisite() && $plugin->is_network_activated() && ! is_network_admin() ) {
			$options = (array) get_site_option( 'wp_stream_network', array() );
			$option  = isset( $options['general_site_access'] ) ? absint( $options['general_site_access'] ) : 1;

			$this->disable_access = ( $option ) ? false : true;
		}

		// Register settings page.
		if ( ! $this->disable_access ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
		}

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'prepare_admin_notices' ) );
		add_action( 'shutdown', array( $this, 'admin_notices' ) );

		// Add admin body class.
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Plugin action links.
		add_filter(
			'plugin_action_links',
			array(
				$this,
				'plugin_action_links',
			),
			10,
			2
		);

		// Load admin scripts and styles.
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'admin_enqueue_scripts',
			)
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_menu_css' ) );

		// Reset Streams database.
		add_action(
			'wp_ajax_wp_stream_reset',
			array(
				$this,
				'wp_ajax_reset',
			)
		);

		// Auto purge setup.
		add_action( 'wp_loaded', array( $this, 'purge_schedule_setup' ) );
		add_action(
			'wp_stream_auto_purge',
			array(
				$this,
				'purge_scheduled_action',
			)
		);

		// Ajax users list.
		add_action(
			'wp_ajax_wp_stream_filters',
			array(
				$this,
				'ajax_filters',
			)
		);
	}

	/**
	 * Load admin classes
	 *
	 * @action init
	 */
	public function init() {
		$this->network     = new Network( $this->plugin );
		$this->live_update = new Live_Update( $this->plugin );
		$this->export      = new Export( $this->plugin );

		// Check if the host has configured the `REMOTE_ADDR` correctly.
		$client_ip = $this->plugin->get_client_ip_address();
		if ( empty( $client_ip ) && $this->is_stream_screen() ) {
			$this->notice( __( 'Stream plugin can\'t determine a reliable client IP address! Please update the hosting environment to set the $_SERVER[\'REMOTE_ADDR\'] variable or use the wp_stream_client_ip_address filter to specify the verified client IP address!', 'stream' ) );
		}
	}

	/**
	 * Output specific updates passed as URL parameters.
	 *
	 * @action admin_notices
	 *
	 * @return void
	 */
	public function prepare_admin_notices() {
		$message = wp_stream_filter_input( INPUT_GET, 'message' );

		switch ( $message ) {
			case 'settings_reset':
				$this->notice( esc_html__( 'All site settings have been successfully reset.', 'stream' ) );
				break;
		}
	}

	/**
	 * Handle notice messages according to the appropriate context (WP-CLI or the WP Admin)
	 *
	 * @param string $message Message to output.
	 * @param bool   $is_error If the message is error_level (true) or warning (false).
	 */
	public function notice( $message, $is_error = true ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$message = wp_strip_all_tags( $message );

			if ( $is_error ) {
				WP_CLI::warning( $message );
			} else {
				WP_CLI::success( $message );
			}
		} else {
			// Trigger admin notices late, so that any notices which occur during page load are displayed.
			add_action( 'shutdown', array( $this, 'admin_notices' ) );

			$notice = compact( 'message', 'is_error' );

			if ( ! in_array( $notice, $this->notices, true ) ) {
				$this->notices[] = $notice;
			}
		}
	}

	/**
	 * Show an error or other message in the WP Admin
	 *
	 * @action shutdown
	 */
	public function admin_notices() {
		global $allowedposttags;

		$custom = array(
			'progress' => array(
				'class' => true,
				'id'    => true,
				'max'   => true,
				'style' => true,
				'value' => true,
			),
		);

		$allowed_html = array_merge( $allowedposttags, $custom );

		ksort( $allowed_html );

		foreach ( $this->notices as $notice ) {
			$class_name   = empty( $notice['is_error'] ) ? 'updated' : 'error';
			$html_message = sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wpautop( $notice['message'] ) );

			echo wp_kses( $html_message, $allowed_html );
		}
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public function register_menu() {
		/**
		 * Filter the main admin menu title
		 *
		 * @return string
		 */
		$main_menu_title = apply_filters( 'wp_stream_admin_menu_title', esc_html__( 'Stream', 'stream' ) );

		/**
		 * Filter the main admin menu position
		 *
		 * Note: Using longtail decimal string to reduce the chance of position conflicts, see Codex
		 *
		 * @return string
		 */
		$main_menu_position = apply_filters( 'wp_stream_menu_position', '2.999999' );

		/**
		 * Filter the main admin page title
		 *
		 * @return string
		 */
		$main_page_title = apply_filters( 'wp_stream_admin_page_title', esc_html__( 'Stream Records', 'stream' ) );

		$this->screen_id['main'] = add_menu_page(
			$main_page_title,
			$main_menu_title,
			$this->view_cap,
			$this->records_page_slug,
			array( $this, 'render_list_table' ),
			'div',
			$main_menu_position
		);

		/**
		 * Fires before submenu items are added to the Stream menu
		 * allowing plugins to add menu items before Settings
		 *
		 * @return void
		 */
		do_action( 'wp_stream_admin_menu' );

		/**
		 * Filter the Settings admin page title
		 *
		 * @return string
		 */
		$settings_page_title = apply_filters( 'wp_stream_settings_form_title', esc_html__( 'Stream Settings', 'stream' ) );

		$this->screen_id['settings'] = add_submenu_page(
			$this->records_page_slug,
			$settings_page_title,
			esc_html__( 'Settings', 'stream' ),
			$this->settings_cap,
			$this->settings_page_slug,
			array( $this, 'render_settings_page' )
		);

		if ( isset( $this->screen_id['main'] ) ) {
			/**
			 * Fires just before the Stream list table is registered.
			 *
			 * @return void
			 */
			do_action( 'wp_stream_admin_menu_screens' );

			// Register the list table early, so it associates the column headers with 'Screen settings'.
			add_action(
				'load-' . $this->screen_id['main'],
				array(
					$this,
					'register_list_table',
				)
			);
		}
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook  Current hook.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		wp_register_script( 'wp-stream-select2', $this->plugin->locations['url'] . 'ui/lib/select2/js/select2.full.min.js', array( 'jquery' ), '3.5.2', true );
		wp_register_style( 'wp-stream-select2', $this->plugin->locations['url'] . 'ui/lib/select2/css/select2.min.css', array(), '3.5.2' );
		wp_register_script( 'wp-stream-timeago', $this->plugin->locations['url'] . 'ui/lib/timeago/jquery.timeago.js', array(), '1.4.1', true );

		$locale    = strtolower( substr( get_locale(), 0, 2 ) );
		$file_tmpl = 'ui/lib/timeago/locales/jquery.timeago.%s.js';

		if ( file_exists( $this->plugin->locations['dir'] . sprintf( $file_tmpl, $locale ) ) ) {
			wp_register_script(
				'wp-stream-timeago-locale',
				$this->plugin->locations['url'] . sprintf( $file_tmpl, $locale ),
				array( 'wp-stream-timeago' ),
				'1',
				false
			);
		} else {
			wp_register_script(
				'wp-stream-timeago-locale',
				$this->plugin->locations['url'] . sprintf( $file_tmpl, 'en' ),
				array( 'wp-stream-timeago' ),
				'1',
				false
			);
		}

		$min = wp_stream_min_suffix();
		wp_enqueue_style( 'wp-stream-admin', $this->plugin->locations['url'] . 'ui/css/admin.' . $min . 'css', array(), $this->plugin->get_version() );

		$script_screens = array( 'plugins.php' );

		if ( in_array( $hook, $this->screen_id, true ) || in_array( $hook, $script_screens, true ) ) {
			wp_enqueue_script( 'wp-stream-select2' );
			wp_enqueue_style( 'wp-stream-select2' );

			wp_enqueue_script( 'wp-stream-timeago' );
			wp_enqueue_script( 'wp-stream-timeago-locale' );

			wp_enqueue_script(
				'wp-stream-admin',
				$this->plugin->locations['url'] . 'ui/js/admin.' . $min . 'js',
				array(
					'jquery',
					'wp-stream-select2',
				),
				$this->plugin->get_version(),
				false
			);
			wp_enqueue_script(
				'wp-stream-admin-exclude',
				$this->plugin->locations['url'] . 'ui/js/exclude.' . $min . 'js',
				array(
					'jquery',
					'wp-stream-select2',
				),
				$this->plugin->get_version(),
				false
			);
			wp_enqueue_script(
				'wp-stream-live-updates',
				$this->plugin->locations['url'] . 'ui/js/live-updates.' . $min . 'js',
				array(
					'jquery',
					'heartbeat',
				),
				$this->plugin->get_version(),
				false
			);

			wp_localize_script(
				'wp-stream-admin',
				'wp_stream',
				array(
					'i18n'       => array(
						'confirm_purge'     => esc_html__( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
						'confirm_defaults'  => esc_html__( 'Are you sure you want to reset all site settings to default? This cannot be undone.', 'stream' ),
					),
					'locale'     => esc_js( $locale ),
					'gmt_offset' => get_option( 'gmt_offset' ),
				)
			);

			$order_types = array( 'asc', 'desc' );

			wp_localize_script(
				'wp-stream-live-updates',
				'wp_stream_live_updates',
				array(
					'current_screen'      => $hook,
					'current_page'        => isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : '1', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'current_order'       => isset( $_GET['order'] ) && in_array( strtolower( $_GET['order'] ), $order_types, true ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						? esc_js( $_GET['order'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						: 'desc',
					'current_query'       => wp_stream_json_encode( $_GET ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'current_query_count' => count( $_GET ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			);
		}

		/**
		 * The maximum number of items that can be updated in bulk without receiving a warning.
		 *
		 * Stream watches for bulk actions performed in the WordPress Admin (such as updating
		 * many posts at once) and warns the user before proceeding if the number of items they
		 * are attempting to update exceeds this threshold value. Since Stream will try to save
		 * a log for each item, it will take longer than usual to complete the operation.
		 *
		 * The default threshold is 100 items.
		 *
		 * @return int
		 */
		$bulk_actions_threshold = apply_filters( 'wp_stream_bulk_actions_threshold', 100 );

		wp_enqueue_script(
			'wp-stream-global',
			$this->plugin->locations['url'] . 'ui/js/global.' . $min . 'js',
			array( 'jquery' ),
			$this->plugin->get_version(),
			false
		);

		wp_localize_script(
			'wp-stream-global',
			'wp_stream_global',
			array(
				'bulk_actions'       => array(
					'i18n'      => array(
						/* translators: %s: a number of items (e.g. "1,742") */
						'confirm_action' => sprintf( esc_html__( 'Are you sure you want to perform bulk actions on over %s items? This process could take a while to complete.', 'stream' ), number_format( absint( $bulk_actions_threshold ) ) ),
					),
					'threshold' => absint( $bulk_actions_threshold ),
				),
				'plugins_screen_url' => self_admin_url( 'plugins.php#stream' ),
			)
		);
	}

	/**
	 * Check whether or not the current admin screen belongs to Stream
	 *
	 * @return bool
	 */
	public function is_stream_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = wp_stream_filter_input( INPUT_GET, 'page' );
		if ( is_string( $page ) && false !== strpos( $page, $this->records_page_slug ) ) {
			return true;
		}

		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			return ( Alerts::POST_TYPE === $screen->post_type );
		}

		return false;
	}

	/**
	 * Add a specific body class to all Stream admin screens
	 *
	 * @param string $classes  CSS classes to output to body.
	 *
	 * @filter admin_body_class
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$stream_classes = array();

		if ( $this->is_stream_screen() ) {
			$stream_classes[] = $this->admin_body_class;

			if ( isset( $_GET['page'] ) ) { // // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$stream_classes[] = sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		/**
		 * Filter the Stream admin body classes
		 *
		 * @return array
		 */
		$stream_classes = apply_filters( 'wp_stream_admin_body_classes', $stream_classes );
		$stream_classes = implode( ' ', array_map( 'trim', $stream_classes ) );

		return sprintf( '%s %s ', $classes, $stream_classes );
	}

	/**
	 * Add menu styles for various WP Admin skins
	 *
	 * @uses \wp_add_inline_style()
	 *
	 * @action admin_enqueue_scripts
	 */
	public function admin_menu_css() {
		$min = wp_stream_min_suffix();
		wp_register_style( 'wp-stream-datepicker', $this->plugin->locations['url'] . 'ui/css/datepicker.' . $min . 'css', array(), $this->plugin->get_version() );
		wp_register_style( 'wp-stream-icons', $this->plugin->locations['url'] . 'ui/stream-icons/style.css', array(), $this->plugin->get_version() );

		// Make sure we're working off a clean version.
		if ( ! file_exists( ABSPATH . WPINC . '/version.php' ) ) {
			return;
		}
		include ABSPATH . WPINC . '/version.php';

		if ( ! isset( $wp_version ) ) {
			return;
		}

		$body_class   = $this->admin_body_class;
		$records_page = $this->records_page_slug;
		$stream_url   = $this->plugin->locations['url'];

		if ( version_compare( $wp_version, '3.8-alpha', '>=' ) ) {
			wp_enqueue_style( 'wp-stream-icons' );

			$css = "
				#toplevel_page_{$records_page} .wp-menu-image:before {
					font-family: 'WP Stream' !important;
					content: '\\73' !important;
				}
				#toplevel_page_{$records_page} .wp-menu-image {
					background-repeat: no-repeat;
				}
				#menu-posts-feedback .wp-menu-image:before {
					font-family: dashicons !important;
					content: '\\f175';
				}
				#adminmenu #menu-posts-feedback div.wp-menu-image {
					background: none !important;
					background-repeat: no-repeat;
				}
				body.{$body_class} #wpbody-content .wrap h1:nth-child(1):before {
					font-family: 'WP Stream' !important;
					content: '\\73';
					padding: 0 8px 0 0;
				}
			";
		} else {
			$css = "
				#toplevel_page_{$records_page} .wp-menu-image {
					background: url( {$stream_url}ui/stream-icons/menuicon-sprite.png ) 0 90% no-repeat;
				}
				/* Retina Stream Menu Icon */
				@media  only screen and (-moz-min-device-pixel-ratio: 1.5),
						only screen and (-o-min-device-pixel-ratio: 3/2),
						only screen and (-webkit-min-device-pixel-ratio: 1.5),
						only screen and (min-device-pixel-ratio: 1.5) {
					#toplevel_page_{$records_page} .wp-menu-image {
						background: url( {$stream_url}ui/stream-icons/menuicon-sprite-2x.png ) 0 90% no-repeat;
						background-size:30px 64px;
					}
				}
				#toplevel_page_{$records_page}.current .wp-menu-image,
				#toplevel_page_{$records_page}.wp-has-current-submenu .wp-menu-image,
				#toplevel_page_{$records_page}:hover .wp-menu-image {
					background-position: top left;
				}
			";
		}

		\wp_add_inline_style( 'wp-admin', $css );
	}

	/**
	 * Handle the reset AJAX request to reset logs.
	 *
	 * @return bool
	 */
	public function wp_ajax_reset() {
		check_ajax_referer( 'stream_nonce_reset', 'wp_stream_nonce_reset' );

		if ( ! current_user_can( $this->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		$this->erase_stream_records();

		if ( defined( 'WP_STREAM_TESTS' ) && WP_STREAM_TESTS ) {
			return true;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => is_network_admin() ? $this->network->network_settings_page_slug : $this->settings_page_slug,
					'message' => 'data_erased',
				),
				self_admin_url( $this->admin_parent_page )
			)
		);

		exit;
	}

	/**
	 * Clears stream records from the database.
	 *
	 * @return void
	 */
	private function erase_stream_records() {
		global $wpdb;

		$where = '';

		if ( is_multisite() && ! $this->plugin->is_network_activated() ) {
			$where .= $wpdb->prepare( ' AND `blog_id` = %d', get_current_blog_id() );
		}

		$wpdb->query(
			"DELETE `stream`, `meta`
			FROM {$wpdb->stream} AS `stream`
			LEFT JOIN {$wpdb->streammeta} AS `meta`
			ON `meta`.`record_id` = `stream`.`ID`
			WHERE 1=1 {$where};" // @codingStandardsIgnoreLine $where already prepared
		);
	}

	/**
	 * Schedules a purge of records.
	 *
	 * @return void
	 */
	public function purge_schedule_setup() {
		if ( ! wp_next_scheduled( 'wp_stream_auto_purge' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wp_stream_auto_purge' );
		}
	}

	/**
	 * Executes a scheduled purge
	 *
	 * @return void
	 */
	public function purge_scheduled_action() {
		global $wpdb;

		// Don't purge when in Network Admin unless Stream is network activated.
		if (
			is_multisite()
			&&
			is_network_admin()
			&&
			! $this->plugin->is_network_activated()
		) {
			return;
		}

		$defaults = $this->plugin->settings->get_defaults();
		if ( is_multisite() && $this->plugin->is_network_activated() ) {
			$options = (array) get_site_option( 'wp_stream_network', $defaults );
		} else {
			$options = (array) get_option( 'wp_stream', $defaults );
		}

		if ( ! empty( $options['general_keep_records_indefinitely'] ) || ! isset( $options['general_records_ttl'] ) ) {
			return;
		}

		$days     = $options['general_records_ttl'];
		$timezone = new DateTimeZone( 'UTC' );
		$date     = new DateTime( 'now', $timezone );

		$date->sub( DateInterval::createFromDateString( "$days days" ) );

		$where = $wpdb->prepare( ' AND `stream`.`created` < %s', $date->format( 'Y-m-d H:i:s' ) );

		// Multisite but NOT network activated, only purge the current blog.
		if ( is_multisite() && ! $this->plugin->is_network_activated() ) {
			$where .= $wpdb->prepare( ' AND `blog_id` = %d', get_current_blog_id() );
		}

		$wpdb->query(
			"DELETE `stream`, `meta`
			FROM {$wpdb->stream} AS `stream`
			LEFT JOIN {$wpdb->streammeta} AS `meta`
			ON `meta`.`record_id` = `stream`.`ID`
			WHERE 1=1 {$where};" // @codingStandardsIgnoreLine $where already prepared
		);
	}

	/**
	 * Returns the admin action links.
	 *
	 * @filter plugin_action_links
	 *
	 * @param array  $links Action links.
	 * @param string $file  Plugin file.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links, $file ) {
		if ( plugin_basename( $this->plugin->locations['dir'] . 'stream.php' ) !== $file ) {
			return $links;
		}

		// Also don't show links in Network Admin if Stream isn't network enabled.
		if ( is_network_admin() && is_multisite() && ! $this->plugin->is_network_activated() ) {
			return $links;
		}

		if ( is_network_admin() ) {
			$admin_page_url = add_query_arg(
				array(
					'page' => $this->network->network_settings_page_slug,
				),
				network_admin_url( $this->admin_parent_page )
			);
		} else {
			$admin_page_url = add_query_arg(
				array(
					'page' => $this->settings_page_slug,
				),
				admin_url( $this->admin_parent_page )
			);
		}

		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'default' ) );

		return $links;
	}

	/**
	 * Render main page
	 */
	public function render_list_table() {
		$this->list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->list_table->display(); ?>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$option_key  = $this->plugin->settings->option_key;
		$form_action = apply_filters( 'wp_stream_settings_form_action', admin_url( 'options.php' ) );

		$page_description = apply_filters( 'wp_stream_settings_form_description', '' );

		$sections   = $this->plugin->settings->get_fields();
		$active_tab = wp_stream_filter_input( INPUT_GET, 'tab' );
		$min        = wp_stream_min_suffix();
		wp_enqueue_script( 'wp-stream-settings', $this->plugin->locations['url'] . 'ui/js/settings.' . $min . 'js', array( 'jquery' ), $this->plugin->get_version(), true );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! empty( $page_description ) ) : ?>
				<p><?php echo esc_html( $page_description ); ?></p>
			<?php endif; ?>

			<?php settings_errors(); ?>

			<?php if ( count( $sections ) > 1 ) : ?>
				<h2 class="nav-tab-wrapper">
					<?php $i = 0; ?>
					<?php foreach ( $sections as $section => $data ) : ?>
						<?php $i++; ?>
						<?php $is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section ); ?>
						<a href="<?php echo esc_url( add_query_arg( 'tab', $section ) ); ?>" class="nav-tab <?php echo $is_active ? esc_attr( ' nav-tab-active' ) : ''; ?>">
							<?php echo esc_html( $data['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</h2>
			<?php endif; ?>

			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="<?php echo esc_attr( $form_action ); ?>" enctype="multipart/form-data">
					<div class="settings-sections">
						<?php
						$i = 0;
						foreach ( $sections as $section => $data ) {
							$i++;

							$is_active = ( ( 1 === $i && ! $active_tab ) || $active_tab === $section );

							if ( $is_active ) {
								settings_fields( $option_key );
								do_settings_sections( $option_key );
							}
						}
						?>
					</div>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Instantiate the list table
	 */
	public function register_list_table() {
		$this->list_table = new List_Table(
			$this->plugin,
			array(
				'screen' => $this->screen_id['main'],
			)
		);
	}

	/**
	 * Check if a particular role has access
	 *
	 * @param string $role  User role.
	 *
	 * @return bool
	 */
	private function role_can_view( $role ) {
		if ( in_array( $role, $this->plugin->settings->options['general_role_access'], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter user caps to dynamically grant our view cap based on allowed roles
	 *
	 * @param array   $allcaps  All capabilities.
	 * @param array   $caps     Required caps.
	 * @param array   $args     Unused.
	 * @param WP_User $user     User.
	 *
	 * @filter user_has_cap
	 *
	 * @return array
	 */
	public function filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		global $wp_roles;

		$_wp_roles = isset( $wp_roles ) ? $wp_roles : new WP_Roles();

		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();

		// @see
		// https://github.com/WordPress/WordPress/blob/c67c9565f1495255807069fdb39dac914046b1a0/wp-includes/capabilities.php#L758
		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $_wp_roles, 'is_role' )
				)
			)
		);

		$stream_view_caps = array( $this->view_cap );

		foreach ( $caps as $cap ) {
			if ( in_array( $cap, $stream_view_caps, true ) ) {
				foreach ( $roles as $role ) {
					if ( $this->role_can_view( $role ) ) {
						$allcaps[ $cap ] = true;

						break 2;
					}
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Filter role caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter role_has_cap
	 *
	 * @param array  $allcaps  All capabilities.
	 * @param string $cap      Require cap.
	 * @param string $role     User role.
	 *
	 * @return array
	 */
	public function filter_role_caps( $allcaps, $cap, $role ) {
		$stream_view_caps = array( $this->view_cap );

		if ( in_array( $cap, $stream_view_caps, true ) && $this->role_can_view( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Ajax callback for return a user list.
	 *
	 * @action wp_ajax_wp_stream_filters
	 */
	public function ajax_filters() {
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			wp_die( '-1' );
		}

		check_ajax_referer( 'stream_filters_user_search_nonce', 'nonce' );

		switch ( wp_stream_filter_input( INPUT_GET, 'filter' ) ) {
			case 'user_id':
				$users = array_merge(
					array(
						0 => (object) array(
							'display_name' => 'WP-CLI',
						),
					),
					get_users()
				);

				$search = wp_stream_filter_input( INPUT_GET, 'q' );
				if ( $search ) {
					// `search` arg for get_users() is not enough
					$users = array_filter(
						$users,
						function ( $user ) use ( $search ) {
							return false !== mb_strpos( mb_strtolower( $user->display_name ), mb_strtolower( $search ) );
						}
					);
				}

				if ( count( $users ) > $this->preload_users_max ) {
					$users = array_slice( $users, 0, $this->preload_users_max );
				}

				// Get gravatar / roles for final result set.
				$results = $this->get_users_record_meta( $users );

				break;
		}

		if ( isset( $results ) ) {
			echo wp_stream_json_encode( $results ); // xss ok.
		}

		die();
	}

	/**
	 * Return relevant user meta data.
	 *
	 * @param array $authors  Author data.
	 * @return array
	 */
	public function get_users_record_meta( $authors ) {
		$authors_records = array();

		foreach ( $authors as $user_id => $args ) {
			$author = new Author( $args->ID );

			$authors_records[ $user_id ] = array(
				'text'  => $author->get_display_name(),
				'id'    => $author->id,
				'label' => $author->get_display_name(),
				'icon'  => $author->get_avatar_src( 32 ),
				'title' => '',
			);
		}

		return $authors_records;
	}

	/**
	 * Get user meta in a way that is also safe for VIP
	 *
	 * @param int    $user_id   User ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Return first found meta value connected to the meta key (optional).
	 *
	 * @return mixed
	 */
	public function get_user_meta( $user_id, $meta_key, $single = true ) {
		return get_user_meta( $user_id, $meta_key, $single );
	}

	/**
	 * Update user meta in a way that is also safe for VIP
	 *
	 * @param int    $user_id      User ID.
	 * @param string $meta_key     Meta key.
	 * @param mixed  $meta_value   Meta value.
	 * @param mixed  $prev_value   Previous meta value being overwritten (optional).
	 *
	 * @return int|bool
	 */
	public function update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = '' ) {
		return update_user_meta( $user_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Delete user meta in a way that is also safe for VIP
	 *
	 * @param int    $user_id     User ID.
	 * @param string $meta_key    Meta key.
	 * @param mixed  $meta_value  Meta value (optional).
	 *
	 * @return bool
	 */
	public function delete_user_meta( $user_id, $meta_key, $meta_value = '' ) {
		return delete_user_meta( $user_id, $meta_key, $meta_value );
	}
}
