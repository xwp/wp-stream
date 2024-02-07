<?php
/**
 * Connector for WooCommerce
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_WooCommerce
 */
class Connector_Woocommerce extends Connector {
	/**
	 * Context name
	 *
	 * @var string
	 */
	public $name = 'woocommerce';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '2.1.10';

	/**
	 * Actions registered for this context
	 *
	 * @var array
	 */
	public $actions = array(
		'wp_stream_record_array',
		'updated_option',
		'transition_post_status',
		'deleted_post',
		'woocommerce_order_status_changed',
		'woocommerce_attribute_added',
		'woocommerce_attribute_updated',
		'woocommerce_attribute_deleted',
		'woocommerce_tax_rate_added',
		'woocommerce_tax_rate_updated',
		'woocommerce_tax_rate_deleted',
	);

	/**
	 * Taxonomies tracked by this connector.
	 *
	 * @var array
	 */
	public $taxonomies = array(
		'product_type',
		'product_cat',
		'product_tag',
		'product_shipping_class',
		'shop_order_status',
	);

	/**
	 * Post-types tracked by this connector.
	 *
	 * @var array
	 */
	public $post_types = array(
		'product',
		'product_variation',
		'shop_order',
		'shop_coupon',
	);

	/**
	 * Is the most recently update order logged yet.
	 *
	 * @var boolean
	 */
	private $order_update_logged = false;

	/**
	 * Caches WooCommerce settings page objects.
	 *
	 * @var array
	 */
	private $settings_pages = array();

	/**
	 * Caches WooCommerce settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Stores the WooCommerce version number.
	 *
	 * @var string|null
	 */
	private $plugin_version = null;

