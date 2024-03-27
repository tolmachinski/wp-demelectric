=== WPO365 | Mail Integration for Office 365 / Outlook ===
Contributors: wpo365
Tags: smtp, outlook smtp, 365 smtp, live smtp, oauth
Requires at least: 5.5.3
Tested up to: 6.1
Stable tag: 1.9.0
Requires PHP: 7.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connects WordPress to Office 365, Outlook or Exchange using OAuth2 allowing you to send email via Microsoft’s Graph API instead of SMTP & wp_mail

== Description ==

= Support ending =

Early November 2022, ownership of the **Mail Integration for Office 365 / Outlook** plugin transferred to [WPO365](https://www.wpo365.com/). We are committed to provide (best-effort based) support for this plugin until the end of 2023.

To ensure, however, that we are able to provide you with long time support, we urge you to download and install the [WPO365 | MICROSOFT GRAPH MAILER](https://wordpress.org/plugins/wpo365-msgraphmailer/) plugin for WordPress instead (and de-activate the **Mail Integration for Office 365 / Outlook** plugin and remove it from your WordPress website).

If you have already installed and configured the **Mail Integration for Office 365 / Outlook plugin**, then please make sure to check out our easy-to-understand [online migration](https://docs.wpo365.com/article/165-migrate-from-mail-integration-for-office-365-outlook-to-wpo365-microsoft-graph-mailer) guide.

Marco van Wieren | Downloads by van Wieren | https://www.wpo365.com/

= About this plugin =

**Mail Integration for Office 365/Outlook (using OAuth2 and Microsoft's Graph API rather than SMTP)**

This plugin addresses the limitations of current free SMTP plugins, allowing you to send email from WordPress via your Microsoft Account and ensure your email gets delivered reliably, avoiding the spam box! Note, unlike other free SMTP plugins, this one authenticates with the Microsoft Graph API rather than SMTP using OAuth2. This addresses the recent change by Microsoft to disable basic authentication over SMTP (i.e. username and password), preventing other free SMTP plugins from working with Microsoft Accounts. **Unfortunately, Microsoft has yet to enable their Graph API to work with the free versions of Outlook, Live, Hotmail etc. The plugin should however work with the paid for personal and business versions of Office 365, Outlook and Microsoft Exchange.**

== Frequently Asked Questions ==

*What does it do?*

Put simply, the plugin relays all emails sent from WordPress through your Microsoft email account (or an account of your choosing, for example a shared mailbox). This helps improve the deliverability of your WordPress mail, as Microsoft’s mail servers are set up to avoid being flagged as spam, and should already be configured as the trusted mail server for your domain.

*Why another WordPress mail plugin?!*

Microsoft has disabled basic authentication (username and password) over the SMTP protocol, requiring more modern and secure methods of authentication such as OAuth 2.0 (the protocol used by this plugin). As such most of the SMTP plugins available do not support Office 365/Outlook, or if they do, the functionality is a paid for feature.

*What are the prerequisites?*

To start using Mail Integration for Office 365, you need to setup a Microsoft Azure Active Directory account. Azure Active Directory comes in several flavours (paid and free), but it is possible to use the free tier for the purpose of this plugin. You can register for this here (you’re probably already registered if you are using Office 365). You will also need SSL enabled for at least the admin side of your website to ensure the OAuth keys are exchanged over a secure connection.

== Installation ==

You will need to follow the steps outlined on the following website to setup this plugin correctly: [https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin](https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin)

## Setup Instructions

You will need to follow the steps outlined on the following website to setup this plugin correctly: [https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin](https://docs.wpo365.com/article/164-mail-integration-365-wordpress-plugin)

== Changelog ==
Ver. 1.9.0 - Ownership of the plugin transferred to WPO365 and tested with WordPress 6.1 / PHP 8.0
Ver. 1.8.6 - Corrected typo of atts filter variable to args
Ver. 1.8.5 - Added code to handle empty to, cc, bc and reply to email addresses, as well as adding an admin error if wp_mail is utilised by another plugins
Ver. 1.8.4 - Added Important Updates! notification to alert users to need to re-authenticate with Office 365
Ver. 1.8.3 - Addressed composer dependency issues by using php-scoper to namespace php libraries. Also addressed empty email array bug.
Ver. 1.8.2 - Modified wp_mail plugable function to address various issues with closing SMTP mail events and forwarding to the Graph API
Ver. 1.7.1 - Fixed undefined variable error for $cc, $bcc and $reply_to in older PHP versions
Ver. 1.7.0 - Fixed WooCommerce and HTML issue
Ver. 1.6.0 - Fixed email content type issue, invalid oauth state issue, and reply-to and send as bug
Ver. 1.5.0 - Fixed additional multisite bug and reply-to bug
Ver. 1.4.0 - Fixed bug where multisite options were unsupported
Ver. 1.3.0 - Changed error and OAuth scope handling.
Ver. 1.2.0 - Added support for sending attachments <3MB in size. Fixed session bug with WordPress API.
Ver. 1.1.0 - Added prefix namespace to composer libraries to avoid conflicts with other plugins using Guzzle whilst also updating the oauth2-azure library at the same time.
Ver. 1.0.4 - Fixed issue with code running outside of settings page, leading to error messages on admin backend. Replaced localhost with 127.0.0.1 if present in redirect URI to prevent potential hostname resolution issues. Updated to handle multiple "to" email addresses (either as a comma separated string, or a one dimensional array)
Ver. 1.0.3 - Fixed issue with send as feature, preventing emails being sent on behalf of others.
Ver. 1.0.2 - Updated readme.txt description and tags.
Ver. 1.0.1 - Fixed minor spelling and display issues.