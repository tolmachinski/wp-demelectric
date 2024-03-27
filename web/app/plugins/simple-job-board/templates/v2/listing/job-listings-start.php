<?php
/**
 * Job Listing Start
 *
 * @author 	PressTigers
 * @package     Simple_Job_Board
 * @subpackage  Simple_Job_Board/templates/listing
 * @version     2.0.0
 * @since       2.1.0
 * @since       2.4.0   Revised whole HTML template
 */

ob_start();

// Get Current Page Slug  
$page_slug = sjb_get_slugs();
$slug = ( get_option('permalink_structure') ) ? $page_slug : '';

if(strpos(esc_url(home_url('/')), '?lang=')){
    $action_page_url = $slug ;
}else{
    $action_page_url =  esc_url(home_url('/')) . $slug;
}
?>

<div class="sjb-listing">
    <?php
        if ( ( NULL != filter_input( INPUT_GET, 'selected_category' ) || NULL != filter_input( INPUT_GET, 'selected_jobtype' ) || NULL != filter_input( INPUT_GET, 'selected_location' ) || filter_input( INPUT_GET, 'search_keywords' ) ) ) {

            if ( ( '-1' != filter_input( INPUT_GET, 'selected_category' ) || '-1' != filter_input( INPUT_GET, 'selected_jobtype' ) || '-1' != filter_input( INPUT_GET, 'selected_location' ) || filter_input( INPUT_GET, 'search_keywords' ) != '') ) {
                echo '<p><a href="' . esc_url($action_page_url) . '" class="btn btn-primary">' . __( 'Clear Results', 'simple-job-board') . '</a></p>';
            }
        }   
        $view = get_option('job_board_listing_view');
        $class = ( 'list-view' === $view ) ? 'list-view' : 'grid-view';
    ?>
    <!-- start Jobs Listing: List View -->
    <div class="<?php echo esc_attr( $class ); ?>">
        
    <?php
    $html_listing_start = ob_get_clean();

    /**
     * Modify Job Listing Start Template
     *                                       
     * @since   2.4.0
     * 
     * @param   html    $html_listing_start   Job Listing Start HTML          .
     */
    echo apply_filters( 'sjb_listing_start_template', $html_listing_start );