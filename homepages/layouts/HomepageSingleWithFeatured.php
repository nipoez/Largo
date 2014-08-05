<?php

include_once __DIR__ . '/HomepageSingle.php';

class HomepageSingleWithFeatured extends HomepageSingle {
	var $name = 'One big story and list of featured stories';
	var $type = 'featured';
	var $description = 'A single story with full-width image treatment. Featured stories appear to the right of the big story\'s headline and excerpt.';
	var $sidebars = array(
		'Home Bottom Left', 'Home Bottom Center', 'Home Bottom Right'
	);

	public function moreStories() {
		return homepage_feature_stories_list();
	}
}