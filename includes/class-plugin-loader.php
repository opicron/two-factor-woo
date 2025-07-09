<?php

/**
 * Plugin_Loader class file.
 *
 * @package two-factor-woo
 */

namespace Two_Factor_Woo;
use Two_Factor_Core, WP_error;



class Plugin_Loader {

	public static function load()
	{
		// Register plugin assets
		add_action( 'wp_enqueue_scripts', [self::class, 'register_assets'] );

		// add init action
		add_action( 'init', array( self::class, 'init' ), 11 );

		// add two factor endpoint
		add_action( 'init', [self::class,'woo_add_two_factor_endpoint'] );
	}

	public static function register_assets()
	{
		wp_register_script(
			'two-factor-woo',
			TWO_FACTOR_WOO_URI . '/assets/two-factor-woo.js',
			array(),
			filemtime( TWO_FACTOR_WOO_PATH . '/assets/two-factor-woo.js' ),
			array( 'in_footer' => true )
		);

		// Localize the AJAX URL
		// do we need to add a nonce?
		wp_localize_script('two-factor-woo', 'WC_2FA', [
			'ajax_url' => admin_url('admin-ajax.php?action=wc_2fa_login_check'),
			//'nonce'    => wp_create_nonce('wc_2fa_nonce')
		]);

		wp_enqueue_script( 'two-factor-woo' );
	}

	public static function init()
	{
		// add query var endpoint
		add_filter( 'woocommerce_get_query_vars', [self::class, 'woo_add_query_var_endpoint'] );

		// woo add two factor to account menu
		add_filter( 'woocommerce_account_menu_items', [self::class,'woo_account_menu_add_two_factor'], 20 );

		// woo endpoint title
		add_filter( 'woocommerce_endpoint_two-factor_title', [self::class,'woo_endpoint_title'], 1, 2 );

		// filter providers
		add_filter( 'two_factor_providers', [self::class,'two_factor_remove_providers'] );

		// woocommerce endpoint 2fa settings screen
		add_action( 'woocommerce_account_two-factor_endpoint', [self::class,'woo_two_factor_endpoint_settings'] );

		// two factor settings  save form
		add_action('template_redirect', [self::class,'woo_settings_two_factor_save_form'] );

		// remove two factor login form on frontend
		add_action('wp_login', [self::class,'two_factor_remove_login_frontend'], 1, 2);

		// woocommerce process two factor login
		add_action('woocommerce_process_login_errors', [self::class,'woo_process_two_factor_login'], 999, 3);

		// wordpress process two factor login
		// HOW TO GET DEFAULT WORDPRESS LOGIN WORKING?
		//add_filter('wp_authenticate_user', [self::class,'wp_process_2fa_login'], 10, 2);

		// woocommerce add auth code to login form
		add_action( 'woocommerce_login_form', [self::class,'woo_add_login_auth_code_field'] );

		// woocommerce two step 2fa check
		add_action( 'wp_ajax_nopriv_wc_2fa_login_check', [self::class,'woo_login_2fa_check'] );
		add_action( 'wp_ajax_wc_2fa_login_check', [self::class,'woo_login_2fa_check'] );

	}

	// wordpress process two factor login
	// HOW TO GET THIS WORKING? ALWAYS THROWS INVALID AUTH CODE
	public static function wp_process_2fa_login($user, $password)
	{
		// Only process on WooCommerce login form
		if (!defined('WC_DOING_FRONTEND_LOGIN')) {
			if (isset($_POST['login']) && !is_admin() && !defined('DOING_AJAX')) {
				define('WC_DOING_FRONTEND_LOGIN', true);
			}
		}

		if (!defined('WC_DOING_FRONTEND_LOGIN') || !isset($_POST['authcode']))
			return $user;

		// 2FA required for this user?
		if (class_exists('Two_Factor_Core') && Two_Factor_Core::is_user_using_two_factor($user->ID)) {
			$provider = Two_Factor_Core::get_primary_provider_for_user($user->ID);
			//$code = isset($_POST['authcode']) ? trim($_POST['authcode']) : '';
			if ($provider || !$provider->validate_authentication($user)) {
				// This will trigger WooCommerce login error
				return new WP_Error('two_factor', __('Invalid authentication code2.', 'your-textdomain'));
        		}
		}
		return $user;
	}

