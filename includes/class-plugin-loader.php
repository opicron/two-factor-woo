/**
 * Plugin_Loader class file.
 *
 * @package two-factor-woo
 */

namespace Two_Factor_Woo\Inc;


class Plugin_Loader {

	public static function load()
	{
		// Register plugin assets
		//add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );

		// add init action
		add_action( 'init', array( self::class, 'init' ), 11 );
	}

	public static function init()
	{
		// add two factor endpoint
		add_action( 'init', [self::class,'woo_add_two_factor_endpoint'] );

		// add query var endpoint
		add_filter( 'woocommerce_get_query_vars', [self::class, 'woo_add_query_var_endpoint'] );

		// woo add two factor to account menu
		add_filter( 'woocommerce_account_menu_items', [self::class,'woo_account_menu_add_two_factor'], 20 );

		// woo endpoint title
		add_filter( 'woocommerce_endpoint_two-factor_title', [self::class,'woo_endpoint_title'], 1, 2 );

		// filter providers
		add_filter( 'two_factor_providers', [self::class,'two_factor_remove_providers'] );

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

}
