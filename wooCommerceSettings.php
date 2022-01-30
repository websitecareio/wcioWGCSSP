<?php
/*
 * Plugin Name: WooCommerce Settings Tab Demo
 * Plugin URI: https://gist.github.com/BFTrick/b5e3afa6f4f83ba2e54a
 * Description: A plugin demonstrating how to add a WooCommerce settings tab.
 * Author: Patrick Rauland
 * Author URI: http://speakinginbytes.com/
 * Version: 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

class WC_wciowgcssp {

    /*
     * Bootstraps the class and hooks required actions & filters.
     *
     */
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_wciowgcssp', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_wciowgcssp', __CLASS__ . '::update_settings' );
    }


    /*
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['wciowgcssp'] = __( 'WooCommerce Gift Cards SYNC servicepos.com', 'woocommerce-settings-tab-demo' );
        return $settings_tabs;
    }


    /*
     * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    /*
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    /*
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Section Title', 'woocommerce-settings-tab-demo' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_wciowgcssp_section_title'
            ),
            'title' => array(
                'name' => __( 'ServicePOS Token', 'woocommerce-settings-tab-demo' ),
                'type' => 'text',
                'desc' => __( 'Token can be found at https://app.detalteq.com/en -> Settings -> Generel -> Users. Only users that are not shop users have their API token displayed on the settings page.', 'woocommerce-settings-tab-demo' ),
                'id'   => 'wc_wciowgcssp_token'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_wciowgcssp_section_end'
            )
        );

        return apply_filters( 'wc_wciowgcssp_settings', $settings );
    }

}

WC_wciowgcssp::init();
