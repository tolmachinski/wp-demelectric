<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
/**
 * Simple_Job_Board_Rewrite Class
 * 
 * This is used to define the job board rewrite rules. These rewrite rules prevent
 * the hotlinking of resumes & also force resumes to download rather than 
 * opening in browser.
 *
 * @link        https://wordpress.org/plugins/simple-job-board
 * @since       2.1.0
 * @since       2.2.3   Updated anti-hotlinking rules specific to uploads/jobpost.
 * @since       2.4.3   Removed the anti-hotlinking rules specific to uploads/jobpost.
 * 
 * @package    Simple_Job_Board
 * @subpackage Simple_Job_Board/includes
 * @author     PressTigers <support@presstigers.com>
 */

class Simple_Job_Board_Rewrite {

    /**
     * Constructor
     * @since   2.9.6   Added index.html file
     */
    public function __construct() {
        $uploads_dir = wp_upload_dir();
        $file = array(
            'basedir' => $uploads_dir['basedir'] . '/jobpost',
            'file' => 'index.html',
        );
        
        // Protect resume files from hotlinking
        if (!(wp_mkdir_p($file['basedir']) && file_exists(trailingslashit($file['basedir']) . $file['file']))) {
            $file_handle = @fopen(trailingslashit($file['basedir']) . $file['file'], 'w');
        }
    }
    /**
     * job_board_rewrite function.
     * 
     * @since   2.1.0
     * @since   2.9.6   Updated htaccess rules.
     */
    public function job_board_rewrite() {
        if (!function_exists('get_home_path')) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        // Home Path
        $root_path = get_home_path();
        $file_existing_permission = '';

        $uploads_dir = wp_upload_dir();

        // Rules for Download Files Forcefully
        $forcedownload_rule = "AddType application/octet-stream .pdf .txt\n";

        // Changing File to Writable Mode
        if (file_exists($root_path . '.htaccess') && !is_writable($root_path . '.htaccess')) {
            $file_existing_permission = substr(decoct(fileperms($root_path . '.htaccess')), -4);
            chmod($root_path . '.htaccess', 0777);
        }

        // Appending rules in .htaccess
        if (file_exists($root_path . '.htaccess') && is_writable($root_path . '.htaccess')) {

            $forcedownload_rule = explode("\n", $forcedownload_rule);

            // Anti-Hotlinking Rules Writing in .htaccess file
            if (!function_exists('insert_with_markers')) {
                require_once( ABSPATH . 'wp-admin/includes/misc.php' );
            }

            // Remove Hotlinking Rules
            insert_with_markers($root_path . '.htaccess', 'Hotlinking', '');

            /* Revert File Permission  */
            if (!empty($file_existing_permission)) {
                chmod( $root_path . '.htaccess', $file_existing_permission );
            }
        }

        $random_hash = bin2hex(random_bytes(20));
        update_option('sjb_htaccess_hash', sanitize_text_field($random_hash));

        $file = array(
            'basedir' => $uploads_dir['basedir'] . '/jobpost',
            'file' => '.htaccess',
            'str1' => 'RewriteEngine On',
            'str2' => 'RewriteCond %{QUERY_STRING} !^' . $random_hash . '$ [NC]',
            'str3' => 'RewriteRule ^.*$ - [R=403,L]',
        );
        
        // Protect resume files from hotlinking
        if (wp_mkdir_p($file['basedir']) && file_exists(trailingslashit($file['basedir']) . $file['file'])) {

            // Delete file if it exists already.
            wp_delete_file($file['basedir']. $file['file']);
            if ($file_handle = @fopen(trailingslashit($file['basedir']) . $file['file'], 'w')) {
                fwrite($file_handle, $file['str1'] . "\n");
                fwrite($file_handle, $file['str2'] . "\n");
                fwrite($file_handle, $file['str3'] . "\n");
                fclose($file_handle);
            }
        }else{
            if ($file_handle = @fopen(trailingslashit($file['basedir']) . $file['file'], 'w')) {
                fwrite($file_handle, $file['str1'] . "\n");
                fwrite($file_handle, $file['str2'] . "\n");
                fwrite($file_handle, $file['str3'] . "\n");
                fclose($file_handle);
            }
        }
        
    }

}

new Simple_Job_Board_Rewrite();