<?php

/**
 * Plugin Name: Walnut.Marketing Portal
 * Plugin URI: https://walnut.marketing
 * Description: Adds the Walnut Marketing Portal tracking script to your website.
 * Version: 0.2.05
 * Author: JD Dev
 * License:GPL-2.0+
 * License URI:http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace WalnutMarketing;

define(__NAMESPACE__.'\ACTIVEDEMAND_VER', '0.2.05');
define(__NAMESPACE__."\PLUGIN_VENDOR", "Walnut Marketing");
define(__NAMESPACE__."\PLUGIN_VENDOR_LINK", "https://walnut.marketing");
define(__NAMESPACE__."\PREFIX", 'walnut');

include plugin_dir_path(__FILE__).'class-SCCollector.php';
include plugin_dir_path(__FILE__).'linked-forms.php';


//--------------- AD update path --------------------------------------------------------------------------
function activedemand_update()
{

    //get ensure a cookie is set. This call creates a cookie if one does not exist
    activedemand_get_cookie_value();

    $key = PREFIX.'_version';
    $version = get_option($key);

    if (ACTIVEDEMAND_VER === $version) return;
    activedemand_plugin_activation();
    update_option($key, ACTIVEDEMAND_VER);


}

add_action('init', __NAMESPACE__.'\activedemand_update');



function activedemand_gutenberg_blocks()
{
    if (!function_exists('register_block_type')) {
        return false;
    }

    if (get_option(PREFIX.'_show_gutenberg_blocks', TRUE)) {
        $available_blocks = array(
            array(
                'label' => 'Select a block',
                'value' => 0
            )
        );

        $available_forms = array(
            array(
                'label' => 'Select a form',
                'value' => 0
            )        
        );

        if ( is_admin() ) {
            $url = "https://api.activedemand.com/v1/smart_blocks.json";
            $activedemand_blocks = json_decode(activedemand_getHTML($url, 10));

            if (is_array($activedemand_blocks)) {
                foreach ($activedemand_blocks as $block) {
                    $available_blocks[] = array(
                        'label' => $block->name,
                        'value' => $block->id
                    );
                }
            }

            $url = "https://api.activedemand.com/v1/forms.json";
            $activedemand_forms = json_decode(activedemand_getHTML($url, 10));

            if (is_array($activedemand_forms)) {
                foreach ($activedemand_forms as $form) {
                    $available_forms[] = array(
                        'label' => $form->name,
                        'value' => $form->id
                    );
                }
            }
        }    

        /*register js for dynamic blocks block*/
        wp_register_script(
            'activedemand_blocks',        
            plugins_url( 'gutenberg-blocks/dynamic-content-blocks/block.build.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element' )
        );

        /*pass dynamic blocks list to js*/
        wp_localize_script( 'activedemand_blocks', 'activedemand_blocks', $available_blocks);

        /*register gutenberg block for dynamic blocks*/
        register_block_type( 'activedemand/content-block', array(
            'attributes' => array(
                'block_id' => array(
                    'type' => 'number'                
                )
            ),
            'render_callback' => __NAMESPACE__.'\activedemand_render_dynamic_content_block',
            'editor_script' => 'activedemand_blocks',
        ));


        /*register js for forms block*/
        wp_register_script(
            'activedemand_forms',        
            plugins_url( 'gutenberg-blocks/forms/block.build.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element' )
        );

        /*pass forms list to js*/
        wp_localize_script( 'activedemand_forms', 'activedemand_forms', $available_forms);
        
        /*register gutenberg block for forms*/
        register_block_type( 'activedemand/form', array(
            'attributes' => array(
                'form_id' => array(
                    'type' => 'number'                
                )
            ),
            'render_callback' => __NAMESPACE__.'\activedemand_render_form',
            'editor_script' => 'activedemand_forms'
        ));

        /*register gutenberg block category (ActiveDemand Blocks)*/
        add_filter( 'block_categories', __NAMESPACE__.'\activedemand_block_category', 10, 2);
    }
}

add_action('init', __NAMESPACE__.'\activedemand_gutenberg_blocks');

function activedemand_render_dynamic_content_block($params)
{
    $block_id = isset($params['block_id']) ? (int)$params['block_id'] : 0;
    if ($block_id) {
        return do_shortcode("[activedemand_block id='$block_id']");
    }
}

