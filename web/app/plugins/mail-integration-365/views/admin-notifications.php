<?php

// Prevent public access to this script
defined('ABSPATH') or die();

?>

<style>
    .wpo365-notice {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-left-width: 4px;
        border-left-color: #72aee6;
        box-shadow: 0 1px 1px rgb(0 0 0 / 4%);
        margin: 5px 0 15px;
        padding: 1px 12px;
    }
</style>

<script>
    window.wpo365 = window.wpo365 || {}
    window.wpo365.wpoCollapsed = false
    window.wpo365.wpoCollapse = () => {
        const items = document.getElementsByClassName('wpoCollapsable')
        for (let i = 0; i < items.length; i++) {
            items.item(i).style.display = window.wpo365.wpoCollapsed ? 'block' : 'none'
        }
        window.wpo365.wpoCollapsed = !window.wpo365.wpoCollapsed
        const button = document.getElementById('wpoCollapseButton').innerText = window.wpo365.wpoCollapsed ? 'Read more' : 'Read less'
    }
</script>

<div>
    <div style="margin-left: 2px; background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; border-left-color: #72aee6; box-shadow: 0 1px 1px rgb(0 0 0 / 4%); margin: 5px 0 15px; padding: 1px 12px;">
        <table style="border: 0; border-collapse: collapse; width: 100%; max-width: 1024px;">
            <tbody>
                <tr>
                    <td style="width: 65px; vertical-align: middle; border-top: 0px; height: 65px;">
                        <div">
                            <a href="https://www.wpo365.com/" target="_blank">
                                <img style="width: 100%; max-height: 48px; height: auto; max-width: 48px; min-width: 48px; border: 0px;" src="https://www.wpo365.com/wp-content/uploads/2021/07/icon-128x128-1-128x128.png?notification">
                            </a>
                        </div">
                    </td>
                    <td style="vertical-align: middle; border-top: 0px; height: 65px;">
                        <h3>Support-ending notice</h3>
                    </td>
                    <td style="width: 150px; ; border-top: 0px; height: 65px;">
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <p>
                            Early November 2022, ownership of the
                            <strong><a href="https://wordpress.org/plugins/mail-integration-365/" target="_blank">Mail Integration for Office 365 / Outlook</a></strong> plugin transferred to <strong>
                                <a href="https://www.wpo365.com/" target="_blank">WPO365</a></strong>. We are committed to provide
                            (best-effort based) support for this plugin until the end of 2023.
                        </p>
                        <p>
                            To ensure, however, that we are able to provide you with long time support, we urge you to download and install the
                            <a href="<?php echo ($install_url) ?>"><strong>WPO365 | MICROSOFT GRAPH MAILER</strong></a> plugin for WordPress instead (and de-activate the&nbsp;<strong>Mail Integration for Office 365 / Outlook</strong> plugin and remove it from your WordPress website).
                        </p>
                        <p>
                            If you have already installed and configured the&nbsp;
                            <strong>Mail Integration for Office 365 / Outlook</strong>&nbsp;plugin, then please make sure to check out&nbsp;our easy-to-understand
                            <a href="https://docs.wpo365.com/article/165-migrate-from-mail-integration-for-office-365-outlook-to-wpo365-microsoft-graph-mailer" target="_blank">online migration guide</a>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <p style="margin-bottom: 15px;">
                            - Marco van Wieren | Downloads by van Wieren |
                            <a href="https://www.wpo365.com/" target="_blank">https://www.wpo365.com/</a>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>