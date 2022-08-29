<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Extend the class with our serviceclass
class wcioWGCSSPservice extends wcioWGCSSP {
      
      public  $token;
      
      public function __construct() {     

            $this->token = get_option("wc_wciowgcssp_token"); // ServicePOS token

                        // Run this code if the gift card plugin is Woo Gift Card
                        add_action( 'wcio_wgcssp_cron_sync_woo_service_pos', array($this, 'wcio_wgcssp_cron_sync_woo_service_pos'));
                        add_action( 'wcio_wgcssp_cron_sync_service_pos_woo', array($this, 'wcio_wgcssp_cron_sync_service_pos_woo'));
                        		  		
      }
		
	
      //  Tjekker WooCommerce Gift Cards og opretter dem i ServicePOS hvis de ikke allerede findes. Hvis de findes i ServicePOS gør den ikke mere.
      function wcio_wgcssp_cron_sync_woo_service_pos() {

            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "posts";
		  
		// If its less than 5 minutes ago since last action, then dont? allow this ro run again.
		$wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
		if($wcio_wgcssp_last_action > (time()-300)) { return; }
            
            // Get Gift cards from database
            $wooGiftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE post_type = 'gift_card' ORDER BY ID DESC");
            
            // Get ServicePOS giftcards
            // Sets the amount of gift cards per page.
            $paginationPageLength = 250; // 250 is new servicepos limit in future releases

            // Get the count
            $query = array("paginationPageLength" => 1, "paginationStart" => 0); // Start from page 1 (0)
            $giftcards = $this->call("GET", "/giftcards", $query);
           
            // Get amount of giftcards from ServicePOS
            $countGiftcards = $giftcards["count"];

            // Now loop the calls until we have looped though all the giftcards
            // $x is the pages.   
            $queryGiftcards = array("content" => array(), "count" => $countGiftcards);
            for($x = 0; $x <= ceil($countGiftcards/$paginationPageLength); $x++) {

                  // We need to loop all pages
                  // Now we do the query with the paging.
                  $query = array("paginationPageLength" => $paginationPageLength, "paginationStart" => $paginationPageLength*$x); // Start from page 1 (0)
                  $queryGiftcards = $this->call("GET", "/giftcards", $query);
                  //$queryGiftcards["content"][] = $tmpContent["content"];

            // Loop all WooCommerce giftcards
                  foreach ( $wooGiftCards as $card ) {
                                    
                  // Update last action
                  update_option( 'wcio_wgcssp_last_action', time() );

                              
                        $code = $card->post_title; // YITH
                        $sender = ""; // We try without this at fist
                        $sender_email = ""; // We try without this at fist

                        $balance = get_post_meta( $card->ID, "_ywgc_amount_total", true );  // This is initial balance
                        $remaining = get_post_meta( $card->ID, "_ywgc_balance_total", true ); // This is remaining
                        $expire_date = ""; // We try without this at fist
                        $redeem_date = ""; // We try without this at fist
                        $spent = $balance-$remaining; // This is spent

                              // Loop the giftcard data from before.
                              $cardFound = 0;


                              foreach($queryGiftcards["content"] AS $giftcard) {

                                    // First check if the giftcard is available in ServicePOS variable
                                    // We cannot break this loop until we auctually find it, because we have to check all cards
                                    if($this->codeToServicePos($code) == $giftcard["giftcardno"]) {

                                          $cardFound = 1;
                                          
                                          die("here2");
                                          // If gift card was found at ServicePOS.
                                          // Match values to make sure this isnt an outdated card.
                                          //$servicePOSAmount = $queryGiftcards["content"]["0"]["amount"]; // Overwridden to fix error.
                                          $servicePOSAmountRemaining = $giftcard["amount"]-$giftcard["amountspent"]; // Full amount minus amount spent gives remaining

                                                // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                                                // If the card in WooCommerce have been used more then the one in ServicePOS, then update ServicePOS
                                                if($remaining < ($giftcard["amount"]-$giftcard["amountspent"])) {

                                                      // If WooCommerce gift card ave more spent on it, then we need to update ServicePos
                                                      // Now updat the amount spent.
                                                      $servicePOSAmountSpent = $giftcard["amount"]-$remaining; // Full amount minus the remaining from wooCommerce gives the amount spent
                                                      $giftcardData = [
                                                      'amountspent' => (int)$servicePOSAmountSpent,
                                                      'type' => 'giftcard',
                                                      ];

                                                      if($remaining != $servicePOSAmountRemaining) {

                                                      // Update giftcard in servicePOS
                                                      $updateServicePOSGiftcard = $this->call("PUT", "/giftcards/".$giftcard["id"]."", ['content' => $giftcardData]);

                                                      continue;

                                                } else {

                                                      // ServicePOs have most spent, then update WooCommerce
                                                      // CodeToWoo Not needed, this stems from Woo.
                                                      $remaining = $servicePOSAmountRemaining;
                                                      $updateWooGiftCard = $wpdb->query($wpdb->prepare("UPDATE $table_prefix$WooCommerceGiftCardTableName SET remaining='$remaining' WHERE code='$code'"));
                                                      continue;

                                                }

                                          }                               

                                    } 

                              }     

                              // IF card wasnt found in servicepos query
                              if($cardFound == 0) {
                                    
                                    // It wasnt dead, now create it in ServicePOS since its not there. 
                                    $giftcard = [
                                    "giftcardno" => $this->codeToServicePos($code),
                                    "amount" => (int)$balance, 
                                    "type" => "giftcard",
                                    "customer" => array(
                                          "name" => $sender,
                                          "email" => $sender_email,
                                    )
                                    ];

                                    $createServicePOSGiftcard = $this->call("POST", "/giftcards",  ['content' => $giftcard]);
                                    continue;
                        
                              
                              }


               }

            }

      }

