<?php
namespace WP_Stream;

class Test_Plugin extends WP_StreamTestCase {

	/**
	 * Make sure the plugin is initialized with it's global variable.
	 */
	public function test_plugin_initialized() {
		$this->assertFalse( null == $this->plugin );
	}

	/*
	 * Also tests private method locate_plugin
	 */
	public function test_construct() {
		$this->assertInternalType( 'array', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations );
		$this->assertArrayHasKey( 'plugin', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations['plugin'] );
		$this->assertArrayHasKey( 'dir', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations['dir'] );
		$this->assertArrayHasKey( 'url', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations['url'] );
		$this->assertArrayHasKey( 'inc_dir', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations['inc_dir'] );
		$this->assertArrayHasKey( 'class_dir', $this->plugin->locations );
		$this->assertNotEmpty( $this->plugin->locations['class_dir'] );

		$this->assertNotEmpty( $this->plugin->db );
		$this->assertNotEmpty( $this->plugin->log );
		$this->assertNotEmpty( $this->plugin->admin );
		$this->assertNotEmpty( $this->plugin->install );
	}

	public function test_autoload() {
		$this->assertTrue( class_exists( '\WP_Stream\Admin' ) );
		$this->assertFalse( class_exists( '\WP_Stream\HAL9000' ) );
	}

	public function test_i18n() {
		global $l10n;

		/**
		 * Make sure we get the correct MO file during tests.
		 * WP looks in develop installation where MO file is not found.
		 */
		add_filter( 'load_textdomain_mofile', function( $mofile ) {
			$locale = get_locale();
			$mofile = sprintf( '%s/languages/stream-%s.mo', $this->plugin->locations['dir'], $locale );
			return $mofile;
		} );

		$this->plugin->i18n();
		$this->assertArrayHasKey( 'stream', $l10n );
	}

	public function test_init() {
		$this->plugin->settings   = null;
		$this->plugin->alerts     = null;
		$this->plugin->connectors = null;

		$this->assertEmpty( $this->plugin->settings );
		$this->assertEmpty( $this->plugin->alerts );
		$this->assertEmpty( $this->plugin->connectors );

		$this->plugin->init();

		$this->assertNotEmpty( $this->plugin->settings );
		$this->assertNotEmpty( $this->plugin->alerts );
		$this->assertNotEmpty( $this->plugin->connectors );
	}

	public function test_frontend_indicator() {
		ob_start();
		$this->plugin->frontend_indicator();
		$comment = ob_get_clean();

		$this->assertNotEmpty( $comment );
		$this->assertContains( 'Stream WordPress user activity plugin', $comment );
	}

	public function test_get_version() {
		$version = $this->plugin->get_version();
		$this->assertNotEmpty( $version );
	}

	public function test_get_client_ip_address() {
		$this->assertEquals( $_SERVER['REMOTE_ADDR'], $this->plugin->get_client_ip_address() );
	}
}
