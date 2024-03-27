<?php

/**
 * Mail Integration for Office 365 - Admin Class
 *
 * Handles creation of plugin menus, settings and options
 **/

namespace Classes\Mail_Integration_365;

use _PhpScoper99e9e79e8301\TheNetworg\OAuth2\Client\Provider\Azure;
use _PhpScoper99e9e79e8301\League\OAuth2\Client\Provider\Exception\IdentityProviderException;

if (!class_exists("Classes\Mail_Integration_365\Admin")) {
    class Admin
    {
        // Declare public static properties
        public static $options_name = "mail_integration_365_plugin_ops";
        public static $settings_page_slug = "mail-integration-365-settings";

        public static function get_setting_fields($show_send_as_config)
        {
            $setting_fields = array(
                "oauth_settings" => array(
                    "description" => "Please enter your Office 365 OAUTH credentials required to connect to your mail server.",
                    "fields" => array(
                        "client_id" => array(
                            "args" => array(
                                "type" => "text"
                            )
                        ),
                        "tenant_id" => array(
                            "args" => array(
                                "type" => "text"
                            )
                        ),
                        "client_secret" => array(
                            "args" => array(
                                "type" => "password"
                            )
                        ),
                        "redirect_uri" => array(
                            "args" => array(
                                "type" => "text",
                                "readonly" => "readonly",
                                "onfocus" => "this.select();",
                                "inline_copy_button" => true
                            )
                        )
                    )
                ),
                "deactivation_options" => array(
                    "description" => "You can choose to keep your OAuth credentials within the WordPress database after plugin deactivation below, in case you are only temporarily deactivating this plugin. The default is for your credentials to be deleted.",
                    "fields" => array(
                        "keep_oauth_credentials" => array(
                            "args" => array(
                                "type" => "checkbox"
                            )
                        )
                    )
                ),
                /*
                "radio_example" => array(
                    "description" => "Radio Buttons",
                    "fields" => array(
                        "radio_button_label" => array(
                            "args" => array(
                                "type" => 'radio',
                                "items" => array("Radio Button 1", "Radio Button 2")
                            )
                        )
                    )
                )
                */
            );

            if ($show_send_as_config) {
                $setting_fields["send_as_another_account"] =  array(
                    "description" => 'If you wish to send emails from another account in your organisation (for example a shared mailbox), enable the option below and enter the email of this account in the box that appears. NOTE THIS DOESN\'T WORK FOR ACCOUNT AN ALIAS! You will need to <a href="https://docs.microsoft.com/en-us/microsoft-365/admin/add-users/give-mailbox-permissions-to-another-user?view=o365-worldwide">grant appropriate access to this other account within Office 365</a> for this option to work.',
                    "fields" => array(
                        "enable_send_as" => array(
                            "args" => array(
                                "type" => "checkbox",
                                "classes" => array(
                                    "options-show"
                                )
                            )
                        ),
                        "send_as_email" => array(
                            "args" => array(
                                "type" => "email",
                                "classes" => array(
                                    "options-hidden"
                                )
                            )
                        )
                    )
                );
            }

            return $setting_fields;
        }

        // Declare private properties
        private $access_token;
        private $options;
        private $provider;
        private $backend_scripts;

        /**
         * Construct class object
         */
        public function __construct()
        {
            // Set options class property
            $this->options = get_option(self::$options_name);

            // Create settings menu
            add_action("admin_menu", array($this, "create_settings_menu"));

            // Register scripts
            add_action("wp_loaded", array($this, "register_admin_scripts"));

            // Enqueue styles and scripts
            add_action("admin_enqueue_scripts", array($this, "enqueue_admin_styles_and_scripts"));

            // Register settings
            add_action("admin_init", array($this, "register_settings"));
        }

        /**
         * Register admin scripts
         *
         *  @param   string      $hook     Page name
         */
        public function register_admin_scripts($hook)
        {
            // Check admin page is the one we wish to load a style/script on.
            global $mail_integration_365_page_name;
            if ($hook != $mail_integration_365_page_name) {
                return; // exit if incorrect screen id
            }

            $this->admin_scripts = array(
                array(
                    "handle" => "admin-scripts",
                    "src" => plugins_url("js/admin-scripts.js", Core::$root_file_path),
                    "deps" => array("jquery", "clipboard"),
                    "ver" => filemtime(Core::$root_plugin_path . "js/admin-scripts.js"),
                    "footer" => true
                )
            );

            foreach ($this->admin_scripts as $value) {
                wp_register_script($value["handle"], $value["src"], $value["deps"], $value["ver"], $value["footer"]);
            }
        }

        /**
         * Load admin styles and scripts
         *
         *  @param   string      $hook     Page name
         */
        public function enqueue_admin_styles_and_scripts($hook)
        {
            // Check admin page is the one we wish to load a style/script on.
            global $mail_integration_365_page_name;
            if ($hook != $mail_integration_365_page_name) {
                return; // exit if incorrect screen id
            }

            /*
            // Enqueue styles
            foreach ($this->admin_styles as $value) {
                wp_enqueue_style($value["handle"]);
            }
            */

            // Enqueue scripts
            foreach ($this->admin_scripts as $value) {
                wp_enqueue_script($value["handle"]);
            }
        }

        /**
         * Construct input element
         *
         *  @param   string      $start_string     The first part of the input element
         *  @param   string      $end_string       The end part of the input element
         *  @param   array       $args             The input arguments
         */
        private function construct_input($start_string, $end_string, $args)
        {
            // Check if element should be readonly/enabled on focus
            if (array_key_exists("readonly", $args)) {
                $start_string .= sprintf(' readonly="%1$s"', $args["readonly"]);
            }
            if (array_key_exists("onfocus", $args)) {
                $start_string .= sprintf(' onfocus="%1$s"', $args["onfocus"]);
            }

            // Add custom classes
            if (array_key_exists("classes", $args)) {
                foreach ($args["classes"] as $class) {
                    $start_string .= sprintf(' class=%1$s ', $class);
                }
            }

            $string = $start_string . $end_string;

            // Add inline copy button opposite text input
            if (array_key_exists("inline_copy_button", $args)) {
                $string .= '<div class="button inline-copy-btn" data-clipboard-target="#%1$s"><span class="dashicons dashicons-clipboard" style="margin-top: 4px;" title="Copy text to clipboard"></span></div>';
            }

            return $string;
        }

        /**
         * Register plugin settings
         */
        public function register_settings($show_send_as_config)
        {
            $options = get_option(self::$options_name);

            $section_description = function ($args) use ($show_send_as_config) {
                foreach (self::get_setting_fields($show_send_as_config) as $key => $value) {
                    if ($args["id"] === self::$options_name . "_" . $key . "_section") {
                        if ($value["description"] != false) {
                            _e($value["description"], Core::$text_domain);
                        }
                        break;
                    }
                }
            };

            /*
             * Creates an input tag for a html form
             *
             *  @param   array      $args     Input arguments
             */
            $create_field = function ($args) {
                if ($args["type"] == "password") {
                    printf(
                        $this->construct_input('<input id="%1$s" name="%2$s[%3$s]" size="40" type="password" value="%4$s"', ' />', $args),
                        esc_attr($args["label_for"]),
                        esc_attr($args["option_name"]),
                        esc_attr($args["name"]),
                        esc_attr__($args["value"], Core::$text_domain)
                    );
                } elseif ($args["type"] == "radio") {
                    $count = 1;

                    foreach ($args["items"] as $item) {
                        printf(
                            $this->construct_input('<label><input %1$s id="%2$s" name="%3$s[%4$s]" value="%5$s" type="radio"', ' />%6$s</label><br />', $args),
                            checked($args["value"], $item, false),
                            esc_attr($args['label_for']) . "-" . $count,
                            esc_attr($args['option_name']),
                            esc_attr($args["name"]),
                            esc_attr($item),
                            esc_html__($item, Core::$text_domain)
                        );

                        $count++;
                    }
                } elseif ($args["type"] == "checkbox") {
                    if (isset($args["items"])) {
                        $count = 1;

                        foreach ($args["items"] as $item) {
                            printf(
                                $this->construct_input('<label><input %1$s id="%2$s" name="%3$s[%4$s]" value="%5$s" type="checkbox"', ' />%6$s</label><br />', $args),
                                checked($args["value"], $item, false),
                                esc_attr($args['label_for']) . "-" . $count,
                                esc_attr($args['option_name']),
                                esc_attr($args["name"]),
                                esc_attr($item),
                                esc_html__($item, Core::$text_domain)
                            );
                        }

                        $count++;
                    } else {
                        printf(
                            $this->construct_input('<input %1$s id="%2$s" name="%3$s[%4$s]" type="checkbox"', ' />', $args),
                            checked("on", $args["value"], false), // No need to escape as this is not user input
                            esc_attr($args["label_for"]),
                            esc_attr($args["option_name"]),
                            esc_attr($args["name"])
                        );
                    }
                } elseif ($args["type"] == "text" || $args["type"] == "email") {
                    printf(
                        $this->construct_input('<input id="%1$s" name="%2$s[%3$s]" size="40" type="%4$s" value="%5$s"', ' />', $args),
                        esc_attr($args["label_for"]),
                        esc_attr($args["option_name"]),
                        esc_attr($args["name"]),
                        esc_attr($args["type"]),
                        esc_attr__($args["value"], Core::$text_domain)
                    );
                } elseif ($args["type"] == "wysiwyg") {
                    echo wp_editor(html_entity_decode($args["value"]), "wysiwyg", array("textarea_name" => $args["option_name"] . "[" . $args["name"] . "]"));
                } elseif ($args["type"] == "link") {
                    printf(
                        $this->construct_input('<a href="%1$s" class="button button-secondary"', '>%2$s</a>', $args),
                        esc_attr__($args["value"], Core::$text_domain),
                        esc_html($args["link_text"])
                    );
                } elseif ($args["type"] == "error") {
                    printf(
                        $this->construct_input('<p class="notice notice-warning" style="width: 300px"', '>%1$s</p>', $args),
                        esc_html__($args["value"], Core::$text_domain)
                    );
                }
            };
            /*
             * Validate the option inputs on settings submit event
             *
             *  @param   array      $input     Array of settings options
             */
            $validate_settings = function ($input) {
                // Hold the sanitised values
                $sanitised_input = array();

                // Loop through the input and sanitise each of the values
                foreach ($input as $key => $val) {
                    if ($key === self::$options_name . '_send_as_email_field' && isset($input[self::$options_name . '_enable_send_as_field'])) {
                        if ($input[self::$options_name . '_enable_send_as_field'] === 'on' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                            $this->add_error("invalid-send-as-email", "The send as email is not valid.", "error");
                        }
                    }
                    $sanitised_input[$key] = sanitize_text_field($val);
                }

                return $sanitised_input;
            };

            foreach (self::get_setting_fields($show_send_as_config) as $key1 => $value1) {
                // Add settings section
                add_settings_section(
                    self::$options_name . "_" . $key1 . "_section",
                    ucwords(str_replace("_", " ", $key1)),
                    $section_description,
                    self::$settings_page_slug . "_" . $key1
                );

                // Add settings section fields
                foreach ($value1["fields"] as $key2 => $value2) {
                    $field_id = self::$options_name . "_" . $key2 . "_field";

                    if (isset($this->options[$field_id])) {
                        $option_value = $this->options[$field_id];
                    } elseif (isset($value2["args"]["value"])) {
                        $option_value = $value2["args"]["value"];
                    } else {
                        $option_value = "";
                    }

                    $args = array(
                        "label_for" => $field_id,
                        "option_name" => self::$options_name,
                        "name" => $field_id,
                        "value" => $option_value
                    );

                    // Check for dependencies
                    if (array_key_exists("dependencies", $value2)) {
                        $bool = false;

                        foreach ($value2["dependencies"]["fields"] as $val) {
                            $field_value = isset($this->options[$field_id = self::$options_name . "_" . $val . "_field"]) ? $this->options[$field_id = self::$options_name . "_" . $val . "_field"] : "";
                            if ($field_value == "") {
                                $bool = true;
                            }
                        }

                        if ($bool) {
                            $args["type"] = "error";
                            $args["value"] = __($value2["dependencies"]["error_message"], Core::$text_domain);
                        }
                    }

                    add_settings_field(
                        $args["label_for"],
                        ucwords(str_replace("_", " ", $key2)),
                        $create_field,
                        self::$settings_page_slug . "_" . $key1,
                        self::$options_name . "_" . $key1 . "_section",
                        wp_parse_args($args, $value2["args"]) //the $args object to pass to the callback
                    );
                }

                //Register setting
                register_setting(
                    self::$settings_page_slug . "_" . $key1,
                    self::$options_name,
                    $validate_settings
                );
            }
        }

        /**
         * Create settings menu
         */
        public function create_settings_menu()
        {
            global $mail_integration_365_page_name;

            $mail_integration_365_page_name = add_options_page(
                ucwords(str_replace("-", " ", self::$settings_page_slug)),
                ucwords(str_replace("-", " ", self::$settings_page_slug)),
                "install_plugins",
                self::$settings_page_slug,
                array($this, "create_options_page")
            );
        }

        /**
         * Set OAuth provider
         */
        public function set_oauth_provider()
        {
            // Hack to stop settings errors showing twice
            global $wp_settings_errors;
            $wp_settings_errors = array();

            try {
                $this->provider = new Azure([
                    "clientId" => $this->options[self::$options_name . "_client_id_field"],
                    "clientSecret" => $this->options[self::$options_name . "_client_secret_field"],
                    "redirectUri" => $this->options[self::$options_name . "_redirect_uri_field"],
                    "tenant" => $this->options[self::$options_name . "_tenant_id_field"],
                ]);

                // Set to use v2 API, skip the line or set the value to Azure::ENDPOINT_VERSION_1_0 if willing to use v1 API
                $this->provider->defaultEndPointVersion = Azure::ENDPOINT_VERSION_2_0;
            } catch (IdentityProviderException $e) {
                // Failed to get the access token or user details.
                $this->add_error("oauth-provider-error", $e->getMessage(), "error");
            }
        }

        /**
         * Get authorisation url
         */
        public function get_authorisation_url()
        {
            // Hack to stop settings errors showing twice
            global $wp_settings_errors;
            $wp_settings_errors = array();

            try {
                $base_graph_uri = esc_url_raw($this->provider->getRootMicrosoftGraphUri(null));
                $this->provider->scope = "openid profile email offline_access " . $base_graph_uri . "/User.Read " . $base_graph_uri . "/Mail.Send " . $base_graph_uri . "/Mail.Send.Shared " . $base_graph_uri . "/Mail.ReadWrite " . $base_graph_uri . "/Mail.ReadWrite.Shared";

                set_transient(get_current_user_id() . 'mail-integration-365-oauth2-auth-url', esc_url_raw($this->provider->getAuthorizationUrl()), 3600);
                set_transient(get_current_user_id() . 'mail-integration-365-oauth2-state', sanitize_text_field($this->provider->getState()), 3600);
            } catch (IdentityProviderException $e) {
                // Failed to get the access token or user details.
                $this->add_error("oauth-provider-error", $e->getMessage(), "error");
            }
        }

        /**
         * Create settings page
         */
        public function create_options_page()
        {
            global $mail_integration_365_page_name;
            $screen = get_current_screen();

            // Check settings page is the one we wish to run code for, otherwise exit
            if ($screen->id != $mail_integration_365_page_name) {
                return; // exit if incorrect screen id
            }

            // Set global SSL boolean to register if page is loaded over SSL or not
            global $mail_integration_365_ssl_check;
            $mail_integration_365_ssl_check = true;

            if (current_user_can("install_plugins")) {
                // Make sure site is accessing admin via ssl
                if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                    if ($_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https') {
                        $mail_integration_365_ssl_check = false;
                    }
                } elseif (!isset($_SERVER['HTTPS'])) {
                    $mail_integration_365_ssl_check = false;
                }

                // Set redirect uri if not already set
                if (empty($this->options[self::$options_name . "_redirect_uri_field"])) {
                    $link = get_site_url();

                    // Make sure site url is https if served over https
                    if (strpos($link, 'https://') === false) {
                        $link = str_replace("http://", "https://", $link);
                    }

                    // Replace localhost with 127.0.0.1 to prevent issues with hostname resolution (as advised by Microsoft for redirect URI)
                    $link = str_replace("localhost", "127.0.0.1", $link);

                    $this->options[self::$options_name . "_redirect_uri_field"] =  esc_url_raw($link . "/wp-admin/options-general.php?page=" . self::$settings_page_slug);
                }

                // Set oauth provider if required options are present
                if (isset($this->options[self::$options_name . "_client_id_field"]) && isset($this->options[self::$options_name . "_client_secret_field"]) && isset($this->options[self::$options_name . "_tenant_id_field"])) {
                    $this->set_oauth_provider();
                }

                // Check page is loaded over SSL
                if (!$mail_integration_365_ssl_check) {
                    $this->add_error("ssl-error", __('You must <a href="https://wordpress.org/support/article/administration-over-ssl/">enable ssl for the admin pages of wordpress</a> to use this plugin.', Core::$text_domain), "error");
                }
                // Check if authorisation code and state is present in callback url
                elseif (empty($_GET['settings-updated'])) {
                    if (isset($_GET["error_description"])) {
                        $this->add_error("ms-graph-api-error", esc_html__($_GET["error_description"], Core::$text_domain), "error");
                    } else if (isset($_GET["code"]) && isset($_GET["state"]) && get_transient(get_current_user_id() . 'mail-integration-365-oauth2-state')) {
                        // Display Microsoft Graph API errors if present in callback
                        if (get_transient(get_current_user_id() . 'mail-integration-365-oauth2-state') == sanitize_text_field($_GET["state"])) {
                            try {
                                // Try to get an access token using the authorization code grant.
                                $this->access_token = $this->provider->getAccessToken(
                                    "authorization_code",
                                    ["code" => sanitize_text_field($_GET["code"])]
                                );

                                // Save access token and delete associated transients to generate new scope/authorisation url
                                update_option(self::$options_name . "_access_token", $this->access_token);

                                delete_transient(get_current_user_id() . 'mail-integration-365-oauth2-state');
                                delete_transient(get_current_user_id() . 'mail-integration-365-oauth2-auth-url');

                                // Regenerate the authorisation url and associated scope
                                $this->get_authorisation_url();
                            } catch (IdentityProviderException $e) {
                                // Failed to get the access token or user details.
                                $this->add_error("oauth-provider-error", $e->getMessage(), "error");
                            }
                        } else {
                            $this->add_error("oauth-provider-error", "Invalid OAuth State", "error");
                        }
                    }
                }
                // Show Microsoft Graph Errors if present in callback url
                elseif (isset($_GET["error_description"])) {
                    echo $_GET["error_description"];
                    $this->add_error("ms-graph-api-error", esc_html__($_GET["error_description"], Core::$text_domain), "error");
                }
                // Generate new authorisation url and state on page load if client id, client secret and tenant id are present
                elseif (isset($this->options[self::$options_name . "_client_id_field"]) && isset($this->options[self::$options_name . "_client_secret_field"]) && isset($this->options[self::$options_name . "_tenant_id_field"])) {
                    $this->get_authorisation_url();
                }

                // Show message to indicate successful configuration of plugin
                if (get_option(self::$options_name . "_access_token")) {
                    $this->add_error("oauth-settings-set-successfully", __('Plugin is configured to send mail via Office 365', Core::$text_domain), "info");
                }

                // Re-register settings to populate redirect-uri value
                $show_send_as_config = is_array($this->options) && !empty($this->options[self::$options_name . "_send_as_email_field"]);
                $this->register_settings($show_send_as_config);

                require_once Core::$root_plugin_path . "/views/admin_settings.php";
            } else {
                wp_die(__("User is not authorized to access this page.", Core::$text_domain));
            }
        }

        /**
         * Add settings API error
         *
         * @param   string  $key        The key identifying the error message
         * @param   string  $message    The error message to display to the user
         * @param   string  $type    	The type of message to display to the user
         */
        public function add_error($key, $message, $type)
        {
            add_settings_error(
                self::$settings_page_slug,
                $key,
                __($message, Core::$text_domain),
                $type
            );
        }

        /**
         * Helper to show an admin notice.
         * 
         * @since   1.9.0
         * 
         * @return  void
         */
        public static function show_migrate_notice()
        {
            $install_url = wp_nonce_url(
                add_query_arg(
                    array(
                        'action' => 'install-plugin',
                        'plugin' => 'wpo365-msgraphmailer',
                        'from'   => 'plugins',
                    ),
                    self_admin_url('update.php')
                ),
                'install-plugin_wpo365-msgraphmailer'
            );

            ob_start();
            include(dirname(dirname(__DIR__)) . '/views/admin-notifications.php');
            $content = ob_get_clean();
            echo '' . $content;
        }
    }
}
