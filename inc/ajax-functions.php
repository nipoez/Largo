<?php
/*
 * When changing the operation of these functions, be sure to test it in:
 * - the homepage, all templates
 * - category archive pages
 * - series archive pages
 * - custom series landing pages
 * - search results pages
 */

/*
 * Enqueue script for "load more posts" functionality
 */
if (!function_exists('largo_load_more_posts_enqueue_script')) {
	function largo_load_more_posts_enqueue_script() {
		wp_enqueue_script(
			'load-more-posts',
			get_template_directory_uri() . '/js/load-more-posts.js',
			array('jquery'), null, true
		);
	}
	add_action('wp_enqueue_scripts', 'largo_load_more_posts_enqueue_script');
}

/*
 * Fills JavaScript variable LMP with posts rendered on page
 *
 * Canonically, this should be hooked on wp_enqueue_scripts, but it needs access to $shown_ids
 */
if (!function_exists('largo_load_more_posts_data')) {
	function largo_load_more_posts_data() {
		global $wp_query, $shown_ids;

		$query = $wp_query->query;

		// No sticky posts or featured posts
		$query = array_merge(array(
			'post__not_in' => $shown_ids,
		), $query );

		$LMP = apply_filters('largo_load_more_posts_json', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'paged' => (!empty($wp_query->query_vars['paged']))? $wp_query->query_vars['paged'] : 0,
			'query' => $query,
			'is_home' => $wp_query->is_home()
		));

		?>
		<script type="text/javascript">
			var LMP = <?php echo json_encode($LMP); ?>;
		</script>
	<?php
	}
	add_action('wp_footer', 'largo_load_more_posts_data');
}

/*
 * Renders markup for a page of posts and sends it back over the wire.
 */
if (!function_exists('largo_load_more_posts')) {
	function largo_load_more_posts() {
		$paged = $_POST['paged'];
		$context = (isset($_POST['query']))? $_POST['query'] : array();

		// Making sure that this isn't home
		if (isset($_POST['is_home']))
			$is_home = ($_POST['is_home'] == 'false')? false : true;
		else if ( isset($_POST['query']['cat']) ||
			isset($_POST['query']['author']) ||
			isset($_POST['query']['term']) || # tags, taxonomies and custom taxonomies
			isset($_POST['query']['s'])) # searches
			$is_home = false;
		else
			$is_home = true;

		$args = array_merge(array(
			'paged'               => $paged,
			'post_status'         => 'publish',
			'posts_per_page'      => intval(get_option('posts_per_page')),
			'ignore_sticky_posts' => true,
		), $context);

		// num_posts_home is only relevant on the homepage
		if ( of_get_option('num_posts_home') && $is_home )
			$args['posts_per_page'] = of_get_option('num_posts_home');
		// The first 'page' of the homepage is in $shown_ids, so this number should actually be minus one.
		if ( $is_home )
			$args['paged'] = ( $args['paged'] - 1 );
		if ( of_get_option('cats_home') )
			$args['cat'] = of_get_option('cats_home');
		$query = new WP_Query($args);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) : $query->the_post();
				$partial = ( get_post_type() == 'argolinks' ) ? 'argolinks' : 'home';
				get_template_part( 'partials/content', $partial );
			endwhile;
		}
		wp_die();
	}
	add_action('wp_ajax_nopriv_load_more_posts', 'largo_load_more_posts');
	add_action('wp_ajax_load_more_posts', 'largo_load_more_posts');
}