	// woocommerce two step 2fa check

	public static function woo_login_2fa_check()
	{
		$username = $_POST['username'] ?? '';
		$password = $_POST['password'] ?? '';

		$user = wp_authenticate($username, $password);
		if (is_wp_error($user)) {
			//this will cause js to do a regular submit form
			wp_send_json(['success' => false, 'message' => 'Invalid credentials']);
		}

		if (Two_Factor_Core::is_user_using_two_factor($user->ID)) {
			wp_send_json(['two_factor_required' => true]);
		}

		// If no 2FA, allow login (handle yourself or let WC process)
		wp_send_json(['success' => true, 'redirect' => wc_get_page_permalink('myaccount')]);
	}

	// woocommerce add auth code to login form

	public static function woo_add_login_auth_code_field()
	{
		//return if not account page (or checkout)
		if ( !is_account_page() && !is_checkout() )
			return;

		?>
			<div id="two-factor-2fa-wrap" style="display:none;">
				<label for=authcode"><?php _e('Authentication Code', 'your-textdomain'); ?></label>
				<input type="text" name="authcode" id="authcode" autocomplete="off" pattern="[0-9 ]*" inputmode="numeric" placeholder="eg. 123456" class="input" />
				<span class="two-factor-error" style="display:none;color:red;"></span>
			</div>
		<?php
	}

	// woocommerce process two factor login

	public static function woo_process_two_factor_login($errors, $username, $password)
	{
		if (!empty($_POST['authcode'])) {
        		$user = get_user_by('login', $username);
			if ($user && Two_Factor_Core::is_user_using_two_factor($user->ID)) {
				$provider = Two_Factor_Core::get_primary_provider_for_user($user->ID);
				if ($provider && !$provider->validate_authentication($user)) {
					$errors->add('two_factor', __('Invalid authentication code.', 'your-textdomain'));
				}
			}
		}
		else
		{
			// when javascript not loaded this makes sure to throw error
			// else user is logged in without auth code!
			$errors->add('two_factor', __('No authentication code.', 'your-textdomain'));
		}
		return $errors;
	}


	// remove two factor login form on frontend

	public static function two_factor_remove_login_frontend($user_login, $user)
	{
		//only remove in frontend
		if (!is_login())
		{
			remove_action( 'wp_login', [ 'Two_Factor_Core', 'wp_login' ]);
		}
	}

	// add two factor endpoint

	public static function woo_add_two_factor_endpoint()
	{
		add_rewrite_endpoint( 'two-factor', EP_ROOT | EP_PAGES );
	}

	// add query var endpoint

	public static function woo_add_query_var_endpoint( $endpoints )
	{
		$endpoints['two-factor'] = 'two-factor';
		return $endpoints;
	}

	// woo add two factor to account menu

