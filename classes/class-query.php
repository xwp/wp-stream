<?php
/**
 * Queries the database for stream records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Query
 */
class Query {
	/**
	 * Hold the number of records found
	 *
	 * @var int
	 */
	public $found_records = 0;

	/**
	 * Query records
	 *
	 * @param array $args Arguments to filter the records by.
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$join  = '';
		$where = '';

		/**
		 * PARSE CORE PARAMS
		 */
		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.blog_id = %d", $args['blog_id'] );
		}

		if ( is_numeric( $args['object_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.object_id = %d", $args['object_id'] );
		}

		if ( is_numeric( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.user_id = %d", $args['user_id'] );
		}

		if ( ! empty( $args['user_role'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.user_role = %s", $args['user_role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$field = ! empty( $args['search_field'] ) ? $args['search_field'] : 'summary';

			// Sanitize field.
			$allowed_fields = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
			if ( in_array( $field, $allowed_fields, true ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.{$field} LIKE %s", "%{$args['search']}%" ); // @codingStandardsIgnoreLine can't prepare column name
			}
		}

		if ( ! empty( $args['connector'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.connector = %s", $args['connector'] );
		}

		if ( ! empty( $args['context'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.context = %s", $args['context'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.action = %s", $args['action'] );
		}

		if ( ! empty( $args['ip'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip = %s", wp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		/**
		 * PARSE DATE PARAM FAMILY
		 */
		if ( ! empty( $args['date'] ) ) {
			$args['date_from'] = $args['date'];
			$args['date_to']   = $args['date'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_from'] . ' 00:00:00' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) >= %s", $date );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_to'] . ' 23:59:59' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) <= %s", $date );
		}

		if ( ! empty( $args['date_after'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_after'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) > %s", $date );
		}

		if ( ! empty( $args['date_before'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_before'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) < %s", $date );
		}

		/**
		 * PARSE __IN PARAM FAMILY
		 */
		$ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__in' === substr( $arg, -4 ) ) {
				$ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $ins ) ) {
			foreach ( $ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
				}
			}
		}

		/**
		 * PARSE __NOT_IN PARAM FAMILY
		 */
		$not_ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__not_in' === substr( $arg, -8 ) ) {
				$not_ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $not_ins ) ) {
			foreach ( $not_ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__not_in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s NOT IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
				}
			}
		}

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$limits   = '';
		$page     = absint( $args['paged'] );
		$per_page = absint( $args['records_per_page'] );

		if ( $per_page >= 0 ) {
			$offset = absint( ( $page - 1 ) * $per_page );
			$limits = "LIMIT {$offset}, {$per_page}";
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'summary', 'created', 'connector', 'context', 'action' );

		// Default to sorting by record ID.
		$orderby = "$wpdb->stream.ID";

		if ( in_array( $args['orderby'], $orderable, true ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->stream, $args['orderby'] );
		} elseif ( 'meta_value_num' === $args['orderby'] && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $args['orderby'] && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		}

		// Show the recent records first by default.
		$order = 'DESC';
		if ( 'ASC' === strtoupper( $args['order'] ) ) {
			$order = 'ASC';
		}

		$orderby = sprintf( 'ORDER BY %s %s', $orderby, $order );

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields  = (array) $args['fields'];
		$selects = array();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				// We'll query the meta table later.
				if ( 'meta' === $field ) {
					continue;
				}

				$selects[] = sprintf( "$wpdb->stream.%s", $field );
			}
		} else {
			$selects[] = "$wpdb->stream.*";
		}

		$join      = "LEFT JOIN $wpdb->streammeta ON $wpdb->stream.ID = $wpdb->streammeta.record_id";
		$selects[] = "$wpdb->streammeta.meta_key";
		$selects[] = "$wpdb->streammeta.meta_value";
		$select    = implode( ', ', $selects );

		/**
		 * Filters query WHERE statement as an alternative to filtering
		 * the $query using the hook below.
		 *
		 * @param string $where  WHERE statement.
		 *
		 * @return string
		 */
		$where = apply_filters( 'wp_stream_db_query_where', $where );

		/**
		* Build the query but just to get the IDs
		*/
		$query = "SELECT $wpdb->stream.ID
		FROM $wpdb->stream
		WHERE 1=1 {$where}
		{$orderby}
		{$limits}";

		$ids = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $ids ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ID IN (%d" . str_repeat( ', %d', count( $ids ) - 1 ) . ')', $ids );
		} else {
			$where .= ' AND 0 = 1';
		}

		/**
		 * BUILD THE FINAL QUERY
		 */
		$query = "SELECT {$select}
		FROM $wpdb->stream
		{$join}
		WHERE 1=1 {$where}
		{$orderby}";

		/**
		 * Filter allows the final query to be modified before execution
		 *
		 * @param string $query
		 * @param array  $args
		 *
		 * @return string
		 */
		$query = apply_filters( 'wp_stream_db_query', $query, $args );

		// Build result count query.
		$count_query = "SELECT COUNT(*) as found
		FROM $wpdb->stream
		WHERE 1=1 {$where}";

		/**
		 * Filter allows the result count query to be modified before execution.
		 *
		 * @param string $query
		 * @param array  $args
		 *
		 * @return string
		 */
		$count_query = apply_filters( 'wp_stream_db_count_query', $count_query, $args );

		$items = array();
		foreach ( $wpdb->get_results( $query ) as $item ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! isset( $items[ $item->ID ] ) ) {
				$items[ $item->ID ]       = clone $item;
				$items[ $item->ID ]->meta = array();
				unset( $items[ $item->ID ]->meta_key );
				unset( $items[ $item->ID ]->meta_value );
			}
			if ( isset( $items[ $item->ID ]->meta[ $item->meta_key ] ) ) {
				if ( ! is_array( $items[ $item->ID ]->meta[ $item->meta_key ] ) ) {
					$items[ $item->ID ]->meta[ $item->meta_key ] = array( $items[ $item->ID ]->meta[ $item->meta_key ] );
				}
				$items[ $item->ID ]->meta[ $item->meta_key ][] = $item->meta_value;
			} else {
				$items[ $item->ID ]->meta[ $item->meta_key ] = $item->meta_value;
			}
		}
		/**
		 * QUERY THE DATABASE FOR RESULTS
		 */
		$result = array(
			'items' => $items,
			'count' => absint( $wpdb->get_var( $count_query ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $result;
	}
}
