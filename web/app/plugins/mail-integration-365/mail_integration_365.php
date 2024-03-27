<?php

/**
 * Plugin Name:       WPO365 | Mail Integration for Office 365 / Outlook
 * Plugin URI:        https://wordpress.org/plugins/mail-integration-365
 * Description:       Plugin for sending mail via Microsoft 365 / Outlook using OAuth2 and Microsoft's Graph API rather than SMTP.
 * Version:           1.9.0
 * Requires PHP:      7.1.1
 * Author:            marco@wpo365.com
 * Author URI:        https://www.wpo365.com
 * Text Domain:       mail-integration-365
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// AutoLoad Azure OAuth2 PHP Library
include_once(dirname(__FILE__) . "/libs/vendor/autoload.php");

// Autoload plugin classes
spl_autoload_register("mail_integration_365_autoloader");

/**
 * The autoloader function to load all the plugin"s classes
 *
 * @param   string      $class_name     The name of the class currently being loaded
 */
function mail_integration_365_autoloader($class_name)
{
    if (false !== strpos($class_name, "Mail_Integration_365")) {
        $file = strtolower(str_replace("\\", DIRECTORY_SEPARATOR, $class_name));
        require_once $file . ".php";
    }
}

// Instantiate the Core class containing core plugin methods and functionality
new Classes\Mail_Integration_365\Core(__FILE__, plugin_dir_path(__FILE__));

use Classes\Mail_Integration_365;

// Override pluggable wp_mail() function
if (!function_exists("wp_mail")) {
    /**
     * Override wp_mail() function to capture wp_mail events and avoid triggering of phpmailer
     *
     * @param   string          $to             The email address the email is to be sent to
     * @param   string          $subject        The email subject
     * @param   string          $message        The main content of the email
     * @param   string/array    $headers        The email headers
     * @param   array           $attachments    The email file attachments
     * @return  bool            $bool           Whether the email contents were sent successfully.
     */
    function wp_mail($to, $subject, $message, $headers = "", $attachments = array())
    {
        // Instantiate the Mail class
        $mail = new Classes\Mail_Integration_365\Mail(__FILE__, plugin_dir_path(__FILE__));

        $args = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

        if (isset($args['to'])) {
            $to = $args['to'];
        }

        if (!is_array($to)) {
            $to = explode(',', $to);
        }

        if (isset($args['subject'])) {
            $subject = $args['subject'];
        }

        if (isset($args['message'])) {
            $message = $args['message'];
        }

        if (isset($args['headers'])) {
            $headers = $args['headers'];
        }

        if (isset($args['attachments'])) {
            $attachments = $args['attachments'];
        }

        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        // Headers.
        $cc       = array();
        $bcc      = array();
        $reply_to = array();

        if (empty($headers)) {
            $headers = array();
        } else {
            if (!is_array($headers)) {
                // Explode the headers out, so this function can take
                // both string headers and an array of headers.
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }
            $headers = array();

            // If it's actually got contents.
            if (!empty($tempheaders)) {
                // Iterate through the raw headers.
                foreach ((array) $tempheaders as $header) {
                    if (strpos($header, ':') === false) {
                        if (false !== stripos($header, 'boundary=')) {
                            $parts    = preg_split('/boundary=/i', trim($header));
                            $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                        }
                        continue;
                    }
                    // Explode them out.
                    list($name, $content) = explode(':', trim($header), 2);

                    // Cleanup crew.
                    $name    = trim($name);
                    $content = trim($content);

                    switch (strtolower($name)) {
                            // Mainly for legacy -- process a "From:" header if it's there.
                        case 'from':
                            $bracket_pos = strpos($content, '<');
                            if (false !== $bracket_pos) {
                                // Text before the bracketed email is the "From" name.
                                if ($bracket_pos > 0) {
                                    $from_name = substr($content, 0, $bracket_pos - 1);
                                    $from_name = str_replace('"', '', $from_name);
                                    $from_name = trim($from_name);
                                }

                                $from_email = substr($content, $bracket_pos + 1);
                                $from_email = str_replace('>', '', $from_email);
                                $from_email = trim($from_email);

                                // Avoid setting an empty $from_email.
                            } elseif ('' !== trim($content)) {
                                $from_email = trim($content);
                            }
                            break;
                        case 'content-type':
                            if (strpos($content, ';') !== false) {
                                list($type, $charset_content) = explode(';', $content);
                                $content_type = trim($type);
                                if (false !== stripos($charset_content, 'charset=')) {
                                    $charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
                                } elseif (false !== stripos($charset_content, 'boundary=')) {
                                    $boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
                                    $charset  = '';
                                }

                                // Avoid setting an empty $content_type.
                            } elseif ('' !== trim($content)) {
                                $content_type = trim($content);
                            }
                            break;
                        case 'cc':
                            $cc = array_merge((array) $cc, explode(',', $content));
                            break;
                        case 'bcc':
                            $bcc = array_merge((array) $bcc, explode(',', $content));
                            break;
                        case 'reply-to':
                            $reply_to = array_merge((array) $reply_to, explode(',', $content));
                            break;
                        default:
                            // Add it to our grand headers array.
                            $headers[trim($name)] = trim($content);
                            break;
                    }
                }
            }
        }

        // If we don't have a content-type from the input headers.
        if (!isset($content_type)) {
            $content_type = 'text/plain';
        }

        /**
         * Filters the wp_mail() content type.
         *
         * @since 2.3.0
         *
         * @param string $content_type Default wp_mail() content type.
         */
        $content_type = apply_filters('wp_mail_content_type', $content_type);

        // Send mail via the api and throw write any error to log file
        $result = $mail->send($to, $subject, $message, $cc, $bcc, $reply_to, $content_type, $attachments);

        // False result is used for successful email given that an error message 
        // would evaluate to true if returned by send function.
        if (!$result) {
            return true;
        } else {
            $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
            $mail_error_data['graph_api_exception_code'] = $result->getCode();

            /** This filter is documented in wp-includes/pluggable.php */
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $result->getMessage(), $mail_error_data));

            return false;
        }
    }
} else {
    if (!(defined('DOING_AJAX') && DOING_AJAX)) {
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
?>
            <div class="notice notice-warning is-dismissible">
                <p><?php echo "Another plugin is conflicting with <strong>Mail Integration 365</strong>. You will need to disable this plugin in order for emails to be sent." ?></p>
            </div>
<?php
        });
    }
}