function activedemand_block_category( $categories, $post ) {
    return array_merge(
        $categories,
        array(
            array(
                'slug' => 'activedemand-blocks',
                'title' => __( 'Walnut Marketing Blocks', 'activedemand-blocks' ),
            ),
        )
    );
}

function activedemand_render_form($params)
{
    $form_id = isset($params['form_id']) ? (int)$params['form_id'] : 0;
    if ($form_id) {
        return do_shortcode("[activedemand_form id='$form_id']");
    }
}

//---------------Version Warning---------------------------//
/**function phpversion_warning_notice(){
    if(!((int)phpversion()<7)) return;
    $class='notice notice-warning is-dismissible';

    $message=(__(PLUGIN_VENDOR.' will deprecate PHP5 support soon -- we recommend updating to PHP7.'));
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}
add_action('admin_notices', __NAMESPACE__.'\phpversion_warning_notice');
*/
//--------------- AD Server calls -------------------------------------------------------------------------

function activedemand_getHTML($url, $timeout, $args = array())
{
    $result = false;
    $fields_string = activedemand_field_string($args);
    $response = wp_remote_get($url."?".$fields_string,
        array(
            'timeout'   => $timeout,
            'sslverify' => false,
        )
    );

    if ( is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200 ) {
        $result = $response['body'];
    }

    return $result;
}

function activedemand_postHTML($url, $args, $timeout)
{
    $result = false;
    $fields_string = activedemand_field_string($args);
    $response = wp_remote_post(
        $url,
        array(
            'method'        => 'POST',
            'timeout'       => $timeout,
            'body'          => $fields_string,
            'sslverify'     => false            
        )
    );

    if ( is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200 ) {
        $result = $response['body'];
    }

    return $result;
}

/**
 * Adds ActiveDEMAND popups if API Key isset and activedemand_server_showpopups is true
 *
 * @param string $content
 * @return string $content with popup prefix
 */

function activedemand_api_key()
{
    $options = retrieve_activedemand_options();
    if (is_array($options) && array_key_exists(PREFIX.'_appkey', $options)) {
        $activedemand_appkey = $options[PREFIX."_appkey"];
    } else {
        $activedemand_appkey = "";
    }

    return $activedemand_appkey;
}

function activedemand_field_string($args, $api_key = '')
{

    $fields_string = "";
    $activedemand_appkey = activedemand_api_key();

    if ("" != $api_key) {
        $activedemand_appkey = $api_key;
    }

    if ("" != $activedemand_appkey) {

        $cookievalue = activedemand_get_cookie_value();
        $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        } else {
            $referrer = "";
        }
        if ($cookievalue != "") {
            $fields = array(
                'api-key' => $activedemand_appkey,
                'activedemand_session_guid' => activedemand_get_cookie_value(),
                'url' => $url,
                'ip_address' => activedemand_get_ip_address(),
                'referer' => $referrer,
                'user_agent' => $_SERVER["HTTP_USER_AGENT"]
            );
        } else {
            $fields = array(
                'api-key' => $activedemand_appkey,
                'url' => $url,
                'ip_address' => activedemand_get_ip_address(),
                'referer' => $referrer,
                'user_agent' => $_SERVER["HTTP_USER_AGENT"]
            );

        }
        if (is_array($args)) {
            $fields = array_merge($fields, $args);
        }
        $fields_string = http_build_query($fields);
    }

    return $fields_string;
}

add_action('init', __NAMESPACE__.'\activedemand_get_cookie_value');

function activedemand_get_cookie_value()
{
    //if (is_admin()) return "";

    static $cookieValue = "";

    if(!empty($cookieValue)) return $cookieValue;
        //not editing an options page etc.

        if (!empty($_COOKIE['activedemand_session_guid'])) {
            $cookieValue = $_COOKIE['activedemand_session_guid'];

        } else {
            $server_side = get_option(PREFIX.'_server_side', TRUE);;
            if($server_side){
                $urlParms = $_SERVER['HTTP_HOST'];
                if (NULL != $urlParms) {
                        $cookieValue = activedemand_get_GUID();
                        $basedomain = activedemand_get_basedomain();
                        setcookie('activedemand_session_guid', $cookieValue, time() + (60 * 60 * 24 * 365 * 10), "/", $basedomain);
                }
            }
        }

    return $cookieValue;
}


