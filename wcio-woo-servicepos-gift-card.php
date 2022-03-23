<?php
/**
 * Plugin Name: Websitecare.io - WooCommerce Gift Cards SYNC servicepos.com
 * Plugin URI: websitecare.io
 * Description: Syncronises 
 * Version: 1.0.9
 * Author: Kim Vinberg
 * Author URI: websitecare.io
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include("wooCommerceSettings.php");

register_activation_hook( __FILE__, array('wcioWGCSSP', 'activatePlugin') );

/* Main plugin functions */
class wcioWGCSSP {

    private $token;
    private $giftcardplugin;

    function __construct()
    {

            $this->token = get_option("wc_wciowgcssp_token"); // ServicePOS token
            $this->giftcardplugin = get_option("wc_wciowgcssp_giftcardplugin") ?? ""; // Gift card plugin you want to use.

            add_action( 'wcio_wgcssp_cron_sync_woo_service_pos', array( $this, 'wcio_wgcssp_cron_sync_woo_service_pos' ));
            add_action( 'wcio_wgcssp_cron_sync_service_pos_woo', array( $this, 'wcio_wgcssp_cron_sync_service_pos_woo' ));

            // Add JS to cart page based on the plugin used
            if($this->giftcardplugin == "woo-gift-cards" || $this->giftcardplugin == "") {

                  add_action( 'wp_footer', array( $this, 'woo_gift_cards_checkout_script'), 9999 );

            }

            // Updates and settings
            add_action( 'admin_init', array( $this, 'myplugin_register_settings') );
            add_action('admin_menu', array( $this, 'myplugin_register_options_page') );

            $slug = "wcio-woo-servicepos-gift-card";
            $token = get_option( 'wcio-dm-api-key' );
            require dirname(__FILE__) . "/plugin-update-checker/plugin-update-checker.php";
            $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
              'https://websitecare.io/wp-json/wcio/product/'.$slug.'/update/?token='.$token.'',
              __FILE__,
              $slug // Product slug
            );


    }

// Add this to your function. It will add the option field for the API key

// Updates and settings
function myplugin_register_settings() {
    add_option( 'wcio-dm-api-key', '');
    register_setting( 'wcio-dm-options-group', 'wcio-dm-api-key', 'myplugin_callback' );
}

function myplugin_register_options_page() {
    add_options_page('Digital manager', 'Digital Manager', 'manage_options', 'myplugin', array($this, 'myplugin_options_page') );
}

