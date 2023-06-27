<?php
/**
 * Plugin Name: Woo Gift Cards synchronize Customers 1st. (Formerly known as ServicePOS)
 * Plugin URI: https://websitecare.dk/
 * Description: Synchronize WooCommerce gift cards with Customers 1st. (Formerly known as ServicePOS)
 * Version: 1.3.8
 * Author: Websitecare.dk
 * Author URI: https://websitecare.dk
 */
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
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

        require dirname(__FILE__) . "/plugin-update-checker/plugin-update-checker.php";

        $myUpdateChecker = PucFactory::buildUpdateChecker(
            'https://github.com/websitecareio/wcio-woo-servicepos-gift-card/',
            __FILE__,
            'wcio-woo-servicepos-gift-card'
        );

        //Set the branch that contains the stable release.
        $myUpdateChecker->setBranch('main');
	
		// Verify schedule_event
	    add_action('admin_init',  array($this, 'check_and_schedule_event'));
	    
        // Add options
        add_action('admin_init', array($this, 'custom_plugin_register_settings'));
        add_action('admin_menu', array($this, 'custom_plugin_setting_page'));

        // Add menu 
        add_action('admin_menu',  array($this, 'my_custom_menu'));

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
                            <input type='text' class="regular-text" id="wcio_wgcssp_last_action_id" name="wcio_wgcssp_last_action" value="<?php echo get_option('wcio_wgcssp_last_action'); ?>"><br>
			    <input type='text' class="regular-text" id="wcio_wgcssp_last_action_id_2" name="wcio_wgcssp_last_action_2" value="<?php echo get_option('wcio_wgcssp_last_action_2'); ?>">
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



function check_and_schedule_event() {
	
	// wcio_wgcssp_cron_sync_woo_service_pos
    $event_name = 'wcio_wgcssp_cron_sync_woo_service_pos';
	// Check if the event is already scheduled
    $next_scheduled = wp_next_scheduled($event_name);

    if ($next_scheduled === false) {
        // Event is not scheduled, schedule it
        wp_schedule_event(time(), 'five_minutes', $event_name);
    }
	
// wcio_wgcssp_cron_sync_service_pos_woo
    $event_name = 'wcio_wgcssp_cron_sync_service_pos_woo';
	// Check if the event is already scheduled
    $next_scheduled = wp_next_scheduled($event_name);

    if ($next_scheduled === false) {
        // Event is not scheduled, schedule it
        wp_schedule_event(time(), 'five_minutes', $event_name);
    }
	
	
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

    // menu for logs 
    function my_custom_menu() {
        add_submenu_page(
            'options-general.php', //parent slug
            'ServicePOS log', //page title
            'ServicePOS log', //menu title
            'manage_options', //capability
            'my-table', //menu slug
            array($this, 'my_table_content')  //callback function
        );
    }
  
    
    function my_table_content() {
        global $wpdb;
        $table_name = $wpdb->prefix . "wcioWGCSSP_log";
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY ID DESC LIMIT 100");
        echo '<div class="wrap"><table class="wp-list-table widefat fixed">';
        echo '<thead><tr><th width="10%">ID</th><th width="20%">Time</th><th>Logdata</th><th width="20%">Giftcard</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . $row->id . '</td>';
            echo '<td>' . $row->time . '</td>';
            echo '<td>' . $row->logdata . '</td>';
            echo '<td>' . $row->giftcard . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    
    // Log call
    function logging($logdata, $giftcard = "") {

        global $wpdb;
        $table_name = $wpdb->prefix . "wcioWGCSSP_log";
        $table_check = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        // Check if table exists
        if ($table_check != $table_name) {
            //table does not exist, create it
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                logdata text NOT NULL,
                giftcard varchar(100) DEFAULT '' NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        // Save a log file.
        $data = array(
            'time' => date('Y-m-d H:i:s', time()),
            'logdata' => $logdata, 
            'giftcard' => $giftcard,
        );
        $wpdb->insert($table_name, $data);

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
            $this->logging("STATUS ERROR SERVICEPOS: <br>
            'endpoint' => $url<br>
            'status' => $status<br>
            'error' => ''<br>
            'method' => $method<br>
            'result' => $result<br>
            'token' => " . $this->token . "<br>", "");
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

        // Log this call
      /*  $log  = date("d-m-Y H:i:s") . "," . time() . ",$result," . json_encode($method) . ", " . json_encode($endpoint) . ", " . json_encode($data) . "" . PHP_EOL;
        // Save string to log, use FILE_APPEND to append.
        $logdata = "<strong>ServicePOS call:</strong><br>
        <strong>Result:</strong> $result<br>
        <strong>Method:</strong> " . json_encode($method) . "<br>
        <strong>Data (postfields):</strong> ".json_encode($data)."<br>
        <strong>Endpoint:</strong> " . json_encode($endpoint) ."";
        $giftcard = "";
        //$this->logging($logdata, $giftcard);
        */
        sleep(1); // Delay execution for X seconds
        return json_decode($result, true);
    }

    
    function search($array, $key, $value)
    {
          $results = array();

          if (is_array($array)) {
                if (isset($array[$key]) && $array[$key] == $value) {
                      $results[] = $array;
                }

                foreach ($array as $subarray) {
                      $results = array_merge($results, $this->search($subarray, $key, $value));
                }
          }

          return $results;
    }
}



$wcioWGCSSP = new wcioWGCSSP();
$giftcardPlugin = $wcioWGCSSP->giftcardplugin;

// Include Gift card services
if ($giftcardPlugin == "woo-gift-cards" || $giftcardPlugin == "") {

    include(dirname(__FILE__) . "/includes/woo-gift-cards.php");

}

// If the site is using yith-woocommerce-gift-cards for WooCommerce plugin
if ($giftcardPlugin == "yith-woocommerce-gift-cards") {

    include(dirname(__FILE__) . "/includes/yith-woocommerce-gift-cards.php");

}

$service = new wcioWGCSSPservice();
if(!is_admin() ) {
//echo $service->wcio_wgcssp_cron_sync_service_pos_woo();
}
