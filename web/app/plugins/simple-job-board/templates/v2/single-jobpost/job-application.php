<?php
/**
 * Display the job application form.
 *
 * Override this template by copying it to yourtheme/simple_job_board/v2/single-jobpost/job-application.php
 *
 * @author  PressTigers
 * @package     Simple_Job_Board
 * @subpackage  Simple_Job_Board/Templates
 * @version     1.0.0
 * @since       2.1.0
 * @since       2.2.2   Added more @hooks in application form.
 * @since       2.3.0   Added "sjb_job_application_template" filter & "sjb_job_application_form_fields" filter.
 * @since       2.7.0   Revised the application HTML & added loader to application
 */
ob_start();
global $post;

/**
 * Fires on job detail page before displaying job application section.
 *                  
 * @since   2.1.0                   
 */
do_action('sjb_job_application_before');
?>

<!-- Start Job Application Form
================================================== -->
<form class="jobpost-form" id="sjb-application-form" name="c-assignments-form"  enctype="multipart/form-data">
    <h3><?php echo apply_filters('sjb_job_application_form_title', esc_html__('Apply Online', 'simple-job-board')); ?></h3>    
    <div class="row">
        <?php
        /**
         * Fires on job detail page at start of job application form. 
         *                 
         * @since   2.3.0                   
         */
        do_action('sjb_job_application_form_fields_start');

        $allowed_tags = sjb_get_allowed_html_tags();
        $keys = get_post_custom_keys(get_the_ID());
        $section_no = 1;
        $total_sections = 0;

        // Get total sections
        if (NULL != $keys):
            foreach ($keys as $key):
                if (substr($key, 0, 7) == 'jobapp_'):
                    $val = get_post_meta(get_the_ID(), $key, TRUE);
                    $val = maybe_unserialize($val);
                    if ('section_heading' == $val['type']) {
                        $total_sections++;
                    }
                endif;
            endforeach;
        endif;
        if (0 < $total_sections) {
            echo '<div class="col-md-12">';
        }

        if (NULL != $keys):
            foreach ($keys as $key):
                if (substr($key, 0, 7) == 'jobapp_'):
                    $val = get_post_meta(get_the_ID(), $key, TRUE);
                    $val = maybe_unserialize($val);
                    $is_required = isset($val['optional']) ? "checked" === $val['optional'] ? 'required="required"' : "" : 'required="required"';
                    $required_class = isset($val['optional']) ? "checked" === $val['optional'] ? "sjb-required" : "sjb-not-required" : "sjb-required";
                    $required_field_asterisk = isset($val['optional']) ? "checked" === $val['optional'] ? '<span class="required">*</span>' : "" : '<span id="sjb-required">*</span>';
                    $id = preg_replace('/[^\p{L}\p{N}\_]/u', '_', $key);
                    $name = preg_replace('/[^\p{L}\p{N}\_]/u', '_', $key);
                    $label = isset($val['label']) ? $val['label'] : ucwords(str_replace('_', ' ', substr($key, 7)));

                    // Field Type Meta
                    $field_type_meta = array(
                        'id' => $id,
                        'name' => $name,
                        'label' => $label,
                        'type' => $val['type'],
                        'is_required' => $is_required,
                        'required_class' => $required_class,
                        'required_field_asterisk' => $required_field_asterisk,
                        'options' => $val['options'],
                    );

                    /**
                     * Fires on job detail page at start of job application form. 
                     *                 
                     * @since   2.3.0                   
                     */
                    do_action('sjb_job_application_form_fields', $field_type_meta);

                    switch ($val['type']) {
                        case 'section_heading':
                            if (1 < $section_no) {
                                echo '</div>';
                            }
                            echo '<div class="form-box">'
                            . '<h3>' . esc_attr($label) . '</h3>';
                            $section_no++;
                            break;
                        case 'text':
                            echo '<div class="col-md-3 col-xs-12">'
                            . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                            . '</div>'
                            . '<div class="col-md-9 col-xs-12">'
                            . '<div class="form-group">'
                            . '<input type="text" name="' . esc_attr($name) . '" class="form-control ' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . '>'
                            . '</div>'
                            . '</div>'
                            . '<div class="clearfix"></div>';
                            break;
                        case 'text_area':
                            echo '<div class="col-md-3 col-xs-12">'
                            . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                            . '</div>'
                            . '<div class="col-md-9 col-xs-12">'
                            . '<div class="form-group">'
                            . '<textarea name="' . esc_attr($name) . '" class="form-control ' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . '  cols="30" rows="5"></textarea>'
                            . '</div>'
                            . '</div>'
                            . '<div class="clearfix"></div>';
                            break;
                        case 'email':
                            echo '<div class="col-md-3 col-xs-12">'
                            . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                            . '</div>'
                            . '<div class="col-md-9 col-xs-12">'
                            . '<div class="form-group">'
                            . '<input type="email" name="' . esc_attr($name) . '" class="form-control sjb-email-address ' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . '><span class="sjb-invalid-email validity-note">' . esc_html__('A valid email address is required.', 'simple-job-board') . '</span>'
                            . '</div>'
                            . '</div>'
                            . '<div class="clearfix"></div>';
                            break;
                        case 'phone':
                            echo '<div class="col-md-3 col-xs-12">'
                            . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                            . '</div>'
                            . '<div class="col-md-9 col-xs-12">'
                            . '<div class="form-group">'
                            . '<input type="tel" name="' . esc_attr($name) . '" class="form-control sjb-phone-number sjb-numbers-only ' . $required_class . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . '><span class="sjb-invalid-phone validity-note" id="' . esc_attr($id) . '-invalid-phone">' . esc_html__('A valid phone number is required.', 'simple-job-board') . ' </span>'
                            . '</div>'
                            . '</div>'
                            . '<div class="clearfix"></div>';
                            break;
                        case 'date':
                            echo '<div class="col-md-3 col-xs-12">'
                            . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                            . '</div>'
                            . '<div class="col-md-9 col-xs-12">'
                            . '<div class="form-group">'
                            . '<input type="text" name="' . esc_attr($name) . '" class="form-control sjb-datepicker ' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . ' maxlength="10">'
                            . '</div>'
                            . '</div>'
                            . '<div class="clearfix"></div>';
                            break;
                        case 'radio':
                            if ($val['options'] != '') {
                                echo '<div class="col-md-3 col-xs-12">'
                                . '<label class="sjb-label-control" for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                                . '</div>'
                                . '<div class="col-md-9 col-xs-12">'
                                . '<div class="form-group">';
                                $options = explode(',', $val['options']);
                                $i = 0;
                                foreach ($options as $option) {
                                    echo '<label class="small"><input type="radio" name="' . esc_attr($name) . '" class=" ' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" value="' . esc_attr($option) . '"  ' . sjb_is_checked($i) . ' ' . esc_attr($is_required) . '>' . esc_attr($option) . ' </label> ';
                                    $i++;
                                }
                                echo '</div></div>'
                                . '<div class="clearfix"></div>';
                            }
                            break;
                        case 'dropdown':
                            if ($val['options'] != '') {
                                echo '<div class="col-md-3 col-xs-12">'
                                . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                                . '</div>'
                                . ' <div class="col-md-9 col-xs-12">'
                                . '<div class="form-group">'
                                . '<select class="form-control" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" ' . esc_attr($is_required) . '>';
                                $options = explode(',', $val['options']);
                                foreach ($options as $option) {
                                    echo '<option class="' . esc_attr($required_class) . '" value="' . esc_attr($option) . '" >' . esc_attr($option) . ' </option>';
                                }
                                echo '</select>'
                                . '</div>'
                                . '</div>'
                                . '<div class="clearfix"></div>';
                            }
                            break;
                        case 'checkbox' :
                            if ($val['options'] != '') {
                                echo '<div class="col-md-3 col-xs-12">'
                                . '<label for="' . esc_attr($key) . '">' . esc_attr($label) . wp_kses($required_field_asterisk, $allowed_tags) . '</label>'
                                . '</div>'
                                . '<div class="col-md-9 col-xs-12">'
                                . '<div class="form-group">';
                                $options = explode(',', $val['options']);
                                $i = 0;

                                foreach ($options as $option) {
                                    echo '<label class="small"><input type="checkbox" name="' . esc_attr($name) . '[]" class="' . esc_attr($required_class) . '" id="' . esc_attr($id) . '" value="' . esc_attr($option) . '"  ' . esc_attr($i) . ' ' . esc_attr($is_required) . '>' . esc_attr($option) . ' </label>';
                                    $i++;
                                }
                                echo '</div></div>'
                                . '<div class="clearfix"></div>';
                            }
                            break;
                    }
                endif;
            endforeach;
            if ($total_sections > 0 && $total_sections + 1 == $section_no) {
                echo '</div>';
                echo '<div class="clearfix"></div>';
            }
        endif;

        /**
         * Modify the output of file upload button. 
         * 
         * @since   2.2.0 
         * 
         * @param   string  $sjb_attach_resume  Attach resume button.
         */
        if (0 < $total_sections) {
            echo '<div class="row">';
        }

        $sjb_attach_resume = '<div class="col-md-3 col-xs-12">'
                . '<label for="applicant_resume">' . apply_filters('sjb_resume_label', __('Attach Resume', 'simple-job-board')) . '<span class="sjb-required required">*</span></label>'
                . '</div>'
                . '<div class="col-md-9 col-xs-12">
                                    <div class="form-group">'
                . '<input type="file" name="applicant_resume" id="applicant-resume" class="sjb-attachment form-control "' . apply_filters('sjb_resume_required', 'required="required"') . '>'
                . '<span class="sjb-invalid-attachment validity-note" id="file-error-message"></span>'
                . '</div>'
                . '</div>'
                . '<div class="clearfix"></div>';
        echo apply_filters('sjb_attach_resume', $sjb_attach_resume);

        if (0 < $total_sections) {
            echo '</div>';
        }

        /**
         * GDPR Part
         * 
         * @since 2.6.0
         */
        //Enable GDPR Settings
        $sjb_gdpr_settings = get_option('job_board_privacy_settings');

        $privacy_policy_label = get_option('job_board_privacy_policy_label', '');
        $privacy_policy_content = get_option('job_board_privacy_policy_content', '');
        $term_conditions_label = get_option('job_board_term_conditions_label', '');
        $term_conditions_content = get_option('job_board_term_conditions_content', '');

        if ('yes' == $sjb_gdpr_settings) {
            ?>
            <?php
            if ($privacy_policy_content) {
                if (0 < $total_sections) {
                    ?>
                    <div class="row"> 
                    <?php } ?>
                    <div class="form-group ">
                        <?php if ($privacy_policy_label) { ?>
                            <div class="col-md-3 col-xs-12">
                                <label for="jobapp_pp" class="sjb-privacy-policy-label"><?php printf(__("%s", 'simple-job-board'), esc_attr($privacy_policy_label)); ?></label>
                            </div>
                            <div class="col-md-9 col-xs-12">
                                <div id="jobapp-pp">
                                    <p class="sjb-privacy-policy"><?php printf(__("%s", 'simple-job-board'), wp_kses_post(stripslashes_deep(trim($privacy_policy_content)))); ?></p>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="col-md-12 col-xs-12">
                                <div id="jobapp-pp">
                                    <p class="sjb-privacy-policy"><?php printf(__("%s", 'simple-job-board'), wp_kses_post(stripslashes_deep(trim($privacy_policy_content)))); ?></p>
                                </div>
                            </div>
                        <?php } ?>
                    </div><div class="clearfix"></div>
                    <?php if (0 < $total_sections) { ?>
                    </div>
                    <?php
                }
            }
            if ($term_conditions_content) {
                if (0 < $total_sections) {
                    ?>
                    <div class="row"> 
                    <?php } ?>
                    <div class="form-group ">

                        <?php if ($term_conditions_label) { ?>
                            <div class="col-md-3 col-xs-12">
                                <label for="jobapp_tc"><?php printf(__("%s", 'simple-job-board'), esc_attr($term_conditions_label)); ?></label>
                            </div>
                            <div class="col-md-9 col-xs-12">
                                <div id="jobapp-tc">
                                    <label class="small">
                                        <input type="checkbox" class="sjb-required" name="jobapp_tc" id="jobapp-tc" value="<?php echo wp_kses_post(stripslashes_deep(trim(htmlspecialchars($term_conditions_content)))); ?>" required="required">
                                        <?php printf(__("%s", 'simple-job-board'), wp_kses_post(stripslashes_deep(trim($term_conditions_content)))); ?>
                                        <span class="required">*</span>
                                    </label>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="col-md-12 col-xs-12">
                                <div id="jobapp-tc">
                                    <label class="small">
                                        <input type="checkbox" class="sjb-required" name="jobapp_tc" id="jobapp-tc" value="<?php echo wp_kses_post(stripslashes_deep(trim(htmlspecialchars($term_conditions_content)))); ?>" required="required">
                                        <?php printf(__("%s", 'simple-job-board'), wp_kses_post(stripslashes_deep(trim($term_conditions_content)))); ?>
                                        <span class="required">*</span>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                    </div> <div class="clearfix"></div>
                    <?php if (0 < $total_sections) { ?>
                    </div>
                <?php } ?>
                <?php
            }
        }

        /**
         * Fires on job detail page before job submit button. 
         *                 
         * @since   2.2.0                   
         */
        do_action('sjb_job_application_form_fields_end');
        ?>
        <input type="hidden" name="job_id" value="<?php the_ID(); ?>" >
        <input type="hidden" name="action" value="process_applicant_form" >
        <input type="hidden" name="wp_nonce" value="<?php echo wp_create_nonce('jobpost_security_nonce') ?>" >
        <div class="clearfix"></div> 
        <?php if (0 === $total_sections) { ?>
            <div class="col-md-12 col-xs-12">
            <?php } ?>

            <div class="form-group" id="sjb-form-padding-button">
                <?php
                /**
                 * Button -> Before submit button
                 * 
                 * @since   2.10.0
                 */
                do_action('sjb_job_application_form_submit_btn_start');
                ?>

                <button class="btn btn-primary app-submit"><?php esc_html_e('Submit', 'simple-job-board'); ?></button> 
                
                <?php
                /**
                 * Button -> After submit button
                 * 
                 * @since   2.10.0
                 */
                do_action('sjb_job_application_form_submit_btn_end');
                ?>
                
            </div>
            <?php if (0 === $total_sections) { ?>
            </div>
        <?php } ?>

        <?php
        if (0 < $total_sections) {
            echo '</div>';
        }
        ?>
        <div class="clearfix"></div>
    </div>
    <?php
    /**
     * Template -> Loader Overlay Template
     * 
     * @since   2.7.0
     */
    get_simple_job_board_template('single-jobpost/loader.php');
    ?>
</form>

<div class="clearfix"></div>

<?php
/**
 * Fires on job detail page after displaying job application form.
 *                  
 * @since 2.1.0                   
 */
do_action('sjb_job_application_end');
?>

<div id="jobpost_form_status"></div>
<!-- ==================================================
End Job Application Form -->

<?php
/**
 * Fires on job detail page after displaying job application section.
 *                  
 * @since   2.1.0                   
 */
do_action('sjb_job_application_after');

$html_job_application = ob_get_clean();

/**
 * Modify the Job Application Form Template. 
 *                                       
 * @since   2.3.0
 * 
 * @param   html    $html_job_application   Job Application Form HTML.                   
 */
echo apply_filters('sjb_job_application_template', $html_job_application);
