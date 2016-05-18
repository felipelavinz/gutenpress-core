<?php
/**
 * Post Query abstract class
 *
 * Acts like a wrapper for the WordPress loops that implements
 * the Iterator interface so a "foreach" can be used for looping
 * through the elements.
 * The class constructor takes an array of arguments that are
 * passed directly to WP_Query, so it's fully compatible with
 * any WordPress query.
 * Each of the iterable elements is an instance of a custom
 * post type specific class that's extending Post_Object, and
 * acts as a decorator for WP_Post so it can use its own custom
 * methods
 *
 * @package GutenPress
 */

namespace GutenPress;

abstract class Post_Query implements \Iterator, \Countable{

	/**
	 * Holds the instance of WP_Query which we'll iterate over
	 * @var \WP_Query
	 */
	private $query;

	/**
	 * Holds an instance of the current post decorator instance
	 * @var \WP_Post
	 */
	private $the_post;

	/**
	 * Keep a copy of the global post
	 * @var \WP_Post
	 */
	private $_wp_post;

	/**
	 * The post type slug for this type of query
	 * @var string
	 */
	private $post_type;

	/**
	 * An array of arguments passed to WP_Query
	 * @var array
	 */
	private $query_args;

	/**
	 * The FQN of a decorator for WP_Post
	 * @var string
	 */
	protected $decorator;

	/**
	 * @param array $query_args An array of arguments passed on to WP_Query
	 */
	public function __construct( array $query_args = array() ){
		$this->query_args = $query_args;
		$this->post_type = $this->get_post_type();
		$this->decorator = $this->get_decorator();
		if ( ! $this->decorator ) {
			$this->decorator = 'GutenPress\Post_Object';
		}
		// @todo
		if ( ! is_subclass_of($this->decorator, 'GutenPress\Post_Object') ) {
			throw new \Exception( sprintf( __('%s must be a subclass of GutenPress\Post_Object', 'gutenpress'), $this->decorator ) );
		}
	}

	/**
	 * Get the post type for this type of query
	 * @return string A post type slug
	 */
	abstract protected function get_post_type();

	/**
	 * Get the name for the WP_Post decorator
	 * @return string The FQN of a WP_Post decorator. It could be empty
	 */
	abstract protected function get_decorator();

	/**
	 * Parse query params and execute WP_Query
	 */
	private function do_query(){
		$query_args = wp_parse_args( $this->query_args, array(
			'post_type' => $this->post_type
		) );
		$this->query = new \WP_Query( $query_args );
	}

	/**
	 * Re-use an existing WP_Query (for instance, on archive templates where it doesn't make sense to repeat the query)
	 * @param \WP_Query object
	 * @throws \Exception Fails if $this->query was already set
	 */
	public function set_query( \WP_Query $query ){
		if ( $this->query instanceof \WP_Query ) {
			throw new \Exception( sprintf( __('The query for this object was already set. You might want to create a new %s', 'gutenpress'), get_called_class() ) );
		}
		$this->query = $query;
		$this->post_type = $this->query->get('post_type');
		$this->pre_loop();
	}

	/**
	 * Return the current WP_Query
	 * @return \WP_Query The current WP_Query
	 */
	public function get_query(){
		if ( ! $this->query ) {
			$this->do_query();
		}
		return $this->query;
	}

	public function get_found_posts_ids(){
		if ( ! isset( $this->query ) ) {
			$this->do_query();
			$this->pre_loop();
		}
		return wp_list_pluck( $this->query->posts, 'ID' );
	}

	/**
	 * Implement countable interface, so we can do count($object)
	 * @return int The number of posts on the current query
	 */
	public function count(){
		if ( ! isset( $this->query ) ) {
			$this->do_query();
			$this->pre_loop();
		}
		return $this->query->post_count;
	}


	/**
	 * Iterator methods
	 */

	public function current(){
		if ( isset($this->the_post) ) {
			return $this->the_post;
		}
		if ( ! isset($this->query) ) {
			$this->rewind();
		}
		global $post;
		$this->query->the_post();
		$this->the_post = new $this->decorator( $post );
		return $this->the_post;
	}
	public function key(){
		return $this->query->current_post;
	}
	public function next(){
		$this->the_post = null;
	}
	public function rewind(){
		// check if query was already made
		if ( ! isset( $this->query ) ) {
			// if no custom query was made, we might want to use WordPress' query
			global $wp_query;
			if ( empty( $this->query_args ) && ( $wp_query->is_archive() || $wp_query->is_singular() ) ) {
				$this->set_query( $wp_query );
			} else {
				$this->do_query();
			}
			$this->pre_loop();
		} else {
			$this->query->rewind_posts();
		}
	}
	public function valid(){
		if ( $this->query->have_posts() ) {
			return true;
		} else {
			// loop it's ending, so let's cleanup
			wp_reset_query();
			// manually reset the post data
			global $post;
			$post = $this->_wp_post;
			return false;
		}
	}

	private function pre_loop(){
		global $post;
		$this->_wp_post = $post;
	}
}