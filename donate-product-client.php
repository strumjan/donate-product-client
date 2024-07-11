<?php
/*
* Plugin Name: Donate Product Client
 * Description: Retrieves donation products from a host and adds them as optional products in the WooCommerce checkout page.
 * Version: 1.0
 * Author: Ilija Iliev Strumjan
 * Text Domain: donate-product-client
 * Domain Path: /languages
*/

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'dpc_activate');
register_deactivation_hook(__FILE__, 'dpc_deactivate');

function dpc_activate() {
    // Set default options
    add_option('dpc_host_url', '');
    add_option('dpc_client_key', '');
    add_option('dpc_jwt_token', '');
}

function dpc_deactivate() {
    // Clean up options
    delete_option('dpc_host_url');
    delete_option('dpc_client_key');
    delete_option('dpc_jwt_token');
}

// Register activation hook to create the virtual donation product
register_activation_hook(__FILE__, 'dpc_create_virtual_product');

function dpc_create_virtual_product() {
    if (get_option('dpc_virtual_product_id')) {
        return; // Product already exists
    }

    $product = new WC_Product();
    $product->set_name('Donation Product');
    $product->set_status('private');
    $product->set_catalog_visibility('hidden');
    $product->set_price(0);
    $product->set_regular_price(0);
    $product->set_virtual(true);
    $product->save();

    update_option('dpc_virtual_product_id', $product->get_id());
}

// Register settings page
add_action('admin_menu', 'dpc_register_settings_page');

function dpc_register_settings_page() {
    add_options_page('Donate Product Client Settings', 'Donate Product Client', 'manage_options', 'donate-product-client', 'dpc_settings_page');
}

// Hook to save settings and create virtual product if not exists
add_action('admin_init', 'dpc_register_settings');
add_action('update_option_dpc_host_url', 'dpc_create_virtual_product_if_not_exists');
add_action('update_option_dpc_client_key', 'dpc_create_virtual_product_if_not_exists');

function dpc_create_virtual_product_if_not_exists() {
    if (!get_option('dpc_virtual_product_id')) {
        dpc_create_virtual_product();
    }
}

function dpc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Donate Product Client Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('dpc_settings_group');
            do_settings_sections('dpc_settings_page');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'dpc_register_settings');

function dpc_register_settings() {
    register_setting('dpc_settings_group', 'dpc_host_url');
    register_setting('dpc_settings_group', 'dpc_client_key');

    add_settings_section('dpc_settings_section', 'Host Settings', null, 'dpc_settings_page');

    add_settings_field('dpc_host_url', 'Host URL', 'dpc_host_url_callback', 'dpc_settings_page', 'dpc_settings_section');
    add_settings_field('dpc_client_key', 'Client Key', 'dpc_client_key_callback', 'dpc_settings_page', 'dpc_settings_section');
}

function dpc_host_url_callback() {
    $host_url = esc_attr(get_option('dpc_host_url'));
    echo "<input type='text' name='dpc_host_url' value='$host_url' />";
}

function dpc_client_key_callback() {
    $client_key = esc_attr(get_option('dpc_client_key'));
    echo "<input type='text' name='dpc_client_key' value='$client_key' />";
}

// Само за тест
// Function to set admin notice
function dpc_set_admin_notice($message, $type = 'success') {
    set_transient('dpc_admin_notice', array('message' => $message, 'type' => $type), 30);
}

// Function to display admin notice
add_action('admin_notices', 'dpc_display_admin_notice');
function dpc_display_admin_notice() {
    if ($transient = get_transient('dpc_admin_notice')) {
        $message = $transient['message'];
        $type = $transient['type'];
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
        delete_transient('dpc_admin_notice');
    }
}
//Крај на само за тест

