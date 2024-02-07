<?php
/**
 * Initializes plugin
 *
 * @package WP_Stream;
 */

namespace WP_Stream;

/**
 * Class Plugin
 */
class Plugin {
	/**
	 * Plugin version number.
	 *
	 * TODO Maybe pass this as a constructor dependency?
	 *
	 * @const string
	 */
	const VERSION = '4.0.0';

	/**
	 * WP-CLI command
	 *
	 * @const string
	 */
	const WP_CLI_COMMAND = 'stream';

	/**
	 * Holds and manages WordPress Admin configurations.
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Holds and manages alerts.
	 *
	 * @var Alerts
	 */
	public $alerts;

	/**
	 * Holds and manages alerts lists.
	 *
	 * @var Alerts_List
	 */
	public $alerts_list;

	/**
	 * Holds and manages connectors
	 *
	 * @var Connectors
	 */
	public $connectors;

	/**
	 * Holds and manages DB connections.
	 *
	 * @var DB
	 */
	public $db;

	/**
	 * Holds and manages records.
	 *
	 * @var Log
	 */
	public $log;

	/**
	 * Stores and manages WordPress settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Process DB migrations.
	 *
	 * @var Install
	 */
	public $install;

	/**
	 * URLs and Paths used by the plugin
	 *
	 * @var array
	 */
	public $locations = array();

	/**
	 * IP address for the current request to be associated with the log entry.
	 *
	 * @var null|false|string Valid IP address, null if not set, false if invalid.
	 */
	protected $client_ip_address;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$locate = $this->locate_plugin();

		$this->locations = array(
			'plugin'    => $locate['plugin_basename'],
			'dir'       => $locate['dir_path'],
			'url'       => $locate['dir_url'],
			'inc_dir'   => $locate['dir_path'] . 'includes/',
			'class_dir' => $locate['dir_path'] . 'classes/',
		);

		spl_autoload_register( array( $this, 'autoload' ) );

		// Load helper functions.
		require_once $this->locations['inc_dir'] . 'functions.php';

		// Load DB helper interface/class.
		$driver_class = apply_filters( 'wp_stream_db_driver', '\WP_Stream\DB_Driver_WPDB' );
		$driver       = null;

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}

		$error = false;
		if ( ! $this->db ) {
			$error = esc_html__( 'Stream: Could not load chosen DB driver.', 'stream' );
		} elseif ( ! $driver instanceof DB_Driver ) {
			$error = esc_html__( 'Stream: DB driver must implement DB Driver interface.', 'stream' );
		}

		if ( $error ) {
			wp_die(
				esc_html( $error ),
				esc_html__( 'Stream DB Error', 'stream' )
			);
		}

		// Load languages.
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

		// Load logger class.
		$this->log = apply_filters( 'wp_stream_log_handler', new Log( $this ) );

		// Set the IP address for the current request.
		$this->client_ip_address = wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );

		// Load settings and connectors after widgets_init and before the default init priority.
		add_action( 'init', array( $this, 'init' ), 9 );

		// Add frontend indicator.
		add_action( 'wp_head', array( $this, 'frontend_indicator' ) );

		// Change DB driver after plugin loaded if any add-ons want to replace.
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );

		// Load admin area classes.
		if ( is_admin() || ( defined( 'WP_STREAM_DEV_DEBUG' ) && WP_STREAM_DEV_DEBUG ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->admin   = new Admin( $this );
			$this->install = $driver->setup_storage( $this );
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$this->admin = new Admin( $this, $driver );
		}

		// Load WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( self::WP_CLI_COMMAND, 'WP_Stream\CLI' );
		}
	}

	/**
	 * Autoloader for classes
	 *
	 * @param string $class  Fully qualified classname to be loaded.
	 */
	public function autoload( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<autoload>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}

		static $reflection;

		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}

		if ( $reflection->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}

		$autoload_name = $matches['autoload'];
		$autoload_dir  = \trailingslashit( $this->locations['class_dir'] );
		$autoload_path = sprintf( '%sclass-%s.php', $autoload_dir, strtolower( str_replace( '_', '-', $autoload_name ) ) );

		if ( is_readable( $autoload_path ) ) {
			require_once $autoload_path;
		}
	}

	/**
	 * Loads the translation files.
	 *
	 * @action plugins_loaded
	 */
	public function i18n() {
		load_plugin_textdomain( 'stream', false, dirname( $this->locations['plugin'] ) . '/languages/' );
	}

	/**
	 * Load Settings, Notifications, and Connectors
	 *
	 * @action init
	 */
	public function init() {
		$this->settings    = new Settings( $this );
		$this->connectors  = new Connectors( $this );
		$this->alerts      = new Alerts( $this );
		$this->alerts_list = new Alerts_List( $this );

	}

	/**
	 * Displays an HTML comment in the frontend head to indicate that Stream is activated,
	 * and which version of Stream is currently in use.
	 *
	 * @action wp_head
	 *
	 * @return string|void An HTML comment, or nothing if the value is filtered out.
	 */
	public function frontend_indicator() {
		/* translators: Localization not needed */
		$comment = sprintf( 'Stream WordPress user activity plugin v%s', esc_html( $this->get_version() ) );

		/**
		 * Filter allows the HTML output of the frontend indicator comment
		 * to be altered or removed, if desired.
		 *
		 * @return string  The content of the HTML comment
		 */
		$comment = apply_filters( 'wp_stream_frontend_indicator', $comment );

		if ( ! empty( $comment ) ) {
			echo sprintf( "<!-- %s -->\n", esc_html( $comment ) ); // xss ok.
		}
	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @return array
	 */
	private function locate_plugin() {
		$dir_url         = trailingslashit( plugins_url( '', dirname( __FILE__ ) ) );
		$dir_path        = plugin_dir_path( dirname( __FILE__ ) );
		$dir_basename    = basename( $dir_path );
		$plugin_basename = trailingslashit( $dir_basename ) . $dir_basename . '.php';

		return compact( 'dir_url', 'dir_path', 'dir_basename', 'plugin_basename' );
	}

	/**
	 * Getter for the version number.
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Change plugin database driver in case driver plugin loaded after stream
	 */
	public function plugins_loaded() {
		// Load DB helper interface/class.
		$driver_class = apply_filters( 'wp_stream_db_driver', '\WP_Stream\DB_Driver_WPDB' );

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}
	}

	/**
	 * Returns true if Stream is network activated, otherwise false
	 *
	 * @return bool
	 */
	public function is_network_activated() {

		$is_network_activated = false;

		if ( $this->is_mustuse() ) {
			$is_network_activated = true;
		} else {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			$is_network_activated = is_plugin_active_for_network( $this->locations['plugin'] );
		}

		/**
		 * Filter allows the network activated detection to be overridden.
		 *
		 * @param string           $is_network_activated  Whether the plugin is network activated.
		 * @param WP_Stream\Plugin $plugin                The stream plugin object.
		 */
		return apply_filters( 'wp_stream_is_network_activated', $is_network_activated, $this );
	}

	/**
	 * Returns true if Stream is a must-use plugin, otherwise false
	 *
	 * @return bool
	 */
	public function is_mustuse() {
		$stream_php = trailingslashit( WPMU_PLUGIN_DIR ) . $this->locations['plugin'];

		if ( file_exists( $stream_php ) && class_exists( 'WP_Stream\Plugin' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the IP address for the current request.
	 *
	 * @return false|null|string Valid IP address, null if not set, false if invalid.
	 */
	public function get_client_ip_address() {
		return apply_filters( 'wp_stream_client_ip_address', $this->client_ip_address );
	}
}
