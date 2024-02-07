<?php
/**
 * Connector for WordPress SEO
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_WordPress_SEO
 */
class Connector_WordPress_SEO extends Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'wordpressseo';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '1.5.3.3';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'wpseo_handle_import',
		'wpseo_import',
		'seo_page_wpseo_files',
		'added_post_meta',
		'updated_post_meta',
		'deleted_post_meta',
	);

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public $option_groups = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( defined( 'WPSEO_VERSION' ) && version_compare( WPSEO_VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html_x( 'WordPress SEO', 'wordpress-seo', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'  => esc_html_x( 'Created', 'wordpress-seo', 'stream' ),
			'updated'  => esc_html_x( 'Updated', 'wordpress-seo', 'stream' ),
			'added'    => esc_html_x( 'Added', 'wordpress-seo', 'stream' ),
			'deleted'  => esc_html_x( 'Deleted', 'wordpress-seo', 'stream' ),
			'exported' => esc_html_x( 'Exported', 'wordpress-seo', 'stream' ),
			'imported' => esc_html_x( 'Imported', 'wordpress-seo', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'wpseo_dashboard'               => esc_html_x( 'Dashboard', 'wordpress-seo', 'stream' ),
			'wpseo_titles'                  => _x( 'Titles &amp; Metas', 'wordpress-seo', 'stream' ),
			'wpseo_social'                  => esc_html_x( 'Social', 'wordpress-seo', 'stream' ),
			'wpseo_xml'                     => esc_html_x( 'XML Sitemaps', 'wordpress-seo', 'stream' ),
			'wpseo_permalinks'              => esc_html_x( 'Permalinks', 'wordpress-seo', 'stream' ),
			'wpseo_internal-links'          => esc_html_x( 'Internal Links', 'wordpress-seo', 'stream' ),
			'wpseo_advanced'                => esc_html_x( 'Advanced', 'wordpress-seo', 'stream' ),
			'wpseo_rss'                     => esc_html_x( 'RSS', 'wordpress-seo', 'stream' ),
			'wpseo_import'                  => esc_html_x( 'Import & Export', 'wordpress-seo', 'stream' ),
			'wpseo_bulk-title-editor'       => esc_html_x( 'Bulk Title Editor', 'wordpress-seo', 'stream' ),
			'wpseo_bulk-description-editor' => esc_html_x( 'Bulk Description Editor', 'wordpress-seo', 'stream' ),
			'wpseo_files'                   => esc_html_x( 'Files', 'wordpress-seo', 'stream' ),
			'wpseo_meta'                    => esc_html_x( 'Content', 'wordpress-seo', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		// Options.
		$option = $record->get_meta( 'option', true );
		if ( $option ) {
			$key = $record->get_meta( 'option_key', true );

			$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			) . '#stream-highlight-' . esc_attr( $key );
		} elseif ( 'wpseo_files' === $record->context ) {
			$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'wpseo_meta' === $record->context ) {
			$post = get_post( $record->object_id );

			if ( $post ) {
				$posts_connector = new Connector_Posts();
				$post_type_name  = $posts_connector->get_post_type_name( get_post_type( $post->ID ) );

				if ( 'trash' === $post->post_status ) {
					$untrash = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'untrash',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'untrash-post_%d', $post->ID )
					);

					$delete = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'delete',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'delete-post_%d', $post->ID )
					);

					/* translators: %s: a post type singular name (e.g. "Post") */
					$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $untrash;
					/* translators: %s: a post type singular name (e.g. "Post") */
					$links[ sprintf( esc_html_x( 'Delete %s Permanently', 'Post type singular name', 'stream' ), $post_type_name ) ] = $delete;
				} else {
					/* translators: %s: a post type singular name (e.g. "Post") */
					$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

					$view_link = get_permalink( $post->ID );
					if ( $view_link ) {
						$links[ esc_html__( 'View', 'stream' ) ] = $view_link;
					}

					$revision_id = $record->get_meta( 'revision_id', true );
					if ( $revision_id ) {
						$links[ esc_html__( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Register connection
	 */
	public function register() {
		if ( is_network_admin() && ! is_plugin_active_for_network( 'wordpress-seo/wordpress-seo-main.php' ) ) {
			return;
		}
		parent::register();

		foreach ( \WPSEO_Options::$options as $class ) {
			/**
			 * WPSEO Options object.
			 *
			 * @var WPSEO_Options $class
			 */
			$this->option_groups[ $class::get_instance()->group_name ] = array(
				'class' => $class,
				'name'  => $class::get_instance()->option_name,
			);
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );
	}

	/**
	 * Register admin scripts.
	 *
	 * @param string $hook  Current hook.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 0 === strpos( $hook, 'seo_page_' ) ) {
			$stream = wp_stream_get_instance();
			$src    = $stream->locations['url'] . '/ui/js/wpseo-admin.js';
			wp_enqueue_script(
				'stream-connector-wpseo',
				$src,
				array( 'jquery' ),
				$stream->get_version(),
				false
			);
		}
	}

	/**
	 * Track importing settings from other plugins
	 *
	 * @action wpseo_handle_import
	 */
	public function callback_wpseo_handle_import() {
		$imports = array(
			'importheadspace'   => esc_html__( 'HeadSpace2', 'stream' ), // type = checkbox.
			'importaioseo'      => esc_html__( 'All-in-One SEO', 'stream' ), // type = checkbox.
			'importaioseoold'   => esc_html__( 'OLD All-in-One SEO', 'stream' ), // type = checkbox.
			'importwoo'         => esc_html__( 'WooThemes SEO framework', 'stream' ), // type = checkbox.
			'importrobotsmeta'  => esc_html__( 'Robots Meta (by Yoast)', 'stream' ), // type = checkbox.
			'importrssfooter'   => esc_html__( 'RSS Footer (by Yoast)', 'stream' ), // type = checkbox.
			'importbreadcrumbs' => esc_html__( 'Yoast Breadcrumbs', 'stream' ), // type = checkbox.
		);

		$opts = wp_stream_filter_input( INPUT_POST, 'wpseo' );

		foreach ( $imports as $key => $name ) {
			if ( isset( $opts[ $key ] ) ) {
				$this->log(
					sprintf(
						/* translators: %1$s: an import method, %2$s: an extra string (sometimes blank) (e.g. "HeadSpace2", ", and deleted old data") */
						__( 'Imported settings from %1$s%2$s', 'stream' ),
						$name,
						isset( $opts['deleteolddata'] ) ? esc_html__( ', and deleted old data', 'stream' ) : ''
					),
					array(
						'key'           => $key,
						'deleteolddata' => isset( $opts['deleteolddata'] ),
					),
					null,
					'wpseo_import',
					'imported'
				);
			}
		}
	}

	/**
	 * Track importing settings
	 *
	 * @callback wpseo_import
	 */
	public function callback_wpseo_import() {
		$opts = wp_stream_filter_input( INPUT_POST, 'wpseo' );

		if ( wp_stream_filter_input( INPUT_POST, 'wpseo_export' ) ) {
			$this->log(
				sprintf(
					/* translators: %s: an extra string (sometimes blank) (e.g. ", including taxonomy meta") */
					__( 'Exported settings%s', 'stream' ),
					isset( $opts['include_taxonomy_meta'] ) ? esc_html__( ', including taxonomy meta', 'stream' ) : ''
				),
				array(
					'include_taxonomy_meta' => isset( $opts['include_taxonomy_meta'] ),
				),
				null,
				'wpseo_import',
				'exported'
			);
		} elseif ( isset( $_FILES['settings_import_file']['name'] ) ) { // phpcs: input var okay.
			$this->log(
				sprintf(
					/* translators: %s: a filename (e.g. "test.xml") */
					__( 'Tried importing settings from "%s"', 'stream' ),
					sanitize_text_field( wp_unslash( $_FILES['settings_import_file']['name'] ) ) // phpcs: input var okay.
				),
				array(
					'file' => sanitize_text_field( wp_unslash( $_FILES['settings_import_file']['name'] ) ), // phpcs: input var okay.
				),
				null,
				'wpseo_import',
				'exported'
			);
		}
	}

	/**
	 * Tracks creation of SEO-related files.
	 *
	 * @action seo_page_wpseo_files
	 */
	public function callback_seo_page_wpseo_files() {
		if ( wp_stream_filter_input( INPUT_POST, 'create_robots' ) ) {
			$message = esc_html__( 'Tried creating robots.txt file', 'stream' );
		} elseif ( wp_stream_filter_input( INPUT_POST, 'submitrobots' ) ) {
			$message = esc_html__( 'Tried updating robots.txt file', 'stream' );
		} elseif ( wp_stream_filter_input( INPUT_POST, 'submithtaccess' ) ) {
			$message = esc_html__( 'Tried updating htaccess file', 'stream' );
		}

		if ( isset( $message ) ) {
			$this->log(
				$message,
				array(),
				null,
				'wpseo_files',
				'updated'
			);
		}
	}

	/**
	 * Tracks the creation of WordPress SEO post meta
	 *
	 * @action added_post_meta
	 *
	 * @param int    $meta_id     Meta ID.
	 * @param int    $object_id   Object ID.
	 * @param string $meta_key    Meta key.
	 * @param string $meta_value  Meta value.
	 */
	public function callback_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Tracks the updates to WordPress SEO post meta
	 *
	 * @action updated_post_meta
	 *
	 * @param int    $meta_id     Meta ID.
	 * @param int    $object_id   Object ID.
	 * @param string $meta_key    Meta key.
	 * @param string $meta_value  Meta value.
	 */
	public function callback_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Tracks the deletions of WordPress SEO post meta
	 *
	 * @action deleted_post_meta
	 *
	 * @param int    $meta_id     Meta ID.
	 * @param int    $object_id   Object ID.
	 * @param string $meta_key    Meta key.
	 * @param string $meta_value  Meta value.
	 */
	public function callback_deleted_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		$this->meta( $object_id, $meta_key, $meta_value );
	}

	/**
	 * Logs WordPress SEO meta activity
	 *
	 * @param int    $object_id   Object ID.
	 * @param int    $meta_key    Meta key.
	 * @param string $meta_value  Meta value.
	 */
	private function meta( $object_id, $meta_key, $meta_value ) {
		$prefix = \WPSEO_Meta::$meta_prefix;

		\WPSEO_Metabox::translate_meta_boxes();

		if ( 0 !== strpos( $meta_key, $prefix ) ) {
			return;
		}

		$key = str_replace( $prefix, '', $meta_key );

		foreach ( \WPSEO_Meta::$meta_fields as $tab => $fields ) {
			if ( isset( $fields[ $key ] ) ) {
				$field = $fields[ $key ];
				break;
			}
		}

		if ( ! isset( $field, $field['title'], $tab ) || '' === $field['title'] ) {
			return;
		}

		$post            = get_post( $object_id );
		$post_type_label = get_post_type_labels( get_post_type_object( $post->post_type ) )->singular_name;

		$this->log(
			sprintf(
				/* translators: %1$s: a meta field title, %2$s: a post title, %3$s: a post type (e.g. "Description", "Hello World", "Post") */
				__( 'Updated "%1$s" of "%2$s" %3$s', 'stream' ),
				$field['title'],
				$post->post_title,
				$post_type_label
			),
			array(
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
				'post_type'  => $post->post_type,
			),
			$object_id,
			'wpseo_meta',
			'updated'
		);
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data  Record data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		global $pagenow;

		if ( 'options.php' === $pagenow && 'settings' === $data['connector'] && wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ) ) {
			if ( ! isset( $data['args']['context'] ) || ! isset( $this->option_groups[ $data['args']['context'] ] ) ) {
				return $data;
			}

			$page   = preg_match( '#page=([^&]*)#', wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ), $match ) ? $match[1] : '';
			$labels = $this->get_context_labels();

			if ( ! isset( $labels[ $page ] ) ) {
				return $data;
			}

			$label = $this->settings_labels( $data['args']['option_key'] );
			if ( ! $label ) {
				/* translators: %s: a context (e.g. "Dashboard") */
				$data['message'] = esc_html__( '%s settings updated', 'stream' );
				$label           = $labels[ $page ];
			}

			$data['args']['label']   = $label;
			$data['args']['context'] = $page;
			$data['context']         = $page;
			$data['connector']       = $this->name;
		}

		return $data;
	}

	/**
	 * Return the labels
	 *
	 * @param string $option  Name of option to be retrieved.
	 *
	 * @return array|bool.
	 */
	private function settings_labels( $option ) {
		$labels = array(
			// wp-content/plugins/wordpress-seo/admin/pages/dashboard.php:.
			'yoast_tracking'                         => esc_html_x( "Allow tracking of this WordPress install's anonymous data.", 'wordpress-seo', 'stream' ), // type = checkbox.
			'disableadvanced_meta'                   => esc_html_x( 'Disable the Advanced part of the WordPress SEO meta box', 'wordpress-seo', 'stream' ), // type = checkbox.
			'alexaverify'                            => esc_html_x( 'Alexa Verification ID', 'wordpress-seo', 'stream' ), // type = textinput.
			'msverify'                               => esc_html_x( 'Bing Webmaster Tools', 'wordpress-seo', 'stream' ), // type = textinput.
			'googleverify'                           => esc_html_x( 'Google Webmaster Tools', 'wordpress-seo', 'stream' ), // type = textinput.
			'pinterestverify'                        => esc_html_x( 'Pinterest', 'wordpress-seo', 'stream' ), // type = textinput.
			'yandexverify'                           => esc_html_x( 'Yandex Webmaster Tools', 'wordpress-seo', 'stream' ), // type = textinput.

			// wp-content/plugins/wordpress-seo/admin/pages/advanced.php:.
			'breadcrumbs-enable'                     => esc_html_x( 'Enable Breadcrumbs', 'wordpress-seo', 'stream' ), // type = checkbox.
			'breadcrumbs-sep'                        => esc_html_x( 'Separator between breadcrumbs', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-home'                       => esc_html_x( 'Anchor text for the Homepage', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-prefix'                     => esc_html_x( 'Prefix for the breadcrumb path', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-archiveprefix'              => esc_html_x( 'Prefix for Archive breadcrumbs', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-searchprefix'               => esc_html_x( 'Prefix for Search Page breadcrumbs', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-404crumb'                   => esc_html_x( 'Breadcrumb for 404 Page', 'wordpress-seo', 'stream' ), // type = textinput.
			'breadcrumbs-blog-remove'                => esc_html_x( 'Remove Blog page from Breadcrumbs', 'wordpress-seo', 'stream' ), // type = checkbox.
			'breadcrumbs-boldlast'                   => esc_html_x( 'Bold the last page in the breadcrumb', 'wordpress-seo', 'stream' ), // type = checkbox.
			'post_types-post-maintax'                => esc_html_x( 'Taxonomy to show in breadcrumbs for post types', 'wordpress-seo', 'stream' ), // type = select.

			// wp-content/plugins/wordpress-seo/admin/pages/metas.php:.
			'forcerewritetitle'                      => esc_html_x( 'Force rewrite titles', 'wordpress-seo', 'stream' ), // type = checkbox.
			'noindex-subpages-wpseo'                 => esc_html_x( 'Noindex subpages of archives', 'wordpress-seo', 'stream' ), // type = checkbox.
			'usemetakeywords'                        => _x( 'Use <code>meta</code> keywords tag?', 'wordpress-seo', 'stream' ), // type = checkbox.
			'noodp'                                  => _x( 'Add <code>noodp</code> meta robots tag sitewide', 'wordpress-seo', 'stream' ), // type = checkbox.
			'noydir'                                 => _x( 'Add <code>noydir</code> meta robots tag sitewide', 'wordpress-seo', 'stream' ), // type = checkbox.
			'hide-rsdlink'                           => esc_html_x( 'Hide RSD Links', 'wordpress-seo', 'stream' ), // type = checkbox.
			'hide-wlwmanifest'                       => esc_html_x( 'Hide WLW Manifest Links', 'wordpress-seo', 'stream' ), // type = checkbox.
			'hide-shortlink'                         => esc_html_x( 'Hide Shortlink for posts', 'wordpress-seo', 'stream' ), // type = checkbox.
			'hide-feedlinks'                         => esc_html_x( 'Hide RSS Links', 'wordpress-seo', 'stream' ), // type = checkbox.
			'disable-author'                         => esc_html_x( 'Disable the author archives', 'wordpress-seo', 'stream' ), // type = checkbox.
			'disable-date'                           => esc_html_x( 'Disable the date-based archives', 'wordpress-seo', 'stream' ), // type = checkbox.

			// wp-content/plugins/wordpress-seo/admin/pages/network.php:.
			'access'                                 => esc_html_x( 'Who should have access to the WordPress SEO settings', 'wordpress-seo', 'stream' ), // type = select.
			'defaultblog'                            => esc_html_x( 'New blogs get the SEO settings from this blog', 'wordpress-seo', 'stream' ), // type = textinput.
			'restoreblog'                            => esc_html_x( 'Blog ID', 'wordpress-seo', 'stream' ), // type = textinput.

			// wp-content/plugins/wordpress-seo/admin/pages/permalinks.php:.
			'stripcategorybase'                      => _x( 'Strip the category base (usually <code>/category/</code>) from the category URL.', 'wordpress-seo', 'stream' ), // type = checkbox.
			'trailingslash'                          => esc_html_x( "Enforce a trailing slash on all category and tag URL's", 'wordpress-seo', 'stream' ), // type = checkbox.
			'cleanslugs'                             => esc_html_x( 'Remove stop words from slugs.', 'wordpress-seo', 'stream' ), // type = checkbox.
			'redirectattachment'                     => esc_html_x( "Redirect attachment URL's to parent post URL.", 'wordpress-seo', 'stream' ), // type = checkbox.
			'cleanreplytocom'                        => _x( 'Remove the <code>?replytocom</code> variables.', 'wordpress-seo', 'stream' ), // type = checkbox.
			'cleanpermalinks'                        => esc_html_x( "Redirect ugly URL's to clean permalinks. (Not recommended in many cases!)", 'wordpress-seo', 'stream' ), // type = checkbox.
			'force_transport'                        => esc_html_x( 'Force Transport', 'wordpress-seo', 'stream' ), // type = select.
			'cleanpermalink-googlesitesearch'        => esc_html_x( "Prevent cleaning out Google Site Search URL's.", 'wordpress-seo', 'stream' ), // type = checkbox.
			'cleanpermalink-googlecampaign'          => esc_html_x( 'Prevent cleaning out Google Analytics Campaign & Google AdWords Parameters.', 'wordpress-seo', 'stream' ), // type = checkbox.
			'cleanpermalink-extravars'               => esc_html_x( 'Other variables not to clean', 'wordpress-seo', 'stream' ), // type = textinput.

			// wp-content/plugins/wordpress-seo/admin/pages/social.php:.
			'opengraph'                              => esc_html_x( 'Add Open Graph meta data', 'wordpress-seo', 'stream' ), // type = checkbox.
			'facebook_site'                          => esc_html_x( 'Facebook Page URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'instagram_url'                          => esc_html_x( 'Instagram URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'linkedin_url'                           => esc_html_x( 'LinkedIn URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'myspace_url'                            => esc_html_x( 'MySpace URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'pinterest_url'                          => esc_html_x( 'Pinterest URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'youtube_url'                            => esc_html_x( 'YouTube URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'google_plus_url'                        => esc_html_x( 'Google+ URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'og_frontpage_image'                     => esc_html_x( 'Image URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'og_frontpage_desc'                      => esc_html_x( 'Description', 'wordpress-seo', 'stream' ), // type = textinput.
			'og_frontpage_title'                     => esc_html_x( 'Title', 'wordpress-seo', 'stream' ), // type = textinput.
			'og_default_image'                       => esc_html_x( 'Image URL', 'wordpress-seo', 'stream' ), // type = textinput.
			'twitter'                                => esc_html_x( 'Add Twitter card meta data', 'wordpress-seo', 'stream' ), // type = checkbox.
			'twitter_site'                           => esc_html_x( 'Site Twitter Username', 'wordpress-seo', 'stream' ), // type = textinput.
			'twitter_card_type'                      => esc_html_x( 'The default card type to use', 'wordpress-seo', 'stream' ), // type = select.
			'googleplus'                             => esc_html_x( 'Add Google+ specific post meta data (excluding author metadata)', 'wordpress-seo', 'stream' ), // type = checkbox.
			'plus-publisher'                         => esc_html_x( 'Google Publisher Page', 'wordpress-seo', 'stream' ), // type = textinput.
			'fbadminapp'                             => esc_html_x( 'Facebook App ID', 'wordpress-seo', 'stream' ), // type = textinput.

			// wp-content/plugins/wordpress-seo/admin/pages/xml-sitemaps.php:.
			'enablexmlsitemap'                       => esc_html_x( 'Check this box to enable XML sitemap functionality.', 'wordpress-seo', 'stream' ), // type = checkbox.
			'disable_author_sitemap'                 => esc_html_x( 'Disable author/user sitemap', 'wordpress-seo', 'stream' ), // type = checkbox.
			'disable_author_noposts'                 => esc_html_x( 'Users with zero posts', 'wordpress-seo', 'stream' ), // type = checkbox.
			'user_role-administrator-not_in_sitemap' => esc_html_x( 'Filter specific user roles - Administrator', 'wordpress-seo', 'stream' ), // type = checkbox.
			'user_role-editor-not_in_sitemap'        => esc_html_x( 'Filter specific user roles - Editor', 'wordpress-seo', 'stream' ), // type = checkbox.
			'user_role-author-not_in_sitemap'        => esc_html_x( 'Filter specific user roles - Author', 'wordpress-seo', 'stream' ), // type = checkbox.
			'user_role-contributor-not_in_sitemap'   => esc_html_x( 'Filter specific user roles - Contributor', 'wordpress-seo', 'stream' ), // type = checkbox.
			'user_role-subscriber-not_in_sitemap'    => esc_html_x( 'Filter specific user roles - Subscriber', 'wordpress-seo', 'stream' ), // type = checkbox.
			'xml_ping_yahoo'                         => esc_html_x( 'Ping Yahoo!', 'wordpress-seo', 'stream' ), // type = checkbox.
			'xml_ping_ask'                           => esc_html_x( 'Ping Ask.com', 'wordpress-seo', 'stream' ), // type = checkbox.
			'entries-per-page'                       => esc_html_x( 'Max entries per sitemap page', 'wordpress-seo', 'stream' ), // type = textinput.
			'excluded-posts'                         => esc_html_x( 'Posts to exclude', 'wordpress-seo', 'stream' ), // type = textinput.
			'post_types-post-not_in_sitemap'         => _x( 'Post Types Posts (<code>post</code>)', 'wordpress-seo', 'stream' ), // type = checkbox.
			'post_types-page-not_in_sitemap'         => _x( 'Post Types Pages (<code>page</code>)', 'wordpress-seo', 'stream' ), // type = checkbox.
			'post_types-attachment-not_in_sitemap'   => _x( 'Post Types Media (<code>attachment</code>)', 'wordpress-seo', 'stream' ), // type = checkbox.
			'taxonomies-category-not_in_sitemap'     => _x( 'Taxonomies Categories (<code>category</code>)', 'wordpress-seo', 'stream' ), // type = checkbox.
			'taxonomies-post_tag-not_in_sitemap'     => _x( 'Taxonomies Tags (<code>post_tag</code>)', 'wordpress-seo', 'stream' ), // type = checkbox.

			// Added manually.
			'rssbefore'                              => esc_html_x( 'Content to put before each post in the feed', 'wordpress-seo', 'stream' ),
			'rssafter'                               => esc_html_x( 'Content to put after each post', 'wordpress-seo', 'stream' ),
		);

		$ast_labels = array(
			'title-'        => esc_html_x( 'Title template', 'wordpress-seo', 'stream' ), // type = textinput.
			'metadesc-'     => esc_html_x( 'Meta description template', 'wordpress-seo', 'stream' ), // type = textarea.
			'metakey-'      => esc_html_x( 'Meta keywords template', 'wordpress-seo', 'stream' ), // type = textinput.
			'noindex-'      => esc_html_x( 'Meta Robots', 'wordpress-seo', 'stream' ), // type = checkbox.
			'noauthorship-' => esc_html_x( 'Authorship', 'wordpress-seo', 'stream' ), // type = checkbox.
			'showdate-'     => esc_html_x( 'Show date in snippet preview?', 'wordpress-seo', 'stream' ), // type = checkbox.
			'hideeditbox-'  => esc_html_x( 'WordPress SEO Meta Box', 'wordpress-seo', 'stream' ), // type = checkbox.
			'bctitle-'      => esc_html_x( 'Breadcrumbs Title', 'wordpress-seo', 'stream' ), // type = textinput.
			'post_types-'   => esc_html_x( 'Post types', 'wordpress-seo', 'stream' ), // type = checkbox.
			'taxonomies-'   => esc_html_x( 'Taxonomies', 'wordpress-seo', 'stream' ), // type = checkbox.
		);

		if ( $option ) {
			if ( isset( $labels[ $option ] ) ) {
				return $labels[ $option ];
			} else {
				foreach ( $ast_labels as $key => $trans ) {
					if ( 0 === strpos( $option, $key ) ) {
						return $trans;
					}
				}

				return false;
			}
		}

		return $labels;
	}
}
