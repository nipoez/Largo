<?php
/**
 * Plugin Name: Navis Slideshows
 * Description: Slideshows that take advantage of the Slides jQuery plugin.
 * Version: 0.3
 * Author: Project Argo, Crowd Favorite, Cornershop Creative
 * License: GPLv2
*/
/*
	Copyright 2011 National Public Radio, Inc.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Navis_Slideshows {

	function __construct() {
		add_action( 'init', array( &$this, 'add_slideshow_header' ) );
		add_action('wp_head', array( &$this, 'argo_slideshow_css' ) );

		add_filter(
			'post_gallery', array( &$this, 'handle_slideshow' ), 10, 2
		);

		if ( ! is_admin() )
			return;

		add_action(
			'save_post', array( &$this, 'tag_post_as_slideshow' ), 10, 2
		);
		remove_shortcode('gallery');
		add_shortcode('gallery', array( &$this, 'handle_slideshow' ),10,2);
	}


	function add_slideshow_header() {
		// slides-specific CSS
		$slides_css = get_template_directory_uri() . '/lib/navis-slideshows/css/slides.css';
		wp_enqueue_style(
			'navis-slides', $slides_css, array(), '1.0'
		);
	}

	//add slideshow width to header
	function argo_slideshow_css() {
	?>
		<style type="text/css">.navis-slideshow .slides_container div {width: 100%;}</style>
	<?php
	}


	/**
	 *
	 * @uses global $post WP Post object
	 */
	function handle_slideshow( $output, $attr ) {
		/**
		 * Grab attachments
		 */
		global $post;

		// jQuery slides plugin, available at http://slidesjs.com/
		$slides_src = get_template_directory_uri() . '/lib/navis-slideshows/js/jquery.slides.min.js';
		wp_enqueue_script(
			'jquery-slides', $slides_src, array( 'jquery' ), '3.0', true
		);

		$imagesloaded_src = get_template_directory_uri() . '/lib/navis-slideshows/js/imagesloaded.pkgd.min.js';
		wp_enqueue_script(
			'imagesloaded', $imagesloaded_src, array('jquery'), '3.1.8', true
		);

		// our custom js
		$show_src = get_template_directory_uri() . '/lib/navis-slideshows/js/navis-slideshows.js';
		wp_enqueue_script(
			'navis-slideshows', $show_src, array( 'jquery-slides' ),
			'0.1', true
		);

		$attr = shortcode_atts( array(
			'order'	  => 'ASC',
			'orderby'	=> 'menu_order ID',
			'id'		 => $post->ID,
			'itemtag'	=> 'dl',
			'icontag'	=> 'dt',
			'captiontag' => 'dd',
			'columns'	=> 3,
			'size'	   => 'thumbnail',
			'link' => 'file',
			'ids'		 => '',
		), $attr );

		$id = intval( $attr['id'] );
		$order = ( $attr['order'] == 'ASC' ) ? 'ASC' : 'DESC';
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( ! $attr['orderby'] )
				unset( $attr['orderby'] );
		}
		$itemtag = sanitize_key( $attr['itemtag'] );
		$icontag = sanitize_key( $attr['icontag'] );
		$captiontag = sanitize_key( $attr['captiontag'] );
		$columns = intval( $attr['columns'] );
		$size = sanitize_key( $attr['size'] );
		$link = sanitize_key( $attr['link'] );
		$ids = array_map( 'intval', explode(',', $attr['ids'] ) );


		// XXX: this could be factored out to a common function for getting
		// a post's images
		//if no IDs set, get attached posts
		if ( empty( $ids ) ) {
			$attachments = get_children( array(
				'post_parent'	=> $id,
				'post_status'	=> 'inherit',
				'post_type'	  => 'attachment',
				'post_mime_type' => 'image',
				'order'		  => $order,
				'orderby'		=> $orderby,
				'numberposts' => 200
			) );
		} else {

			$raw_attachments = get_posts( array(
				'post__in'		 => $ids,
				'post_type'	  => 'attachment',
				'post_mime_type' => 'image',
				'numberposts' => 200
			) );

			// If attachments are returned, put them into the array in the order specified
			if (is_array($raw_attachments)) {
				foreach ($ids as $id) {
					foreach ($raw_attachments as $attachment) {
						if ($id == $attachment->ID) {
							$attachments[(string) $id] = $attachment;
						}
					}
				}
			}
		}

		if ( empty( $attachments ) )
			return '';

		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $id => $attachment )
				$output .= wp_get_attachment_link( $id, $size, true ) . "\n";
			return $output;
		}

		$postid = ($post->ID) ? $post->ID : rand(0,10000);
		$plink = get_permalink();
		$post_html_id = $postid . "-" . wp_create_nonce( 'slideshow' . time() . rand(0,10000) );	//appending a random value so that 2 slideshows can peacefully coexist

		$output .= '
			<div id="slides-' . esc_attr( $post_html_id ) . '" class="navis-slideshow">';

		/*-- Add images --*/
		$count = 0;
		$total = count( $attachments );
		foreach ( $attachments as $id => $attachment ) {
			$count++;
			$image = wp_get_attachment_image_src( $id, "large" );

			// Credit functionality provided by navis-media-credit plugin
			$credit = '';
			if ( function_exists( 'navis_get_media_credit' ) ) {
				$creditor = navis_get_media_credit( $id );
				$credit = $creditor->to_string();
			}

			$themeta = $attachment->post_title;
			$caption = $attachment->post_excerpt;
			$permalink = $attachment->ID;
			$slidediv = $post_html_id . '-slide' . $count;
			$img_url = wp_get_attachment_url( $id );

			// This embeds the first two slides directly in the page
			// and leaves placeholders for the remaining ones to be
			// loaded just-in-time with JavaScript.
			if ( $count < 3 || $count == $total ) {
				if ( 'file' == $link) {
					$output .= sprintf(
						'<div id="%s"><a href="%s" target="_blank"><img src="%s" width="%d" height="%d" /></a>',
						esc_attr( $slidediv ), esc_url( $img_url ), esc_url( $image[0] ), (int) $image[1], (int) $image[2]
					);
				} else {
					$output .= sprintf(
						'<div id="%s"><img src="%s" width="%d" height="%d" />',
						esc_attr( $slidediv ), esc_url( $image[0] ), (int) $image[1], (int) $image[2]
					);
				}
			} else {
				if ( 'file' == $link ) {
					$output .= sprintf(
						'<div id="%s" data-src="%s*%d*%d" data-href="%s" />',
						esc_attr( $slidediv ), esc_url( $image[0] ), (int) $image[1], (int) $image[2], esc_url( $img_url )
					);
				} else {
					$output .= sprintf(
						'<div id="%s" data-src="%s*%d*%d" />',
						esc_attr( $slidediv ), esc_url( $image[0] ), (int) $image[1], (int) $image[2]
					);
				}
			}

			if ( ! empty( $credit ) ) {
				$output .= '<h6 class="credit">';
				$output .= wp_kses_post( $credit );
				$output .= '</h6>';
			}

			$output .= '<h6 class="permalink"><a href="#" class="slide-permalink"><i class="icon-link"></i> ' . esc_attr( __( 'permalink', 'largo' ) ) . '</a></h6>';
			if ( ! empty( $caption ) ) {
				$output .= '<p class="wp-caption-text">' . wp_kses_post( $caption ) . '</p>';
			}

			$output .= '</div>';
		}
		$output .= '</div>';

		$this->postid = $post_html_id;
		$this->permalink = $plink;
		$this->slide_count = $count;

		$output .= sprintf(
			"<script>jQuery( document ).ready( function() { " .
				"navisloadSlideshow( '%s', '%s', %d ) } );</script>",
			$this->postid, $this->permalink, $this->slide_count
		);

		return $output;
	}


	/**
	 * Applies the Slideshow custom taxonomy term to a post when it contains
	 * a gallery, and removes it when it doesn't.
	 *
	 * @param $post_ID ID of post
	 * @param $post Post object
	 * @todo add checks for post type, taxonomy existence, etc.
	 */
	function tag_post_as_slideshow( $post_ID, $post, $taxonomy = 'feature' ) {

		if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			return;
		}

		$ss_term = get_term_by( 'slug', 'slideshow', $taxonomy );
		$post_terms = wp_get_object_terms( $post_ID, $taxonomy );

		$new_post_terms = array();
		// if we have a [gallery] shortcode in our post
		if ( stripos( $post->post_content, '[gallery' ) !== false ) {
			$seen_ss = false;
			foreach ( $post_terms as $post_term ) {
				if ( $post_term->term_id == $ss_term->term_id ) {
					$seen_ss = true;
				}
				$new_post_terms[] = $post_term->slug;
			}

			if ( ! $seen_ss )
				$new_post_terms[] = $ss_term->slug;

		} else {
			$new_post_terms = array();
			foreach ( $post_terms as $post_term ) {
				if ( $post_term->term_id == $ss_term->term_id )
					continue;

				$new_post_terms[] = $post_term->slug;
			}
		}

		wp_set_object_terms( $post_ID, $new_post_terms, $taxonomy );
	}

}

new Navis_Slideshows;
