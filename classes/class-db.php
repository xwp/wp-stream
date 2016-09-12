<?php
namespace WP_Stream;

class DB {
	/**
	 * Hold the Driver class
	 *
	 * @var DB_Driver
	 */
	protected $driver;

	/**
	 * Number of records in last request
	 *
	 * @var int
	 */
	protected $found_records_count = 0;

	/**
	 * Class constructor.
	 *
	 * @param DB_Driver $driver Driver we want to use.
	 */
	public function __construct( $driver ) {
		$this->driver = $driver;
	}

	/**
	 * Insert a record
	 *
	 * @param array $record
	 *
	 * @return int
	 */
	public function insert( $record ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return false;
		}

		/**
		 * Filter allows modification of record information
		 *
		 * @param array $record
		 *
		 * @return array
		 */
		$record = apply_filters( 'wp_stream_record_array', $record );

		array_walk( $record, function( &$value, &$key ) {
			if ( ! is_array( $value ) ) {
				$value = strip_tags( $value );
			}
		});

		if ( empty( $record ) ) {
			return false;
		}

		$fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action' );
		$data   = array_intersect_key( $record, array_flip( $fields ) );

		$meta = array();
		foreach ( (array) $record['meta'] as $key => $vals ) {
			// If associative array, serialize it, otherwise loop on its members
			$vals = ( is_array( $vals ) && 0 !== key( $vals ) ) ? array( $vals ) : $vals;

			foreach ( (array) $vals as $num => $val ) {
				$vals[ $num ] = maybe_serialize( $val );
			}
			$meta[ $key ] = $vals;
		}

		$data['meta'] = $meta;

		$record_id = $this->driver->insert_record( $data );

		if ( ! $record_id ) {
			/**
			 * Fires on a record insertion error
			 *
			 * @param array $record
			 * @param mixed $result
			 */
			do_action( 'wp_stream_record_insert_error', $record, false );

			return false;
		}

		/**
		 * Fires after a record has been inserted
		 *
		 * @param int   $record_id
		 * @param array $record
		 */
		do_action( 'wp_stream_record_inserted', $record_id, $record );

		return absint( $record_id );
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * GROUP BY allows query to find just the first occurrence of each value in the column,
	 * increasing the efficiency of the query.
	 *
	 * @see assemble_records
	 * @since 1.0.4
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	public function existing_records( $column ) {
		// Sanitize column
		$allowed_columns = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
		if ( ! in_array( $column, $allowed_columns, true ) ) {
			return array();
		}

		$rows = $this->driver->get_column_values( $column );

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			$output_array = array();

			foreach ( $rows as $row ) {
				foreach ( $row as $cell => $value ) {
					$output_array[ $value ] = $value;
				}
			}

			return (array) $output_array;
		}

		$column = sprintf( 'stream_%s', $column );

		$term_labels = wp_stream_get_instance()->connectors->term_labels;
		return isset( $term_labels[ $column ] ) ? $term_labels[ $column ] : array();
	}

	/**
	 * Get stream records
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function get_records( $args ) {
		$defaults = array(
			// Search param
			'search'           => null,
			'search_field'     => 'summary',
			'record_after'     => null, // Deprecated, use date_after instead
			// Date-based filters
			'date'             => null, // Ex: 2015-07-01
			'date_from'        => null, // Ex: 2015-07-01
			'date_to'          => null, // Ex: 2015-07-01
			'date_after'       => null, // Ex: 2015-07-01T15:19:21+00:00
			'date_before'      => null, // Ex: 2015-07-01T15:19:21+00:00
			// Record ID filters
			'record'           => null,
			'record__in'       => array(),
			'record__not_in'   => array(),
			// Pagination params
			'records_per_page' => get_option( 'posts_per_page', 20 ),
			'paged'            => 1,
			// Order
			'order'            => 'desc',
			'orderby'          => 'date',
			// Fields selection
			'fields'           => array(),
		);

		// Additional property fields
		$properties = array(
			'user_id'   => null,
			'user_role' => null,
			'ip'        => null,
			'object_id' => null,
			'site_id'   => null,
			'blog_id'   => null,
			'connector' => null,
			'context'   => null,
			'action'    => null,
		);

		/**
		 * Filter allows additional query properties to be added
		 *
		 * @return array Array of query properties
		 */
		$properties = apply_filters( 'wp_stream_query_properties', $properties );

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}

			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @return array  Array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$result = (array) $this->driver->get_records( $args );
		$this->found_records_count = isset( $result['count'] ) ? $result['count'] : 0;

		return empty( $result['items'] ) ? array() : $result['items'];
	}

	/**
	 * Helper function, backwards compatibility
	 *
	 * @param array $args Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		return $this->get_records( $args );
	}

	/**
	 * Return the number of records found in last request
	 *
	 * return int
	 */
	public function get_found_records_count() {
		return $this->found_records_count;
	}

	/**
	 * Public getter to return table names
	 *
	 * @return array
	 */
	public function get_table_names() {
		return $this->driver->get_table_names();
	}
}
