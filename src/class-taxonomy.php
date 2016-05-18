<?php

namespace GutenPress;

abstract class Taxonomy{

	use Singleton;

	/**
	 * Reserved words; shouldn't be used for your taxonomy
	 * @var array
	 * @link http://codex.wordpress.org/register_taxonomy#Reserved_Terms
	 */
	protected static $reserved_terms = array(
		'attachment',
		'attachment_id',
		'author',
		'author_name',
		'calendar',
		'cat',
		'category',
		'category__and',
		'category__in',
		'category__not_in',
		'category_name',
		'comments_per_page',
		'comments_popup',
		'customize_messenger_channel',
		'customized',
		'cpage',
		'day',
		'debug',
		'error',
		'exact',
		'feed',
		'fields',
		'hour',
		'link_category',
		'm',
		'minute',
		'monthnum',
		'more',
		'name',
		'nav_menu',
		'nonce',
		'nopaging',
		'offset',
		'order',
		'orderby',
		'p',
		'page',
		'page_id',
		'paged',
		'pagename',
		'pb',
		'perm',
		'post',
		'post__in',
		'post__not_in',
		'post_format',
		'post_mime_type',
		'post_status',
		'post_tag',
		'post_type',
		'posts',
		'posts_per_archive_page',
		'posts_per_page',
		'preview',
		'robots',
		's',
		'search',
		'second',
		'sentence',
		'showposts',
		'static',
		'subpost',
		'subpost_id',
		'tag',
		'tag__and',
		'tag__in',
		'tag__not_in',
		'tag_id',
		'tag_slug__and',
		'tag_slug__in',
		'taxonomy',
		'tb',
		'term',
		'theme',
		'type',
		'w',
		'withcomments',
		'withcomments',
		'year',
	);

	/**
	 * Get the taxonomy key.
	 *
	 * Should use just lowercase letters and the underscore character and be
	 * less than 32 characters long. It can't use one of WordPress' reserved
	 * terms.
	 *
	 * @return string
	 */
	abstract public function get_taxonomy();

	/**
	 * Get the types of objects that can use this taxonomy.
	 *
	 * Could be any of the built-in post types or custom post types. It might be
	 * return a null value to register the taxonomy and not associate it with
	 * an object.
	 *
	 * @return string|array|null
	 */
	abstract public function get_object_type();

	/**
	 * An array with taxonomy registration arguments
	 *
	 * @return array
	 * @link http://codex.wordpress.org/register_taxonomy#Arguments
	 */
	abstract public function get_taxonomy_args();

	public static function register_taxonomy(){
		$taxclass = get_called_class();
		$taxonomy = new $taxclass;
		$tax_name = $taxonomy->get_taxonomy();
		$tax_name = $taxonomy->sanitize_tax_name( $tax_name );
		if ( in_array( $tax_name, static::$reserved_terms ) ) {
			return new WP_Error( 'gutenpress_taxonomy_reserved', sprintf( __("You can't use %s as taxonomy key because it's a reserved word", 'gutenpress'), $tax_name ) );
		}
		return register_taxonomy( $tax_name, $taxonomy->get_object_type(), $taxonomy->get_taxonomy_args() );
	}

	/**
	 * Sanitize the taxonomy name. Should contain only lowercase or underscore characters
	 * @param  string $tax_name Raw tax name
	 * @return string           Sanitized taxonomy name
	 */
	private function sanitize_tax_name( $tax_name ){
		$key = strtolower( $tax_name );
		$key = preg_replace( '/[^a-z0-9_]/', '', $key );
		return $key;
	}

	public static function activate_plugin(){
		$tax_hndl = \get_called_class();
		$taxonomy = new $tax_hndl;
		$tax_hndl::register_taxonomy();

		do_action( "gutenpress_taxonomy_{$taxonomy->get_taxonomy()}_activation", $taxonomy );

		// regenerate permalinks structure
		flush_rewrite_rules();
	}
}