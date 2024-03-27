<?php

/**
 * Mail Integration for Office 365 - Mail Class
 *
 * Captures overridden wp_mail() events, instigating a Microsoft
 * Graph API request using OAuth2 authentication rather than
 * the SMTP protocol
 **/

namespace Classes\Mail_Integration_365;

use _PhpScoper99e9e79e8301\TheNetworg\OAuth2\Client\Provider\Azure;
use _PhpScoper99e9e79e8301\League\OAuth2\Client\Provider\Exception\IdentityProviderException;

if (!class_exists("Classes\Mail_Integration_365\Mail")) {
    class Mail
    {
        public $options;

        /**
         * Construct class object
         */
        public function __construct()
        {
            // Get main plugin options
            $this->options = get_option(Admin::$options_name);
        }

        /**
         * Custom send() function using captured wp_mail() event arguments
         * to send an email via the Microsoft Graph API using OAuth2 credentials
         *
         * @param   array   $args   An array containing the captured wp_mail() arguments
         */
        public function send($to, $subject, $message, $cc, $bcc, $reply_to, $content_type, $attachments)
        {
            try {
                // Set up the OAuth2 provider using the Azure OAuth2 PHP Library
                $provider = new Azure([
                    "clientId" => $this->options[Admin::$options_name . "_client_id_field"],
                    "clientSecret" => $this->options[Admin::$options_name . "_client_secret_field"],
                    "redirectUri" => $this->options[Admin::$options_name . "_redirect_uri_field"],
                    "tenant" => $this->options[Admin::$options_name . "_tenant_id_field"]
                ]);

                $access_token = get_option(Admin::$options_name . "_access_token");

                // Convert content type to Microsoft Graph content types
                if ($content_type == "text/plain") {
                    $content_type = "text";
                } elseif (strpos($content_type, "text/html") !== false) {
                    $content_type = "html";
                }

                // Create the email body object that can be serialised as JSON
                $body = array(
                    "message" => array(
                        "subject" => $subject,
                        "body" => array(
                            "contentType" => $content_type,
                            "content" => $message
                        ),
                        "toRecipients" => array(),
                        "ccRecipients" => array(),
                        "bccRecipients" => array(),
                        "replyTo" => array()
                    ),
                    "saveToSentItems" => "true", // Set to store emails in saved items by default
                );

                // Parse to address/es
                foreach ($to as $email) {
                    if (strpos($email, "@") !== false) {
                        // Extract email from address if in the format "Foo <bar@baz.com>". 
                        if (preg_match('/(.*)<(.+)>/', $email, $matches)) {
                            if (count($matches) == 3) {
                                $email = $matches[2];
                            }
                        }

                        array_push($body["message"]["toRecipients"], array("emailAddress" => array("address" => $email)));
                    }
                }

                // Parse cc address/es
                foreach ($cc as $email) {
                    if (strpos($email, "@") !== false) {
                        // Extract email from address if in the format "Foo <bar@baz.com>". 
                        if (preg_match('/(.*)<(.+)>/', $email, $matches)) {
                            if (count($matches) == 3) {
                                $email = $matches[2];
                            }
                        }

                        array_push($body["message"]["ccRecipients"], array("emailAddress" => array("address" => $email)));
                    }
                }

                foreach ($bcc as $email) {
                    if (strpos($email, "@") !== false) {
                        // Extract email from address if in the format "Foo <bar@baz.com>". 
                        if (preg_match('/(.*)<(.+)>/', $email, $matches)) {
                            if (count($matches) == 3) {
                                $email = $matches[2];
                            }
                        }

                        array_push($body["message"]["bccRecipients"], array("emailAddress" => array("address" => $email)));
                    }
                }

                // Parse reply-to address/es
                foreach ($reply_to as $email) {
                    if (strpos($email, "@") !== false) {
                        // Extract email from address if in the format "Foo <bar@baz.com>". 
                        if (preg_match('/(.*)<(.+)>/', $email, $matches)) {
                            if (count($matches) == 3) {
                                $email = $matches[2];
                            }
                        }

                        array_push($body["message"]["replyTo"], array("emailAddress" => array("address" => $email)));
                    }
                }

                // Send mail from a specified account
                if (isset($this->options[Admin::$options_name . "_enable_send_as_field"]) && $this->options[Admin::$options_name . "_enable_send_as_field"] === 'on' && isset($this->options[Admin::$options_name . "_send_as_email_field"])) {
                    $body["message"]["from"] = array(
                        "emailAddress" => array(
                            "address" => $this->options[Admin::$options_name . "_send_as_email_field"]
                        )
                    );
                }

                // Set Azure endpoint version
                $provider->defaultEndPointVersion = Azure::ENDPOINT_VERSION_2_0;

                // Get the base Microsoft Graph URI
                $base_graph_uri = $provider->getRootMicrosoftGraphUri(null);


                // Set provider scope
                $provider->scope = "openid profile email offline_access " . $base_graph_uri . "/User.Read " . $base_graph_uri . "/Mail.Send " . $base_graph_uri . "/Mail.Send.Shared " . $base_graph_uri . "/Mail.ReadWrite " . $base_graph_uri . "/Mail.ReadWrite.Shared";

                // Check if access token has expired and update plugin options with new access token.
                // The post() function used to instigate the API request below, does also check this by
                // default. However, it does not return the new access token to save.
                if ($access_token->hasExpired()) {
                    // Request a new access token using the refresh token embedded in $access_token
                    $access_token = $provider->getAccessToken("refresh_token", [
                        "refresh_token" => $access_token->getRefreshToken()
                    ]);

                    // Update the current saved access token with the new one
                    update_option(Admin::$options_name . "_access_token", $access_token);
                }

                // Prevent crash of app for empty attachment array
                $attachments = array_filter($attachments);

                // Handle email attachments
                if (empty($attachments)) {
                    // Send the API request via the Azure OAuth2 PHP library"s post() function
                    $provider->post($base_graph_uri . "/v1.0/me/sendMail", $body, $access_token);
                } else {
                    $total_size = 0;
                    $body["message"]["attachments"] = array();

                    // Check if total attachments are over 3MB in size
                    foreach ($attachments as $key => $file) {
                        $handle = fopen($file, 'r');
                        $file_size = fileSize($file);
                        $total_size += $file_size;
                        $attachment = array(
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => basename($file),
                            'contentType' => mime_content_type($file),
                            'contentBytes' => base64_encode(fread($handle, $file_size)),
                        );
                        array_push($body["message"]["attachments"], $attachment);
                    }

                    if ($total_size > 3145728) {
                        throw new IdentityProviderException('Attachments were too large to email via Mail Integration 365');
                    } else {
                        // Send the API request via the Azure OAuth2 PHP library"s post() function
                        $provider->post($base_graph_uri . "/v1.0/me/sendMail", $body, $access_token);
                    }
                }
            } catch (IdentityProviderException $e) {
                // Return exception object
                return $e;;
            } finally {
                // Return false if mail sends successfully (false is used rather than true, as we wish to return an error message on failure, which evaluates to a true rather than false response)
                return false;
            }
        }
    }
}
