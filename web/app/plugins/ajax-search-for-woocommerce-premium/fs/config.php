<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Create a helper function for easy SDK access.
function dgoraAsfwFs()
{
    global  $dgoraAsfwFs ;
    
    if ( !isset( $dgoraAsfwFs ) ) {
        // Include Freemius SDK.
        require_once dirname( __FILE__ ) . '/lib/start.php';
        $dgoraAsfwFs = fs_dynamic_init( array(
            'id'             => '700',
            'slug'           => 'ajax-search-for-woocommerce',
            'type'           => 'plugin',
            'public_key'     => 'pk_f4f2a51dbe0aee43de0692db77a3e',
            'is_premium'     => true,
            'premium_suffix' => 'Pro',
            'has_addons'     => false,
            'has_paid_plans' => true,
            'menu'           => array(
            'slug'    => 'dgwt_wcas_settings',
            'support' => false,
        ),
            'is_live'        => true,
        ) );
    }
    
    return $dgoraAsfwFs;
}

// Init Freemius.
dgoraAsfwFs();
// Signal that SDK was initiated.
do_action( 'dgoraAsfwFs_loaded' );
dgoraAsfwFs()->add_filter( 'plugin_icon', function () {
    return dirname( dirname( __FILE__ ) ) . '/assets/img/logo-128.png';
} );
// Uninstall
if ( dgoraAsfwFs()->is__premium_only() ) {
    dgoraAsfwFs()->add_action( 'after_uninstall', function () {
        global  $wpdb ;
        /* ----------------------
         * WIPE DATABASE TABLES
         * --------------------- */
        $pluginTables = array();
        $tables = $wpdb->get_results( "SHOW TABLES" );
        if ( !empty($tables) && is_array( $tables ) ) {
            foreach ( $tables as $table ) {
                if ( !empty($table) && is_object( $table ) ) {
                    foreach ( $table as $tableName ) {
                        if ( !empty($tableName) && is_string( $tableName ) && strpos( $tableName, 'dgwt_wcas_' ) !== false ) {
                            $pluginTables[] = $tableName;
                        }
                    }
                }
            }
        }
        foreach ( $pluginTables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
        /* ----------------------
         * WIPE SETTINGS
         * --------------------- */
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dgwt_wcas_indexer_last_build%'" ) );
        $options = array(
            'dgwt_wcas_schedule_single',
            'dgwt_wcas_schedule_last_data',
            'dgwt_wcas_version_pro',
            'dgwt_wcas_inv_index_db_version',
            'dgwt_wcas_index_db_version',
            'dgwt_wcas_tax_index_db_version',
            'dgwt_wcas_var_index_db_version',
            'dgwt_wcas_ven_index_db_version',
            'dgwt_wcas_settings_show_advanced',
            'dgwt_wcas_db_json_support',
            'dgwt_wcas_images_regenerated',
            'dgwt_wcas_debug_search_logs',
            'dgwt_wcas_settings_version',
            'dgwt_wcas_settings_version_pro',
            'dgwt_wcas_indexer_prepare_process_exist',
            'dgwt_wcas_activation_date',
            'dgwt_wcas_dismiss_review_notice',
            'dgwt_wcas_indexer_last_failure_data',
            'dgwt_wcas_auto_send_indexer_failure_reports',
            'dgwt_wcas_dismiss_indexer_failure_notices'
        );
        foreach ( $options as $option ) {
            delete_option( $option );
        }
        /* ----------------------
         * WIPE TRANSIENTS
         * --------------------- */
        $transients = array(
            'dgwt_wcas_indexer_details_display',
            'dgwt_wcas_searchable_custom_fields',
            'dgwt_wcas_troubleshooting_async_results',
            'dgwt_wcas_indexer_debug',
            'dgwt_wcas_indexer_debug_scope'
        );
        foreach ( $transients as $transient ) {
            delete_transient( $transient );
        }
        if ( is_multisite() ) {
            foreach ( get_sites() as $site ) {
                
                if ( is_numeric( $site->blog_id ) && $site->blog_id > 1 ) {
                    $table = $wpdb->prefix . $site->blog_id . '_' . 'options';
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE 'dgwt_wcas_indexer_last_build%'" ) );
                    foreach ( $options as $option ) {
                        $wpdb->delete( $table, array(
                            'option_name' => $option,
                        ) );
                    }
                    foreach ( $transients as $transient ) {
                        $wpdb->delete( $table, array(
                            'option_name' => '_transient_' . $transient,
                        ) );
                        $wpdb->delete( $table, array(
                            'option_name' => '_transient_timeout_' . $transient,
                        ) );
                    }
                }
            
            }
        }
        /* ----------------------
         * WIPE FILES (DEPRECATED)
         * --------------------- */
        $upload_dir = wp_upload_dir();
        
        if ( !empty($upload_dir['basedir']) ) {
            $path = $upload_dir['basedir'] . '/wcas-search/';
            
            if ( file_exists( $path ) ) {
                $index = $path . 'products.index';
                if ( file_exists( $index ) ) {
                    unlink( $index );
                }
                rmdir( $path );
            }
        
        }
    
    } );
}