function activedemand_get_basedomain()
{
    $result = "";

    $urlParms = $_SERVER['HTTP_HOST'];
    if (NULL != $urlParms) {
        $result = str_replace('www.', "", $urlParms);
    }
    return $result;
}

// create a session if one doesn't exist
function activedemand_get_GUID()
{
    if (function_exists('com_create_guid')) {
        return com_create_guid();
    } else {
        mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }
}


// get the ip address
function activedemand_get_ip_address()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

//--------------- Admin Menu -------------------------------------------------------------------------
function activedemand_menu()
{
    include plugin_dir_path( __FILE__).'settings.php';
    global $activedemand_plugin_hook;
    $activedemand_plugin_hook = add_options_page(PLUGIN_VENDOR.' options', PLUGIN_VENDOR, 'manage_options', PREFIX.'_options', __NAMESPACE__.'\activedemand_plugin_options');
    add_action('admin_init', __NAMESPACE__.'\register_activedemand_settings');

}

function retrieve_activedemand_options(){
  $options = is_array(get_option(PREFIX.'_options_field'))? get_option(PREFIX.'_options_field') : array();
  $woo_options=is_array(get_option(PREFIX.'_woocommerce_options_field'))? get_option(PREFIX.'_woocommerce_options_field') : array();
  if(!empty($options) && !empty($woo_options)){
    return \array_merge($options, $woo_options);
  }
  return $options;
}

function register_activedemand_settings()
{
    register_setting(PREFIX.'_options', PREFIX.'_options_field');
    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_woocommerce_options_field');
    register_setting(PREFIX.'_options', PREFIX.'_server_showpopups');
    register_setting(PREFIX.'_options', PREFIX.'_show_tinymce');
    register_setting(PREFIX.'_options', PREFIX.'_show_gutenberg_blocks');
    register_setting(PREFIX.'_options', PREFIX.'_server_side');
    register_setting(PREFIX.'_options', PREFIX.'_v2_script_url');

    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_stale_cart_map');
    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_wc_actions_forms');
}


function activedemand_enqueue_scripts()
{
    $script_url = get_option(PREFIX.'_v2_script_url');
    if (!isset($script_url) || "" == $script_url) {
        $activedemand_appkey = activedemand_api_key();
        if ("" != $activedemand_appkey) {
            $script_url = activedemand_getHTML("https://api.activedemand.com/v1/script_url", 10);
            update_option(PREFIX.'_v2_script_url', $script_url);

        }
    }
    if (!isset($script_url) || "" == $script_url) {
        $script_url = 'https://static.activedemand.com/public/javascript/ad.collect.min.js.jgz';
    }
    wp_enqueue_script('ActiveDEMAND-Track', $script_url);
}


function activedemand_admin_enqueue_scripts()
{
    global $pagenow;

    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

    }
}

function activedemand_plugin_action_links($links, $file)
{
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.PREFIX.'_options">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}



function get_base_url()
{
    return plugins_url(null, __FILE__);
}

function activedemand_register_tinymce_javascript($plugin_array)
{
    $plugin_array['activedemand'] = plugins_url('/js/tinymce-plugin.js', __FILE__);
    return $plugin_array;
}


function activedemand_buttons()
{
    add_filter("mce_external_plugins", __NAMESPACE__.'\activedemand_add_buttons');
    add_filter('mce_buttons', __NAMESPACE__.'\activedemand_register_buttons');
}

function activedemand_add_buttons($plugin_array)
{
    $plugin_array['activedemand'] = get_base_url() . '/includes/activedemand-plugin.js';
    return $plugin_array;
}

function activedemand_register_buttons($buttons)
{
    array_push($buttons, 'insert_form_shortcode');
    return $buttons;
}


function activedemand_add_editor()
{
    global $pagenow;

    // Add html for shortcodes popup
    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        // echo "Including Micey!";
        include plugin_dir_path(__FILE__).'partials/tinymce-editor.php';
    }

}

function activedemand_clean_url($url)
{


    if (TRUE == strpos($url, 'ad.collect.min.js.jgz'))
    {
        return "$url' async defer";
    }
    if (TRUE == strpos($url, '/load.js'))
    {
        return "$url' async defer";
    }

    return $url;

}

//Constant used to track stale carts
define(__NAMESPACE__.'\AD_CARTTIMEKEY', 'ad_last_cart_update');

/**
 * Adds cart timestamp to usermeta
 */
