<?php
/**
 * Connector for Settings
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Settings
 */
class Connector_Settings extends Connector {
	/**
	 * Prefix for the highlight URL hash
	 *
	 * @const string
	 */
	const HIGHLIGHT_FIELD_URL_HASH_PREFIX = 'wp-stream-highlight:';

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'settings';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'allowed_options',
		'update_option',
		'update_site_option',
		'update_option_permalink_structure',
		'update_option_category_base',
		'update_option_tag_base',
	);

	/**
	 * Labels used for WordPress Settings
	 *
	 * @var array
	 */
	public $labels = array();

	/**
	 * Option names used in options-permalink.php
	 *
	 * @var array
	 */
	public $permalink_options = array(
		'permalink_structure',
		'category_base',
		'tag_base',
	);

	/**
	 * Option names used in network/settings.php
	 *
	 * @var array
	 */
	public $network_options = array(
		'registrationnotification',
		'registration',
		'add_new_users',
		'menu_items',
		'upload_space_check_disabled',
		'blog_upload_space',
		'upload_filetypes',
		'site_name',
		'first_post',
		'first_page',
		'first_comment',
		'first_comment_url',
		'first_comment_author',
		'welcome_email',
		'welcome_user_email',
		'fileupload_maxk',
		'global_terms_enabled',
		'illegal_names',
		'limited_email_domains',
		'banned_email_domains',
		'WPLANG',
		'blog_count',
		'user_count',
		'admin_email',
		'new_admin_email',
	);

	/**
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = false;

	/**
	 * Register all context hooks
	 *
	 * @return void
	 */
	public function register() {
		parent::register();

		$this->labels = array(
			// General.
			'blogname'                      => esc_html__( 'Site Title', 'stream' ),
			'blogdescription'               => esc_html__( 'Tagline', 'stream' ),
			'gmt_offset'                    => esc_html__( 'Timezone', 'stream' ),
			'admin_email'                   => esc_html__( 'E-mail Address', 'stream' ),
			'new_admin_email'               => esc_html__( 'E-mail Address', 'stream' ),
			'siteurl'                       => esc_html__( 'WordPress Address (URL)', 'stream' ),
			'home'                          => esc_html__( 'Site Address (URL)', 'stream' ),
			'users_can_register'            => esc_html__( 'Membership', 'stream' ),
			'default_role'                  => esc_html__( 'New User Default Role', 'stream' ),
			'timezone_string'               => esc_html__( 'Timezone', 'stream' ),
			'date_format'                   => esc_html__( 'Date Format', 'stream' ),
			'time_format'                   => esc_html__( 'Time Format', 'stream' ),
			'start_of_week'                 => esc_html__( 'Week Starts On', 'stream' ),
			// Writing.
			'use_smilies'                   => esc_html__( 'Formatting', 'stream' ),
			'use_balanceTags'               => esc_html__( 'Formatting', 'stream' ),
			'default_category'              => esc_html__( 'Default Post Category', 'stream' ),
			'default_post_format'           => esc_html__( 'Default Post Format', 'stream' ),
			'mailserver_url'                => esc_html__( 'Mail Server', 'stream' ),
			'mailserver_login'              => esc_html__( 'Login Name', 'stream' ),
			'mailserver_pass'               => esc_html__( 'Password', 'stream' ),
			'default_email_category'        => esc_html__( 'Default Mail Category', 'stream' ),
			'default_link_category'         => esc_html__( 'Default Link Category', 'stream' ),
			'ping_sites'                    => esc_html__( 'Update Services', 'stream' ),
			// Reading.
			'show_on_front'                 => esc_html__( 'Front page displays', 'stream' ),
			'page_on_front'                 => esc_html__( 'Front page displays', 'stream' ),
			'page_for_posts'                => esc_html__( 'Front page displays', 'stream' ),
			'posts_per_page'                => esc_html__( 'Blog pages show at most', 'stream' ), // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page, because this is not a query.
			'posts_per_rss'                 => esc_html__( 'Syndication feeds show the most recent', 'stream' ),
			'rss_use_excerpt'               => esc_html__( 'For each article in a feed, show', 'stream' ),
			'blog_public'                   => esc_html__( 'Search Engine Visibility', 'stream' ),
			// Discussion.
			'default_pingback_flag'         => esc_html__( 'Default article settings', 'stream' ),
			'default_ping_status'           => esc_html__( 'Default article settings', 'stream' ),
			'default_comment_status'        => esc_html__( 'Default article settings', 'stream' ),
			'require_name_email'            => esc_html__( 'Other comment settings', 'stream' ),
			'comment_registration'          => esc_html__( 'Other comment settings', 'stream' ),
			'close_comments_for_old_posts'  => esc_html__( 'Other comment settings', 'stream' ),
			'close_comments_days_old'       => esc_html__( 'Other comment settings', 'stream' ),
			'thread_comments'               => esc_html__( 'Other comment settings', 'stream' ),
			'thread_comments_depth'         => esc_html__( 'Other comment settings', 'stream' ),
			'page_comments'                 => esc_html__( 'Other comment settings', 'stream' ),
			'comments_per_page'             => esc_html__( 'Other comment settings', 'stream' ),
			'default_comments_page'         => esc_html__( 'Other comment settings', 'stream' ),
			'comment_order'                 => esc_html__( 'Other comment settings', 'stream' ),
			'comments_notify'               => esc_html__( 'E-mail me whenever', 'stream' ),
			'moderation_notify'             => esc_html__( 'E-mail me whenever', 'stream' ),
			'comment_moderation'            => esc_html__( 'Before a comment appears', 'stream' ),
			'comment_whitelist'             => esc_html__( 'Before a comment appears', 'stream' ),
			'comment_max_links'             => esc_html__( 'Comment Moderation', 'stream' ),
			'moderation_keys'               => esc_html__( 'Comment Moderation', 'stream' ),
			'blacklist_keys'                => esc_html__( 'Comment Blacklist', 'stream' ),
			'show_avatars'                  => esc_html__( 'Show Avatars', 'stream' ),
			'avatar_rating'                 => esc_html__( 'Maximum Rating', 'stream' ),
			'avatar_default'                => esc_html__( 'Default Avatar', 'stream' ),
			// Media.
			'thumbnail_size_w'              => esc_html__( 'Thumbnail size', 'stream' ),
			'thumbnail_size_h'              => esc_html__( 'Thumbnail size', 'stream' ),
			'thumbnail_crop'                => esc_html__( 'Thumbnail size', 'stream' ),
			'medium_size_w'                 => esc_html__( 'Medium size', 'stream' ),
			'medium_size_h'                 => esc_html__( 'Medium size', 'stream' ),
			'large_size_w'                  => esc_html__( 'Large size', 'stream' ),
			'large_size_h'                  => esc_html__( 'Large size', 'stream' ),
			'uploads_use_yearmonth_folders' => esc_html__( 'Uploading Files', 'stream' ),
			// Permalinks.
			'permalink_structure'           => esc_html__( 'Permalink Settings', 'stream' ),
			'category_base'                 => esc_html__( 'Category base', 'stream' ),
			'tag_base'                      => esc_html__( 'Tag base', 'stream' ),
			// Network.
			'registrationnotification'      => esc_html__( 'Registration notification', 'stream' ),
			'registration'                  => esc_html__( 'Allow new registrations', 'stream' ),
			'add_new_users'                 => esc_html__( 'Add New Users', 'stream' ),
			'menu_items'                    => esc_html__( 'Enable administration menus', 'stream' ),
			'upload_space_check_disabled'   => esc_html__( 'Site upload space check', 'stream' ),
			'blog_upload_space'             => esc_html__( 'Site upload space', 'stream' ),
			'upload_filetypes'              => esc_html__( 'Upload file types', 'stream' ),
			'site_name'                     => esc_html__( 'Network Title', 'stream' ),
			'first_post'                    => esc_html__( 'First Post', 'stream' ),
			'first_page'                    => esc_html__( 'First Page', 'stream' ),
			'first_comment'                 => esc_html__( 'First Comment', 'stream' ),
			'first_comment_url'             => esc_html__( 'First Comment URL', 'stream' ),
			'first_comment_author'          => esc_html__( 'First Comment Author', 'stream' ),
			'welcome_email'                 => esc_html__( 'Welcome Email', 'stream' ),
			'welcome_user_email'            => esc_html__( 'Welcome User Email', 'stream' ),
			'fileupload_maxk'               => esc_html__( 'Max upload file size', 'stream' ),
			'global_terms_enabled'          => esc_html__( 'Terms Enabled', 'stream' ),
			'illegal_names'                 => esc_html__( 'Banned Names', 'stream' ),
			'limited_email_domains'         => esc_html__( 'Limited Email Registrations', 'stream' ),
			'banned_email_domains'          => esc_html__( 'Banned Email Domains', 'stream' ),
			'WPLANG'                        => esc_html__( 'Network Language', 'stream' ),
			'blog_count'                    => esc_html__( 'Blog Count', 'stream' ),
			'user_count'                    => esc_html__( 'User Count', 'stream' ),
			// Other.
			'wp_stream_db'                  => esc_html__( 'Stream Database Version', 'stream' ),
		);

		// These option labels are special and need to change based on multisite context.
		if ( is_network_admin() ) {
			$this->labels['admin_email']     = esc_html__( 'Network Admin Email', 'stream' );
			$this->labels['new_admin_email'] = esc_html__( 'Network Admin Email', 'stream' );
		}

		add_action( 'admin_head', array( $this, 'highlight_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_jquery_color' ) );
		add_action( sprintf( 'update_option_theme_mods_%s', get_option( 'stylesheet' ) ), array( $this, 'log_theme_modification' ), 10, 2 );
	}

	/**
	 * Logs changes to the theme modifications.
	 *
	 * @action update_option_theme_mods_{name}
	 *
	 * @param mixed $old_value  Old setting value.
	 * @param mixed $new_value  New setting value.
	 */
	public function log_theme_modification( $old_value, $new_value ) {
		$this->callback_updated_option( 'theme_mods', $old_value, $new_value );
	}

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public function get_label() {
		return esc_html__( 'Settings', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'updated' => esc_html__( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		$context_labels = array(
			'settings'          => esc_html__( 'Settings', 'stream' ),
			'general'           => esc_html__( 'General', 'stream' ),
			'writing'           => esc_html__( 'Writing', 'stream' ),
			'reading'           => esc_html__( 'Reading', 'stream' ),
			'discussion'        => esc_html__( 'Discussion', 'stream' ),
			'media'             => esc_html__( 'Media', 'stream' ),
			'permalink'         => esc_html__( 'Permalinks', 'stream' ),
			'network'           => esc_html__( 'Network', 'stream' ),
			'wp_stream'         => esc_html__( 'Stream', 'stream' ),
			'custom_background' => esc_html__( 'Custom Background', 'stream' ),
			'custom_header'     => esc_html__( 'Custom Header', 'stream' ),
		);

		if ( is_network_admin() ) {
			$context_labels = array_merge(
				$context_labels,
				array(
					'wp_stream_network'  => esc_html__( 'Stream Network', 'stream' ),
					'wp_stream_defaults' => esc_html__( 'Stream Defaults', 'stream' ),
				)
			);
		}

		return $context_labels;
	}

	/**
	 * Return context by option name and key
	 *
	 * @param string $option_name  Option name.
	 * @param string $key          Option key.
	 *
	 * @return string Context slug
	 */
	public function get_context_by_key( $option_name, $key ) {
		$contexts = array(
			'theme_mods' => array(
				'custom_background' => array(
					'background_image',
					'background_position_x',
					'background_repeat',
					'background_attachment',
					'background_color',
				),
				'custom_header'     => array(
					'header_image',
					'header_textcolor',
				),
			),
		);

		if ( isset( $contexts[ $option_name ] ) ) {
			foreach ( $contexts[ $option_name ] as $context => $keys ) {
				if ( in_array( $key, $keys, true ) ) {
					return $context;
				}
			}
		}

		return false;
	}

	/**
	 * Find out if the option key should be ignored and not logged
	 *
	 * @param string $option_name  Option name.
	 * @param string $key          Option key.
	 *
	 * @return bool Whether option key is ignored or not
	 */
	public function is_key_ignored( $option_name, $key ) {
		$ignored = array(
			'theme_mods' => array(
				'background_image_thumb',
				'header_image_data',
			),
		);

		if ( isset( $ignored[ $option_name ] ) ) {
			return in_array( $key, $ignored[ $option_name ], true );
		}

		return false;
	}

	/**
	 * Find out if the option should be ignored and not logged
	 *
	 * @param string $option_name  Option name.
	 *
	 * @return bool Whether the option is ignored or not
	 */
	public function is_option_ignored( $option_name ) {
		if ( 0 === strpos( $option_name, '_transient_' ) || 0 === strpos( $option_name, '_site_transient_' ) ) {
			return true;
		}

		if ( '$' === substr( $option_name, -1 ) ) {
			return true;
		}

		$default_ignored = array(
			'image_default_link_type',
			'medium_large_size_w',
			'medium_large_size_h',
		);

		/**
		 * Filters the boolean output for is_option_ignored().
		 *
		 * @param boolean $is_ignored True if ignored, otherwise false.
		 * @param string  $option_name Current option name.
		 * @param array   $default_ignored Default options for Stream to ignore.
		 *
		 * @return boolean
		 */
		return apply_filters(
			'wp_stream_is_option_ignored',
			in_array( $option_name, $default_ignored, true ),
			$option_name,
			$default_ignored
		);
	}

	/**
	 * Find out if array keys in the option should be logged separately
	 *
	 * @param mixed $value  Option value.
	 *
	 * @return bool Whether the option should be treated as a group
	 */
	public function is_option_group( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( 0 === count( array_filter( array_keys( $value ), 'is_string' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return translated labels for all default Settings fields found in WordPress.
	 *
	 * @param string $field_key  Field key.
	 *
	 * @return array Field label translations
	 */
	public function get_field_label( $field_key ) {
		if ( isset( $this->labels[ $field_key ] ) ) {
			return $this->labels[ $field_key ];
		}

		return $field_key;
	}

	/**
	 * Enqueue jQuery Color plugin
	 *
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public function enqueue_jquery_color() {
		wp_enqueue_script( 'jquery-color' );
	}

	/**
	 * Return translated labels for all serialized Settings found in WordPress.
	 *
	 * @param string $option_name  Option name.
	 * @param string $field_key    Field key.
	 *
	 * @return string Field key translation or key itself if not found
	 */
	public function get_serialized_field_label( $option_name, $field_key ) {
		$labels = array(
			'theme_mods' => array(
				// Custom Background.
				'background_image'        => esc_html__( 'Background Image', 'stream' ),
				'background_position_x'   => esc_html__( 'Background Position', 'stream' ),
				'background_repeat'       => esc_html__( 'Background Repeat', 'stream' ),
				'background_attachment'   => esc_html__( 'Background Attachment', 'stream' ),
				'background_color'        => esc_html__( 'Background Color', 'stream' ),
				// Custom Header.
				'header_image'            => esc_html__( 'Header Image', 'stream' ),
				'header_textcolor'        => esc_html__( 'Text Color', 'stream' ),
				'header_background_color' => esc_html__( 'Header and Sidebar Background Color', 'stream' ),
				// Featured Content.
				'featured_content_layout' => esc_html__( 'Layout', 'stream' ),
				// Custom Sidebar.
				'sidebar_textcolor'       => esc_html__( 'Header and Sidebar Text Color', 'stream' ),
				// Custom Colors.
				'color_scheme'            => esc_html__( 'Color Scheme', 'stream' ),
				'main_text_color'         => esc_html__( 'Main Text Color', 'stream' ),
				'secondary_text_color'    => esc_html__( 'Secondary Text Color', 'stream' ),
				'link_color'              => esc_html__( 'Link Color', 'stream' ),
				'page_background_color'   => esc_html__( 'Page Background Color', 'stream' ),
			),
		);

		/**
		 * Filter allows for insertion of serialized labels
		 *
		 * @param  array  $labels  Serialized labels
		 * @return array  Updated array of serialzed labels
		 */
		$labels = apply_filters( 'wp_stream_serialized_labels', $labels );

		if ( isset( $labels[ $option_name ] ) && isset( $labels[ $option_name ][ $field_key ] ) ) {
			return $labels[ $option_name ][ $field_key ];
		}

		return $field_key;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		$context_labels = $this->get_context_labels();
		$plugin         = wp_stream_get_instance();

		$rules = array(
			'stream'            => array(
				'menu_slug'    => 'wp_stream',
				'submenu_slug' => $plugin->admin->settings_page_slug,
				'url'          => function( $rule, $record ) use ( $plugin ) {
					$option_key = $record->get_meta( 'option_key', true );
					$url_tab    = null;

					if ( '' !== $option_key ) {
						foreach ( $plugin->settings->get_fields() as $tab_name => $tab_properties ) {
							foreach ( $tab_properties['fields'] as $field ) {
								$field_key = sprintf( '%s_%s', $tab_name, $field['name'] );
								if ( $field_key === $option_key ) {
									$url_tab = $tab_name;
									break 2;
								}
							}
						}
					}

					return add_query_arg(
						array(
							'page' => $rule['submenu_slug'],
							'tab'  => $url_tab,
						),
						admin_url( 'admin.php' )
					);
				},
				'applicable'   => function( $submenu, $record ) {
					return 'wp_stream' === $record->context;
				},
			),
			'background_header' => array(
				'menu_slug'    => 'themes.php',
				'submenu_slug' => function( $record ) {
					return str_replace( '_', '-', $record->context );
				},
				'url'          => function( $rule, $record ) {
					return add_query_arg( 'page', $rule['submenu_slug']( $record ), admin_url( $rule['menu_slug'] ) );
				},
				'applicable'   => function( $submenu, $record ) {
					return in_array( $record->context, array( 'custom_header', 'custom_background' ), true );
				},
			),
			'general'           => array(
				'menu_slug'    => 'options-general.php',
				'submenu_slug' => function( $record ) {
					return sprintf( 'options-%s.php', $record->context );
				},
				'url'          => function( $rule, $record ) {
					return admin_url( $rule['submenu_slug']( $record ) );
				},
				'applicable'   => function( $submenu, $record ) {
					return ! empty( $submenu['options-general.php'] );
				},
			),
			'network'           => array(
				'menu_slug'    => 'settings.php',
				'submenu_slug' => function( $record ) {
					return 'settings.php';
				},
				'url'          => function( $rule, $record ) {
					return network_admin_url( $rule['menu_slug'] );
				},
				'applicable'   => function( $submenu, $record ) {
					if ( ! $record->blog_id ) {
						return ! empty( $submenu['settings.php'] );
					}
					return false;
				},
			),
		);

		if ( 'settings' !== $record->context && in_array( $record->context, array_keys( $context_labels ), true ) ) {
			global $submenu;

			$applicable_rules = array_filter(
				$rules,
				function( $rule ) use ( $submenu, $record ) {
					return call_user_func( $rule['applicable'], $submenu, $record );
				}
			);

			if ( ! empty( $applicable_rules ) ) {
				// The first applicable rule wins.
				$rule         = array_shift( $applicable_rules );
				$menu_slug    = $rule['menu_slug'];
				$submenu_slug = ( is_object( $rule['submenu_slug'] ) && $rule['submenu_slug'] instanceof \Closure ? $rule['submenu_slug']( $record ) : $rule['submenu_slug'] );
				$url          = $rule['url']( $rule, $record );

				if ( isset( $submenu[ $menu_slug ] ) ) {
					$found_submenus = wp_list_filter(
						$submenu[ $menu_slug ],
						array(
							2 => $submenu_slug,
						)
					);
				}

				if ( ! empty( $found_submenus ) ) {
					$target_submenu                  = array_pop( $found_submenus );
					list( $menu_title, $capability ) = $target_submenu;

					if ( current_user_can( $capability ) ) {
						$url        = apply_filters( 'wp_stream_action_link_url', $url, $record );
						$field_name = $record->get_meta( 'option_key', true );

						/* translators: %s: a context (e.g. "Editor") */
						$text = sprintf( esc_html__( 'Edit %s Settings', 'stream' ), $context_labels[ $record->context ] );

						if ( '' === $field_name ) {
							$field_name = $record->get_meta( 'option', true );
						}

						if ( '' !== $field_name ) {
							$url = sprintf( '%s#%s%s', rtrim( preg_replace( '/#.*/', '', $url ), '/' ), self::HIGHLIGHT_FIELD_URL_HASH_PREFIX, $field_name );
						}

						$links[ $text ] = $url;
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Trigger this connector from WP CLI or the Customizer, only for known Settings
	 *
	 * @action update_option
	 *
	 * @param string $option     Option name.
	 * @param mixed  $old_value  Option old value.
	 * @param mixed  $value      Option new value.
	 */
	public function callback_update_option( $option, $old_value, $value ) {
		if ( ( defined( '\WP_CLI' ) && \WP_CLI || did_action( 'customize_save' ) ) && array_key_exists( $option, $this->labels ) ) {
			$this->callback_updated_option( $option, $old_value, $value );
		}
	}

	/**
	 * Trigger this connector core tracker, only on options.php page
	 *
	 * @action whitelist_options
	 *
	 * @param array $options  Options.
	 *
	 * @return array
	 */
	public function callback_allowed_options( $options ) {
		add_action( 'updated_option', array( $this, 'callback' ), 10, 3 );

		return $options;
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_permalink_structure
	 *
	 * @param mixed $old_value  Option old value.
	 * @param mixed $value      Option new value.
	 */
	public function callback_update_option_permalink_structure( $old_value, $value ) {
		$this->callback_updated_option( 'permalink_structure', $old_value, $value );
	}

	/**
	 * Trigger this connector core tracker, only on network/settings.php page
	 *
	 * @action update_site_option
	 *
	 * @param string $option     Option name.
	 * @param mixed  $value      Option new value.
	 * @param mixed  $old_value  Option old value.
	 */
	public function callback_update_site_option( $option, $value, $old_value ) {
		$this->callback_updated_option( $option, $old_value, $value );
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_category_base
	 *
	 * @param mixed $old_value  Option old value.
	 * @param mixed $value      Option new value.
	 */
	public function callback_update_option_category_base( $old_value, $value ) {
		$this->callback_updated_option( 'category_base', $old_value, $value );
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_tag_base
	 *
	 * @param mixed $old_value  Option old value.
	 * @param mixed $value      Option new value.
	 */
	public function callback_update_option_tag_base( $old_value, $value ) {
		$this->callback_updated_option( 'tag_base', $old_value, $value );
	}

	/**
	 * Track updated settings
	 *
	 * @action updated_option
	 *
	 * @param string $option     Option name.
	 * @param mixed  $old_value  Option old value.
	 * @param mixed  $value      Option new value.
	 */
	public function callback_updated_option( $option, $old_value, $value ) {
		global $whitelist_options, $new_whitelist_options;

		if ( $this->is_option_ignored( $option ) ) {
			return;
		}

		$options = array_merge(
			(array) $whitelist_options,
			(array) $new_whitelist_options,
			array(
				'permalink' => $this->permalink_options,
			),
			array(
				'network' => $this->network_options,
			)
		);

		foreach ( $options as $key => $opts ) {
			if ( in_array( $option, $opts, true ) ) {
				$context = $key;
				break;
			}
		}

		if ( ! isset( $context ) ) {
			$context = 'settings';
		}

		$changed_options = array();

		if ( $this->is_option_group( $value ) ) {
			foreach ( $this->get_changed_keys( $old_value, $value ) as $field_key ) {
				if ( ! $this->is_key_ignored( $option, $field_key ) ) {
					$key_context       = $this->get_context_by_key( $option, $field_key );
					$changed_options[] = array(
						'label'      => $this->get_serialized_field_label( $option, $field_key ),
						'option'     => $option,
						'option_key' => $field_key,
						'context'    => ( false !== $key_context ? $key_context : $context ),
						'old_value'  => isset( $old_value[ $field_key ] ) ? $this->sanitize_value( $old_value[ $field_key ] ) : null,
						'value'      => isset( $value[ $field_key ] ) ? $this->sanitize_value( $value[ $field_key ] ) : null,
					);
				}
			}
		} else {
			$changed_options[] = array(
				'label'     => $this->get_field_label( $option ),
				'option'    => $option,
				'context'   => $context,
				'old_value' => $this->sanitize_value( $old_value ),
				'value'     => $this->sanitize_value( $value ),
			);
		}

		foreach ( $changed_options as $properties ) {
			$this->log(
				/* translators: %s: a setting name (e.g. "Language") */
				__( '"%s" setting was updated', 'stream' ),
				$properties,
				null,
				$properties['context'],
				'updated'
			);
		}
	}

	/**
	 * Add class to highlight field by URL param
	 *
	 * @action admin_head
	 */
	public function highlight_field() {
		?>
		<script>
			(function ($) {
				$(function () {
					var hashPrefix = <?php echo wp_stream_json_encode( self::HIGHLIGHT_FIELD_URL_HASH_PREFIX ); // xss ok. ?>,
						hashFieldName = "",
						fieldNames = [],
						$select2Choices = {},
						$field = {};

					if (location.hash.substr(1, hashPrefix.length) === hashPrefix) {
						hashFieldName = location.hash.substr(hashPrefix.length + 1);
						fieldNames = [hashFieldName];

						$field = $("input, textarea, select").filter(function () {
							return fieldNames.indexOf( $(this).attr("name") ) > -1;
						});

						// try to find wp_stream field
						if ( $field.length === 0 ) {
							fieldNames = [
								"wp_stream_" + hashFieldName,
								"wp_stream[" + hashFieldName + "]"
							];

							$field = $("input, textarea, select, div").filter(function () {
								return fieldNames.indexOf( $(this).attr("id") ) > -1;
							});

							// if the field has been selectified, the list is the one to be colorized
							$select2Choices = $field.find(".select2-choices");
							if ( $select2Choices.length === 1 ) {
								$field = $select2Choices;
							}
						}

						$("html, body")
							.animate({
								scrollTop: ($field.closest("tr").length === 1 ? $field.closest("tr") : $field).offset().top - $("#wpadminbar").height()
							}, 1000, function () {

							$field
								.css("background", $(this).css("background-color"))
								.animate({
									backgroundColor: "#fffedf",
								}, 250);

								$("label")
									.filter(function () {
										return fieldNames.indexOf( $(this).attr("for") ) > -1;
									})
									.animate({
										color: "#d54e21"
									}, 250);
								}
							);
					}
				});
			}(jQuery));
		</script>
		<?php
	}

	/**
	 * Find out if array keys in the option should be logged separately
	 *
	 * @deprecated 3.0.6
	 * @deprecated Use is_option_group()
	 * @see is_option_group()
	 *
	 * @param string $key        Key.
	 * @param mixed  $old_value  Old value.
	 * @param mixed  $value      New value.
	 *
	 * @return bool Whether the option should be treated as a group
	 */
	public function is_key_option_group( $key, $old_value, $value ) {
		_deprecated_function( __FUNCTION__, '3.0.6', 'is_option_group' );
		return $this->is_option_group( $value );
	}

	/**
	 * Sanitize values, so that we don't store complex data, such as arrays or objects
	 *
	 * @param mixed $value  Raw input value.
	 * @return string
	 */
	public function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			return '';
		} elseif ( is_object( $value ) && ! in_array( '__toString', get_class_methods( $value ), true ) ) {
			return '';
		}

		return strval( $value );
	}
}
