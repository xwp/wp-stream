<?php
/**
 * Manages a single alert, acting as a model.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert
 *
 * @package WP_Stream
 */
class Alert {

	/**
	 * Alert post ID
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Creation date
	 *
	 * @var string
	 */
	public $date;

	/**
	 * Status
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Alert author ID
	 *
	 * @var int
	 */
	public $author;

	/**
	 * Alert type
	 *
	 * @var string
	 */
	public $alert_type;

	/**
	 * Alert meta data
	 *
	 * @var int
	 */
	public $alert_meta;

	/**
	 * Holds instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class constructor
	 *
	 * @param object $item Alert data.
	 * @param Plugin $plugin Instance of plugin object.
	 * @return void
	 */
	public function __construct( $item, $plugin ) {
		$this->plugin = $plugin;

		$this->ID     = isset( $item->ID ) ? $item->ID : null;
		$this->status = isset( $item->status ) ? $item->status : 'wp_stream_disabled';
		$this->date   = isset( $item->date ) ? $item->date : null;
		$this->author = isset( $item->author ) ? $item->author : null;

		$this->alert_type = isset( $item->alert_type ) ? $item->alert_type : null;
		$this->alert_meta = isset( $item->alert_meta ) ? $item->alert_meta : array();
	}

	/**
	 * Saves alert state.
	 *
	 * @todo Clean up/Remove unnecessary conditional statements.
	 * @return int The Post ID of the alert.
	 */
	public function save() {

		$args = array(
			'ID'           => $this->ID,
			'post_date'    => $this->date,
			'post_status'  => $this->status,
			'post_content' => '',
			'post_title'   => $this->get_title(),
			'post_author'  => $this->author,
			'post_type'    => Alerts::POST_TYPE,
		);

		// Remove empty "ID" field, if new post.
		if ( empty( $args['ID'] ) ) {
			unset( $args['ID'] );
		}

		// Create or update alert and assign the ID.
		$post_id = wp_insert_post( $args );
		if ( empty( $args['ID'] ) ) {
			$this->ID = $post_id;
		}

		// Save alert type and meta.
		$meta = array(
			'alert_type' => $this->alert_type,
			'alert_meta' => $this->alert_meta,
		);

		foreach ( $meta as $key => $value ) {
			$this->update_meta( $key, $value );
		}

		return $post_id;
	}

	/**
	 * Process settings form data
	 *
	 * @todo Confirm if the function is necessary, it's currently unreference
	 * anywhere else in the plugin.
	 * @param array $data Processed post object data.
	 * @return array New post object data.
	 */
	public function process_settings_form( $data ) {

		$args = array(
			'post_date'   => $this->date,
			'post_status' => $this->status,
			'post_title'  => $this->get_title(),
			'post_author' => $this->author,
			'post_type'   => Alerts::POST_TYPE,
		);

		foreach ( $args as $key => $value ) {
			$data[ $key ] = $value;
		}

		$meta_input = array(
			'alert_type' => $this->alert_type,
			'alert_meta' => $this->alert_meta,
		);

		foreach ( $meta_input as $key => $value ) {
			$this->update_meta( $key, $value );
		}

		return $data;
	}