// Function to fetch JSON file
function dpc_fetch_campaign_data() {
    $host_url = get_option('dpc_host_url');
    $client_key = get_option('dpc_client_key'); // se koristi za JWT token

    if (empty($host_url) || empty($client_key)) {
        dpc_set_admin_notice('Host URL or Client Key is missing.', 'error');
        return null;
    }

    $client_domain = str_replace('.', '_', $_SERVER['HTTP_HOST']);
    $client_key_short = substr($client_key, 0, 8);
    $json_url = "{$host_url}/wp-content/plugins/donate-product-host/campaigns/{$client_domain}_{$client_key_short}.json";

    // Set the admin notice to display the JSON URL
    dpc_set_admin_notice("JSON URL: $json_url", 'info');

    $response = wp_remote_get($json_url, array(
        'sslverify' => false,
        'headers' => array(
            'Authorization' => 'Bearer ' . $client_key
        )
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        dpc_set_admin_notice('Failed to fetch the JSON file: ' . $error_message, 'error');
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

// Hook into WooCommerce checkout
add_action('woocommerce_review_order_before_order_total', 'dpc_add_donation_product');

function dpc_add_donation_product() {
    $campaign_data = dpc_fetch_campaign_data();

    if ($campaign_data) {
        $campaign_name = $campaign_data['campaign_name'];
        $product_id = $campaign_data['product_id'];
        $product_price = $campaign_data['product_price'];
        $max_quantity = isset($campaign_data['required_quantity']) ? intval($campaign_data['required_quantity']) : 0;

        if ($max_quantity > 0) {
            echo '<tr class="donation_product">
                    <th>' . esc_html($campaign_name) . '</th>
                    <td>
                        <input type="checkbox" id="add_donation_product" name="add_donation_product" onchange="checkboxAction();" value="' . esc_attr($product_id) . '" data-price="' . esc_attr($product_price) . '">
                        <input type="number" id="donation_product_quantity" name="donation_product_quantity" onchange="updateTotal();" min="1" max="' . esc_attr($max_quantity) . '" value="1" style="width: 60px; margin-left: 10px;" disabled>
                        ' . wc_price($product_price) . '
                    </td>
                  </tr>';
        }
    }
}

// Enqueue the script for adding donation product price in total
add_action('wp_enqueue_scripts', 'dpc_enqueue_scripts');

function dpc_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_script('dpc_donation_product', plugins_url('/dpc-donation-product.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('dpc_donation_product', 'wc_price_params', array(
            'currency_format_num_decimals' => get_option('woocommerce_price_num_decimals'),
            'currency_format_symbol'       => get_woocommerce_currency_symbol()
        ));
    }
}

// Add donate product in order
add_action('woocommerce_checkout_create_order', 'dpc_add_donation_product_to_order', 20, 1);

function dpc_add_donation_product_to_order($order) {
    if (isset($_POST['add_donation_product']) && $_POST['add_donation_product']) {
        $product_id = get_option('dpc_virtual_product_id');
        $product_quantity = isset($_POST['donation_product_quantity']) ? intval($_POST['donation_product_quantity']) : 1;

        // Fetch the campaign data
        $campaign_data = dpc_fetch_campaign_data();

        if ($campaign_data) {
            $product_name = $campaign_data['campaign_name'];
            $product_price = floatval($campaign_data['product_price']);

            // Create a new order item for the donation product
            $item = new WC_Order_Item_Product();
            $item->set_product_id($product_id);
            $item->set_name($product_name);
            $item->set_quantity($product_quantity);
            $item->set_total($product_price * $product_quantity);
            $item->add_meta_data('_donation_product', 'yes', true);
            $item->add_meta_data('_donation_product_quantity', sanitize_text_field($_POST['donation_product_quantity']), true);

            // Add the item to the order
            $order->add_item($item);
        }
    }
}


// Add donation product to cart as a fee
add_action('woocommerce_cart_calculate_fees', 'dpc_add_donation_product_to_cart');

function dpc_add_donation_product_to_cart() {
    if (isset($_POST['add_donation_product']) && !empty($_POST['add_donation_product'])) {
        $campaign_data = dpc_fetch_campaign_data();

        if ($campaign_data) {
            $product_price = floatval($campaign_data['product_price']);
            WC()->cart->add_fee(__('Donation', 'donate-product-client'), $product_price);// Tuka kako da fali kolichina
        }
    }
}

// Hook into WooCommerce order placement
add_action('woocommerce_thankyou', 'dpc_handle_order_placement');

function dpc_handle_order_placement($order_id) {
    //error_log("dpc_handle_order_placement executed with order ID: " . $order_id);
    $order = wc_get_order($order_id);

    $donated_quantity = 0;
    $donation_product_added = false;

    foreach ($order->get_items() as $item_id => $item) {
        if ($item->get_meta('_donation_product') === 'yes') {
            $donation_product_added = true;
            $donated_quantity = intval($item->get_meta('_donation_product_quantity'));
            break;
        }
    }

    if ($donation_product_added && $donated_quantity > 0) {
        //error_log("Donation product was added to the order.");

        $campaign_data = dpc_fetch_campaign_data();

        if ($campaign_data) {
            $client_domain = str_replace('.', '_', $_SERVER['HTTP_HOST']);
            $client_key = get_option('dpc_client_key'); // se koristi za JWT token

            //error_log("Parameters: client_domain = $client_domain, client_key = $client_key, donated_quantity = $donated_quantity");

            // Update JSON quantity on the host server
            $response = wp_remote_post(get_option('dpc_host_url') . '/wp-json/donate-product-host/v1/update_quantity', array(
                'method' => 'POST',
                'sslverify' => false,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $client_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'client_domain' => $client_domain,
                    'donated_quantity' => $donated_quantity,
                )),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                //error_log("Failed to update the JSON file: " . $error_message);
            } else {
                $response_body = json_decode(wp_remote_retrieve_body($response), true);
                //error_log("Response body: " . print_r($response_body, true));
            }

            // Send email notification to host
            $to = sanitize_email($campaign_data['host_email']);
            $subject = __('New Donation Order', 'donate-product-client');
            $body = sprintf(__('Order ID: %s', 'donate-product-client'), $order->get_order_number());
            $body .= "\n" . sprintf(__('Customer Name: %s', 'donate-product-client'), $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $body .= "\n" . sprintf(__('Customer Email: %s', 'donate-product-client'), $order->get_billing_email());

            wp_mail($to, $subject, $body);
        } else {
            //error_log("Campaign data not found.");
        }
    } else {
        //error_log("Donation product not found in the order or donated quantity is zero.");
    }
}



// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dpc_add_plugin_action_links');

function dpc_add_plugin_action_links($links) {
    $settings_link = '<a href="options-general.php?page=donate-product-client">' . __('Settings', 'donate-product-client') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
