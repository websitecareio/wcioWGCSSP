<?php
/**
 * Plugin Name: Woo Gift Cards synchronize Servicepos.com
 * Plugin URI: https://websitecare.io/wordpress-plugins/woocommerce-servicepos-sync/
 * Description: Synchronize WooCommerce gift cards with ServicePOS 
 * Version: 1.1.2
 * Author: Websitecare.io
 * Author URI: https://websitecare.io
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include("wooCommerceSettings.php");

register_activation_hook( __FILE__, array('wcioWGCSSP', 'activatePlugin') );

/* Main plugin functions */
class wcioWGCSSP {

    public  $token;
    public $giftcardplugin;
    public $wcioWGCSSPservice;

    public function __construct()
    {

            $this->token = get_option("wc_wciowgcssp_token"); // ServicePOS token

            $this->giftcardplugin = get_option("wc_wciowgcssp_giftcardplugin") ?? ""; // Gift card plugin you want to use.


            // Include Gift card services
            if($this->giftcardplugin == "woo-gift-cards" || $this->giftcardplugin == "") {

                  include(dirname(__FILE__)."/includes/woo-gift-cards.php");

            }
            
            // If the site is using Flexible PDF Coupons Pro for WooCommerce plugin
            if($this->giftcardplugin == "flexible-pdf-coupons" ) {

                  include(dirname(__FILE__)."/includes/flexible-pdf-coupons.php");
   
            }
           
            // Add service class
            $this->wcioWGCSSPservice = new wcioWGCSSPservice();

            // Updates and settings
            add_action( 'admin_init', array( $this, 'myplugin_register_settings') );
            add_action('admin_menu', array( $this, 'myplugin_register_options_page') );

            $slug = "wcio-woo-servicepos-gift-card";
            $updateToken = get_option( 'wcio-dm-api-key' );
            require dirname(__FILE__) . "/plugin-update-checker/plugin-update-checker.php";
            $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
              'https://websitecare.io/wp-json/wcio/product/'.$slug.'/update/?token='.$updateToken.'',
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

    


    // Call ServicePOS
    function call($method, $endpoint, $data = false) {       
       
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
    

}



$wcioWGCSSP = new wcioWGCSSP();
$service = new wcioWGCSSPservice();

//echo $service->wcio_wgcssp_cron_sync_woo_service_pos();