	/**
	 * Query record meta
	 *
	 * @param string $meta_key Meta key to retrieve (optional). Otherwise will
	 *  grab all meta data for the ID.
	 * @param bool   $single Whether to only retrieve the first value (optional).
	 *
	 * @return mixed Single value if $single is true, array if false.
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		return get_post_meta( $this->ID, $meta_key, $single );
	}

	/**
	 * Update record meta
	 *
	 * @param string $meta_key Meta key to update.
	 * @param string $meta_value Value to update with.
	 * @param string $prev_value Previous value to change (optional).
	 * @return array
	 */
	public function update_meta( $meta_key, $meta_value, $prev_value = '' ) {
		return update_post_meta( $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of the alert.
	 *
	 * @todo enhance human readability
	 * @return string The title of the alert
	 */
	public function get_title() {

		$alert_type = $this->get_alert_type_obj()->name;

		$output = array();
		foreach ( array( 'action', 'author', 'context' ) as $trigger_type ) {
			$output[ $trigger_type ] = $this->plugin->alerts->alert_triggers[ $trigger_type ]->get_display_value( 'list_table', $this );
		}
		$title = '';
		foreach ( $this->plugin->alerts->alert_triggers as $trigger_type => $trigger_obj ) {
			$value  = $trigger_obj->get_display_value( 'list_table', $this );
			$title .= $value . ' > ';
		}
		$title = rtrim( $title, ' > ' );
		return $title;
	}

	/**
	 * Retrieve current alert type object
	 *
	 * @return Alert_Type
	 */
	public function get_alert_type_obj() {
		if ( array_key_exists( $this->alert_type, $this->plugin->alerts->alert_types ) ) {
			$obj = $this->plugin->alerts->alert_types[ $this->alert_type ];
		} else {
			$obj = new Alert_Type_None( $this->plugin );
		}
		return $obj;
	}

	/**
	 * Check if record matches trigger criteria.
	 *
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @return bool True if a positive match. False otherwise.
	 */
	public function check_record( $record_id, $recordarr ) {
		return apply_filters( 'wp_stream_alert_trigger_check', true, $record_id, $recordarr, $this );
	}

	/**
	 * Trigger alert for a specific record.
	 *
	 * @param int $record_id Record ID.
	 * @param int $recordarr Record Data.
	 */
	public function send_alert( $record_id, $recordarr ) {
		$this->get_alert_type_obj()->alert( $record_id, $recordarr, $this );
	}

	/**
	 * Record Alerts triggered by a Record.
	 *
	 * This should be used any time an alert is triggered.
	 *
	 * Stores the post ID of an Alert triggered by a record.
	 *
	 * As an example, this exists so that its value(s) can then be used
	 * to fetch meta from that Alert in later operations.
	 *
	 * This also creates the Alert triggered meta if not found.
	 *
	 * @see Alert_Type_Highlight::alert() for an example.
	 *
	 * @param object $record     The Record.
	 * @param string $alert_slug The Alert Type slug.
	 * @param array  $alert_meta Alert meta.
	 *
	 * @return bool If the meta was updated successfully.
	 */
	public static function update_record_triggered_alerts( $record, $alert_slug, $alert_meta ) {
		if ( ! is_string( $alert_slug ) ) {
			return false;
		}

		if ( is_array( $record ) ) {
			$record = (object) $record;
		}
		if ( empty( $record->ID ) ) {
			return false;
		}
		$record           = new Record( $record );
		$alerts_triggered = $record->get_meta( Alerts::ALERTS_TRIGGERED_META_KEY, true );

		if ( empty( $alerts_triggered ) || ! is_array( $alerts_triggered ) ) {
			$alerts_triggered = array(
				$alert_slug => $alert_meta,
			);
		} elseif ( ! array_key_exists( $alert_slug, $alerts_triggered ) || ! is_array( $alerts_triggered[ $alert_slug ] ) ) {
			$alerts_triggered[ $alert_slug ] = $alert_meta;
		}
		return $record->update_meta( Alerts::ALERTS_TRIGGERED_META_KEY, $alerts_triggered );
	}

	/**
	 * Get a meta value from the Alert that a Record has triggered.
	 *
	 * If a Record has triggered an Alert (post), this fetches a specific
	 * Alert meta (i.e., "post meta") value from that Alert.
	 *
	 * First, it gets the array of Alerts that the Record has triggered.
	 * Then, using the requested Alert Type, it grabs the first item (post ID) in
	 * that Type.
	 *
	 * Using that ID, it fetches that Alert post's meta, then
	 * returns the value of the requested setting (ie., "post meta" field).
	 *
	 * @see Alert_Type_Highlight::post_class() for an example.
	 *
	 * @param object $record The Record object.
	 * @param string $alert_slug The slug of the Alert Type.
	 * @param string $setting The requested meta value of the Alert.
	 * @param mixed  $default The default value if no value is found.
	 *
	 * @return mixed
	 */
	public function get_single_alert_setting_from_record( $record, $alert_slug, $setting, $default = false ) {
		if ( ! is_object( $record ) || ! is_string( $alert_slug ) || ! is_string( $setting ) ) {
			return false;
		}
		$record           = new Record( $record );
		$alerts_triggered = $record->get_meta( Alerts::ALERTS_TRIGGERED_META_KEY, true );

		// Ensure we have a meta array and that this record has triggered a highlight alert.
		if ( empty( $alerts_triggered ) || ! is_array( $alerts_triggered ) || ! array_key_exists( $alert_slug, $alerts_triggered ) ) {
			return false;
		}

		$values = $alerts_triggered[ $alert_slug ];
		if ( empty( $values ) ) {
			return false;
		}

		/**
		 * Grab an Alert post ID.
		 *
		 * @todo Determine which Alert post takes priority.
		 */
		if ( is_array( $values ) ) {
			$post_id = $values[0];
		} else {
			$post_id = $values;
		}

		if ( ! is_numeric( $post_id ) ) {
			return false;
		}

		$alert = $this->plugin->alerts->get_alert( $post_id );

		$value = ! empty( $alert->alert_meta[ $setting ] ) ? $alert->alert_meta[ $setting ] : $default;
		return $value;
	}
}
