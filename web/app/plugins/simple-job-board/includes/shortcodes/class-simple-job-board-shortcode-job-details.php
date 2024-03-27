<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Simple_Job_Board_Shortcode_job_details Details Page
 * 
 * This class lists the jobs on frontend for SJB detail widget
 * 
 * @link        https://wordpress.org/plugins/simple-job-board
 * @since       2.9.6
 * @since       2.10.0   Changed defined templates to do_actions.
 * @package     Simple_Job_Board
 * @author      PressTigers <support@presstigers.com>
 */


class Simple_Job_Board_Shortcode_job_details {

	public function __construct() {

        // Hook -> Add Job "Job details" widget
        add_shortcode('job_details', array($this, 'sjb_job_form_function'));
    }

	public function sjb_job_form_function() {
		
		do_action('sjb_enqueue_scripts');
		
		do_action('sjb_single_job_content_start');

		do_action('sjb_single_job_listing_start') ?>
		
		<div class="job-description" id="job-desc">
		
			<?php
			global $post;
			
			echo __( nl2br(get_post( $post->ID )->post_content) );
			?>
		</div>
		<div class="clearfix"></div>
		<?php

		do_action('sjb_single_job_listing_end');
		
		do_action('sjb_single_job_content_end');

	}

}