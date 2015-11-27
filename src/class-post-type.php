<?php
/**
 * GutenPress Post Type plugin handler
 *
 * Handles the registration and activation for a Custom Post Type plugin.
 * A CPT plugin created with GutenPress extends this class and
 * implements the abstract methods.
 * Plus, it should call activate_plugin on register_activation_hook
 * and register_post_type on init
 */
namespace GutenPress;

abstract class Post_Type{

	/**
	 * Holds a list of reserved post type keys
	 *
	 * @var array
	 */
	protected static $reserved_words = array(
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'action',
		'author',
		'order',
		'theme'
	);

	protected $instance;

	/**
	 * Get the post type key.
	 *
	 * This is what will be used on the database to identify custom post types.
	 * Must not exceed 20 characters, and not use capital letters or spaces.
	 * It is recommended to use a singular name
	 *
	 * @return string Post type key
	 */
	abstract public function get_post_type();

	abstract public function get_post_type_args();

	public function get_post_type_object(){
		return get_post_type_object( $this->get_post_type() );
	}

	/**
	 * Handle custom post type registration
	 *
	 * This method it's executed on the "init" hook and calls
	 * register_post_type() with the params defined on static::get_post_type_args()
	 *
	 * @return object|WP_Error True if the post type was correctly registered, WP_Error otherwhise
	 */
	public static function register_post_type(){
		$class = get_called_class();
		$post_type = new $class;

		// Check if the defined key it's on the list of reserved words
		$post_type_key = sanitize_key( $post_type->get_post_type() );
		if ( in_array( $post_type_key, static::$reserved_words ) ) {
			return new WP_Error( 'gutenpress_post_type_reserved', sprintf( __("You can't use %s as post type key because it's a reserved word", 'gutenpress'), $post_type_key ) );
		}

		// Try to register the post type. Further validations are handled by WordPress
		return register_post_type( $post_type_key, $post_type->get_post_type_args() );
	}

	/**
	 * Activation routine for a GutenPress custom post type plugin
	 *
	 * This method should be called on register_activation_hook.
	 * It will add all capabilities for the administrator role and
	 * flush rewrite rules so permalinks can work correctly
	 */
	public static function activate_plugin(){
		$admin = get_role('administrator');
		$class = get_called_class();

		// init post type, to include new slug on rewrite flush (if necessary)
		// this will also test if the post type key it's a reserved word and fail accordingly
		$can_register = $class::register_post_type();
		if ( is_wp_error( $can_register ) ) {
			wp_die( $can_register->get_message() );
		}

		// instantiate the post type object to get the registartion arguments
		$post_type      = new $class;
		$post_type_args = (object) $post_type->get_post_type_args();

		// the capabilities property must be set as an array (it can be empty)
		if ( ! isset( $post_type_args->capabilities ) ) {
			$post_type_args->capabilities = array();
		} elseif ( ! is_array( $post_type_args->capabilities ) ) {
			$post_type_args->capabilities = (array) $post_type_args->capabilities;
		}

		if ( empty( $post_type_args->capabilities ) && ( empty( $post_type_args->capability_type ) || ! isset( $post_type_args->map_meta_cap ) || ! (bool) $post_type_args->map_meta_cap ) ) {
			return new WP_Error( 'gutenpress_post_type_undefined_capabilities', sprintf( __('You must define %s capabilities or use map_meta_cap and define capability_type', 'gutenpress'), $class ) );
		}

		$capabilities = get_post_type_capabilities( $post_type_args );

		// add capabilities to the administrator role
		foreach ( $capabilities as $key => $val ){
			$admin->add_cap( $val );
		}

		/**
		 * Fires before flushing rewrite rules when activating a post type plugin.
		 *
		 * You could use this hook to add permissions for roles other than administrator, such as editors.
		 *
		 * @param object $post_type Post type class handler
		 */
		do_action( "gutenpress_post_type_{$post_type->get_post_type()}_activation", $post_type );

		// regenerate permalinks structure
		flush_rewrite_rules();
	}
}