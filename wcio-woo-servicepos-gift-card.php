<?php
/**
 * Plugin Name: Woo Gift Cards synchronize Servicepos.com
 * Plugin URI: https://websitecare.io/wordpress-plugins/woocommerce-servicepos-sync/
 * Description: Synchronize WooCommerce gift cards with ServicePOS 
 * Version: 1.2.5
 * Author: Websitecare.io
 * Author URI: https://websitecare.io
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

include("wooCommerceSettings.php");


/* Main plugin functions */
class wcioWGCSSP
{

    public $token;
    public $giftcardplugin;

    public function __construct()
    {

        $this->token = get_option("wc_wciowgcssp_token"); // ServicePOS token
        $this->giftcardplugin = get_option("wc_wciowgcssp_giftcardplugin") ?? ""; // Gift card plugin you want to use.

        // Add cron sheduels
        add_filter('cron_schedules', array($this, 'add_cron_interval'));


        // Updates and settings
        add_action('admin_init', array($this, 'myplugin_register_settings'));
        add_action('admin_menu', array($this, 'myplugin_register_options_page'));

        $slug = "wcio-woo-servicepos-gift-card";
        $updateToken = get_option('wcio-dm-api-key');
        require dirname(__FILE__) . "/plugin-update-checker/plugin-update-checker.php";
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/websitecareio/wcio-woo-servicepos-gift-card',
            __FILE__,
            $slug // Product slug
        );

        // Add options
        add_action('admin_init', array($this, 'custom_plugin_register_settings'));
        add_action('admin_menu', array($this, 'custom_plugin_setting_page'));


        register_activation_hook(__FILE__, array($this, 'activatePlugin'));
    }


    /*
	   *  Add options
	   * 
	   */
    function custom_plugin_register_settings()
    {

        register_setting('wcio_wgcssp_service_option_group', 'wcio_wgcssp_last_action'); // Used to make sure the plugin does not keep running when not neeeded.

    }

    function custom_plugin_setting_page()
    {

        add_options_page('WordPress sync ServicePOS', 'WordPress sync ServicePOS', 'manage_options', 'wcio_wgcssp_service_option',  array($this, 'custom_page_html_form'));
    }

    function custom_page_html_form()
    { ?>
        <div class="wrap">
            <h2>Settings for WordPress sync ServicePOS</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('wcio_wgcssp_service_option_group');
                ?>

                <table class="form-table">

                    <tr>
                        <th><label for="first_field_id">Last action:</label></th>

                        <td>
                            <p>Last action is when the plugin last had an action and will make sure the plugin doesnt spin in a loop.<br>This field value should look something like this <?php echo time(); ?>.<br>Do not empty this field unless its for testing.
                            </p>
                            <input type='text' class="regular-text" id="wcio_wgcssp_last_action_id" name="wcio_wgcssp_last_action" value="<?php echo get_option('wcio_wgcssp_last_action'); ?>">
                        </td>
                    </tr>


                    <tr>
                        <th><label for="first_field_id">Last logged actions:</label></th>

                        <td>
                            <p>Click here to view the log file: <a href="/wp-content/uploads/wcio_wgcssp_service.txt" target="_blank">Log file</a></p>
                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>

        </div>
    <?php
    }


    // Add this to your function. It will add the option field for the API key

    // Updates and settings
    function myplugin_register_settings()
    {
        add_option('wcio-dm-api-key', '');
        register_setting('wcio-dm-options-group', 'wcio-dm-api-key', 'myplugin_callback');
    }

    function myplugin_register_options_page()
    {
        add_options_page('Digital manager', 'Digital Manager', 'manage_options', 'myplugin', array($this, 'myplugin_options_page'));
    }

    function myplugin_options_page()
    {
    ?>
        <div>
            <?php screen_icon(); ?>
            <h2>Digital manager options</h2>
            <form method="post" action="options.php">
                <?php settings_fields('wcio-dm-options-group'); ?>
                <table>
                    <tr valign="top">
                        <th scope="row"><label for="wcio-dm-api-key">API key</label></th>
                        <td><input type="text" id="wcio-dm-api-key" name="wcio-dm-api-key" value="<?php echo get_option('wcio-dm-api-key'); ?>" placeholder="Enter API key" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    function add_cron_interval($schedules)
    {
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__('Every Five Minute'),
        );
        return $schedules;
    }


    function activatePlugin()
    {

        if (!wp_next_scheduled('wcio_wgcssp_cron_sync_woo_service_pos')) {
            wp_schedule_event(time(), 'five_minutes', 'wcio_wgcssp_cron_sync_woo_service_pos');
        }

        if (!wp_next_scheduled('wcio_wgcssp_cron_sync_service_pos_woo')) {
            wp_schedule_event(time(), 'five_minutes', 'wcio_wgcssp_cron_sync_service_pos_woo');
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
                'token' => " . $this->token . "<br>
            ";
        }
        curl_close($curl);

        $logfile = dirname(__FILE__) . "/../../uploads/wcio_wgcssp_service.txt";
        // Check if we need to clean log
        if (file_exists($logfile)) {
            if (filesize($logfile) > "100000000") { // If above 100 mb
                unlink($logfile);
            }
        }

        // Log this call
        $log  = date("d-m-Y H:i:s") . "," . time() . ",$result," . json_encode($method) . ", " . json_encode($endpoint) . ", " . json_encode($data) . "" . PHP_EOL;
        // Save string to log, use FILE_APPEND to append.
        // file_put_contents($logfile, $log, FILE_APPEND);
        sleep(1); // Delay execution for X seconds
        return json_decode($result, true);
    }
}



$wcioWGCSSP = new wcioWGCSSP();
$giftcardPlugin = $wcioWGCSSP->giftcardplugin;

// Include Gift card services
if ($giftcardPlugin == "woo-gift-cards" || $giftcardPlugin == "") {

    include(dirname(__FILE__) . "/includes/woo-gift-cards.php");

}

// If the site is using Flexible PDF Coupons Pro for WooCommerce plugin
if ($giftcardPlugin == "flexible-pdf-coupons") {

    include(dirname(__FILE__) . "/includes/flexible-pdf-coupons.php");

}

// If the site is using yith-woocommerce-gift-cards for WooCommerce plugin
if ($giftcardPlugin == "yith-woocommerce-gift-cards") {

    include(dirname(__FILE__) . "/includes/yith-woocommerce-gift-cards.php");

}

$service = new wcioWGCSSPservice();
//echo $service->wcio_wgcssp_cron_sync_woo_service_pos();
