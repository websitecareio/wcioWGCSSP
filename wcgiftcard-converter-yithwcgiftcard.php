<?php
die("Not active");
// Convert WooCommerce Gift Cards to YITH WooCommerce giftcards
// For use with c1st integration
// Path to the wp-load.php file
$wp_load_path = 'wp-load.php';

if ( ! file_exists( $wp_load_path ) ) {
    die( 'Error: Cannot find the wp-load.php file.' );
}

// Load the bare minimum of WordPress
require_once( $wp_load_path );


global $wpdb;

// Replace 'wp_' with your table prefix if it's different
$table_name = $wpdb->prefix . 'woocommerce_gc_cards';

$results = $wpdb->get_results( "SELECT * FROM {$table_name}" );

if ( $results ) {
    foreach ( $results as $row ) {
        // Access the columns like this: $row->column_name
        echo "Card Code: " . $row->code . "<br>";
        echo "Card Remaining: " . $row->remaining . "<br>";

        // if balance emtpy, do not convert.
        if($row->balance != 0) {

                // Not empty, make the code to convert
                create_gift_card_post( str_replace("-","", $row->code), $row->remaining);

        }


    }
} 


// Function to create a new gift card post
function create_gift_card_post( $code, $balance ) {
        $gift_card_data = array(
            'post_title'  => $code,
            'post_status' => 'publish',
            'post_type'   => 'gift_card',
        );

        $gift_card_id = wp_insert_post( $gift_card_data );

        if ( ! is_wp_error( $gift_card_id ) ) {
            // Set custom fields for the gift card post
            update_post_meta( $gift_card_id, '_ywgc_amount_total', $balance );  // This is initial balance
            update_post_meta( $gift_card_id, '_ywgc_balance_total', $balance );
        } else {
            echo "Error creating gift card: " . $gift_card_id->get_error_message();
        }

    }
