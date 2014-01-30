<?php
/*
Plugin Name: bbP Live Preview (bbPress 1.x)
Description: Preview your BuddyPress forum posts (bbPress 1.x only) before posting.
Author: r-a-y, boonebgorges
Version: 0.1
License: GPLv2 or later
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'bbP_Live_Preview' ) ) :

class bbP_Live_Preview {
	/**
	 * Init method.
	 */
	public function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	function __construct() {
		// Bail if we're not running bbPress 1.x
		if ( ! bp_is_active( 'forums' ) ) {
			return;
		}

		// page injection
		add_action( 'groups_forum_new_reply_after', array( $this, 'preview' ) );
		add_action( 'bp_after_group_forum_post_new', array( $this, 'preview' ) );

		// ajax handlers
		add_action( 'wp_ajax_bbp_live_preview'       , array( $this, 'ajax_callback' ) );

		// autoembed hacks - uses BuddyPress
		add_action( 'bp_core_setup_oembed',            array( $this, 'autoembed_hacks' ) );

		// tinymce setup
		add_action( 'bbp_theme_before_reply_form',     array( $this, 'tinymce_setup' ) );
		add_action( 'bbp_theme_before_topic_form',     array( $this, 'tinymce_setup' ) );
	}

	/**
	 * Outputs the AJAX placeholder as well as the accompanying javascript.
	 *
	 * @todo Move JS and inline CSS to static files. Allow timeout variable to be configured.
	 */
	public function preview() {
	?>

		<label id="bbp-post-preview-label" for="bbp-post-preview" style="clear:both; display:none;"><?php _e( 'Preview:', 'bbp-live-preview' ); ?></label>
		<div id="bbp-post-preview" style="display: none; width:95%; border:1px solid #ababab; margin-top:.5em; padding:5px; color:#333;"></div>


		<script type="text/javascript">
			var bbp_preview_timer   = null;
			var bbp_preview_visible = false;
			var bbp_preview_ajaxurl = '<?php echo plugin_dir_url( __FILE__ ) . 'ajax.php'; ?>';

			function bbp_preview_post( text, type ) {
				clearTimeout(bbp_preview_timer);
				bbp_preview_timer = setTimeout(function(){
					var post = jQuery.post(
						bbp_preview_ajaxurl,
						{
							action: 'bbp_live_preview',
							'text': text,
							'type': type
						}
					);

					post.success( function (data) {
						if ( ! bbp_preview_visible ) {
							document.getElementById('bbp-post-preview-label').style.display = 'block';
							document.getElementById('bbp-post-preview').style.display = 'block';
							bbp_preview_visible = true;
						}

						jQuery("#bbp-post-preview").html(data);
					});
				}, 1500);

			}

			// tinymce capture
			function bbp_preview_tinymce_capture(e) {
				if ( e.type == 'keyup' ) {
					var id = e.view.frameElement.id.split('_');

					bbp_preview_post( e.target.innerHTML, id[1] );
				}
			}

			// regular textarea capture
			jQuery(document).ready( function($) {
				$("#reply_text, #topic_text").keyup(function(){
					var textarea = $(this);
					var id = $(this).attr('id').split('_');

					bbp_preview_post( textarea.val(), id[1] );

				});


			});
		</script>

	<?php
	}

	/**
	 * AJAX callback to output the preview text.
	 *
	 * Runs bbPress' filters before output.
	 *
	 * Autoembed preview is only supported when BuddyPress is installed.
	 */
	public function ajax_callback() {
		$type = $_POST['type'];

		if ( empty( $type ) )
			die();

		// if autoembeds are allowed and BP exists, allow autoembeds in preview
		global $wp_embed;

		// remove default bbP autoembed filters /////////////////////////////////
		//
		// newer version of bbP
		remove_filter( 'bbp_get_'. $type . '_content', array( $wp_embed, 'autoembed' ), 2 );

		// older version of bbP
		remove_filter( 'bbp_get_'. $type . '_content', array( $wp_embed, 'autoembed' ), 8 );

		// hack: provide a dummy post ID so embeds will run
		// this is important!
		add_filter( 'embed_post_id', create_function( '', 'return 1;' ) );

		// Remove wp_filter_kses filters from content for capable users
		if ( current_user_can( 'unfiltered_html' ) ) {
			remove_filter( 'bbp_new_' . $type . '_pre_content', 'bbp_filter_kses' );
		}

		// run bbP filters
		$content = apply_filters( 'bp_get_the_topic_post_content', stripslashes( $_POST['text'] ) );

		echo $content;
		die;
	}

	/**
	 * Add autoembed filters for bbPress to BuddyPress' Embeds handler.
	 *
	 * Piggyback off of BuddyPress' {@link BP_Embed} class as it is less
	 * restrictive than WordPress' {@link WP_Embed} class.
	 *
	 * Runs on AJAX only.
	 */
	public function autoembed_hacks( $embed ) {
		// if we're not running AJAX, we don't need to do this
		if ( ! defined( 'DOING_AJAX' ) )
			return;

		add_filter( 'bp_get_the_topic_post_content', array( $embed, 'autoembed' ), 2 );
		add_filter( 'bp_get_the_topic_post_content', array( $embed, 'run_shortcode' ), 1 );
	}

	/**
	 * Register our JS function with TinyMCE.
	 */
	public function tinymce_callback( $mce ) {
		$mce['handle_event_callback'] = 'bbp_preview_tinymce_capture';

		return $mce;
	}

	/**
	 * Setup TinyMCE.
	 */
	public function tinymce_setup() {
		add_filter( 'teeny_mce_before_init', array( $this, 'tinymce_callback' ) );
		add_filter( 'tiny_mce_before_init',  array( $this, 'tinymce_callback' ) );
	}

}

add_action( 'bp_include', array( 'bbP_Live_Preview', 'init' ) );

endif;