function activedemand_woocommerce_cart_update()
{
    $user_id = get_current_user_id();
    update_user_meta($user_id, AD_CARTTIMEKEY, time());
}

add_action('woocommerce_cart_updated', __NAMESPACE__.'\activedemand_woocommerce_cart_update');

/**
 * Deletes timestamp from current user meta
 */
function activedemand_woocommerce_cart_emptied()
{
    $user_id = get_current_user_id();
    delete_user_meta($user_id, AD_CARTTIMEKEY);
}

add_action('woocommerce_cart_emptied', __NAMESPACE__.'\activedemand_woocommerce_cart_emptied');

/**Periodically scans, and sends stale carts to activedemand
 *
 * @global object $wpdb
 *
 * @uses activedemand_send_stale_carts function to process and send
 */

function activedemand_woocommerce_scan_stale_carts()
{
    if(!class_exists('WooCommerce')) return;

    global $wpdb;
    $options = retrieve_activedemand_options();
    $hours = $options['woocommerce_stalecart_hours'];

    $stale_secs = $hours * 60 * 60;

    $carts = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->usermeta . ' WHERE meta_key=%s', AD_CARTTIMEKEY));

    $stale_carts = array();
    $i = 0;
    foreach ($carts as $cart) {
        if ((time() - (int)$cart->meta_value) > $stale_secs) {
            $stale_carts[$i]['user_id'] = $cart->user_id;
            $stale_carts[$i]['cart'] = get_user_meta($cart->user_id, '_woocommerce_persistent_cart', TRUE);
        }
    }
    activedemand_send_stale_carts($stale_carts);
}

add_action(PREFIX.'_hourly', __NAMESPACE__.'\activedemand_woocommerce_scan_stale_carts');

register_activation_hook(__FILE__, __NAMESPACE__.'\activedemand_plugin_activation');

function activedemand_plugin_activation()
{
    if (!wp_next_scheduled(PREFIX.'_hourly')) wp_schedule_event(time(), 'hourly', PREFIX.'_hourly');
}

register_deactivation_hook(__FILE__, __NAMESPACE__.'\activedemand_plugin_deactivation');

function activedemand_plugin_deactivation()
{
    wp_clear_scheduled_hook(__NAMESPACE__.'\\'.PREFIX.'_hourly');
    wp_clear_scheduled_hook(PREFIX.'_hourly');
}

/**Processes and send stale carts
 * Delete the timestamp so carts are only used once
 *
 * @param array $stale_carts
 *
 * @used-by activedemand_woocommerce_scan_stale_carts
 * @uses    function _activedemand_send_stale cart to send each cart individually
 */
function activedemand_send_stale_carts($stale_carts)
{
  $setting=get_setting(PREFIX.'_stale_cart_map');

  if(!$setting || empty($setting)) return;
  if(!isset($setting['id']) || !isset($setting['map'])) return;
  $activedemand_form_id=$setting['id'];
  $url="https://submit.activedemand.com/submit/form/$activedemand_form_id";
    foreach ($stale_carts as $cart) {
        $user = new \WP_User($cart['user_id']);
        $form_data=FormLinker::map_field_keys($setting['map'], array(
          'user'=>$user,
          'cart'=>$cart
        ));

        $response=wp_remote_post($url, array(
          'body'=>$data
        ));

        if(is_wp_error($response)){
          $msg=$response->get_error_message();
          new WP_Error($msg);
        }

        delete_user_meta($user->ID, AD_CARTTIMEKEY);
    }
}