	/**
	 * Register connection
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_stream_posts_exclude_post_types', array( $this, 'exclude_order_post_types' ) );
		add_action( 'wp_stream_comments_exclude_comment_types', array( $this, 'exclude_order_comment_types' ) );

		$this->get_woocommerce_settings_fields();
	}

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) && version_compare( $woocommerce->version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			$this->plugin_version = $woocommerce->version;
			return true;
		}

		return false;
	}

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public function get_label() {
		return esc_html_x( 'WooCommerce', 'woocommerce', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'updated' => esc_html_x( 'Updated', 'woocommerce', 'stream' ),
			'created' => esc_html_x( 'Created', 'woocommerce', 'stream' ),
			'trashed' => esc_html_x( 'Trashed', 'woocommerce', 'stream' ),
			'deleted' => esc_html_x( 'Deleted', 'woocommerce', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		$context_labels = array();

		if ( class_exists( 'Connector_Posts' ) ) {
			$posts_connector = new Connector_Posts();
			$context_labels  = array_merge(
				$context_labels,
				$posts_connector->get_context_labels()
			);
		}

		$custom_context_labels = array(
			'attributes' => esc_html_x( 'Attributes', 'woocommerce', 'stream' ),
		);

		$context_labels = array_merge(
			$context_labels,
			$custom_context_labels,
			$this->settings_pages
		);

		return apply_filters( 'wp_stream_woocommerce_contexts', $context_labels );
	}

	/**
	 * Return settings used by WooCommerce that aren't registered
	 *
	 * @return array Custom settings with translated title and page
	 */
	public function get_custom_settings() {
		$custom_settings = array(
			'woocommerce_frontend_css_colors'     => array(
				'title'   => esc_html__( 'Frontend Styles', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'general',
				'section' => '',
				'type'    => esc_html__( 'setting', 'stream' ),
			),
			'woocommerce_default_gateway'         => array(
				'title'   => esc_html__( 'Gateway Display Default', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => esc_html__( 'setting', 'stream' ),
			),
			'woocommerce_gateway_order'           => array(
				'title'   => esc_html__( 'Gateway Display Order', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => '',
				'type'    => esc_html__( 'setting', 'stream' ),
			),
			'woocommerce_default_shipping_method' => array(
				'title'   => esc_html__( 'Shipping Methods Default', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => esc_html__( 'setting', 'stream' ),
			),
			'woocommerce_shipping_method_order'   => array(
				'title'   => esc_html__( 'Shipping Methods Order', 'stream' ),
				'page'    => 'wc-settings',
				'tab'     => 'shipping',
				'section' => '',
				'type'    => esc_html__( 'setting', 'stream' ),
			),
			'shipping_debug_mode'                 => array(
				'title'   => esc_html__( 'Shipping Debug Mode', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'stream' ),
			),
			'template_debug_mode'                 => array(
				'title'   => esc_html__( 'Template Debug Mode', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'stream' ),
			),
			'uninstall_data'                      => array(
				'title'   => esc_html__( 'Remove post types on uninstall', 'stream' ),
				'page'    => 'wc-status',
				'tab'     => 'tools',
				'section' => '',
				'type'    => esc_html__( 'tool', 'stream' ),
			),
		);

		return apply_filters( 'wp_stream_woocommerce_custom_settings', $custom_settings );
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
		if ( in_array( $record->context, $this->post_types, true ) && get_post( $record->object_id ) ) {
			$edit_post_link = get_edit_post_link( $record->object_id );
			if ( $edit_post_link ) {
				$posts_connector = new Connector_Posts();
				$post_type_name  = $posts_connector->get_post_type_name( get_post_type( $record->object_id ) );
				/* translators: %s: a post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $edit_post_link;
			}

			$permalink = get_permalink( $record->object_id );
			if ( post_type_exists( get_post_type( $record->object_id ) ) && $permalink ) {
				$links[ esc_html__( 'View', 'stream' ) ] = $permalink;
			}
		}

		$context_labels = $this->get_context_labels();
		$option_key     = $record->get_meta( 'option', true );
		$option_page    = $record->get_meta( 'page', true );
		$option_tab     = $record->get_meta( 'tab', true );
		$option_section = $record->get_meta( 'section', true );

		if ( $option_key && $option_tab ) {
			/* translators: %s a context (e.g. "Attribute") */
			$text = sprintf( esc_html__( 'Edit WooCommerce %s', 'stream' ), $context_labels[ $record->context ] );
			$url  = add_query_arg(
				array(
					'page'    => $option_page,
					'tab'     => $option_tab,
					'section' => $option_section,
				),
				admin_url( 'admin.php' ) // Not self_admin_url here, as WooCommerce doesn't exist in Network Admin.
			);

			$links[ $text ] = $url . '#wp-stream-highlight:' . $option_key;
		}

		return $links;
	}

	/**
	 * Prevent the Stream Posts connector from logging orders
	 * so that we can handle them differently here
	 *
	 * @filter wp_stream_posts_exclude_post_types
	 *
	 * @param array $post_types  Ignored post types.
	 *
	 * @return array Filtered post types
	 */
	public function exclude_order_post_types( $post_types ) {
		$post_types[] = 'shop_order';

		return $post_types;
	}

	/**
	 * Prevent the Stream Comments connector from logging status
	 * change comments on orders
	 *
	 * @filter wp_stream_comment_exclude_comment_types
	 *
	 * @param array $comment_types  Ignored post types.
	 *
	 * @return array Filtered post types
	 */
	public function exclude_order_comment_types( $comment_types ) {
		$comment_types[] = 'order_note';

		return $comment_types;
	}

	/**
	 * Log Order major status changes ( creating / updating / trashing )
	 *
	 * @action transition_post_status
	 *
	 * @param string   $new   New status.
	 * @param string   $old   Old status.
	 * @param \WP_Post $post  Post object.
	 */
	public function callback_transition_post_status( $new, $old, $post ) {
		// Only track orders.
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		// Don't track customer actions.
		if ( ! is_admin() ) {
			return;
		}

		// Don't track minor status change actions.
		if ( in_array( wp_stream_filter_input( INPUT_GET, 'action' ), array( 'mark_processing', 'mark_on-hold', 'mark_completed' ), true ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Don't log updates when more than one happens at the same time.
		if ( $post->ID === $this->order_update_logged ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'draft', 'inherit' ), true ) ) {
			return;
		} elseif ( 'auto-draft' === $old && 'publish' === $new ) {
			/* translators: %s: an order title (e.g. "Order #42") */
			$message = esc_html_x(
				'%s created',
				'Order title',
				'stream'
			);
			$action  = 'created';
		} elseif ( 'trash' === $new ) {
			/* translators: %s: an order title (e.g. "Order #42") */
			$message = esc_html_x(
				'%s trashed',
				'Order title',
				'stream'
			);
			$action  = 'trashed';
		} elseif ( 'trash' === $old && 'publish' === $new ) {
			/* translators: %s: an order title (e.g. "Order #42") */
			$message = esc_html_x(
				'%s restored from the trash',
				'Order title',
				'stream'
			);
			$action  = 'untrashed';
		} else {
			/* translators: %s: an order title (e.g. "Order #42") */
			$message = esc_html_x(
				'%s updated',
				'Order title',
				'stream'
			);
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$order           = new \WC_Order( $post->ID );
		$order_title     = esc_html__( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'stream' );

		$this->log(
			$message,
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => null,
			),
			$post->ID,
			$post->post_type,
			$action
		);

		$this->order_update_logged = $post->ID;
	}

	/**
	 * Log order deletion
	 *
	 * @action deleted_post
	 *
	 * @param int $post_id  Post ID.
	 */
	public function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing.
		if ( ! ( $post instanceof \WP_Post ) || 'shop_order' !== $post->post_type ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$order           = new \WC_Order( $post->ID );
		$order_title     = esc_html__( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'stream' );

		$this->log(
			/* translators: %s: an order title (e.g. "Order #42") */
			_x(
				'"%s" deleted from trash',
				'Order title',
				'stream'
			),
			array(
				'post_title'    => $order_title,
				'singular_name' => $order_type_name,
			),
			$post->ID,
			$post->post_type,
			'deleted'
		);
	}

	/**
	 * Log Order minor status changes ( pending / on-hold / failed / processing / completed / refunded / cancelled )
	 *
	 * @action woocommerce_order_status_changed
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $old       Old status.
	 * @param string $new       New status.
	 */
	public function callback_woocommerce_order_status_changed( $order_id, $old, $new ) {
		// Don't track customer actions.
		if ( ! is_admin() ) {
			return;
		}

		// Don't track new statuses.
		if ( empty( $old ) ) {
			return;
		}

		if ( version_compare( $this->plugin_version, '2.2', '>=' ) ) {
			$old_status_name = wc_get_order_status_name( $old );
			$new_status_name = wc_get_order_status_name( $new );
		} else {
			$old_status      = wp_stream_is_vip() ? wpcom_vip_get_term_by( 'slug', $old, 'shop_order_status' ) : get_term_by( 'slug', $old, 'shop_order_status' );
			$new_status      = wp_stream_is_vip() ? wpcom_vip_get_term_by( 'slug', $new, 'shop_order_status' ) : get_term_by( 'slug', $new, 'shop_order_status' );
			$new_status_name = $new_status->name;
			$old_status_name = $old_status->name;
		}

		/* translators: %1$s: an order title, %2$s: order status, %3$s: another order status (e.g. "Order #42", "processing", "complete") */
		$message = esc_html_x(
			'%1$s status changed from %2$s to %3$s',
			'1. Order title, 2. Old status, 3. New status',
			'stream'
		);

		$order           = new \WC_Order( $order_id );
		$order_title     = esc_html__( 'Order number', 'stream' ) . ' ' . esc_html( $order->get_order_number() );
		$order_type_name = esc_html__( 'order', 'stream' );

		$this->log(
			$message,
			array(
				'post_title'      => $order_title,
				'old_status_name' => $old_status_name,
				'new_status_name' => $new_status_name,
				'singular_name'   => $order_type_name,
				'new_status'      => $new,
				'old_status'      => $old,
				'revision_id'     => null,
			),
			$order_id,
			'shop_order',
			'updated'
		);
	}

	/**
	 * Log adding a product attribute
	 *
	 * @action woocommerce_attribute_added
	 *
	 * @param int   $attribute_id  Attribute ID.
	 * @param array $attribute     Attribute data.
	 */
	public function callback_woocommerce_attribute_added( $attribute_id, $attribute ) {
		$this->log(
			/* translators: %s: a term name (e.g. "color") */
			_x(
				'"%s" product attribute created',
				'Term name',
				'stream'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'created'
		);
	}

	/**
	 * Log updating a product attribute
	 *
	 * @action woocommerce_attribute_updated
	 *
	 * @param int   $attribute_id  Attribute ID.
	 * @param array $attribute     Attribute data.
	 */
	public function callback_woocommerce_attribute_updated( $attribute_id, $attribute ) {
		$this->log(
			/* translators: %s a term name (e.g. "color") */
			_x(
				'"%s" product attribute updated',
				'Term name',
				'stream'
			),
			$attribute,
			$attribute_id,
			'attributes',
			'updated'
		);
	}

	/**
	 * Log deleting a product attribute
	 *
	 * @action woocommerce_attribute_updated
	 *
	 * @param int    $attribute_id    Attribute ID.
	 * @param string $attribute_name  Attribute name.
	 */
	public function callback_woocommerce_attribute_deleted( $attribute_id, $attribute_name ) {
		$this->log(
			/* translators: %s: a term name (e.g. "color") */
			_x(
				'"%s" product attribute deleted',
				'Term name',
				'stream'
			),
			array(
				'attribute_name' => $attribute_name,
			),
			$attribute_id,
			'attributes',
			'deleted'
		);
	}

	/**
	 * Log adding a tax rate
	 *
	 * @action woocommerce_tax_rate_added
	 *
	 * @param int   $tax_rate_id  Tax Rate ID.
	 * @param array $tax_rate     Tax Rate data.
	 */
	public function callback_woocommerce_tax_rate_added( $tax_rate_id, $tax_rate ) {
		$this->log(
			/* translators: %4$s: a tax rate name (e.g. "GST") */
			_x(
				'"%4$s" tax rate created',
				'Tax rate name',
				'stream'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'created'
		);
	}

	/**
	 * Log updating a tax rate
	 *
	 * @action woocommerce_tax_rate_updated
	 *
	 * @param int   $tax_rate_id  Tax Rate ID.
	 * @param array $tax_rate     Tax Rate data.
	 */
	public function callback_woocommerce_tax_rate_updated( $tax_rate_id, $tax_rate ) {
		$this->log(
			/* translators: %4$s: a tax rate name (e.g. "GST") */
			_x(
				'"%4$s" tax rate updated',
				'Tax rate name',
				'stream'
			),
			$tax_rate,
			$tax_rate_id,
			'tax',
			'updated'
		);
	}

	/**
	 * Log deleting a tax rate
	 *
	 * @action woocommerce_tax_rate_updated
	 *
	 * @param int $tax_rate_id  Tax Rate ID.
	 */
	public function callback_woocommerce_tax_rate_deleted( $tax_rate_id ) {
		global $wpdb;

		$tax_rate_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates
				WHERE tax_rate_id = %s
				",
				$tax_rate_id
			)
		);

		$this->log(
			/* translators: %4$s: a tax rate name (e.g. "GST") */
			_x(
				'"%s" tax rate deleted',
				'Tax rate name',
				'stream'
			),
			array(
				'tax_rate_name' => $tax_rate_name,
			),
			$tax_rate_id,
			'tax',
			'deleted'
		);
	}

	/**
	 * Filter records and take-over our precious data
	 *
	 * @filter wp_stream_record_array
	 *
	 * @param array $recordarr  Record data to be inserted.
	 *
	 * @return array Filtered record data
	 */
	public function callback_wp_stream_record_array( $recordarr ) {
		foreach ( $recordarr as $key => $record ) {
			if ( ! isset( $record['connector'] ) || ! isset( $record['context'] ) ) {
				continue;
			}

			// Change connector::posts records.
			if ( 'posts' === $record['connector'] && in_array( $record['context'], $this->post_types, true ) ) {
				$recordarr[ $key ]['connector'] = $this->name;
			} elseif ( 'taxonomies' === $record['connector'] && in_array( $record['context'], $this->taxonomies, true ) ) {
				$recordarr[ $key ]['connector'] = $this->name;
			} elseif ( 'settings' === $record['connector'] ) {
				$option = isset( $record['meta']['option_key'] ) ? $record['meta']['option_key'] : false;

				if ( $option && isset( $this->settings[ $option ] ) ) {
					return false;
				}
			}
		}

		return $recordarr;
	}

	/**
	 * Track WooCommerce-specific option changes.
	 *
	 * @param string $option_key   Option key.
	 * @param string $old_value    Old value.
	 * @param string $value        New value.
	 */
	public function callback_updated_option( $option_key, $old_value, $value ) {
		$options = array( $option_key );

		if ( is_array( $old_value ) || is_array( $value ) ) {
			foreach ( $this->get_changed_keys( $old_value, $value ) as $field_key ) {
				$options[] = $field_key;
			}
		}

		foreach ( $options as $option ) {
			if ( ! array_key_exists( $option, $this->settings ) ) {
				continue;
			}

			$this->log(
				/* translators: %1$s: a setting name, %2$s: a setting type (e.g. "Direct Deposit", "Payment Method") */
				__( '"%1$s" %2$s updated', 'stream' ),
				array(
					'label'     => $this->settings[ $option ]['title'],
					'type'      => $this->settings[ $option ]['type'],
					'page'      => $this->settings[ $option ]['page'],
					'tab'       => $this->settings[ $option ]['tab'],
					'section'   => $this->settings[ $option ]['section'],
					'option'    => $option,
					'old_value' => maybe_serialize( $old_value ),
					'value'     => maybe_serialize( $value ),
				),
				null,
				$this->settings[ $option ]['tab'],
				'updated'
			);
		}
	}

	/**
	 * Loads the WooCommerce admin settings.
	 */
	public function get_woocommerce_settings_fields() {
		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WC_Admin_Settings' ) ) {
			return false;
		}

		if ( ! empty( $this->settings ) ) {
			return $this->settings;
		}

		$settings_cache_key = 'stream_connector_woocommerce_settings_' . sanitize_key( WC_VERSION );
		$settings_transient = get_transient( $settings_cache_key );

		if ( $settings_transient ) {
			$settings       = $settings_transient['settings'];
			$settings_pages = $settings_transient['settings_pages'];
		} else {
			global $woocommerce;

			$settings       = array();
			$settings_pages = array();

			foreach ( \WC_Admin_Settings::get_settings_pages() as $page ) {
				/**
				 * Get ID / Label of the page, since they're protected, by hacking into
				 * the callback filter for 'woocommerce_settings_tabs_array'.
				 */
				$info       = $page->add_settings_page( array() );
				$page_id    = key( $info );
				$page_label = current( $info );
				$sections   = $page->get_sections();

				if ( empty( $sections ) ) {
					$sections[''] = $page_label;
				}

				$settings_pages[ $page_id ] = $page_label;

				// Remove non-fields ( sections, titles and whatever ).
				$fields = array();

				foreach ( $sections as $section_key => $section_label ) {
					$_fields = array_filter(
						$page->get_settings( $section_key ),
						function( $item ) {
							return isset( $item['id'] ) && ( ! in_array( $item['type'], array( 'title', 'sectionend' ), true ) );
						}
					);

					foreach ( $_fields as $field ) {
						$title                  = isset( $field['title'] ) ? $field['title'] : ( isset( $field['desc'] ) ? $field['desc'] : 'N/A' );
						$fields[ $field['id'] ] = array(
							'title'   => $title,
							'page'    => 'wc-settings',
							'tab'     => $page_id,
							'section' => $section_key,
							'type'    => esc_html__( 'setting', 'stream' ),
						);
					}
				}

				// Store fields in the global array to be searched later.
				$settings = array_merge( $settings, $fields );
			}

			// Provide additional context for each of the settings pages.
			array_walk(
				$settings_pages,
				function( &$value ) {
					$value .= ' ' . esc_html__( 'Settings', 'stream' );
				}
			);

			// Load Payment Gateway Settings.
			$payment_gateway_settings = array();
			$payment_gateways         = $woocommerce->payment_gateways();

			foreach ( $payment_gateways->payment_gateways as $section_key => $payment_gateway ) {
				$title = $payment_gateway->title;
				$key   = $payment_gateway->plugin_id . $payment_gateway->id . '_settings';

				$payment_gateway_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'payment gateway', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $payment_gateway_settings );

			// Load Shipping Method Settings.
			$shipping_method_settings = array();
			$shipping_methods         = $woocommerce->shipping();

			foreach ( (array) $shipping_methods->shipping_methods as $section_key => $shipping_method ) {
				$title = $shipping_method->title;
				$key   = $shipping_method->plugin_id . $shipping_method->id . '_settings';

				$shipping_method_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'shipping',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'shipping method', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $shipping_method_settings );

			// Load Email Settings.
			$email_settings = array();
			$emails         = $woocommerce->mailer();

			foreach ( $emails->emails as $section_key => $email ) {
				$title = $email->title;
				$key   = $email->plugin_id . $email->id . '_settings';

				$email_settings[ $key ] = array(
					'title'   => $title,
					'page'    => 'wc-settings',
					'tab'     => 'email',
					'section' => strtolower( $section_key ),
					'type'    => esc_html__( 'email', 'stream' ),
				);
			}

			$settings = array_merge( $settings, $email_settings );

			// Tools page.
			$tools_page = array(
				'tools' => esc_html__( 'Tools', 'stream' ),
			);

			$settings_pages = array_merge( $settings_pages, $tools_page );

			// Cache the results.
			$settings_cache = array(
				'settings'       => $settings,
				'settings_pages' => $settings_pages,
			);

			set_transient( $settings_cache_key, $settings_cache, MINUTE_IN_SECONDS * 60 * 6 );
		}

		$custom_settings      = $this->get_custom_settings();
		$this->settings       = array_merge( $settings, $custom_settings );
		$this->settings_pages = $settings_pages;

		return $this->settings;
	}
}
