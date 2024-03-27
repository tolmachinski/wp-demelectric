<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
/**
 * Simple_Job_Board_Widget_Dashboard class
 * 
 * @link        http://presstigers.com
 * @since       2.10.0
 * 
 * @package     Simple_Job_Board
 * @subpackage  Simple_Job_Board/admin
 * @author      PressTigers <support@presstigers.com>
 */

class Simple_Job_Board_Widget_Dashboard {

    /**
     * Register widget with WordPress
     * 
     * @since   2.10.0
     *
     * @param void
     * @param return void
     */
    public function __construct() {
       
        add_action( 'wp_dashboard_setup', array($this, 'add_dashboard_widgets') );
    }

   /**
     * @SJB Core: Add a new dashboard widget.
     * 
     * @since   2.10.0
     * return   void
     */
    function add_dashboard_widgets() {
        wp_add_dashboard_widget( 'sjb_dashboard_widget', 'Simple Job Board Stats', array($this,'dashboard_widget_function'), null, null, 'side', "high" );
    }

    /**
     * @SJB Core: Output the contents of the dashboard widget
     * 
     * @since   2.10.0
     * return   void
     */
    function dashboard_widget_function() {
        
        wp_enqueue_style( 'simple-job-board-dashboard-widget' );

        $site_url = site_url();
        $count_jobs = apply_filters('sjb_dashboard-count_jobs', wp_count_posts( 'jobpost' )->publish);
        $jobs_url = apply_filters('sjb_dashboard-jobs_url', $site_url . '/wp-admin/edit.php?post_type=jobpost');
        $count_applicants = apply_filters('sjb_dashboard-count_applicants', wp_count_posts( 'jobpost_applicants' )->publish);
        $applicants_url = apply_filters('sjb_dashboard-applicants_url', $site_url . '/wp-admin/edit.php?post_type=jobpost_applicants');

        wp_reset_postdata();
        $args = array(
            'post_status'     => 'publish',
            'post_type'       => 'jobpost_applicants',
            'posts_per_page'       => -1,
        );
        $number_posts = get_posts( $args );
        $most_applied = '';
        $most_applied_count = 0;
        $recent_posts = '';
        $applied_url = '';
        if($number_posts){
            $parents = array();
            foreach($number_posts as $post){
                if ( 'publish' == get_post_status ( wp_get_post_parent_id($post->ID) ) ) {
                    $parents[] = wp_get_post_parent_id($post->ID);
                }
            }
            $values = array_count_values($parents);
            
            arsort($values);
            $popular = array_slice($values, 0, 2, true);
            $recent_posts = array_slice($values, 0, 5, true);
            $applied_url = $site_url . '/wp-admin/post.php?post='.array_keys($popular)[0].'&action=edit';
            $most_applied = get_the_title(array_keys($popular)[0]);
            $most_applied_count = array_values($popular)[0];
        }

        wp_reset_postdata();
        $args = array(
            'post_status'       => 'publish',
            'post_type'         => 'jobpost_applicants',
            'posts_per_page'    => -1,
            'meta_key'          => 'sjb_jobapp_status',
            'meta_value'        => 'new'
        );
        $number_posts = get_posts( $args );
        $new_jobs = 0;
        if($number_posts){
            $new_jobs = count($number_posts);
        }

        /**
         * Action -> Before dashboard status list.  
         * 
         * @since 2.10.0 
         */
        do_action('sjb_dashboard_before_status_list');
        ?>

        <ul class="sjb-status-list">	
            <?php 
            
            /**
             * Action -> Before most applied.  
             * 
             * @since 2.10.0 
             */
            do_action('sjb_dashboard_before_most_applied');
            
            if($most_applied) {?>
                <li class="sjb-most-applied">
                    <a href=<?php echo esc_url($applied_url) ?>>
                        <strong>
                            <span class="sjb-most-applied-job">
                                <bdi>
                                    <?php echo esc_html($most_applied)." (".esc_html($most_applied_count).")" ?>
                                </bdi>
                            </span>
                        </strong>
                        <span><?php esc_html_e('Most Applied Job', 'simple-job-board') ?></span>
                    </a>
                </li>
            <?php }
            
             /**
             * Action -> After most applied.  
             * 
             * @since 2.10.0 
             */
            do_action('sjb_dashboard_after_most_applied');
            ?>
            <li class="sjb-posted-jobs">
				<a href=<?php echo esc_url($jobs_url )?>>
                    <strong>
                        <span class="sjb-posted-jobs-count">
                            <bdi><?php echo esc_html($count_jobs) ?></bdi>
                        </span>
                    </strong>
                    <span><?php esc_html_e('Jobs Posted', 'simple-job-board') ?></span>
                </a>
            </li>
            <li class="sjb-new-applications">
				<a href=<?php echo esc_url($applicants_url) ?>>
                    <strong>
                        <span class="sjb-newly-applications">
                            <bdi><?php echo esc_html($new_jobs) ?></bdi>
                        </span>
                    </strong>
                    <span><?php esc_html_e('New Applications', 'simple-job-board') ?></span>
                </a>
            </li>	
            <li class="sjb-all-applicants">
				<a href=<?php echo esc_url($applicants_url) ?>>
                    <strong>
                        <span class="sjb-all-applicants-count">
                            <bdi><?php echo esc_html($count_applicants) ?></bdi>
                        </span>
                    </strong>
                    <span><?php esc_html_e('All Applications', 'simple-job-board') ?></span>
                </a>
            </li>
            <?php
            /**
             * Action -> After all applicants.  
             * 
             * @since 2.10.0 
             */
            do_action('sjb_dashboard_after_all _applicants');
        ?>
        </ul>
        <?php
        /**
         * Action -> After dashboard status list.  
         * 
         * @since 2.10.0 
         */
        do_action('sjb_dashboard_after_status_list');
        ?>
        <?php if($recent_posts) {?>
            <hr>
            <?php
            /**
             * Action -> Before recent jobs.  
             * 
             * @since 2.10.0 
             */
            do_action('sjb_dashboard_before_recent_jobs');
            ?>
            <h3><?php esc_html_e("Recent 5 Jobs stats", 'simple-job-board') ?></h3>
            <table>
                <tr>
                    <th><?php esc_html_e("ID", 'simple-job-board') ?></th>
                    <th><?php esc_html_e("Job Title", 'simple-job-board') ?></th>
                    <th><?php esc_html_e("Applications", 'simple-job-board') ?></th>
                </tr>
                <?php 
                    foreach($recent_posts as $key => $value){
                        echo "<tr>"."<td><a href='".esc_url($site_url . '/wp-admin/post.php?post='.esc_html($key).'&action=edit')."'>".esc_html($key)."</a></td>"."<td><a href='".esc_url($site_url . '/wp-admin/post.php?post='.esc_html($key).'&action=edit')."'>".esc_html(get_the_title($key))."</a></td>"."<td><a href='".esc_url($applicants_url.'&job_id='.$key)."'>".esc_html($value)."</a></td>"."</tr>";
                    }
                ?>
            </table>
        <?php }
        /**
         * Action -> After recent jobs.  
         * 
         * @since 2.10.0 
         */
        do_action('sjb_dashboard_after_recent_jobs');
        ?>
        <a href=<?php echo $applicants_url ?> id="sjb-view-applications" class="button button-primary"><?php esc_html_e("View All Applications", 'simple-job-board') ?></a>
        <?php
        /**
         * Action -> After view jobs.  
         * 
         * @since 2.10.0 
         */
        do_action('sjb_dashboard_after_view_jobs');
    }
}

new Simple_Job_Board_Widget_Dashboard();
