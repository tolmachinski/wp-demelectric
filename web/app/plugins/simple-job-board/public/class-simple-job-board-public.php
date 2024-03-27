<?php
/**
 * Simple_Job_Board_Public Class
 *
 * The public-facing functionality of the plugin.
 *
 * @link       https://wordpress.org/plugins/simple-job-board
 * @since      1.0.0
 *
 * @package    Simple_Job_Board
 * @subpackage Simple_Job_Board/public
 * @author     PressTigers <support@presstigers.com>
 */
class Simple_Job_Board_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $simple_job_board    The ID of this plugin.
     */
    private $simple_job_board;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $simple_job_board       The name of the plugin.
     * @param    string    $version                The version of this plugin.
     */
    public function __construct($simple_job_board, $version)
    {

        $this->simple_job_board = $simple_job_board;
        $this->version = $version;

        /**
         * The class responsible for defining all the custom post types in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-job-board-post-types-init.php';

        /**
         * The class responsible for defining all the shortcodes in the front end area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-job-board-shortcodes-init.php';

        /**
         * The class responsible for Ajax Call on Job Submission in the front end area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-job-board-ajax.php';

        /**
         * The class responsible for Sending email notificatins to Applicant, Admin & HR.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-simple-job-board-notifications.php';

        /**
         * The class responsible for loading job board typography.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-simple-job-board-typography.php';

        // Action -> Load Template Functions.
        add_action('after_setup_theme', array($this, 'sjb_template_functions'), 11);
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     * @since    2.4.0  Updated Outdated Styles
     */
    public function enqueue_styles()
    {

        $sjb_fonts = get_option('sjb_fonts') ;

        if($sjb_fonts == 'enable-fonts'){

            // Enqueue Google Fonts
            wp_enqueue_style($this->simple_job_board . '-google-fonts', 'https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i', array(), $this->version, 'all');
        }

        // Enqueue Font Awesome Styles
        wp_enqueue_style("sjb-fontawesome", plugin_dir_url(dirname(__FILE__)) . 'includes/css/font-awesome.min.css', array(), '5.15.4', 'all');
        wp_enqueue_style($this->simple_job_board . '-jquery-ui', plugin_dir_url(__FILE__) . 'css/jquery-ui.css', array(), '1.12.1', 'all');

        // Enqueue Front-end RTL Styles
        if (is_rtl()) {
            wp_enqueue_style($this->simple_job_board . '-frontend-rtl', plugin_dir_url(__FILE__) . 'css/rtl/simple-job-board-public-rtl.css', array(), '2.0.0', 'all');
        } else {
            wp_enqueue_style($this->simple_job_board . '-frontend', plugin_dir_url(__FILE__) . 'css/simple-job-board-public.css', array(), '3.0.0', 'all');
        }
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     * @since    2.4.0  Updated InputTel Scripts
     * @since    2.9.5  Updated jQuery_alerts strings
     */
    public function enqueue_scripts()
    {
        $sjb_date_format = (!empty(apply_filters('sjb_date_format', get_option('sjb_date_format'))))? $this->convert_date(get_option('sjb_date_format')) : 'dd-mm-yy' ;
        // Register Simple Job Board Front-end Core JS
        wp_register_script($this->simple_job_board . '-front-end', plugin_dir_url(__FILE__) . 'js/simple-job-board-public.js', array('jquery', 'jquery-ui-datepicker'), '1.4.0', true);

        // Register Input Telephone JS
        wp_register_script($this->simple_job_board . '-validate-telephone-input', plugin_dir_url(__FILE__) . 'js/intlTelInput.min.js', array('jquery'), '17.0.0', true);
        wp_register_script($this->simple_job_board . '-validate-telephone-input-utiliy', plugin_dir_url(__FILE__) . 'js/intlTelInput-utils.js', array('jquery'), '7.7.3', true);
        wp_localize_script(
            $this->simple_job_board . '-front-end',
            'application_form',
            array(
                'ajaxurl' => esc_js(admin_url('admin-ajax.php')),
                'setting_extensions' => is_array(get_option('job_board_upload_file_ext')) ? array_map('esc_js', get_option('job_board_upload_file_ext')) : esc_js(get_option('job_board_upload_file_ext')),
                'all_extensions_check' => esc_js(get_option('job_board_all_extensions_check')),
                'allowed_extensions' => is_array(get_option('job_board_allowed_extensions')) ? array_map('esc_js', get_option('job_board_allowed_extensions')) : esc_js(get_option('job_board_allowed_extensions')),
                'job_listing_content' => esc_js(get_option('job_board_listing')),
                'jobpost_content' => esc_js(get_option('job_board_jobpost_content')),
                'jquery_alerts' => array(
                    'invalid_extension' => apply_filters('sjb_invalid_file_ext_alert', esc_html__('This is not an allowed file extension.', 'simple-job-board')),
                    'application_not_submitted' => apply_filters('sjb_job_not_submitted_alert', esc_html__('Your application could not be processed.', 'simple-job-board')),
                    'successful_job_submission' => apply_filters( 'sjb_job_submission_alert', __('Your application has been received. We will get back to you soon.', 'simple-job-board') ),
                    'sjb_quick_job_close' => apply_filters( 'sjb_quick_job_close', __('Are you sure you want to close? All the unsaved data will be lost.', 'simple-job-board') ),
                ),
                'file' => array(
                    'browse' => esc_html__('Browse', 'simple-job-board'),
                    'no_file_chosen' => esc_html__('No file chosen', 'simple-job-board'),
                ),
                'sjb_date_format' => $sjb_date_format,
            )
        );
    }

    /**
     * Load Templates
     *
     * @since    2.1.0
     */
    public function sjb_template_functions()
    {
        include 'partials/simple-job-board-template-functions.php';
    }

    /**
     * PHP date to JS format
     *
     * @since    2.10.0
     */
    public function convert_date($format)
    {

        $php_date_symbol = array('d', 'D', 'j', 'l', 'z', 'F', 'm', 'M', 'n', 'Y', 'y');
        $js_date_symbol = array('dd', 'D', 'd', 'DD', 'o', 'MM', 'mm', 'M', 'm', 'yy', 'y'); // and so on
        $datepicker_format = str_replace($php_date_symbol, $js_date_symbol, $format);
        
        return $datepicker_format;
    }
}
