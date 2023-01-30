<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
      exit;
}


// Extend the class with our serviceclass
class wcioWGCSSPservice extends wcioWGCSSP
{

      public  $token;

      public function __construct()
      {

            $this->token = get_option("wc_wciowgcssp_token"); // Customers 1st. token

            // Run this code if the gift card plugin is Woo Gift Card
            add_action('wcio_wgcssp_cron_sync_woo_service_pos', array($this, 'wcio_wgcssp_cron_sync_woo_service_pos'));
            add_action('wcio_wgcssp_cron_sync_service_pos_woo', array($this, 'wcio_wgcssp_cron_sync_service_pos_woo'));
      }


      //  Tjekker WooCommerce Gift Cards og opretter dem i Customers 1st. hvis de ikke allerede findes. Hvis de findes i Customers 1st. gør den ikke mere.
      function wcio_wgcssp_cron_sync_woo_service_pos()
      {

            // Start run
            $this->logging("Started <strong>wcio_wgcssp_cron_sync_woo_service_pos</strong> function.", "");

            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "posts";

            // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
            $wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
            if ($wcio_wgcssp_last_action > (time() - 300)) {
                  $this->logging("Stopped run because it is less than 5 minutes ago since last run.", "");
                  return;
            }

            // Get Gift cards from database
            $wooGiftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE post_type = 'gift_card' ORDER BY ID DESC");

            // Get ServicePOS giftcards
            // Sets the amount of gift cards per page.
            $paginationPageLength = 249; // 250 is new Customers 1st. limit in future releases

            // Get the count
            $query = array("paginationPageLength" => 1, "paginationStart" => 0, "scope" => "sharegiftcards"); // Start from page 1 (0)
            $giftcards = $this->call("GET", "/giftcards", $query);
            // Get amount of giftcards from Customers 1st.
            $countGiftcards = $giftcards["count"];
            $this->logging("Called GET /giftcards with parameters <strong>\"paginationPageLength\" => 1, \"paginationStart\" => 0</strong> and found <strong>$countGiftcards</strong> to be processed.", "");

            // Make the full list of giftcards from Customers 1st.
            $pagelimit = ceil($countGiftcards / $paginationPageLength);
            $servicePOSGiftcards = array();
            for ($x = 0; $x <= $pagelimit; $x++) {

                  // We need to loop all pages
                  // Now we do the query with the paging.
                  $paginationStart = $paginationPageLength * $x;
                  $query = array("paginationPageLength" => $paginationPageLength, "paginationStart" => $paginationStart, "scope" => "sharegiftcards"); // Start from page 1 (0)
                  $queryGiftcards = $this->call("GET", "/giftcards", $query);

                  // Log the data. We cannot use $giftcards in the log data. For some reason it cannot save it.. DB character limit possible. Using MEDIUMTEXT or LONGTEXT might fix it.
                  $this->logging("Called GET /giftcards with parameters <strong>\"paginationPageLength\" => $paginationPageLength, \"paginationStart\" => $paginationStart</strong><br><strong>Count:</strong> " . count($queryGiftcards["content"]) . "", "");

                  // Merge all giftcards from Customers 1st. into one array.
                  $servicePOSGiftcards = array_merge($servicePOSGiftcards, $queryGiftcards["content"]);
            }

            // Loop all WooCommerce giftcards then in each giftcard we loop Customers 1st. to find it.
            foreach ($wooGiftCards as $card) {

                  // Update last action
                  update_option('wcio_wgcssp_last_action', time());

                  $code = $card->post_title; // YITH
                  $balance = (int)get_post_meta($card->ID, "_ywgc_amount_total", true);  // This is initial balance
                  $remaining = (int)get_post_meta($card->ID, "_ywgc_balance_total", true); // This is remaining

                  $searchGiftCardRaw = $this->search($servicePOSGiftcards, 'giftcardno', $this->codeToServicePos($code));
                  $searchGiftCardCount = count($searchGiftCardRaw);

                  if ($searchGiftCardCount > 0) {

                        $giftcard = $searchGiftCardRaw[0];

                        // First check if the giftcard is available in Customers 1st. variable
                        // We cannot break this loop until we auctually find it, because we have to check all cards
                        if ($this->codeToServicePos($code) == $giftcard["giftcardno"] && $giftcard["giftcardno"] != "") {

                              // If gift card was found at ServicePOS.
                              // Match values to make sure this isnt an outdated card.
                              //$servicePOSAmount = $queryGiftcards["content"]["0"]["amount"]; // Overwridden to fix error.
                              $servicePOSAmountRemaining = $giftcard["amount"] - $giftcard["amountspent"]; // Full amount minus amount spent gives remaining

                              // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                              // If the card in WooCommerce have been used more then the one in Customers 1st., then update Customers 1st.
                              if ($remaining < $servicePOSAmountRemaining) {

                                    // If WooCommerce gift card ave more spent on it, then we need to update Customers 1st.
                                    // Now updat the amount spent.
                                    $servicePOSAmountSpent = $giftcard["amount"] - $remaining; // Full amount minus the remaining from wooCommerce gives the amount spent
                                    $giftcardData = [
                                          'amountspent' => (int)$servicePOSAmountSpent
                                    ];

                                    if ($remaining != $servicePOSAmountRemaining) {

                                          // Update giftcard in Customers 1st.
                                          $updateServicePOSGiftcard = $this->call("PUT", "/giftcards/" . $giftcard["id"] . "", ['content' => $giftcardData]);

                                          // Update giftcard in Customers 1st.
                                          $this->logging("Called PUT /giftcards/" . $giftcard["id"] . " with content <strong>" . json_encode($giftcardData) . "</strong><br>
                                                      Giftcard: " . $giftcard["id"] . " will be updated in ServicePOS with this new value for AMOUNTSPENT: <strong>$servicePOSAmountSpent</strong>.<br>
                                                      Because remaining ($remaining) is LOWER than servicePOSAmountRemaining (" . $giftcard["amount"] . " - " . $giftcard["amountspent"] . ").<br>
                                                      Old value in ServicePOS for AMOUNTSPENT: <strong>$servicePOSAmountRemaining (" . $giftcard["amount"] . " - " . $giftcard["amountspent"] . ")</strong>", $code);
                                          continue;

                                    } else {

                                          // This code does nothing other than updating something with the axact same value...
                                          // Customers 1st. have most spent, then update WooCommerce
                                          // CodeToWoo Not needed, this stems from Woo.
                                          $updateWooGiftCard = $wpdb->query($wpdb->prepare("UPDATE $table_prefix$WooCommerceGiftCardTableName SET remaining='$servicePOSAmountRemaining' WHERE code='$code'"));
                                          // Update woo
                                          $this->logging("Giftcard: " . $code . " will be updated in WooCommerce with this new remaining value: $servicePOSAmountRemaining<br>
                                                      This happens because the remaning value WooCommerce value: $remaining is equal to ServicePOS value: $servicePOSAmountRemaining and needs to be updated", $code);
                                          continue;

                                    }
                              }
                        }

                  } else if ($searchGiftCardCount == 0) { // IF card wasnt found in Customers 1st. query

                        // It wasnt dead, now create it in Customers 1st. since its not there. 
                        // First check what the balance auctually is.
                        if ($balance == 0) {

                              // The balance was 0, most likely due to the card wasnt created with a balance, then we need to use remaining as balance and fix the card.
                              $giftcardAmount = $remaining;

                        } else {

                              $giftcardAmount = $balance;
                        }

                        // Make sure we dont send empty cards
                        if ($this->codeToServicePos($code) == "") {
                              continue;
                        }

                        $giftcard = [
                              "giftcardno" => $this->codeToServicePos($code),
                              "amount" => (int)$giftcardAmount,
                              "type" => "giftcard",
                              "customer" => array(
                                    "name" => "",
                                    "email" => "",
                              )
                        ];

                        $createServicePOSGiftcard = $this->call("POST", "/giftcards",  ['content' => $giftcard]);
                        $this->logging("Giftcard: " . $this->codeToServicePos($code) . " will be created in ServicePOS with this value: <strong>$giftcardAmount</strong>. Content: <strong>" . json_encode($giftcard) . "</strong>.<br>", $this->codeToServicePos($code));
                        continue;

                  }
            }

            $this->logging("Stopped <strong>wcio_wgcssp_cron_sync_woo_service_pos</strong> function.", "");

      }

      // Tjekker Customers 1st. gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
      // THis function does ONLY check Customers 1st., not WooCommerce.
      function wcio_wgcssp_cron_sync_service_pos_woo()
      {

            // Start run
            $this->logging("Started <strong>wcio_wgcssp_cron_sync_service_pos_woo</strong> function.", "");
            // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
            $wcio_wgcssp_last_action = get_option('wcio_wgcssp_last_action');
            if ($wcio_wgcssp_last_action > (time() - 300)) {
                  $this->logging("Stopped run because it is less than 5 minutes ago since last run.", "");
                  return;
            }


            // THis function should check service POS and do the sme as the Woo function did.
            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $WooCommerceGiftCardTableName = "posts";

            // Sets the amount of gift cards per page.
            $paginationPageLength = 249; // 250 is new Customers 1st. limit in future releases. Settings 1 below.

            // Get the count
            $query = array("paginationPageLength" => 1, "paginationStart" => 0, "scope" => "sharegiftcards"); // Start from page 1 (0)
            $giftcards = $this->call("GET", "/giftcards", $query);
            // Get amount of giftcards from ServicePOS
            $countGiftcards = $giftcards["count"];
            $this->logging("Called GET /giftcards with parameters <strong>\"paginationPageLength\" => 1, \"paginationStart\" => 0</strong> and found <strong>$countGiftcards</strong> to be processed.", "");


            // Now loop the calls until we have looped though all the giftcards
            // $x is the page. 
            $pagelimit = ceil($countGiftcards / $paginationPageLength);
            for ($x = 0; $x <= $pagelimit; $x++) {

                  // Now we do the query with the paging.
                  $paginationStart = $paginationPageLength * $x;

                  $query = array("paginationPageLength" => $paginationPageLength, "paginationStart" => $paginationStart, "scope" => "sharegiftcards"); // Start from page 1 (0)
                  $giftcards = $this->call("GET", "/giftcards", $query);

                  // Log the data. We cannot use $giftcards in the log data. For some reason it cannot save it.. DB character limit possible. Using MEDIUMTEXT or LONGTEXT might fix it.
                  $this->logging("Called GET /giftcards with parameters <strong>\"paginationPageLength\" => $paginationPageLength, \"paginationStart\" => $paginationStart</strong><br><strong>Count:</strong> " . count($giftcards["content"]) . "", "");

                  // Loops all Customers 1st. giftcard
                  foreach ($giftcards["content"] as $key => $card) {


                        // Update last action
                        update_option('wcio_wgcssp_last_action', time());

                        $id = $card["id"]; //47021
                        $giftcardno = $card["giftcardno"]; //724503989151
                        $code = $giftcardno; //724503989151
                        $amount = $card["amount"]; //49
                        $amountspent = $card["amountspent"]; //0

                        $amountremaining = $amount - $amountspent; //0

                        // Make woo data format of giftcard and search for the giftcard
                        $codeToWoo = $this->codeToWoo($code);
                        $wooGiftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName WHERE post_type = 'gift_card' AND post_title = '$codeToWoo' LIMIT 1");

                        // If gift card was found in WooCommerce and we verified it was the same card
                        $wooGiftCardNumber = $wooGiftCards["0"]->post_title;
                        if (count($wooGiftCards) == "1" && $wooGiftCardNumber == $codeToWoo && $code != "") {

                              $balance = (int)get_post_meta($wooGiftCards["0"]->ID, "_ywgc_amount_total", true) ?? 0;  // This is initial balance
                              $remaining = (int)get_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", true) ?? 0; // This is remaining

                              $spent = $balance - $remaining; // This is spent

                              // Match values to make sure this isnt an outdated card.
                              $wooRemaning = $remaining;

                              if ($wooRemaning != $amountremaining) {

                                    // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                                    // If the card in WooCommerce have been used more then the one in Customers 1st., then update Customers 1st.
                                    if ($wooRemaning < $amountremaining) {

                                          // If WooCommerce gift card have more spent on it, then we need to update Customers 1st.
                                          $newAmount = $amount - $wooRemaning;
                                          $giftcard = [
                                                'amountspent' => $newAmount
                                          ];

                                          // Update giftcard in Customers 1st.
                                          $this->call("PUT", "/giftcards/" . $id, ['content' => $giftcard]);
                                          $this->logging("Called PUT /giftcards/$id with content <strong>" . json_encode($giftcard) . "</strong><br>
                                          Giftcard: $id will be updated in Customers 1st. with this new value: <strong>$newAmount</strong>.<br>
                                          Because wooRemaning ($wooRemaning) is LOWER than amountremaining ($amountremaining).<br>
                                          Old value in Customers 1st.: <strong>$amountremaining ($amount-$amountspent)</strong>", $wooGiftCardNumber);
                                          continue;

                                    } else {

                                          // Customers 1st. have most spent, then update WooCommerce
                                          // CodeToWoo Not needed, this stems from Woo.
                                          $remaining = $amountremaining;
                                          update_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", $remaining); // This is remaining
                                          $this->logging("Giftcard: " . $wooGiftCards["0"]->ID . " will be updated in WooCommerce with this new value: <strong>$remaining</strong>.<br>
                                          Because wooRemaning ($wooRemaning) is HIGHER than amountremaining ($amountremaining).<br>
                                          Old value in WooCommerce: <strong>$spent = ($balance-$remaining)</strong>", $wooGiftCardNumber);
                                          continue;

                                    }
                              }

                              // Giftcard wasnt found in WooCommerce      
                        } else if (count($wooGiftCards) == "0") {

                              // Skip if its zero, we dont want empty cards in the system.
                              if ($amountremaining == 0) {
                                    continue;
                              }

                              // It wasnt found at WooCommerce.
                              // The card wasnt found in WooCommerce, we need to create it.
                              $time = time();

                              $newWooGiftCardRemaning = $amountremaining;

                              // Create post object
                              $my_post = array(
                                    'post_title'    => wp_strip_all_tags($codeToWoo),
                                    'post_content'  => "",
                                    'post_status'   => 'publish',
                                    'post_author'   => 1,
                                    'post_type'     => "gift_card"
                              );

                              // Insert the post into the database
                              $postID = wp_insert_post($my_post);

                              get_post_meta($postID, "_ywgc_amount_total", $amount);  // This is initial balance
                              update_post_meta($postID, "_ywgc_balance_total", $newWooGiftCardRemaning); // This is remaining

                              $this->logging("Giftcard: " . wp_strip_all_tags($codeToWoo) . " will be created in WooCommerce with this remaining value (Original card value: $amount): <strong>$newWooGiftCardRemaning</strong>.<br>", wp_strip_all_tags($codeToWoo));
                              continue;
                              
                        }
                  }
            }

            $this->logging("Stopped <strong>wcio_wgcssp_cron_sync_service_pos_woo</strong> function.", "");
      }

      // Skal bruges for alle kort der stammer fra Woo og som skal til Customers 1st.
      function codeToServicePos($code)
      {

            // Input XXXX-XXXX-XXXX-XXXX
            // Output: 724503989151  (12 char)()
            $code = str_replace("XXXX", "", $code); // removes X s that have been added to match format.
            $code = str_replace("-", "", $code); // Removes - s that have been added to match format.
            return $code; // outputs a Customers 1st. gift card.

      }

      // Skal bruges for alle kort der stammer fra Customers 1st. og som skal til Woo
      function codeToWoo($code)
      {

            // Input: 724503989151  (12 char)
            // Output XXXX-XXXX-XXXX-XXXX
            $number = str_pad($code, 16, "X", STR_PAD_RIGHT);
            $str = chunk_split($number, 4, '-');
            $str = substr($str, 0, -1);
            return $str;
      }
}
