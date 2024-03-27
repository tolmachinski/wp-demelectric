<?php

/**
 * Mail Integration for Office 365 - Admin Settings View
 *
 * Displays the admin settings page HTML
 **/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Classes\Mail_Integration_365;

?>
<div class="wrap">
    <?php

    // Display migration notices
    Mail_Integration_365\Admin::show_migrate_notice();

    // Display settings errors on first load without form submission
    settings_errors(Classes\Mail_Integration_365\Admin::$settings_page_slug, false, false);

    // Check if page is loaded over SSL
    global $mail_integration_365_ssl_check;
    if ($mail_integration_365_ssl_check) {
    ?>
        <h4><?php _e('To connect to your Office 365/personal Outlook account, you need to follow the steps in the following guide to get the three required credentials requested below: ', Mail_Integration_365\Core::$text_domain); ?><a href="https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin">https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin</a></h4>
        <form method="post" action="options.php">
            <?php
            $options = get_option(Mail_Integration_365\Admin::$options_name);
            $show_send_as_config = is_array($options) && !empty($options[Mail_Integration_365\Admin::$options_name . '_send_as_email_field']);

            foreach (Mail_Integration_365\Admin::get_setting_fields($show_send_as_config) as $key1 => $value1) {
                settings_fields(Mail_Integration_365\Admin::$settings_page_slug . '_' . $key1);
                do_settings_sections(Mail_Integration_365\Admin::$settings_page_slug . '_' . $key1);

                // Check if client id, client secret and tenant ID are set, displaying either a message to request these are set, or button with the authorisation url hyperlink
                if ($key1 == "oauth_settings") {
                    if (get_transient(get_current_user_id() . 'mail-integration-365-oauth2-auth-url')) {
                        printf('<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="mail_integration_365_plugin_ops_authorisation_field">Authorisation</label></th><td><a href="%1$s" class="button button-secondary">Authorize plugin to integrate with Office 365</a></td></tr></tbody></table>', get_transient(get_current_user_id() . 'mail-integration-365-oauth2-auth-url'));
                    } else {
                        echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="mail_integration_365_plugin_ops_authorisation_field">Authorisation</label></th><td><p class="notice notice-warning" style="width: 300px">You need to save settings with a valid Tenant ID, Client ID and Client Secret before you can proceed.</p></td></tr></tbody></table>';
                    }
                }
            }

            submit_button(); ?>
        </form>
    <?php
    }
    ?>
</div>