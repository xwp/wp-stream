<?php
/**
 * Database Driver class for "stream" table responsible for holding records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - DB_Driver_WPDB
 */
class DB_Driver_WPDB implements DB_Driver {
	/**
	 * Holds Query class
	 *
	 * @var Query
	 */
	protected $query;

	/**
	 * Hold records table name
	 *
	 * @var string
	 */
	public $table;

	/**
	 * Hold meta table name
	 *
	 * @var string
	 */
	public $table_meta;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->query = new Query( $this );

		global $wpdb;
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		$this->table      = $prefix . 'stream';
		$this->table_meta = $prefix . 'stream_meta';

		$wpdb->stream     = $this->table;
		$wpdb->streammeta = $this->table_meta;

		// Hack for get_metadata.
		$wpdb->recordmeta = $this->table_meta;
	}

	/**
	 * Insert a record.
	 *
	 * @param array $data Data to insert.
	 *
	 * @return int
	 */
	public function insert_record( $data ) {
		global $wpdb;

		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return false;
		}

		$meta = array();
		if ( array_key_exists( 'meta', $data ) ) {
			$meta = $data['meta'];
			unset( $data['meta'] );
		}

		$result = $wpdb->insert( $this->table, $data );
		if ( ! $result ) {
			return false;
		}

		$record_id = $wpdb->insert_id;

		// Insert record meta.
		foreach ( (array) $meta as $key => $val ) {
			if ( is_array( $val ) ) {
				$vals = $val;
				foreach ( $vals as $k => $val ) {
					if ( is_scalar( $val ) && '' !== $val ) {
						$this->insert_meta( $record_id, $key . '[' . $k . ']', substr( $val, 0, 200 ) );
					}
				}
			} else {
				if ( is_scalar( $val ) && '' !== $val ) {
					$this->insert_meta( $record_id, $key, substr( $val, 0, 200 ) );
				}
			}
		}

		return $record_id;
	}

	/**
	 * Insert record meta
	 *
	 * @param int    $record_id Record ID.
	 * @param string $key       Meta Key.
	 * @param string $val       Meta Data.
	 *
	 * @return array
	 */
	public function insert_meta( $record_id, $key, $val ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_meta,
			array(
				'record_id'  => $record_id,
				'meta_key'   => $key,
				'meta_value' => $val,
			)
		);

		return $result;
	}

	/**
	 * Retrieve records
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function get_records( $args ) {
		return $this->query->query( $args );
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * GROUP BY allows query to find just the first occurrence of each value in the column,
	 * increasing the efficiency of the query.
	 *
	 * @param string $column Column being filtered.
	 *
	 * @return array
	 */
	public function get_column_values( $column ) {
		global $wpdb;
		return (array) $wpdb->get_results(
			"SELECT DISTINCT $column FROM $wpdb->stream", // @codingStandardsIgnoreLine can't prepare column name
			'ARRAY_A'
		);
	}

	/**
	 * Public getter to return table names
	 *
	 * @return array
	 */
	public function get_table_names() {
		return array(
			$this->table,
			$this->table_meta,
		);
	}

	/**
	 * Init storage.
	 *
	 * @param \WP_Stream\Plugin $plugin Instance of the plugin.
	 * @return \WP_Stream\Install
	 */
	public function setup_storage( $plugin ) {
		return new Install( $plugin );
	}

	/**
	 * Purge storage.
	 *
	 * @param \WP_Stream\Plugin $plugin Instance of the plugin.
	 */
	public function purge_storage( $plugin ) {
		// @TODO: Not doing anything here until the deactivation/uninstall flow has been rethought.
	}
}
