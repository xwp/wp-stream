<?php
/**
 * Connector for Posts
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use WP_Post;
use WP_Taxonomy;

/**
 * Class - Connector_Posts
 */
class Connector_Posts extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'posts';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'deleted_post',
		'wp_after_insert_post',
		'transition_post_status',
		'set_object_terms',
	);

	/**
	 * Adds an action to retrieve previous post data before updating a post.
	 */
	public function register() {
		parent::register();
		add_action( 'set_object_terms', array( $this, 'get_previous_post_terms' ), 10, 6 );
	}

	/**
	 * Add an array with the previous terms to a filter for future use.
	 *
	 * @param int|string] $object_id The post id.
	 * @param array       $terms The current terms.
	 * @param array       $tt_ids The current term taxonomy ids.
	 * @param string      $taxonomy The taxonomy slug.
	 * @param bool        $append Whether or not the terms were appended.
	 * @param array       $old_tt_ids The previous term taxonomy ids.
	 * @return void
	 */
	public function get_previous_post_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

		add_filter(
			"wp_stream_previous_{$object_id}_{$taxonomy}_terms",
			static function () use ( $old_tt_ids ) {
				return (array) $old_tt_ids;
			}
		);
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Posts', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'updated'   => esc_html__( 'Updated', 'stream' ),
			'created'   => esc_html__( 'Created', 'stream' ),
			'trashed'   => esc_html__( 'Trashed', 'stream' ),
			'untrashed' => esc_html__( 'Restored', 'stream' ),
			'deleted'   => esc_html__( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		global $wp_post_types;

		$post_types = wp_filter_object_list( $wp_post_types, array(), null, 'label' );
		$post_types = array_diff_key( $post_types, array_flip( $this->get_excluded_post_types() ) );

		add_action( 'registered_post_type', array( $this, 'registered_post_type' ), 10, 2 );

		return $post_types;
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
		$post = get_post( $record->object_id );

		// Let's get action links for all posts.
		if ( $post ) {
			$post_type_name = $this->get_post_type_name( get_post_type( $post->ID ) );

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
				/* translators: %s a post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

				$view_link = get_permalink( $post->ID );
				if ( $view_link ) {
					$links[ esc_html__( 'View', 'stream' ) ] = $view_link;
				}

				$revision_id = absint( $record->get_meta( 'revision_id', true ) );
				$revision_id = $this->get_adjacent_post_revision( $revision_id, false );

				if ( $revision_id ) {
					$links[ esc_html__( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
				}
			}
		}

		return $links;
	}

	/**
	 * Catch registration of post_types after initial loading, to cache its labels
	 *
	 * @action registered_post_type
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $args      Arguments used to register the post type.
	 */
	public function registered_post_type( $post_type, $args ) {
		unset( $args );

		$post_type_obj = get_post_type_object( $post_type );
		$label         = $post_type_obj->label;

		wp_stream_get_instance()->connectors->term_labels['stream_context'][ $post_type ] = $label;
	}

	/**
	 * Generates a list of terms based on the provided term IDs and taxonomy.
	 *
	 * @param array  $updated   The array of term IDs to generate the list from.
	 * @param string $taxonomy  The taxonomy to which the terms belong.
	 *
	 * @return string  The generated list of terms as HTML links.
	 */
	private function make_term_list( $updated, $taxonomy ) {
		$list = array_reduce(
			$updated,
			function ( $acc, $id ) use ( $taxonomy ) {
				$term = get_term( $id, $taxonomy );

				if ( empty( $term ) || is_wp_error( $term ) ) {
					return $acc;
				}

				return $acc .= sprintf(
					'<a href="%s">%s</a>, ',
					get_term_link( $term, $taxonomy ),
					$term->name
				);
			},
			''
		);

		return rtrim( $list, ', ' );
	}

	/**
	 * Log all post status changes ( creating / updating / trashing )
	 *
	 * @action transition_post_status
	 *
	 * @param mixed   $new_status New status.
	 * @param mixed   $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public function callback_transition_post_status( $new_status, $old_status, $post ) {

		// Don't log the non-included post types.
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_type, $this->get_excluded_post_types(), true ) ) {
			return;
		}

		// We don't want the meta box update request either, just the postupdate.
		if ( ! empty( wp_stream_filter_input( INPUT_GET, 'meta-box-loader' ) ) ) {
			return;
		}

		/**
		 * Whether or not there should also be a "post updated" log.
		 */
		$should_log_update = false;

		$start_statuses = array( 'auto-draft', 'inherit', 'new' );
		if ( in_array( $new_status, $start_statuses, true ) ) {
			return;
		} elseif ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		} elseif ( 'draft' === $new_status && 'publish' === $old_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s unpublished',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'trash' === $old_status && 'trash' !== $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s restored from trash',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'untrashed';
		} elseif ( 'draft' === $new_status && 'draft' === $old_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s draft saved',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'publish' === $new_status && ! in_array( $old_status, array( 'future', 'publish' ), true ) ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'draft' === $new_status ) {
			/* translators: %1$s: a post title, %2$s a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s drafted',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'pending' === $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s pending review',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'future' === $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s scheduled for %3$s',
				'1: Post title, 2: Post type singular name, 3: Scheduled post date',
				'stream'
			);
		} elseif ( 'future' === $old_status && 'publish' === $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" scheduled %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'private' === $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s privately published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'trash' === $new_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s trashed',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'trashed';
		} else {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = false;
		}

		if ( ! $summary ) {
			return;
		}

		if ( in_array( $old_status, $start_statuses, true ) && ! in_array( $new_status, $start_statuses, true ) ) {
			$action = 'created';
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$revision_id = $this->get_revision_id( $post );

		$post_type_name = strtolower( $this->get_post_type_name( $post->post_type ) );

		add_filter( 'wp_stream_has_post_transition_log', '__return_true' );
		$this->log(
			$summary,
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
				'post_date'     => $post->post_date,
				'post_date_gmt' => $post->post_date_gmt,
				'new_status'    => $new_status,
				'old_status'    => $old_status,
				'revision_id'   => $revision_id,
			),
			$post->ID,
			$post->post_type,
			$action
		);
	}

	/**
	 * This currently only looks at the posts table.
	 *
	 * @param int|string $post_id The post id.
	 * @param WP_Post    $post_after The post object of the final post.
	 * @param bool       $update Whether or not this is an updated post.
	 * @param WP_Post    $post_before The post object before it was updated.
	 * @return void
	 */
	public function callback_wp_after_insert_post( $post_id, $post_after, $update, $post_before ) {

		// Don't log newly created posts or the non-included post types.
		if ( ! $update || in_array( $post_after->post_type, $this->get_excluded_post_types(), true ) ) {
			return;
		}

		// We don't want the meta box update request either, just the post update.
		if ( ! empty( wp_stream_filter_input( INPUT_GET, 'meta-box-loader' ) ) ) {
			return;
		}

		$start_statuses = array( 'auto-draft', 'inherit', 'new' );
		if (
			in_array( $post_after->post_status, $start_statuses, true ) || in_array( $post_before->post_status, $start_statuses, true ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$action         = 'updated';
		$post_type_name = $this->get_post_type_name( $post_after->post_type );

		$updated_fields = array();

		// Find out what was updated.
		foreach ( $post_after as $field => $value ) {
			if ( $value === $post_before->$field ) {
				continue;
			}

			switch ( $field ) {
				case 'post_author':
					$updated_fields['post_author'] = sprintf(
						/* Translators: %1$s is the previous post author, %2$s is the current post author */
						__( '%1$s to %2$s', 'stream' ),
						$this->get_author_maybe_link( $post_before->post_author ),
						$this->get_author_maybe_link( $post_after->post_author )
					);
					break;
				// Not including these for now.
				case 'post_modified':
				case 'post_modified_gmt':
					// Handled in transition_post_status hook.
				case 'post_status':
					break;
				case 'post_content':
					$updated_fields['post_content'] = __( 'updated', 'stream' );
					break;
				case 'post_date':
				case 'post_date_gmt':
					if ( apply_filters( 'wp_stream_has_post_transition_log', false ) ) {
						break;
					}
					// Break if there's a log, otherwise pass through.
				default:
					$updated_fields[ $field ] = sprintf(
						/* Translators: %1$s is the previous value, %2$s is the current value */
						__( '"%1$s" to "%2$s"', 'stream' ),
						esc_html( $post_before->$field ),
						esc_html( $value )
					);
					break;
			}
		}

		$updated_terms     = array();
		$included_taxes    = $this->get_included_taxonomies( $post_after->ID );
		$post_before_terms = true;

		// Only do this if the filter is working.
		if ( false !== $post_before_terms ) {

			foreach ( $included_taxes as $tax ) {

				$previous_terms = apply_filters( "wp_stream_previous_{$post_after->ID}_{$tax}_terms", false );

				// Bail if the filter failed.
				if ( false === $previous_terms ) {
					continue;
				}

				$tax_terms = wp_list_pluck( get_the_terms( $post_after->ID, $tax ), 'term_taxonomy_id' );

				$added   = array_diff( $tax_terms, $previous_terms );
				$removed = array_diff( $previous_terms, $tax_terms );

				if ( ! empty( $added ) ) {
					$updated_terms[ $tax ]['added'] = $added;
				}

				if ( ! empty( $removed ) ) {
					$updated_terms[ $tax ]['removed'] = $removed;
				}
			}
		}

		// If none of the post fields or terms were updated, there should be a log somewhere else.
		if ( empty( $updated_fields ) && empty( $updated_terms ) ) {
			return;
		}

		$details = '';

		if ( ! empty( $updated_terms ) ) {
			foreach ( $updated_terms as $tax => $term_updates ) {
				$taxonomy = get_taxonomy( $tax );
				$tax_name = ( $taxonomy instanceof WP_Taxonomy ) ? $taxonomy->labels->singular_name : $tax;
				if ( ! empty( $term_updates['added'] ) ) {
					$added_terms = sprintf(
						/* Translators: %1$s is the taxonomy slug and %2$s is a linked list of the added terms. */
						__( ' %1$s terms added: %2$s ', 'stream' ),
						$tax_name,
						$this->make_term_list( $term_updates['added'], $tax )
					);
					$details .= sprintf( '%s<br />', $added_terms );
				}

				if ( ! empty( $term_updates['removed'] ) ) {
					$removed_terms = sprintf(
						/* Translators: %1$s is the taxonomy slug and %2$s is a linked list of the removed terms. */
						__( ' %1$s terms removed: %2$s', 'stream' ),
						$tax_name,
						$this->make_term_list( $term_updates['removed'], $tax )
					);

					$details .= sprintf( '%s<br />', $removed_terms );
				}
			}
		}

		if ( ! empty( $updated_fields ) ) {
			$details .= __( 'Post updates: ', 'stream' );
			// Creating a string for the summary. The array will be stored in the meta.
			$details .= array_reduce(
				array_keys( $updated_fields ),
				function ( $acc, $key ) use ( $updated_fields ) {
					return $acc .= sprintf( ' %s: %s, ', $key, $updated_fields[ $key ] );
				},
				''
			);

			$details = rtrim( $details, ', ' );
		}

		/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "HelloWorld", "Post") */
		$summary = _x(
			'"%1$s" %2$s updated',
			'1: Post title, 2: Post type singular name, 3: Fields updated list',
			'stream'
		);

		$log_summary = apply_filters( 'wp_stream_post_updated_summary', "{$summary}<br />{$details}", $post_after, $post_before, $post_before_terms );

		$this->log(
			$log_summary,
			array(
				'post_title'     => $post_after->post_title,
				'singular_name'  => $post_type_name,
				'fields_updated' => wp_json_encode( $updated_fields ),
				'terms_updated'  => wp_json_encode( $updated_terms ),
				'post_date'      => $post_after->post_date,
				'post_date_gmt'  => $post_after->post_date_gmt,
				'revision_id'    => $this->get_revision_id( $post_after ),
			),
			$post_after->ID,
			$post_after->post_type,
			$action
		);
	}

	/**
	 * Retrieves the author name with an optional link to the author's profile.
	 *
	 * @param int $author_id The ID of the author.
	 * @return string The author name with an optional link to the author's profile.
	 */
	private function get_author_maybe_link( $author_id ) {
		$author = get_userdata( $author_id );

		if ( empty( $author ) || is_wp_error( $author ) ) {
			/* Translators: %d is the user id. */
			return sprintf( __( 'Unknown user %d', 'stream' ), $author_id );
		}

		$author_name = $author->display_name;

		// This is the same cap check as in `get_edit_user_link()` so we'll use it
		// here to return just the name if the link won't work for the current user.
		if ( ! current_user_can( 'edit_user', $author_id ) ) {
			return $author_name;
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_user_link( $author_id ) ),
			esc_html( $author_name )
		);
	}

	/**
	 * Log post deletion
	 *
	 * @action deleted_post
	 *
	 * @param integer $post_id  Post ID.
	 */
	public function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing.
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_type, $this->get_excluded_post_types(), true ) ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$post_type_name = strtolower( $this->get_post_type_name( $post->post_type ) );

		$this->log(
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			_x(
				'"%1$s" %2$s deleted from trash',
				'1: Post title, 2: Post type singular name',
				'stream'
			),
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
			),
			$post->ID,
			$post->post_type,
			'deleted'
		);
	}

	/**
	 * Constructs list of excluded post types for the Posts connector
	 *
	 * @return array List of excluded post types
	 */
	public function get_excluded_post_types() {
		return apply_filters(
			'wp_stream_posts_exclude_post_types',
			array(
				'nav_menu_item',
				'attachment',
				'revision',
			)
		);
	}

	/**
	 * Retrieves the list of taxonomies to include when logging term changes.
	 *
	 * By default, it includes the 'post_tag' and 'category' taxonomies.
	 *
	 * @param int|string $post_id The post id.
	 *
	 * @return array The list of taxonomies to log.
	 */
	public function get_included_taxonomies( $post_id ) {
		/**
		 * Filter the taxonomies for which term changes should be logged.
		 *
		 * @param array      An array of the taxonomies.
		 * @param int|string The post id.
		 */
		return apply_filters(
			'wp_stream_posts_include_taxonomies',
			array(
				'post_tag',
				'category',
			),
			$post_id
		);
	}

	/**
	 * Gets the singular post type label
	 *
	 * @param string $post_type_slug  Post type slug.
	 *
	 * @return string Post type label
	 */
	public function get_post_type_name( $post_type_slug ) {
		$name = esc_html__( 'Post', 'stream' ); // Default.

		if ( post_type_exists( $post_type_slug ) ) {
			$post_type = get_post_type_object( $post_type_slug );
			$name      = $post_type->labels->singular_name;
		}

		return $name;
	}

	/**
	 * Get an adjacent post revision ID
	 *
	 * @param int  $revision_id  Revision ID.
	 * @param bool $previous     Has there been any revision before this one?.
	 *
	 * @return int $revision_id
	 */
	public function get_adjacent_post_revision( $revision_id, $previous = true ) {
		if ( empty( $revision_id ) || ! wp_is_post_revision( $revision_id ) ) {
			return false;
		}

		$revision = wp_get_post_revision( $revision_id );
		$operator = ( $previous ) ? '<' : '>';
		$order    = ( $previous ) ? 'DESC' : 'ASC';

		global $wpdb;
		// @codingStandardsIgnoreStart
		$revision_id = $wpdb->get_var( // db call okay
			$wpdb->prepare(
				"SELECT p.ID
				FROM $wpdb->posts AS p
				WHERE p.post_date {$operator} %s
					AND p.post_type = 'revision'
					AND p.post_parent = %d
				ORDER BY p.post_date {$order}
				LIMIT 1",
				$revision->post_date,
				$revision->post_parent
			)
		);
		// @codingStandardsIgnoreEnd
		// prepare okay

		$revision_id = absint( $revision_id );

		if ( ! wp_is_post_revision( $revision_id ) ) {
			return false;
		}

		return $revision_id;
	}


	/**
	 * Retrieves the ID of the latest revision for a given post.
	 *
	 * @param WP_Post $post The post object.
	 * @return int|null The ID of the latest revision, or null if revisions are not enabled for the post.
	 */
	public function get_revision_id( WP_Post $post ) {
		$revision_id = null;

		if ( wp_revisions_enabled( $post ) ) {
			$revision = get_children(
				array(
					'post_type'      => 'revision',
					'post_status'    => 'inherit',
					'post_parent'    => $post->ID,
					'posts_per_page' => 1, // VIP safe.
					'orderby'        => 'post_date',
					'order'          => 'DESC',
				)
			);

			if ( $revision ) {
				$revision    = array_values( $revision );
				$revision_id = $revision[0]->ID;
			}
		}

		return $revision_id;
	}
}
