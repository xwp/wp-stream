<?php
/**
 * Generates a filterable list of provided records to be displayed HTML Table.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - List_Table
 */
class List_Table extends \WP_List_Table {

	/**
	 * Holds Instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 * @param array  $args   Argument to filter rows by.
	 */
	public function __construct( $plugin, $args = array() ) {
		$this->plugin = $plugin;

		$screen_id = isset( $args['screen'] ) ? $args['screen'] : null;

		/**
		 * Filter the list table screen ID
		 *
		 * @return string
		 */
		$screen_id = apply_filters( 'wp_stream_list_table_screen_id', $screen_id );

		parent::__construct(
			array(
				'post_type' => 'stream',
				'plural'    => 'records',
				'screen'    => $screen_id,
			)
		);

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'label'   => __( 'Records per page', 'stream' ),
				'option'  => 'edit_stream_per_page',
			)
		);

		// Check for default hidden columns.
		$this->get_hidden_columns();

		add_filter(
			'screen_settings',
			array(
				$this,
				'screen_controls',
			),
			10,
			2
		);
		add_filter(
			'set-screen-option',
			array(
				$this,
				'set_screen_option',
			),
			10,
			3
		);

		set_screen_options();
	}

	/**
	 * Renders extra from navigation options.
	 *
	 * @param string $which  Page location.
	 * @return void
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo $this->filters_form(); // xss ok.
		}
	}

	/**
	 * Renders "No items found" message.
	 *
	 * @return void
	 */
	public function no_items() {
		?>
		<div class="stream-list-table-no-items">
			<p><?php esc_html_e( 'No activity records were found.', 'stream' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Returns the table columns to be rendered.
	 *
	 * @return array
	 */
	public function get_columns() {
		/**
		 * Allows devs to add new columns to table
		 *
		 * @return array
		 */
		return apply_filters(
			'wp_stream_list_table_columns',
			array(
				'date'    => __( 'Date', 'stream' ),
				'summary' => __( 'Summary', 'stream' ),
				'user_id' => __( 'User', 'stream' ),
				'context' => __( 'Context', 'stream' ),
				'action'  => __( 'Action', 'stream' ),
				'ip'      => __( 'IP Address', 'stream' ),
			)
		);
	}

	/**
	 * Returns the columns the items can be sort by.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'date' => array( 'date', false ),
		);
	}

	/**
	 * Returns columns hidden from all except specific users.
	 *
	 * @return array|bool
	 */
	public function get_hidden_columns() {
		$user = wp_get_current_user();
		if ( ! $user ) {
			return array();
		}

		// Directly checking the user meta; to check whether user has changed screen option or not.
		$hidden = get_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', true );

		// If user meta is not found; add the default hidden column 'id'.
		if ( ! $hidden ) {
			$hidden = array( 'id' );
			update_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', $hidden );
		}

		return $hidden;
	}

	/**
	 * Prepares table columns and data for render
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = $this->get_hidden_columns();
		$primary  = $columns['summary'];

		$this->_column_headers = array(
			$columns,
			$hidden,
			$sortable,
			$primary,
		);

		$this->items = $this->get_records();

		$total_items = $this->get_total_found_rows();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->get_items_per_page( 'edit_stream_per_page', 20 ),
			)
		);
	}

	/**
	 * Returns records to be displayed
	 *
	 * @return array
	 */
	public function get_records() {
		$args = array();

		// Parse sorting params.
		$order = wp_stream_filter_input( INPUT_GET, 'order' );
		if ( $order ) {
			$args['order'] = $order;
		}

		$orderby = wp_stream_filter_input( INPUT_GET, 'orderby' );
		if ( $orderby ) {
			$args['orderby'] = $orderby;
		}

		$params = array(
			'search',
			'date',
			'date_from',
			'date_to',
			'date_after',
			'date_before',
		);

		foreach ( $params as $param ) {
			$value = wp_stream_filter_input( INPUT_GET, $param );

			if ( $value ) {
				$args[ $param ] = $value;
			}
		}

		// Additional filter properties.
		$properties = array(
			'record',
			'site_id',
			'blog_id',
			'object_id',
			'user_id',
			'user_role',
			'ip',
			'connector',
			'context',
			'action',
		);

		// Add property fields to defaults, including their __in/__not_in variations.
		foreach ( $properties as $property ) {
			$value = wp_stream_filter_input( INPUT_GET, $property );

			// Allow 0 values.
			if ( isset( $value ) && '' !== $value && false !== $value ) {
				$args[ $property ] = $value;
			}

			$value_in = wp_stream_filter_input( INPUT_GET, $property . '__in' );

			if ( $value_in ) {
				$args[ $property . '__in' ] = explode( ',', $value_in );
			}

			$value_not_in = wp_stream_filter_input( INPUT_GET, $property . '__not_in' );

			if ( $value_not_in ) {
				$args[ $property . '__not_in' ] = explode( ',', $value_not_in );
			}
		}

		$args['paged'] = $this->get_pagenum();

		if ( isset( $args['context'] ) && 0 === strpos( $args['context'], 'group-' ) ) {
			$args['connector'] = str_replace( 'group-', '', $args['context'] );
			$args['context']   = '';
		}

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $this->get_items_per_page( 'edit_stream_per_page', 20 );
		}
		$args['records_per_page'] = apply_filters( 'stream_records_per_page', $args['records_per_page'] );

		$items = $this->plugin->db->get_records( $args );

		return $items;
	}

	/**
	 * Get last query found rows
	 *
	 * @return integer
	 */
	public function get_total_found_rows() {
		return $this->plugin->db->get_found_records_count();
	}

	/**
	 * Returns the column content for the provided item and column.
	 *
	 * @param array  $item         Record data.
	 * @param string $column_name  Column name.
	 * @return void
	 */
	public function column_default( $item, $column_name ) {
		$out    = '';
		$record = new Record( $item );

		switch ( $column_name ) {
			case 'date':
				$created     = gmdate( 'Y-m-d H:i:s', strtotime( $record->created ) );
				$date_string = sprintf(
					'<time datetime="%s" class="relative-time record-created">%s</time>',
					wp_stream_get_iso_8601_extended_date( strtotime( $record->created ) ),
					get_date_from_gmt( $created, 'Y/m/d' )
				);
				$out         = $this->column_link( $date_string, 'date', get_date_from_gmt( $created, 'Y/m/d' ) );
				$out        .= '<br />';
				$out        .= get_date_from_gmt( $created, 'h:i:s A' );
				break;

			case 'summary':
				$out          = $record->summary;
				$object_title = $record->get_object_title();
				/* translators: %s: the title of any object, like a Post (e.g. "Hello World") */
				$view_all_text = $object_title ? sprintf( esc_html__( 'View all activity for "%s"', 'stream' ), esc_attr( $object_title ) ) : esc_html__( 'View all activity for this object', 'stream' );

				if ( $record->object_id ) {
					$out .= $this->column_link(
						'<span class="dashicons dashicons-search stream-filter-object-id"></span>',
						array(
							'object_id' => $record->object_id,
							'context'   => $record->context,
						),
						null,
						esc_attr( $view_all_text )
					);
				}
				if ( $record->meta ) {
					$out .= '<details><summary>' . esc_html__( 'Metadata', 'stream' ) . '</summary><pre>' . esc_html( print_r( $record->meta, true ) ) . '</pre></details>';
				}
				$out .= $this->get_action_links( $record );
				break;

			case 'user_id':
				$user = new Author( (int) $record->user_id, (array) $record->user_meta );

				$filtered_records_url = add_query_arg(
					array(
						'page'    => $this->plugin->admin->records_page_slug,
						'user_id' => absint( $user->id ),
					),
					self_admin_url( $this->plugin->admin->admin_parent_page )
				);

				$out = sprintf(
					'<a href="%s">%s <span>%s</span></a>%s%s%s',
					$filtered_records_url,
					$user->get_avatar_img( 80 ),
					$user->get_display_name(),
					$user->is_deleted() ? sprintf( '<br /><small class="deleted">%s</small>', esc_html__( 'Deleted User', 'stream' ) ) : '',
					sprintf( '<br /><small>%s</small>', $user->get_role() ),
					sprintf( '<br /><small>%s</small>', $user->get_agent_label( $user->get_agent() ) )
				);
				break;

			case 'context':
				$connector_title = $this->get_term_title( $record->{'connector'}, 'connector' );
				$context_title   = $this->get_term_title( $record->{'context'}, 'context' );

				$out  = $this->column_link( $connector_title, 'connector', $item->{'connector'} );
				$out .= '<br />&#8627;&nbsp;';
				$out .= $this->column_link(
					$context_title,
					array(
						'connector' => $record->{'connector'},
						'context'   => $record->{'context'},
					)
				);
				break;

			case 'action':
				$out = $this->column_link( $this->get_term_title( $record->{$column_name}, $column_name ), $column_name, $record->{$column_name} );
				break;

			case 'blog_id':
				$blog = ( $record->blog_id && is_multisite() ) ? get_blog_details( $record->blog_id ) : $this->plugin->admin->network->get_network_blog();
				$out  = $this->column_link( $blog->blogname, 'blog_id', $blog->blog_id );
				break;

			case 'ip':
				$out = $this->column_link( $record->{$column_name}, 'ip', $record->{$column_name} );
				break;

			default:
				/**
				 * Registers new Columns to be inserted into the table. The cell contents of this column is set
				 * below with 'wp_stream_insert_column_default_'
				 *
				 * @param array $new_columns  Columns injected in the table.
				 *
				 * @return array
				 */
				$inserted_columns = apply_filters( 'wp_stream_register_column_defaults', array() );

				if ( ! empty( $inserted_columns ) && is_array( $inserted_columns ) ) {
					foreach ( $inserted_columns as $column_title ) {
						/**
						 * If column title inserted via wp_stream_register_column_defaults ($column_title) exists
						 * among columns registered with get_columns ($column_name) and there is an action associated
						 * with this column, do the action
						 *
						 * Also, note that the action name must include the $column_title registered
						 * with wp_stream_register_column_defaults
						 */
						if ( $column_title === $column_name ) {
							/**
							 * Allows for the addition of content under a specified column.
							 *
							 * @param string $out          Column content.
							 * @param object $record       Record with row content.
							 * @param string $column_name  Column name.
							 *
							 * @return string
							 */
							$out = apply_filters( "wp_stream_insert_column_default_{$column_title}", $out, $record, $column_name );
							break;
						}
					}
				}
		}

		$allowed_tags                  = wp_kses_allowed_html( 'post' );
		$allowed_tags['time']          = array(
			'datetime' => true,
			'class'    => true,
		);
		$allowed_tags['img']['srcset'] = true;

		echo wp_kses( $out, $allowed_tags );
	}

	/**
	 * Returns the actions links for the provided record. (Eg. Edit, View)
	 *
	 * @param Record $record  Record.
	 * @return string
	 */
	public function get_action_links( $record ) {
		$out = '';

		/**
		 * Filter allows modification of action links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Action links for this connector
		 */
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );

		/**
		 * Filter allows addition of custom links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Custom links for this connector
		 */
		$custom_links = apply_filters( 'wp_stream_custom_action_links_' . $record->connector, array(), $record );

		if ( $action_links || $custom_links ) {
			$out .= '<div class="row-actions">';
		}

		$links = array();
		if ( $action_links && is_array( $action_links ) ) {
			foreach ( $action_links as $al_title => $al_href ) {
				$links[] = sprintf(
					'<span><a href="%s" class="action-link">%s</a></span>',
					$al_href,
					$al_title
				);
			}
		}

		if ( $custom_links && is_array( $custom_links ) ) {
			foreach ( $custom_links as $key => $link ) {
				$links[] = $link;
			}
		}

		$out .= implode( ' | ', $links );

		if ( $action_links || $custom_links ) {
			$out .= '</div>';
		}

		return $out;
	}

	/**
	 * Returns a link to be display in the table.
	 *
	 * @param string       $display  Text to be displayed in link.
	 * @param string|array $key      Query string variable(s).
	 * @param string       $value    Single query string value.
	 * @param string       $title    Tooltip value.
	 * @return string
	 */
	public function column_link( $display, $key, $value = null, $title = null ) {
		$url = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			self_admin_url( $this->plugin->admin->admin_parent_page )
		);

		$args = ! is_array( $key ) ? array(
			$key => $value,
		) : $key;

		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $title ),
			$display
		);
	}

	/**
	 * Returns the label for a connector term.
	 *
	 * @param string $term  Connector label type.
	 * @param string $type  Connector term.
	 * @return string
	 */
	public function get_term_title( $term, $type ) {
		if ( ! isset( $this->plugin->connectors->term_labels[ 'stream_' . $type ][ $term ] ) ) {
			return $term;
		}

		return $this->plugin->connectors->term_labels[ 'stream_' . $type ][ $term ];
	}

	/**
	 * Assembles records for display in search filters
	 *
	 * Gathers list of all users/connectors, then compares it to
	 * results of existing records.  All items that do not exist in records
	 * get assigned a disabled value of "true".
	 *
	 * @param string $column  List table column name.
	 *
	 * @return array Options to be displayed in search filters
	 */
	public function assemble_records( $column ) {
		// @todo eliminate special condition for authors, especially using a WP_User object as the value; should use string or stringifiable object
		if ( 'user_id' === $column ) {
			$all_records = array();

			// If the number of users exceeds the max users constant value then return an empty array and use AJAX instead.
			$user_count  = count_users();
			$total_users = $user_count['total_users'];

			if ( $total_users > $this->plugin->admin->preload_users_max ) {
				$selected_user = wp_stream_filter_input( INPUT_GET, 'user_id' );
				if ( $selected_user ) {
					$user = new Author( $selected_user );

					return array(
						$selected_user => $user->get_display_name(),
					);
				} else {
					return array();
				}
			}

			$users = array_map(
				function ( $user_id ) {
					return new Author( $user_id );
				},
				get_users(
					array(
						'fields' => 'ID',
					)
				)
			);

			if ( is_multisite() && is_super_admin() ) {
				$super_admins = array_map(
					function ( $login ) {
						$user = get_user_by( 'login', $login );

						return new Author( $user->ID );
					},
					get_super_admins()
				);
				$users        = array_unique( array_merge( $users, $super_admins ) );
			}

			$users[] = new Author(
				0,
				array(
					'is_wp_cli' => true,
				)
			);

			foreach ( $users as $user ) {
				$all_records[ $user->id ] = $user->get_display_name();
			}
		} else {
			$prefixed_column = sprintf( 'stream_%s', $column );
			$all_records     = $this->plugin->connectors->term_labels[ $prefixed_column ];
		}

		$existing_records = $this->plugin->db->existing_records( $column );
		$active_records   = array();
		$disabled_records = array();

		foreach ( $all_records as $record => $label ) {
			if ( array_key_exists( $record, $existing_records ) ) {
				$active_records[ $record ] = array(
					'label'    => $label,
					'disabled' => '',
				);
			} else {
				$disabled_records[ $record ] = array(
					'label'    => $label,
					'disabled' => 'disabled="disabled"',
				);
			}
		}

		// Remove WP-CLI pseudo user if no records with user=0 exist.
		if ( isset( $disabled_records[0] ) ) {
			unset( $disabled_records[0] );
		}

		$sort = function ( $a, $b ) use ( $column ) {
			$label_a = (string) $a['label'];
			$label_b = (string) $b['label'];

			if ( $label_a === $label_b ) {
				return 0;
			}

			return ( strtolower( $label_a ) < strtolower( $label_b ) ) ? - 1 : 1;
		};

		uasort( $active_records, $sort );
		uasort( $disabled_records, $sort );

		// Not using array_merge() in order to preserve the array index for the users dropdown which uses the user_id as the key.
		$all_records = $active_records + $disabled_records;

		return $all_records;
	}

	/**
	 * Returns list filters.
	 *
	 * @return array
	 */
	public function get_filters() {
		$filters = array();

		$date_interval = new Date_Interval();

		$filters['date'] = array(
			'title' => __( 'dates', 'stream' ),
			'items' => $date_interval->intervals,
		);

		$users = $this->get_users_dropdown_items(
			$this->assemble_records( 'user_id' )
		);

		$filters['user_id'] = array(
			'title' => __( 'users', 'stream' ),
			'items' => $users,
			'ajax'  => count( $users ) <= 0,
		);

		$filters['context'] = array(
			'title' => __( 'contexts', 'stream' ),
			'items' => $this->assemble_records( 'context' ),
		);

		$filters['action'] = array(
			'title' => __( 'actions', 'stream' ),
			'items' => $this->assemble_records( 'action' ),
		);

		/**
		 * Filter allows additional filters in the list table dropdowns
		 * Note the format of the filters above, with they key and array
		 * containing a title and array of items.
		 *
		 * @return array
		 */
		return apply_filters( 'wp_stream_list_table_filters', $filters );
	}

	/**
	 * Returns table filters form.
	 *
	 * @return string
	 */
	public function filters_form() {
		$filters = $this->get_filters();

		$filters_string  = sprintf( '<input type="hidden" name="page" value="%s" />', 'wp_stream' );
		$filters_string .= sprintf( '<span class="filter_info hidden">%s</span>', esc_html__( 'Show filter controls via the screen options tab above.', 'stream' ) );

		foreach ( $filters as $name => $data ) {

			$data = wp_parse_args(
				$data,
				array(
					'title' => '',
					'items' => array(),
					'ajax'  => false,
				)
			);

			if ( 'date' === $name ) {
				$filters_string .= $this->filter_date( $data['items'] );
			} else {
				if ( 'context' === $name ) {
					// Add Connectors as parents, and apply the Contexts as children.
					$connectors    = $this->assemble_records( 'connector' );
					$context_items = array();

					foreach ( $connectors as $connector => $item ) {
						$context_items[ $connector ]['label'] = $item['label'];

						foreach ( $data['items'] as $context_value => $context_item ) {
							if ( isset( $this->plugin->connectors->contexts[ $connector ] ) && array_key_exists( $context_value, $this->plugin->connectors->contexts[ $connector ] ) ) {
								$context_items[ $connector ]['children'][ $context_value ] = $context_item;
							}
						}

						if ( isset( $context_items[ $connector ]['children'] ) ) {
							$labels = wp_list_pluck( $context_items[ $connector ]['children'], 'label' );

							// Sort child items by label.
							array_multisort( $labels, SORT_ASC, $context_items[ $connector ]['children'] );
						}
					}

					foreach ( $context_items as $context_value => $context_item ) {
						if ( ! isset( $context_item['children'] ) || empty( $context_item['children'] ) ) {
							unset( $context_items[ $context_value ] );
						}
					}

					$data['items'] = $context_items;

					$labels = wp_list_pluck( $data['items'], 'label' );

					// Sort top-level items by label.
					array_multisort( $labels, SORT_ASC, $data['items'] );

					// Output a hidden input to handle the connector value.
					$filters_string .= sprintf(
						'<input type="hidden" name="connector" class="record-filter-connector" value="%s" />',
						esc_attr( wp_stream_filter_input( INPUT_GET, 'connector' ) )
					);
				}

				$filters_string .= $this->filter_select( $name, $data['title'], $data['items'], $data['ajax'] );
			}
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s" />', __( 'Filter', 'stream' ) );

		// Parse all query vars into an array.
		$query_vars = array();

		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( urldecode( $_SERVER['QUERY_STRING'] ), $query_vars );
		}

		// Ignore certain query vars and query vars that are empty.
		foreach ( $query_vars as $query_var => $value ) {
			if ( '' === $value || 'page' === $query_var || 'paged' === $query_var ) {
				unset( $query_vars[ $query_var ] );
			}
		}

		$url = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			self_admin_url( $this->plugin->admin->admin_parent_page )
		);

		// Display reset action if records are being filtered.
		if ( ! empty( $query_vars ) ) {
			$filters_string .= sprintf( '<a href="%s" id="record-query-reset"><span class="dashicons dashicons-dismiss"></span> <span class="record-query-reset-text">%s</span></a>', esc_url( $url ), __( 'Reset filters', 'stream' ) );
		}

		return sprintf( '<div class="alignleft actions">%s</div>', $filters_string ); // xss ok.
	}

	/**
	 * Returns HTML string of filterable select control with filtered items.
	 *
	 * @param string  $name   Search input.
	 * @param string  $title  Name of the control.
	 * @param array   $items  Items to be filtered.
	 * @param boolean $ajax   Whether is an ajax request or not.
	 * @return string
	 */
	public function filter_select( $name, $title, $items, $ajax = false ) {
		$options  = array( '<option value=""></option>' );
		$selected = wp_stream_filter_input( INPUT_GET, $name );

		foreach ( $items as $key => $item ) {
			$value       = isset( $item['children'] ) ? 'group-' . $key : $key;
			$option_args = array(
				'value'    => $value,
				'selected' => selected( $value, $selected, false ),
				'disabled' => isset( $item['disabled'] ) ? $item['disabled'] : null,
				'icon'     => isset( $item['icon'] ) ? $item['icon'] : null,
				'group'    => isset( $item['children'] ) ? $key : null,
				'tooltip'  => isset( $item['tooltip'] ) ? $item['tooltip'] : null,
				'class'    => isset( $item['children'] ) ? 'level-1' : null,
				'label'    => isset( $item['label'] ) ? $item['label'] : null,
			);
			$options[]   = $this->filter_option( $option_args );

			if ( isset( $item['children'] ) ) {
				foreach ( $item['children'] as $child_value => $child_item ) {
					$option_args = array(
						'value'    => $child_value,
						'selected' => selected( $child_value, $selected, false ),
						'disabled' => isset( $child_item['disabled'] ) ? $child_item['disabled'] : null,
						'icon'     => isset( $child_item['icon'] ) ? $child_item['icon'] : null,
						'group'    => $key,
						'tooltip'  => isset( $child_item['tooltip'] ) ? $child_item['tooltip'] : null,
						'class'    => 'level-2',
						'label'    => isset( $child_item['label'] ) ? '- ' . $child_item['label'] : null,
					);
					$options[]   = $this->filter_option( $option_args );
				}
			}
		}
		$out = sprintf(
			'<select name="%s" class="chosen-select" data-placeholder="%s">%s</select>',
			esc_attr( $name ),
			/* translators: %s: the title of the dropdown menu (e.g. "users") */
			sprintf( esc_attr__( 'Show all %s', 'stream' ), $title ),
			implode( '', $options )
		);

		return $out;
	}

	/**
	 * Return HTML string of a filterable select option.
	 *
	 * @param array $args  Option attributes.
	 * @return string
	 */
	public function filter_option( $args ) {
		$defaults = array(
			'value'    => null,
			'selected' => null,
			'disabled' => null,
			'icon'     => null,
			'group'    => null,
			'tooltip'  => null,
			'class'    => null,
			'label'    => null,
		);
		wp_parse_args( $args, $defaults );

		return sprintf(
			'<option value="%s" %s %s %s %s %s class="%s">%s</option>',
			esc_attr( $args['value'] ),
			$args['selected'],
			$args['disabled'],
			$args['icon'] ? sprintf( 'data-icon="%s"', esc_attr( $args['icon'] ) ) : null,
			$args['group'] ? sprintf( 'data-group="%s"', esc_attr( $args['group'] ) ) : null,
			$args['tooltip'] ? sprintf( 'title="%s"', esc_attr( $args['tooltip'] ) ) : null,
			$args['class'] ? esc_attr( $args['class'] ) : null,
			esc_html( $args['label'] )
		);
	}

	/**
	 * Return HTML string of a filter search box.
	 *
	 * @return string
	 */
	public function filter_search() {
		$search = null;
		if ( isset( $_GET['search'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = sanitize_key( wp_unslash( $_GET['search'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$out = sprintf(
			'<p class="search-box">
				<label class="screen-reader-text" for="record-search-input">%1$s:</label>
				<input type="search" id="record-search-input" name="search" value="%2$s" />
				<input type="submit" name="" id="search-submit" class="button" value="%1$s" />
			</p>',
			esc_attr__( 'Search Records', 'stream' ),
			$search
		);

		return $out;
	}

	/**
	 * Return HTML string of a filter select box based upon date.
	 *
	 * @param array $items  Records.
	 * @return string
	 */
	public function filter_date( $items ) {
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-stream-datepicker' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		$date_predefined = wp_stream_filter_input( INPUT_GET, 'date_predefined' );
		$date_from       = wp_stream_filter_input( INPUT_GET, 'date_from' );
		$date_to         = wp_stream_filter_input( INPUT_GET, 'date_to' );

		ob_start();
		?>
		<div class="date-interval">

			<select class="field-predefined hide-if-no-js chosen-select" name="date_predefined" data-placeholder="<?php esc_attr_e( 'All Time', 'stream' ); ?>">
				<option></option>
				<option value="custom" <?php selected( 'custom' === $date_predefined ); ?>><?php esc_attr_e( 'Custom', 'stream' ); ?></option>
				<?php
				foreach ( $items as $key => $interval ) {
					$end = isset( $interval['end'] ) ? $interval['end']->format( 'Y/m/d' ) : null;

					printf(
						'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
						esc_attr( $key ),
						esc_attr( $interval['start']->format( 'Y/m/d' ) ),
						esc_attr( $end ),
						selected( $key === $date_predefined ),
						esc_html( $interval['label'] )
					);
				}
				?>
			</select>

			<div class="date-inputs">
				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text" name="date_from" class="date-picker field-from" placeholder="<?php esc_attr_e( 'Start Date', 'stream' ); ?>" value="<?php echo esc_attr( $date_from ); ?>"/>
				</div>
				<span class="connector dashicons"></span>

				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text" name="date_to" class="date-picker field-to" placeholder="<?php esc_attr_e( 'End Date', 'stream' ); ?>" value="<?php echo esc_attr( $date_to ); ?>"/>
				</div>
			</div>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Output a Select dropdown of actions relating to the Stream records
	 *
	 * @return string
	 */
	public function record_actions_form() {
		/**
		 * Filter the records screen actions dropdown menu
		 *
		 * @return array Should be in the format of action_slug => 'Action Name'
		 */
		$actions = apply_filters( 'wp_stream_record_actions_menu', array() );

		if ( empty( $actions ) ) {
			return '';
		}

		ob_start();
		printf( '<div class="alignleft actions recordactions"><select name="%s">', esc_attr( 'record-actions' ) );
		printf( '<option value="">%s</option>', esc_attr__( 'Record Actions', 'stream' ) );
		foreach ( $actions as $value => $name ) {
			printf(
				'<option value="%s">%s</option>',
				esc_attr( $value ),
				esc_attr( $name )
			);
		}
		echo '</select></div>';
		wp_nonce_field( 'stream_record_actions_nonce', 'stream_record_actions_nonce' );
		wp_nonce_field( 'stream_filters_user_search_nonce', 'stream_filters_user_search_nonce' );

		printf( '<input type="hidden" name="page" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'page' ) ) );
		printf( '<input type="hidden" name="date_predefined" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'date_predefined' ) ) );
		printf( '<input type="hidden" name="date_from" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'date_from' ) ) );
		printf( '<input type="hidden" name="date_to" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'date_to' ) ) );
		printf( '<input type="hidden" name="user_id" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'user_id' ) ) );
		printf( '<input type="hidden" name="connector" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'connector' ) ) );
		printf( '<input type="hidden" name="context" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'context' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( wp_stream_filter_input( INPUT_GET, 'action' ) ) );

		printf( '<input type="submit" name="" id="record-actions-submit" class="button" value="%s">', esc_attr__( 'Apply', 'stream' ) );
		echo '<div class="clear"></div>';

		return ob_get_clean();
	}

	/**
	 * Renders record filter forms.
	 */
	public function display() {
		$url = self_admin_url( $this->plugin->admin->admin_parent_page );

		echo '<form method="get" action="' . esc_url( $url ) . '" id="record-filter-form">';
		echo $this->filter_search(); // xss ok.
		parent::display();
		echo '</form>';

		echo '<form method="get" action="' . esc_url( $url ) . '" id="record-actions-form">';
		echo $this->record_actions_form(); // xss ok.
		echo '</form>';
	}

	/**
	 * Renders a single record
	 *
	 * @param array $item  Record data.
	 */
	public function single_row( $item ) {
		$classes      = apply_filters( 'wp_stream_record_classes', array(), $item );
		$class_string = '';
		if ( ! empty( $classes ) ) {
			$class_string = ' class="' . esc_attr( join( ' ', $classes ) ) . '"';
		}

		echo sprintf( '<tr%s>', $class_string ); // xss ok.
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Renders table navigation controls
	 *
	 * @param string $which  Location controls.
	 */
	public function display_tablenav( $which ) {
		if ( 'top' === $which ) :
			?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				$this->pagination( $which );
				$this->extra_tablenav( $which );
				?>

				<br class="clear"/>
			</div>
		<?php else : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				/**
				 * Fires after the list table is displayed.
				 */
				do_action( 'wp_stream_after_list_table' );
				$this->pagination( $which );
				$this->extra_tablenav( $which );
				?>

				<br class="clear"/>
			</div>
			<?php
		endif;
	}

	/**
	 * Sets the screen options.
	 *
	 * @param string $dummy   Unused.
	 * @param string $option  Screen option name.
	 * @param string $value   Screen option value.
	 * @return string
	 */
	public function set_screen_option( $dummy, $option, $value ) {
		if ( 'edit_stream_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	/**
	 * Sets the live update options.
	 *
	 * @param string $dummy   Unused.
	 * @param string $option  Screen option name.
	 * @param string $value   Screen option value.
	 * @return string
	 */
	public function set_live_update_option( $dummy, $option, $value ) {
		unset( $value );

		// @codingStandardsIgnoreStart
		if (
			$this->plugin->admin->live_update->user_meta_key === $option
			&&
			isset( $_POST[ $this->plugin->admin->live_update->user_meta_key ] )
		) {
			$value = esc_attr( $_POST[ $this->plugin->admin->live_update->user_meta_key ] ); //input var okay

			return $value;
		}

		// @codingStandardsIgnoreEnd

		return $dummy;
	}

	/**
	 * Return HTML string of the "Live updates" screen option.
	 *
	 * @param string $status  Unused.
	 * @param array  $args    Unused.
	 * @return string
	 */
	public function screen_controls( $status, $args ) {
		unset( $status );
		unset( $args );

		$user_id   = get_current_user_id();
		$option    = $this->plugin->admin->get_user_meta( $user_id, $this->plugin->admin->live_update->user_meta_key, true );
		$heartbeat = wp_script_is( 'heartbeat', 'done' ) ? 'true' : 'false';

		if ( 'on' === $option && 'false' === $heartbeat ) {
			$option = 'off';

			update_user_meta( $user_id, $this->plugin->admin->live_update->user_meta_key, 'off' );
		}

		$nonce = wp_create_nonce( $this->plugin->admin->live_update->user_meta_key . '_nonce' );

		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Live updates', 'stream' ); ?></h5>

			<div>
				<input type="hidden" name="stream_live_update_nonce" id="stream_live_update_nonce" value="<?php echo esc_attr( $nonce ); ?>"/>
			</div>
			<div>
				<input type="hidden" name="enable_live_update_user" id="enable_live_update_user" value="<?php echo absint( $user_id ); ?>"/>
			</div>
			<div class="metabox-prefs stream-live-update-checkbox">
				<label for="enable_live_update">
					<input type="checkbox" value="on" name="enable_live_update" id="enable_live_update" data-heartbeat="<?php echo esc_attr( $heartbeat ); ?>" <?php checked( $option, 'on' ); ?> />
					<?php esc_html_e( 'Enabled', 'stream' ); ?>
					<span class="spinner"></span>
				</label>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	/**
	 * This function is use to map List table column name with excluded setting keys
	 *
	 * @param string $column  List table column name.
	 *
	 * @return string setting name for that column
	 */
	public function get_column_excluded_setting_key( $column ) {
		switch ( $column ) {
			case 'connector':
				$output = 'connectors';
				break;
			case 'context':
				$output = 'contexts';
				break;
			case 'action':
				$output = 'action';
				break;
			case 'ip':
				$output = 'ip_addresses';
				break;
			case 'user_id':
				$output = 'users';
				break;
			default:
				$output = false;
		}

		return $output;
	}

	/**
	 * Get users as dropdown items
	 *
	 * @param array $users  Users.
	 *
	 * @return array
	 */
	public function get_users_dropdown_items( $users ) {
		$record_meta = array();

		foreach ( $users as $user_id => $args ) {
			$user     = new Author( $user_id );
			$disabled = isset( $args['disabled'] ) ? $args['disabled'] : null;

			$record_meta[ $user_id ] = array(
				'text'     => $user->get_display_name(),
				'id'       => $user_id,
				'label'    => $user->get_display_name(),
				'icon'     => $user->get_avatar_src( 32 ),
				'title'    => '',
				'disabled' => $disabled,
			);
		}

		return $record_meta;
	}
}
