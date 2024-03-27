<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly
/**
 * Simple_Job_Board_Shortcodes_Init Class
 * 
 * Custom Post Types Initialization. It includes all files of the custom post types for simple job board.
 *
 * @link        https://wordpress.org/plugins/simple-job-board
 * @since       2.9.6
 *
 * @package     Simple_Job_Board
 * @subpackage  Simple_Job_Board/includes 
 * @author      PressTigers <support@presstigers.com>
 */

class Simple_Job_Board_Shortcodes_Init {
    
    /**
     * Initialize the class and set its properties.
     *
     * @since   2.2.3
     */
    public function __construct() {
        
        //Jobpost shortcode 
        require_once plugin_dir_path ( __FILE__ ) . 'shortcodes/class-simple-job-board-shortcode-jobpost.php';
        
        // Check if Jobpost Class Exists
        if (class_exists ( 'Simple_Job_Board_Shortcode_Jobpost' )) {
            
            // Initialize Jobpost Class
            new Simple_Job_Board_Shortcode_Jobpost();
        }
        
        // Job detail shortcode
        require_once plugin_dir_path ( __FILE__ ) . 'shortcodes/class-simple-job-board-shortcode-job-details.php';
        
        // Check if Applicants Class Exists
        if (class_exists ( 'Simple_Job_Board_Shortcode_job_details' )) {
            
            // Initialize Applicant Class
            new Simple_Job_Board_Shortcode_job_details();
        }
    }
       
}
new Simple_Job_Board_Shortcodes_Init();            