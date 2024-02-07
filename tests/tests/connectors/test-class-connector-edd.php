<?php
/**
 * WP Integration Test w/ Easy Digital Downloads
 *
 * Tests for EDD Connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_EDD extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_EDD class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_EDD::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	/**
	 * Runs after each test
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Create a download
	 *
	 * @return int
	 */
	private function create_simple_download() {
		$post_id = wp_insert_post( array(
			'post_title'    => 'Test Download Product',
			'post_name'     => 'test-download-product',
			'post_type'     => 'download',
			'post_status'   => 'publish'
		) );

		$_download_files = array(
			array(
				'name'      => 'Simple File 1',
				'file'      => 'http://localhost/simple-file1.jpg',
				'condition' => 0
			),
		);

		$meta = array(
			'edd_price'                         => '20.00',
			'_variable_pricing'                 => 0,
			'edd_variable_prices'               => false,
			'edd_download_files'                => array_values( $_download_files ),
			'_edd_download_limit'               => 20,
			'_edd_hide_purchase_link'           => 1,
			'edd_product_notes'                 => 'Purchase Notes',
			'_edd_product_type'                 => 'default',
			'_edd_download_earnings'            => 40,
			'_edd_download_sales'               => 2,
			'_edd_download_limit_override_1'    => 1,
			'edd_sku'                           => 'sku_0012'
		);

		foreach( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return get_post( $post_id );
	}

	/**
	 * Create a percentage discount
	 *
	 * @return int
	 */
	private function create_simple_percent_discount() {
		$post = array(
			'code'              => '20OFF',
			'uses'              => 54,
			'max'               => 10,
			'name'              => '20 Percent Off',
			'type'              => 'percent',
			'amount'            => '20',
			'start'             => '12/12/2010 00:00:00',
			'expiration'        => '12/31/2050 23:59:59',
			'min_price'         => 128,
			'status'            => 'active',
			'product_condition' => 'all'
		);
		$discount_id = edd_store_discount( $post );

		return $discount_id;
	}

	public function test_edd_installed_and_activated() {
		$this->assertTrue( class_exists( 'Easy_Digital_Downloads' ) );
	}

	public function test_check() {
		// Expected log calls.
		$this->mock->expects( $this->exactly( 3 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Thousands Separator',
							'option'       => 'thousands_separator',
							'old_value'    => null,
							'value'        => '.',
							'tab'          => 'general',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Thousands Separator',
							'option'       => 'thousands_separator',
							'old_value'    => '.',
							'value'        => ',',
							'tab'          => 'general',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Thousands Separator',
							'option'       => 'thousands_separator',
							'old_value'    => ',',
							'value'        => null,
							'tab'          => 'general',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				)
			);

		// Update option to trigger callback.
		edd_update_option( 'thousands_separator', '.' );
		edd_update_option( 'thousands_separator', ',' );
		edd_update_option( 'thousands_separator' );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_add_option' ) );
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option' ) );
	}

	public function test_log_override() {
		// Callback for validating expected log data.
		$asserted = 0;
		add_action(
			'wp_stream_log_data',
			function( $data ) use( &$asserted ) {
				if ( 'edd' === $data['connector'] && in_array( $data['context'], array( 'downloads', 'discounts' ), true ) ) {
					$asserted++;
				}

				return $data;
			},
			99
		);

		// Create download and discount to trigger logs.
		$this->create_simple_download();
		$this->create_simple_percent_discount();

		// Check assertion flags
		$this->assertSame( $asserted, 2 );
	}

	public function test_callback_edd_pre_update_discount_status() {
		// Create discount for later use.
		$post_id  = $this->create_simple_percent_discount();
		$discount = new \EDD_Discount( $post_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					sprintf(
						__( '"%1$s" discount %2$s', 'stream' ),
						$discount->name,
						esc_html__( 'deactivated', 'stream' )
					)
				),
				$this->equalTo(
					array(
						'post_id' => $post_id,
						'status'  => 'inactive',
					)
				),
				$this->equalTo( $post_id ),
				$this->equalTo( 'discounts' ),
				$this->equalTo( 'updated' )
			);

		// Update discount status to trigger callback.
		$discount->update_status( 'inactive' );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_edd_pre_update_discount_status' ) );
	}

	public function test_settings_transport_callbacks() {
		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Imported Settings', 'stream' ) ),
					$this->equalTo( array() ),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'imported' ),
				),
				array(
					$this->equalTo( __( 'Exported Settings', 'stream' ) ),
					$this->equalTo( array() ),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'exported' ),
				)
			);

		// Manually trigger callbacks.
		do_action( 'edd_import_settings' );
		do_action( 'edd_export_settings' );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_edd_import_settings' ) );
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_edd_export_settings' ) );
	}

	public function test_meta() {
		// Create and authenticate user.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'User API Key created', 'stream' ) ),
				$this->equalTo( array( 'meta_value' => 1 ) ),
				$this->equalTo( $user_id ),
				$this->equalTo( 'api_keys' ),
				'created'
			);

		// Update API key and trigger callback..
		$_POST['edd_set_api_key'] = 1;
		\edd_update_user_api_key( $user_id );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_add_user_meta' ) );
	}
}
