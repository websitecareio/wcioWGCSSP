<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wcioWGCSSPservice extends wcioWGCSSP {

      function __construct() {

            // Run this code if the gift card plugin is Woo Gift Card
            add_action( 'wcio_wgcssp_cron_sync_woo_service_pos', 'wcio_wgcssp_cron_sync_woo_service_pos');
            add_action( 'wcio_wgcssp_cron_sync_service_pos_woo', 'wcio_wgcssp_cron_sync_service_pos_woo');

      }


      //  Tjekker WooCommerce Gift Cards og opretter dem i ServicePOS hvis de ikke allerede findes. Hvis de findes i ServicePOS gør den ikke mere.
      function wcio_wgcssp_cron_sync_woo_service_pos() {

            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "posts"; // flexible Pdf coupons uses post table for giftcards

            // Fetch all gift cards from table
            $giftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE post_type = 'shop_coupon' ORDER BY ID DESC");

            foreach ( $giftCards as $card ) {

                  echo "<pre>";
                  print_r($card);
                  die();

            }


      }


      // Tjekker ServicePOS gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
      function wcio_wgcssp_cron_sync_service_pos_woo() {


      }

}