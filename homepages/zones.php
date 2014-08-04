<?php

function homepage_view_toggle() {
	ob_start();
?>
	<aside id="view-format">
		<h1><?php _e('View', 'largo'); ?></h1>
		<ul>
			<li><a href="#" class="active" data-style="top">Top Stories</a></li>
			<li><a href="#" data-style="list">Recent stories</a></li>
	</aside>
<?php
	$ret = ob_get_contents();
	ob_end_clean();

	return $ret;
}

function homepage_big_story_headline() {
	$bigStoryPost = largo_home_single_top();
	ob_start();
?>
	<article>
		<h5 class="top-tag"><?php largo_top_term(array('post'=> $bigStoryPost->ID)); ?></h5>
		<h2><a href="<?php the_permalink(); ?>"><?php echo the_title(); ?></a></h2>
		<h5 class="byline"><?php _e('By', 'largo'); ?> <?php largo_author_link(true, $bigStoryPost); ?></h5>
		<section>
			<?php largo_excerpt($bigStoryPost, 2, false); ?>
		</section>
	</article>
<?php
	wp_reset_postdata();
	$ret = ob_get_contents();
	ob_end_clean();
	return $ret;
}

function homepage_feature_stories_list() {
	global $shown_ids;

	$feature = largo_get_the_main_feature();
	$feature_posts = largo_get_recent_posts_for_term($feature, 3, 2);

	if (!empty($feature)) {
?>
	<h5 class="top-tag"><a class="post-category-link" href="<?php echo get_term_link($feature); ?>">
		<?php echo esc_html($feature->name) ?></a></h5>
			<?php foreach ($feature_posts as $feature_post) {
				$shown_ids[] = $feature_post->ID; ?>
				<h4 class="related-story"><a href="<?php echo esc_url(get_permalink($feature_post->ID)); ?>">
					<?php echo get_the_title($feature_post->ID); ?></a></h4>
			<?php } ?>
			<p class="more"><a href="<?php echo get_term_link($feature); ?>">
				<?php _e('Complete Coverage', 'largo'); ?></a></p>
<?php
	}
}

function homepage_series_stories_list() {
	return 'Series stories list...';
}