	public static function woo_account_menu_add_two_factor( $items )
	{
		$new = [];
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'dashboard' ) {
				$new['two-factor'] = __( '2FA Authentication', 'your-textdomain' );
			}
		}
		return $new;
	}

	// woo endpoint title

	public static function woo_endpoint_title( $title, $endpoint )
	{
		return __( '2FA Authentication', 'your-textdomain' );
	}

	// unset providers on front end

	public static function two_factor_remove_providers( $providers )
	{
		if (!is_admin())
		{
			// Unset providers you don't want
			unset( $providers['Two_Factor_FIDO_U2F'] ); //instead of remove_action in init function
			unset( $providers['Two_Factor_Dummy'] );
		}

		return $providers;
	}


	// save form
	// THIS CAUSES ERROR DUE TO Two_Factor_Core

	public static function woo_settings_two_factor_save_form()
	{

		// check endpoint
		if ( ! is_user_logged_in() || ! is_wc_endpoint_url('two-factor') )
			return;

		if (! isset($_POST['frontend_two_factor_nonce']) )
			return;

		if (! wp_verify_nonce($_POST['frontend_two_factor_nonce'], 'frontend_two_factor_update') )
			return;

		$user_id = get_current_user_id();

		//copy of TwoFactorCore code
		//..

		if ( ! isset( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] ) ||
			! is_array( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] ) ) {
			return;
		}

		if ( ! Two_Factor_Core::current_user_can_update_two_factor_options( 'save' ) ) {
			return;
		}

		$providers          = Two_Factor_Core::get_supported_providers_for_user( $user_id );
		$enabled_providers  = $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ];
		$existing_providers = Two_Factor_Core::get_enabled_providers_for_user( $user_id );

		// Enable only the available providers.
		$enabled_providers = array_intersect( $enabled_providers, array_keys( $providers ) );
		update_user_meta( $user_id, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, $enabled_providers );

		// Primary provider must be enabled.
		$new_provider = isset( $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] ) ? $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] : '';
		if ( ! empty( $new_provider ) && in_array( $new_provider, $enabled_providers, true ) ) {
			update_user_meta( $user_id, Two_Factor_Core::PROVIDER_USER_META_KEY, $new_provider );
		} else {
			delete_user_meta( $user_id, Two_Factor_Core::PROVIDER_USER_META_KEY );
		}

		// Have we changed the two-factor settings for the current user? Alter their session metadata.
		if ( $user_id === get_current_user_id() ) {

			if ( $enabled_providers && ! $existing_providers && ! Two_Factor_Core::is_current_user_session_two_factor() ) {
				// We've enabled two-factor from a non-two-factor session, set the key but not the provider, as no provider has been used yet.
				Two_Factor_Core::update_current_user_session( array(
					'two-factor-provider' => '',
					'two-factor-login'    => time(),
				) );
			} elseif ( $existing_providers && ! $enabled_providers ) {
				// We've disabled two-factor, remove session metadata.
				Two_Factor_Core::update_current_user_session( array(
					'two-factor-provider' => null,
					'two-factor-login'    => null,
				) );
			}
		}

		// Destroy other sessions if setup 2FA for the first time, or deactivated a provider
		if (
			// No providers, enabling one (or more)
			( ! $existing_providers && $enabled_providers ) ||
			// Has providers, and is disabling one (or more), but remaining with 2FA.
			( $existing_providers && $enabled_providers && array_diff( $existing_providers, $enabled_providers ) )
		) {
			if ( $user_id === get_current_user_id() ) {
				// Keep the current session, destroy others sessions for this user.
				wp_destroy_other_sessions();
			} else {
				// Destroy all sessions for the user.
				WP_Session_Tokens::get_instance( $user_id )->destroy_all();
			}
		}

		//..
		//end copy TwoFactorCore code

		// Set the notice!
		wc_add_notice( __( 'Two-Factor settings updated.', 'your-textdomain' ), 'success' );

		// Redirect to avoid re-submission
		wp_safe_redirect( add_query_arg( '2fa_updated', '1', wc_get_account_endpoint_url('two-factor') ) );
		exit;

	}


	// woocommerce endpoint 2fa settings screen

	public static function woo_two_factor_endpoint_settings()
	{

		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			echo '<p>' . esc_html__('You must be logged in to manage 2FA.', 'your-textdomain') . '</p>';
			return;
		}

		echo '<form id="two-factor-form" method="post">';

		wp_nonce_field( 'frontend_two_factor_update', 'frontend_two_factor_nonce' );
		do_action('show_user_profile', $user);

		echo '<button '.(!Two_Factor_Core::current_user_can_update_two_factor_options()?'disabled':'').' type="submit" class="button">' . esc_html__('Save Changes', 'your-textdomain') . '</button>';
		echo '</form>';
	}


}

?>