      // Tjekker ServicePOS gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
      function wcio_wgcssp_cron_sync_service_pos_woo() {

		// If its less than 5 minutes ago since last action, then allow this ro run again.
		$wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
		//if($wcio_wgcssp_last_action > (time()-300)) { return; }
		  
		  
      // THis function should check service POS and do the sme as the Woo function did.
      global $wpdb;
      $table_prefix = $wpdb->prefix;
      $WooCommerceGiftCardTableName = "posts";
      
      // Sets the amount of gift cards per page.
      $paginationPageLength = 250; // 250 is new servicepos limit in future releases

      // Get the count
      $query = array("paginationPageLength" => 1, "paginationStart" => 0); // Start from page 1 (0)
      $giftcards = $this->call("GET", "/giftcards", $query);

      // Get amount of giftcards from ServicePOS
      $countGiftcards = $giftcards["count"];
      
      // Now loop the calls until we have looped though all the giftcards
      // $x is the pages. 
            for($x = 0; $x <= ceil($countGiftcards/$paginationPageLength); $x++) {

                  // Now we do the query with the paging.
                  $query = array("paginationPageLength" => $paginationPageLength, "paginationStart" => $paginationPageLength*$x); // Start from page 1 (0)
                  $giftcards = $this->call("GET", "/giftcards", $query);

                  foreach ( $giftcards["content"] as $key => $card ) {
                        
                        // Update last action
                        update_option( 'wcio_wgcssp_last_action', time() );

                        $id = $card["id"]; //47021
                        $giftcardno = $card["giftcardno"]; //724503989151
                        $code = $giftcardno; //724503989151
                        $amount = $card["amount"]; //49
                        $amountspent = $card["amountspent"]; //0

                        $amountremaining = $amount-$amountspent; //0

                        $createddate = $card["createddate"]; //2021-11-22 22:03:39
                        $expirationdate = $card["expirationdate"]; //2023-11-22 22:03:39
                        $paymentid = $card["paymentid"]; //3534778
                        $type = $card["type"]; //giftcard
                        $vat = $card["vat"]; //25
                        $productid = $card["productid"]; //7987676
                        $productno = $card["productno"]; //giftcard
                        $expired = $card["expired"]; //
                        $store = array (
                              "id" => $card["store"]["id"] ?? "", //3241
                              "title" => $card["store"]["title"] ?? "", //websitecare
                              "cityname" => $card["store"]["cityname"] ?? "", //
                              "phone" => $card["store"]["phone"] ?? "", //42 44 46 89
                              "zipcode" =>$card["store"]["zipcode"] ?? "", //
                              "streetname" =>$card["store"]["streetname"] ?? "", //
                              "streetno" =>$card["store"]["streetno"] ?? "", //
                              "email" => $card["store"]["email"] ?? "", //support@websitecare.io
                              "created" => $card["store"]["created"] ?? "", //2021-11-22 14:23:55
                        );

                        $customer = $card["store"]["id"]; //

                        $codeToWoo = $this->codeToWoo($code);
                        $wooGiftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE post_type = 'gift_card' AND post_title = '$codeToWoo' LIMIT 1");
                                                

                        // If gift card was found in WooCommerce.
                        if(count($wooGiftCards) == "1") {

                              $balance = (int)get_post_meta( $wooGiftCards["0"]->ID, "_ywgc_amount_total", true ) ?? 0;  // This is initial balance
                              $remaining = (int)get_post_meta( $wooGiftCards["0"]->ID, "_ywgc_balance_total", true ) ?? 0; // This is remaining

                              $spent = $balance-$remaining; // This is spent
                              
                              // Match values to make sure this isnt an outdated card.
                              $wooRemaning = $remaining;

                              if($wooRemaning != $amountremaining) {

                                    // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                                    // If the card in WooCommerce have been used more then the one in ServicePOS, then update ServicePOS
                                    if($wooRemaning < $amountremaining) {

                                          // If WooCommerce gift card have more spent on it, then we need to update ServicePos
                                          $giftcard = [
                                          'amountspent' => $amount-$wooRemaning,
                                          'type' => 'giftcard',
                                          ];

                                          // Update giftcard in servicePOS
                                          $updateServicePOSGiftcard = $this->call("PUT", "/giftcards/".$id, ['content' => $giftcard]);
                                          continue;


                                    } else {

                                          // ServicePOs have most spent, then update WooCommerce
                                          // CodeToWoo Not needed, this stems from Woo.
                                          $remaining = $amountremaining;
                                          update_post_meta( $wooGiftCards["0"]->ID, "_ywgc_balance_total", $remaining); // This is remaining
                                          continue;

                                    }

                              } else {

                                    // Everything OK

                              }


                        } else if(count($wooGiftCards) == "0") {

                              
                              // Skip if its zero, we dont want empty cards in the system.
                              if($amountremaining == 0) { continue; }

                              // It wasnt found at WooCommerce.
                              // The card wasnt found in WooCommerce, we need to create it.
                              $time = time();

                              $newWooGiftCardRemaning = $amountremaining;
  
                              // Create post object
                              $my_post = array(
                                    'post_title'    => wp_strip_all_tags( $codeToWoo ),
                                    'post_content'  => "",
                                    'post_status'   => 'publish',
                                    'post_author'   => 1,
                                    'post_type'     => "gift_card"
                              );
                              
                              // Insert the post into the database
                              $postID = wp_insert_post( $my_post );

                              get_post_meta( $postID, "_ywgc_amount_total", $amount );  // This is initial balance
                              update_post_meta( $postID, "_ywgc_balance_total", $newWooGiftCardRemaning); // This is remaining
                              continue;

                        }

                  }

            }

      }

      // Skal bruges for alle kort der stammer fra Woo og som skal til ServicePOS
      function codeToServicePos($code) {

            // Input XXXX-XXXX-XXXX-XXXX
            // Output: 724503989151  (12 char)()
            $code = str_replace("XXXX", "", $code); // removes X s that have been added to match format.
            $code = str_replace("-", "", $code); // Removes - s that have been added to match format.
          return $code; // outputs a ServicePOS gift card.

      }

      // Skal bruges for alle kort der stammer fra ServicePOS og som skal til Woo
      function codeToWoo($code) {

            // Input: 724503989151  (12 char)
            // Output XXXX-XXXX-XXXX-XXXX
            $number = str_pad($code, 16, "X", STR_PAD_RIGHT);
            $str = chunk_split($number, 4, '-');
            $str = substr($str, 0, -1);;
            return $str;

      }

}

?>