function myplugin_options_page() {
?>
    <div>
    <?php screen_icon(); ?>
    <h2>Digital manager options</h2>
    <form method="post" action="options.php">
    <?php settings_fields( 'wcio-dm-options-group' ); ?>
     <table>
    <tr valign="top">
    <th scope="row"><label for="wcio-dm-api-key">API key</label></th>
    <td><input type="text" id="wcio-dm-api-key" name="wcio-dm-api-key" value="<?php echo get_option('wcio-dm-api-key'); ?>" placeholder="Enter API key" /></td>
    </tr>
    </table>
    <?php  submit_button(); ?>
    </form>
    </div>
<?php
}
 
    function activatePlugin() {

        if ( ! wp_next_scheduled( 'wcio_wgcssp_cron_sync_woo_service_pos' ) ) {
            wp_schedule_event( time(), 'every_minute', 'wcio_wgcssp_cron_sync_woo_service_pos' );
        }

        if ( ! wp_next_scheduled( 'wcio_wgcssp_cron_sync_service_pos_woo' ) ) {
            wp_schedule_event( time(), 'every_minute', 'wcio_wgcssp_cron_sync_service_pos_woo' );
        }

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
                        $("input[name=coupon_code]").keyup(function() {
                              var foo = $(this).val().split("-").join(""); // remove hyphens
                              if (foo.length > 0) {
                                    foo = foo.match(new RegExp(".{1,4}", "g")).join("-");
                              }
                              
                              value = $(this).val();
                              if(value.length == "12")  {
                                    
                                    $(this).val(pad(foo, 18));
                                    
                              } else {
                                    $(this).val(foo);
                              }
                              
                        });
                  });
                  </script>';
            }
      }

    // Call ServicePOS
    function call($method, $endpoint, $data = false)
    {
        $url = 'https://app.deltateq.com/api' . $endpoint;
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
        ));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status >= 300) {
            return "
                'endpoint' => $url<br>
                'status' => $status<br>
                'error' => ''<br>
                'method' => $method<br>
                'result' => $result<br>
                'token' => ".$this->token."<br>
            ";
        }
        curl_close($curl);
        return json_decode($result, true);
    }
    /*
    * Create a new gift card with data from ServicePOS
    */
    public function createNewGiftCard($data) {

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
    public function wcio_wgcssp_cron_sync_woo_service_pos() {

      global $wpdb;
      $table_prefix = $wpdb->prefix;
      $WooCommerceGiftCardTableName = "woocommerce_gc_cards";

      $giftCards = $wpdb->get_results("SELECT * FROM $table_prefix$WooCommerceGiftCardTableName ORDER BY ID DESC");



        foreach ( $giftCards as $card ) {

          $code = $card->code;
          $sender = $card->sender;
          $sender_email = $card->sender_email;
          $balance = $card->balance; // This is initial balance
          $remaining = $card->remaining; // This is remaining
          $expire_date = $card->expire_date; // This is remaining
          $redeem_date = $card->redeem_date; // This is remaining
          $spent = $balance-$remaining; // This is spent



            $query = array("giftcardno" => $this->codeToServicePos($code));
            $queryGiftcards = $this->call("GET", "/giftcards", $query);

            // If gift card was found at ServicePOS.
            if($queryGiftcards["count"] == "1") {

                // Match values to make sure this isnt an outdated card.
                //$servicePOSAmount = $queryGiftcards["content"]["0"]["amount"]; // Overwridden to fix error.
                $servicePOSAmountRemaining = $queryGiftcards["content"]["0"]["amount"]-$queryGiftcards["content"]["0"]["amountspent"]; // Full amount minus amount spent gives remaining

                if($remaining != $servicePOSAmountRemaining) {

                      // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                      // If the card in WooCommerce have been used more then the one in ServicePOS, then update ServicePOS
                      if($remaining < $queryGiftcards["content"]["0"]["amount"]-$queryGiftcards["content"]["0"]["amountspent"]) {

                        // If WooCommerce gift card ave more spent on it, then we need to update ServicePos
                        $query = $queryGiftcards;

                        // Now updat the amount spent.
                        $servicePOSAmountSpent = $queryGiftcards["content"]["0"]["amount"]-$remaining; // Full amount minus the remaining from wooCommerce gives the amount spent
                        $giftcard = [
                        'amountspent' => (int)$servicePOSAmountSpent,
                        'type' => 'giftcard',
                      ];

                        // Update giftcard in servicePOS
                        $updateServicePOSGiftcard = $this->call("PUT", "/giftcards/".$query["content"]["0"]["id"]."", ['content' => $giftcard]);

                        continue;

                      } else {

                        // ServicePOs have most spent, then update WooCommerce
                        // CodeToWoo Not needed, this stems from Woo.
                        $remaining = $servicePOSAmountRemaining;
                        $updateWooGiftCard = $wpdb->query($wpdb->prepare("UPDATE $table_prefix$WooCommerceGiftCardTableName SET remaining='$remaining' WHERE code='$code'"));
                        continue;

                      }

                } else {
                  // Everything OK
                 }

            } else if($queryGiftcards["count"] == "0") {
              // It wasnt found at ServicePOS.
              // Now check if dead
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
    public function wcio_wgcssp_cron_sync_service_pos_woo() {

      // THis function should check service POS and do the sme as the Woo function did.
      global $wpdb;
      $table_prefix = $wpdb->prefix;
      $WooCommerceGiftCardTableName = "woocommerce_gc_cards";
       
      // Sets the amount of gift cards per page.
      $query = array("paginationPageLength" => 1000);

      $giftcards = $this->call("GET", "/giftcards", $query);

        foreach ( $giftcards["content"] as $key => $card ) {

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
        private function codeToServicePos($code) {
            // Input XXXX-XXXX-XXXX-XXXX
            // Output: 724503989151  (12 char)()
            $code = str_replace("XXXX", "", $code); // removes X s that have been added to match format.
            $code = str_replace("-", "", $code); // Removes - s that have been added to match format.
          return $code; // outputs a ServicePOS gift card.
        }

            // Skal bruges for alle kort der stammer fra ServicePOS og som skal til Woo
        private function codeToWoo($code) {
            // Input: 724503989151  (12 char)
            // Output XXXX-XXXX-XXXX-XXXX
          $number = str_pad($code, 16, "X", STR_PAD_RIGHT);
          $str = chunk_split($number, 4, '-');
          $str = substr($str, 0, -1);;
          return $str;
        }


}

$wcioWGCSSP = new wcioWGCSSP();
