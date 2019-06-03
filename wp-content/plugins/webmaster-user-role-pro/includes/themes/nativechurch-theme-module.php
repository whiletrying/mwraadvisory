<?php
class TDWUR_NativeChurch_Theme {
	private $active;

	function __construct() {
		add_filter( 'webmaster_supported_theme', array( $this, 'is_supported_theme' ) );
		add_filter( 'webmaster_supported_theme_setting_fields', array( $this, 'setting_fields' ) );
	}

	function is_supported_theme( $supported ) {
		if ( $supported ) return $supported;
		
		return $this->is_active();
	}

	function is_active() {
		if ( $this->active ) return true;

		$current_theme = wp_get_theme();
		if ( $current_theme->Name=='Native Church' || $current_theme->Name=='NativeChurch' || $current_theme->Template=='nativechurch' || $current_theme->Template=='Native Church' ) {
			$this->active = true;
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 100 );

			return true;
		}

		return false;
	}

	function setting_fields( $fields = array() ) {
		if ( ! $this->is_active() ) return $fields;

		$fields = array();
		$fields[] = array(
			'id'        => 'nativechurch_theme_settings',
			'type'      => 'checkbox',
			'title'     => __('NativeChurch Theme Compatibility', 'webmaster-user-role'),
			'subtitle'  => __('Webmaster (Admin) users can', 'webmaster-user-role'),

			'options'   => array(
				'access_theme_options_panel' => __('Access Theme Options panel', 'webmaster-user-role'),
			),

			'default'   => array(
				'access_theme_options_panel' => '0',
			)
		);

		return $fields;
	}

	function admin_menu() {
		if ( !TD_WebmasterUserRole::current_user_is_webmaster() ) return;

		$webmaster_user_role_config = TD_WebmasterUserRole::get_config();
		if ( !is_array( $webmaster_user_role_config ) ) return;

		if ( empty( $webmaster_user_role_config['nativechurch_theme_settings']['access_theme_options_panel'] ) ) remove_menu_page( '_options' );
	}

}
new TDWUR_NativeChurch_Theme();