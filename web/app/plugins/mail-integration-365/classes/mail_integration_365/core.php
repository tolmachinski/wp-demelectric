<?php

/**
 * Mail Integration for Office 365 - Core Class
 *
 * Handles activation and deactivation hooks as well as other core methods and functionality
 **/

namespace Classes\Mail_Integration_365;

if (!class_exists("Classes\Mail_Integration_365\Core")) {
    class Core
    {
        // Declare public static properties
        public static $root_file_path;
        public static $root_plugin_path;
        public static $text_domain = 'mail-integration-365';

        /**
         * Construct class object
         *
         *  @param   string      $root_file_path     The plugins root file path
         *  @param   string      $root_plugin_path   The plugins root path
         */
        public function __construct($root_file_path = false, $root_plugin_path = false)
        {
            self::$root_file_path = $root_file_path;
            self::$root_plugin_path = $root_plugin_path;

            // Register activation and deactivation hooks
            register_activation_hook(self::$root_file_path, array($this, "activate"));
            register_deactivation_hook(self::$root_file_path, array($this, "deactivate"));

            // Instantiate other default required classes upon Core class instantiation
            new Admin();
        }

        /**
         * Run on plugin activation event
         */
        public function activate()
        {
            // Add settings option
            add_option(Admin::$options_name, array());
            add_option(Admin::$options_name . "_access_token", false);
        }

        /**
         * Run on plugin deactivation event
         */
        public function deactivate()
        {
            $options = get_option(Admin::$options_name);

            // Delete plugin settings/options on deactivation if this is set to true
            if (
                empty($options[Admin::$options_name . '_keep_oauth_credentials_field'])
                || $options[Admin::$options_name . '_keep_oauth_credentials_field'] != "on"
            ) {
                delete_option(Admin::$options_name);
                delete_option(Admin::$options_name . "_access_token");
            }
        }

        /**
         * Helper for wp_kses to define the allowed HTML element names, attribute names, attribute 
         * values, and HTML entities
         * 
         * @return mixed 
         */
        public static function get_allowed_html()
        {
            global $allowedposttags;

            $allowed_atts = array(
                'action' => array(),
                'align' => array(),
                'alt' => array(),
                'class' => array(),
                'data-nonce'  => array(),
                'data-props' => array(),
                'data-wpajaxadminurl' => array(),
                'data' => array(),
                'dir' => array(),
                'fill' => array(),
                'for' => array(),
                'height' => array(),
                'href' => array(),
                'html' => array(),
                'id' => array(),
                'lang' => array(),
                'method' => array(),
                'name' => array(),
                'novalidate' => array(),
                'onClick' => array(),
                'onclick' => array(),
                'rel' => array(),
                'rev' => array(),
                'src' => array(),
                'style' => array(),
                'tabindex' => array(),
                'target' => array(),
                'title' => array(),
                'type' => array(),
                'type' => array(),
                'value' => array(),
                'viewBox' => array(),
                'width' => array(),
                'x' => array(),
                'xml:lang' => array(),
                'xmlns' => array(),
                'y' => array(),
            );

            // Add custom tags
            $allowed_tags = array('script' => $allowed_atts);
            $allowed_tags['!DOCTYPE'] = $allowed_atts;
            $allowed_tags['body'] = $allowed_atts;
            $allowed_tags['head'] = $allowed_atts;
            $allowed_tags['html'] = $allowed_atts;
            $allowed_tags['rect'] = $allowed_atts;
            $allowed_tags['style'] = $allowed_atts;
            $allowed_tags['svg'] = $allowed_atts;
            $allowed_tags['title'] = $allowed_atts;
            $allowed_tags['button'] = $allowed_atts;
            $allowed_tags['a'] = $allowed_atts;

            // Merge global and custom tags
            $all_allowed_tags = array_merge($allowedposttags, $allowed_tags);

            // Overwrite global ones with custom atts
            $all_allowed_tags['div'] = array_merge($allowedposttags['div'], $allowed_atts);

            return $all_allowed_tags;
        }
    }
}
