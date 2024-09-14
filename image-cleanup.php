<?php
/*
Plugin Name: Unattached Image Cleanup
Description: Deletes unattached images (excluding 'logo-1a.webp') in batches of 50 every 5 minutes.
Version: 1.1
Author: index SYSTEMS
*/

// Schedule the cron job upon plugin activation
function uic_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'uic_delete_unattached_images_event' ) ) {
        wp_schedule_event( time(), 'five_minutes', 'uic_delete_unattached_images_event' );
    }
}
register_activation_hook( __FILE__, 'uic_schedule_cleanup' );

// Clear the cron job upon plugin deactivation
function uic_clear_scheduled_cleanup() {
    wp_clear_scheduled_hook( 'uic_delete_unattached_images_event' );
}
register_deactivation_hook( __FILE__, 'uic_clear_scheduled_cleanup' );

// Add custom interval for every 5 minutes
function uic_custom_cron_intervals( $schedules ) {
    $schedules['five_minutes'] = array(
        'interval' => 300,  // 5 minutes in seconds
        'display'  => __( 'Every 5 Minutes' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'uic_custom_cron_intervals' );

// Function to delete unattached images (50 at a time), excluding 'logo-1a.webp'
function uic_delete_unattached_images() {
    global $wpdb;

    // Query for the first 50 unattached image attachments (post_mime_type starting with 'image/')
    $unattached_images = $wpdb->get_results( "
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
        AND post_mime_type LIKE 'image/%'  -- Only images
        AND post_parent = 0  -- Unattached
        AND ID NOT IN (
            SELECT ID FROM {$wpdb->posts} WHERE post_name = 'logo' -- Exclude 'logo-1a.webp'
        )
        LIMIT 250
    " );

    // If there are unattached images
    if ( ! empty( $unattached_images ) ) {
        foreach ( $unattached_images as $image ) {
            $attachment_id = $image->ID;

            // Get the file path
            $file_path = get_attached_file( $attachment_id );

           

            // Delete the image from the database and the media library
            wp_delete_attachment( $attachment_id, true );

            // Delete the file physically if it exists
            if ( file_exists( $file_path ) ) {
                unlink( $file_path );
            }

            // Log deleted image (optional)
            error_log( "Deleted unattached image ID: $attachment_id, File: $file_path" );
        }
    } else {
        error_log( "No more unattached images found." );
    }
}
add_action( 'uic_delete_unattached_images_event', 'uic_delete_unattached_images' );