function activedemand_woocommerce_order_status_changed($order_id, $order_status_old, $order_status_new)
{
    //post that this person has reviewed their account page.

    $options = retrieve_activedemand_options();
    if (array_key_exists(PREFIX.'_appkey', $options)) {
        $activedemand_appkey = $options[PREFIX."_appkey"];
    }

    if (array_key_exists(PREFIX.'_woo_commerce_use_status', $options)) {
        $activedemand_woo_commerce_use_status = $options[PREFIX."_woo_commerce_use_status"];
    } else {
        $activedemand_woo_commerce_use_status = array('none' => 'none');
    }

    if (array_key_exists(PREFIX.'_woo_commerce_order_form_id', $options)) {
        $activedemand_woo_commerce_order_form_id = $options[PREFIX."_woo_commerce_order_form_id"];

    } else {
        $activedemand_woo_commerce_order_form_id = "0";
    }

    $execute_form_submit = ("" != $activedemand_appkey) && ("0" != $activedemand_woo_commerce_order_form_id) && ("" != $activedemand_woo_commerce_order_form_id) && array_key_exists($order_status_new, $activedemand_woo_commerce_use_status);
    if ($execute_form_submit) {
        $execute_form_submit = $activedemand_woo_commerce_use_status[$order_status_new];
    }


    //we need an email address and a form ID
    if ($execute_form_submit) {
        $order = new \WC_Order($order_id);
        $user_id = (int)$order->get_user_id();

        if (0 == $user_id) {
            $first_name = $order->billing_first_name;
            $last_name = $order->billing_last_name;
            $email_address = $order->billing_email;

        } else {
            $guest = FALSE;

            $current_user = get_userdata($user_id);
            $first_name = $current_user->user_firstname;
            $last_name = $current_user->user_lastname;
            $email_address = $current_user->user_email;

        }


        if (("" != $email_address) && ('0' != $activedemand_woo_commerce_order_form_id)) {

            $form_str = $form_str = activedemand_getHTML("https://api.activedemand.com/v1/forms/fields.xml", 10, array('form_id' => $activedemand_woo_commerce_order_form_id));
            $form_xml = simplexml_load_string($form_str);


            if ("" != $form_xml) {

                if ($form_xml->children()->count() >= 6) {
                    $fields = array();
                    $i = 0;
                    foreach ($form_xml->children() as $child) {

                        if (!array_key_exists(urlencode($child->key), $fields)) {
                            $fields[urlencode($child->key)] = array();
                        }
                        switch ($i) {
                            case 0:
                                array_push($fields[urlencode($child->key)], $first_name);
                                break;
                            case 1:
                                array_push($fields[urlencode($child->key)], $last_name);
                                break;
                            case 2:
                                array_push($fields[urlencode($child->key)], $email_address);
                                break;
                            case 3:
                                array_push($fields[urlencode($child->key)], $order->get_total());
                                break;
                            case 4:
                                array_push($fields[urlencode($child->key)], $order_status_new);
                                break;
                            case 5:
                                array_push($fields[urlencode($child->key)], $order_id);
                                break;
                        }

                        $i++;


                    }


                    activedemand_postHTML("https://api.activedemand.com/v1/forms/" . $activedemand_woo_commerce_order_form_id, $fields, 5);

                }
            } else {
//                error_log("no form fields");
            }


            //$order_status_new;


        }


    } else {
        //      error_log("Not Processing ADForm Submit");
    }//execute form submit


}


if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {


    $options = retrieve_activedemand_options();

    //check to see if we have an API key, if we do not, zero integration is possible

    $activedemand_appkey = "";

    if (is_array($options) && array_key_exists(PREFIX.'_appkey', $options)) {
        $activedemand_appkey = $options[PREFIX."_appkey"];

    }


    if ("" != $activedemand_appkey) {

        add_action('woocommerce_order_status_changed', __NAMESPACE__.'\activedemand_woocommerce_order_status_changed', 10, 3);
    }

}


//defer our script loading
add_filter('clean_url', __NAMESPACE__.'\activedemand_clean_url', 11, 1);
add_action('wp_enqueue_scripts', __NAMESPACE__.'\activedemand_enqueue_scripts');

add_action('admin_enqueue_scripts', __NAMESPACE__.'\activedemand_admin_enqueue_scripts');

add_action('admin_menu', __NAMESPACE__.'\activedemand_menu');
add_filter('plugin_action_links', __NAMESPACE__.'\activedemand_plugin_action_links', 10, 2);


//widgets
// add new buttons

if (get_option(PREFIX.'_show_tinymce', TRUE)) {
    add_action('init', __NAMESPACE__.'\activedemand_buttons');
    add_action('in_admin_footer', __NAMESPACE__.'\activedemand_add_editor');
}


/*
 * Include module for Landing Page delivery
 */

include plugin_dir_path(__FILE__).'landing-pages.php';

add_action('woocommerce_after_checkout_form', function(){
  echo <<<SNIP
  <script type="text/javascript">
    jQuery(document).ready(function($){
      $('script[src$="ad.collect.min.js.jgz"]').load(function(){
        AD.ready(function(){
            AD.flink();
          });
      });
    });
    </script>
SNIP;
});
