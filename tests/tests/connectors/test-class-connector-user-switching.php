<?php
/**
 * WP Integration Test w/ User Switching plugin
 *
 * Tests for User Switching connector class callbacks.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

class Test_WP_Stream_Connector_User_Switching extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_User_Switching class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_User_Switching::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Confirm that User Switching is installed and active.
	 */
	public function test_user_switching_installed_and_activated() {
		$this->assertTrue( class_exists( 'user_switching' ) );
	}

	public function test_callback_switch_to_user() {
		// Create and authenticate user to be switched from.
		$old_user_id    = self::factory()->user->create(
			array(
				'user_login'   => 'oldtestuser',
				'user_role'    => 'administrator',
				'display_name' => 'oldtestuserdisplay',
			)
		);
		wp_set_current_user( $old_user_id );

		// Create user ID for destination user.
		$user_id        = self::factory()->user->create(
			array(
				'user_login'   => 'testuser',
				'user_role'    => 'administrator',
				'display_name' => 'testuserdisplay',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'Switched user to %1$s (%2$s)',
						'1: User display name, 2: User login',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => 'testuserdisplay',
						'user_login'   => 'testuser',
					)
				),
				$this->equalTo( $old_user_id ),
				$this->equalTo( 'sessions' ),
				$this->equalTo( 'switched-to' ),
				$this->equalTo( $old_user_id )
			);

		// Switch to user to trigger callback.
		\switch_to_user( $user_id );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_switch_to_user' ) );
	}

	public function test_callback_switch_back_user() {
		// Create and authenticate users for later use.
		$old_user_id    = self::factory()->user->create(
			array(
				'user_login'   => 'oldtestuser',
				'user_role'    => 'administrator',
				'display_name' => 'oldtestuserdisplay',
			)
		);
		$user_id        = self::factory()->user->create(
			array(
				'user_login'   => 'testuser',
				'user_role'    => 'administrator',
				'display_name' => 'testuserdisplay',
			)
		);

		wp_set_current_user( $old_user_id );
		\switch_to_user( $user_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'Switched back to %1$s (%2$s)',
						'1: User display name, 2: User login',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => 'oldtestuserdisplay',
						'user_login'   => 'oldtestuser',
					)
				),
				$this->equalTo( $user_id ),
				$this->equalTo( 'sessions' ),
				$this->equalTo( 'switched-back' ),
				$this->equalTo( $user_id )
			);

		// Switch to user to trigger callback.
		\switch_to_user( $old_user_id, false, false );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_switch_back_user' ) );
	}

	public function test_callback_switch_off_user() {
		// Create/authenticate user for later use.
		$user_id        = self::factory()->user->create(
			array(
				'user_login'   => 'testuser',
				'user_role'    => 'administrator',
				'display_name' => 'testuserdisplay',
			)
		);
		wp_set_current_user( $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Switched off', 'stream' ) ),
				$this->equalTo( array() ),
				$this->equalTo( $user_id ),
				$this->equalTo( 'sessions' ),
				$this->equalTo( 'switched-off' ),
				$this->equalTo( $user_id )
			);

		// Switch to user to trigger callback.
		\switch_off_user();

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_switch_off_user' ) );
	}
}
