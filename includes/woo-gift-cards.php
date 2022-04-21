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
                        
                        // Add JS to cart page based on the plugin used
                        add_action( 'wp_footer', array($this, 'woo_gift_cards_checkout_script'), 9999 );
            			

		  		
      }
		
	
      /*
      * Create a new gift card with data from ServicePOS
      */
      function createNewGiftCard($data) {

            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "woocommerce_gc_cards";

            $recipient = $data["customer"]["email"] ?? get_option("admin_email");
            $amount = $data["amount"];
            $amountSpent = $data["amountspent"];

            $arrayWithData = array(
            "id" => NULL,
            "code" => $data["giftcardno"],
            "order_id" => "",
            "order_item_id" => "",
            "recipient" => $recipient,
            "redeemed_by" => "", // redeemed_by is the user id of the user (in woocommerce) wo owns this gift card. Is'nt needed and since gift card can be ought from POS , we cant fill this.
            "sender" => get_option("blogname"),
            "sender_email" => get_option("admin_email"), // sender_email is set to admin email due to missing email in ServicePOS
            "message" => $data["freetext"],
            "balance" => $amount,
            "remaining" => $amountSpent,
            "template_id" => "default",
            "create_date" => time(),
            "deliver_date" => time(),
            "delivered" => "0",
            "expire_date" => "0",
            "redeem_date" => "0", // If redeemed, then this
            "is_virtual" => "on",
            "is_active" => "on"
            );

            $result = $wpdb->insert("$table_prefix$WooCommerceGiftCardTableName", $arrayWithData);

            if ($result === false) {

                  $last_error = $wpdb->last_error;
                  $message = json_encode($arrayWithData);
                  wp_mail( get_option( 'admin_email' ), "Unable to create Woo Gift card - $last_error", $message );

            }

      }

      //  Tjekker WooCommerce Gift Cards og opretter dem i ServicePOS hvis de ikke allerede findes. Hvis de findes i ServicePOS gør den ikke mere.
      function wcio_wgcssp_cron_sync_woo_service_pos() {

            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "woocommerce_gc_cards";
		  
		// If its less than 5 minutes ago since last action, then allow this ro run again.
		$wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
		if($wcio_wgcssp_last_action > (time()-300)) { return; }

		
            $giftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName ORDER BY ID DESC");

            // Get ServicePOS giftcards
            $query = array("paginationPageLength" => 1000);
            $queryGiftcards = $this->call("GET", "/giftcards", $query);

            // Loop all WooCommerce giftcards
            foreach ( $giftCards as $card ) {
					
					  
		  // Update last action
		  update_option( 'wcio_wgcssp_last_action', time() );

				
                  $code = $card->code;
                  $sender = $card->sender;
                  $sender_email = $card->sender_email;
                  $balance = $card->balance; // This is initial balance
                  $remaining = $card->remaining; // This is remaining
                  $expire_date = $card->expire_date; // This is remaining
                  $redeem_date = $card->redeem_date; // This is remaining
                  $spent = $balance-$remaining; // This is spent

                        // Loop the giftcard data from before.
                        $cardFound = 0;
                        foreach($queryGiftcards["content"] AS $giftcard) {

                              // First check if the giftcard is available in ServicePOS variable
                              // We cannot break this loop until we auctually find it, because we have to check all cards
                              if($this->codeToServicePos($code) == $giftcard["giftcardno"]) {

                                    $cardFound = 1;
                                    
                                    // If gift card was found at ServicePOS.
                                    // Match values to make sure this isnt an outdated card.
                                    //$servicePOSAmount = $queryGiftcards["content"]["0"]["amount"]; // Overwridden to fix error.
                                    $servicePOSAmountRemaining = $giftcard["amount"]-$giftcard["amountspent"]; // Full amount minus amount spent gives remaining

                                          
                                    
                                          // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                                          // If the card in WooCommerce have been used more then the one in ServicePOS, then update ServicePOS
                                          if($remaining < $giftcard["amount"]-$giftcard["amountspent"]) {

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

                              // Now check if its dead in WooCommerce ( AUtomatically remove if more than a year old.
                              if($remaining == "0" && $expire_date<strtotime('-1 year') || $remaining == "0" && $redeem_date<strtotime('-1 year')) {

                                    // The card is empty and expire or redeem date is more than a year old. Please remove it.
                                    // CodeToWoo Not needed, this stems from Woo.
                                    $updateWooGiftCard = $wpdb->query($wpdb->prepare("DELETE FROM $table_prefix$WooCommerceGiftCardTableName WHERE code='$code'"));
                                    continue;

                              }
                              
                              // It wasnt dead, now create it in ServicePOS since its not there
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

      // Tjekker ServicePOS gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
      function wcio_wgcssp_cron_sync_service_pos_woo() {

		// If its less than 5 minutes ago since last action, then allow this ro run again.
		$wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
		if($wcio_wgcssp_last_action > (time()-300)) { return; }
		  
		  
      // THis function should check service POS and do the sme as the Woo function did.
      global $wpdb;
      $table_prefix = $wpdb->prefix;
      $WooCommerceGiftCardTableName = "woocommerce_gc_cards";
      
      // Sets the amount of gift cards per page.
      $query = array("paginationPageLength" => 10000);

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
                  "id" => $card["store"]["id"], //3241
                  "title" => $card["store"]["title"], //websitecare
                  "cityname" => $card["store"]["cityname"], //
                  "phone" => $card["store"]["phone"], //42 44 46 89
                  "zipcode" =>$card["store"]["zipcode"], //
                  "streetname" =>$card["store"]["streetname"], //
                  "streetno" =>$card["store"]["streetno"], //
                  "email" => $card["store"]["email"], //support@websitecare.io
                  "created" => $card["store"]["created"], //2021-11-22 14:23:55
            );

                  $customer = $card["store"]["id"]; //

                  $codeToWoo = $this->codeToWoo($code);
                  $wooGiftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE code='$codeToWoo' LIMIT 1");

                  // If gift card was found at ServicePOS.
                  if(count($wooGiftCards) == "1") {

                        // Match values to make sure this isnt an outdated card.
                        $wooRemaning = $wooGiftCards["0"]->remaining;

                        if($wooRemaning != $amountremaining) {

                              // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                              // If the card in WooCommerce have been used more then the one in ServicePOS, then update ServicePOS
                              if($wooRemaning < $amountremaining) {

                                    // If WooCommerce gift card have more spent on it, then we need to update ServicePos
                                    $query = $queryGiftcards;

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
                                    $updateWooGiftCard = $wpdb->query($wpdb->prepare("UPDATE $table_prefix$WooCommerceGiftCardTableName SET remaining=$remaining WHERE code='$codeToWoo'"));
                                    continue;

                              }

                        } else {

                              // Everything OK

                        }


            }  else if(count($wooGiftCards) == "0") {

                  // It wasnt found at WooCommerce.
                  // The card wasnt found in WooCommerce, we need to create it.
                  $time = time();

                  $newWooGiftCardRemaning = $amountremaining;
                  $newWooGiftCard = $wpdb->query($wpdb->prepare("INSERT INTO $table_prefix$WooCommerceGiftCardTableName (
                  code, order_id, order_item_id, redeemed_by, recipient, sender, sender_email, message, balance, remaining,  template_id, create_date, deliver_date, delivered, expire_date, redeem_date, is_virtual,  is_active
                  ) values (
                  '$codeToWoo', '0', '0', '0',  '0', '',  '', '',  '$amount', '$newWooGiftCardRemaning', 'default', '$time', '0', '0', '0', '0', 'on',  'on'
                  )"));
                  continue;

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


      /**
      * Adds JS to the footer is the plugin is set to Woo Gift Card
      */
      function woo_gift_cards_checkout_script() {

            global $wp; 
            if ( is_checkout() && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ) {
                  echo '<script>

                  function pad (str, max) {
                        str = str.toString();
                        return str.length < max ? pad(str + "X", max) : str;
                  }

                  jQuery(function($) {
                        $("input[name=wc_gc_cart_code]").keyup(function() {
                              var foo = $(this).val().split("-").join(""); // remove hyphens
                              if (foo.length > 0) {
                                    foo = foo.match(new RegExp(".{1,4}", "g")).join("-");
                              }
                              
                              value = $(this).val();
                              if(value.length == "14")  {
                                    var newValue = value + "-";
                              // $(this).val(newValue);
                                    $(this).val(pad(newValue, 19));
                                    
                              } else {
                                    $(this).val(foo);
                              }
                              
                        });
                  });
                  </script>';
            }
            
      }





}

?>
