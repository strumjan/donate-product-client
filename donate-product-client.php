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

// Load plugin text domain for translations
add_action('plugins_loaded', 'dpc_load_textdomain');
function dpc_load_textdomain() {
    load_plugin_textdomain('donate-product-client', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Register activation hook to create the virtual donation product
register_activation_hook(__FILE__, 'dpc_create_virtual_product');

function dpc_create_virtual_product() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return; // WooCommerce is not active, do not create the product
    }

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
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return; // WooCommerce is not active, do not create the product
    }

    if (!get_option('dpc_virtual_product_id')) {
        dpc_create_virtual_product();
    }
}

function dpc_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Donate Product Client Settings', 'donate-product-client'); ?></h1>
        <p class="description"><?php _e('This is the client part for the Donate Product component. Through it you can include a product donation on your site. From the donor of the donation, you will receive a client key that is made especially for you.', 'donate-product-client'); ?><br /><?php _e('If you have WooCommerce integrated then the donated product will appear on the checkout page above the total.', 'donate-product-client'); ?><br /><?php _e('If you don\'t have WooCommerce integrated then the component generates a "Donate Now" button that takes the customer directly to the donor page where they can make the donation.', 'donate-product-client'); ?></p>
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
    register_setting('dpc_settings_group', 'dpc_conversion_rate');
    register_setting('dpc_settings_group', 'dpc_shortcode');

    add_settings_section('dpc_settings_section', __('Settings', 'donate-product-client'), null, 'dpc_settings_page');

    add_settings_field('dpc_host_url', __('Host URL', 'donate-product-client'), 'dpc_host_url_callback', 'dpc_settings_page', 'dpc_settings_section');
    add_settings_field('dpc_client_key', __('Client Key', 'donate-product-client'), 'dpc_client_key_callback', 'dpc_settings_page', 'dpc_settings_section');
    add_settings_field('dpc_conversion_rate', __('Conversion Rate', 'donate-product-client'), 'dpc_conversion_rate_callback', 'dpc_settings_page', 'dpc_settings_section');
    add_settings_field(
        'dpc_shortcode',
        __('Donation Shortcode', 'donate-product-client'),
        'dpc_shortcode_callback',
        'dpc_settings_page',
        'dpc_settings_section'
    );
}

function dpc_host_url_callback() {
    $host_url = esc_attr(get_option('dpc_host_url'));
    echo "<input type='text' name='dpc_host_url' value='$host_url' />";
    echo '<p class="description">' . __('Enter the domain where the product being donated comes from, in the format https://example.com', 'donate-product-client') . '</p>';
}

function dpc_client_key_callback() {
    $client_key = esc_attr(get_option('dpc_client_key'));
    echo "<input type='text' name='dpc_client_key' value='$client_key' />";
    echo '<p class="description">' . __('Enter the client key that was generated specifically for you. Ask for it from the offerer of the donation.', 'donate-product-client') . '</p>';
}

function dpc_conversion_rate_callback() {
    $conversion_rate = esc_attr(get_option('dpc_conversion_rate', '1')); // Default to 1 if not set
    echo "<input type='text' name='dpc_conversion_rate' value='$conversion_rate' />";
    echo '<p class="description">' . __('Enter the currency conversion rate if you have a different currency than the donation hosting. For example: 61.4', 'donate-product-client') . '</p>';
}

add_filter('pre_update_option_dpc_conversion_rate', 'dpc_conversion_rate_validate', 10, 2);

function dpc_conversion_rate_validate($new_value, $old_value) {
    // If the new value is empty, set it to 1
    if (empty($new_value)) {
        return '1';
    }
    // Otherwise, return the new value
    return $new_value;
}

function dpc_shortcode_callback() {
    $shortcode = '[dpc_donation_button]';
    echo "<input type='text' id='dpc_shortcode' name='dpc_shortcode' value='$shortcode' readonly />";
    echo '<button type="button" onclick="copyShortcode()">' . __('Copy', 'donate-product-client') . '</button>';
    echo '<p class="description">' . __('Use this shortcode to display the donation button on any page.', 'donate-product-client') . '</p>';
}

// JavaScript за копирање на краткиот код
function dpc_admin_scripts() {
    ?>
    <script>
    function copyShortcode() {
        var shortcodeInput = document.getElementById('dpc_shortcode');
        shortcodeInput.select();
        shortcodeInput.setSelectionRange(0, 99999); // За мобилни уреди

        document.execCommand('copy');
        alert('<?php echo __('Shortcode copied to clipboard', 'donate-product-client'); ?>');
    }
    </script>
    <?php
}
add_action('admin_footer', 'dpc_admin_scripts');

// Генерирање краток код за прикажување на копчето за донација
function dpc_donation_button_shortcode() {
    ob_start();
    dpc_generate_donation_button();
    return ob_get_clean();
}
add_shortcode('dpc_donation_button', 'dpc_donation_button_shortcode');

// Функција за генерирање на копчето за донација
function dpc_generate_donation_button() {
    $campaign_data = dpc_fetch_campaign_data();

    if ($campaign_data &&  $campaign_data['campaign_archive'] == 0) {
        $host_checkout_page = $campaign_data['host_checkout_page'];
        $product_id = $campaign_data['product_id'];
        //$product_quantity = isset($_POST['donation_product_quantity']) ? intval($_POST['donation_product_quantity']) : 1;
        $donation_link = $host_checkout_page . $product_id;

        echo '<a href="' . esc_url($donation_link) . '" class="button">' . __('Donate Now', 'donate-product-client') . '</a>';
    } else {
        echo __('No active campaign.', 'donate-product-client') ;
    }
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
    // Split the JWT into its three parts
    // Split the JWT into its three parts
    list($header, $payload, $signature) = explode('.', $client_key);

    // Get the first 8 characters of the signature directly
    $client_key_short = substr($signature, 0, 8);

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

// Check if WooCommerce exist
add_action('plugins_loaded', 'dpc_check_woocommerce');

function dpc_check_woocommerce() {
if ( class_exists( 'WooCommerce' ) ) {
    //error_log('Ima WooCommerce!');
    // Hook into WooCommerce checkout
    add_action('woocommerce_review_order_before_order_total', 'dpc_add_donation_product');

    function dpc_add_donation_product() {
        $campaign_data = dpc_fetch_campaign_data();

        if ($campaign_data &&  $campaign_data['campaign_archive'] == 0) {
            $campaign_name = $campaign_data['campaign_name'];
            $product_id = $campaign_data['product_id'];
            $product_price = $campaign_data['product_price']*get_option('dpc_conversion_rate');
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
                $product_price = floatval($campaign_data['product_price'])*get_option('dpc_conversion_rate');

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
            $product_quantity = isset($_POST['donation_product_quantity']) ? intval($_POST['donation_product_quantity']) : 1;

            if ($campaign_data) {
                $product_price = floatval($campaign_data['product_price']*get_option('dpc_conversion_rate')*$product_quantity);
                WC()->cart->add_fee(__('Donation', 'donate-product-client'), $product_price);
            }
        }
    }

    // Hook into WooCommerce order placement
    add_action('woocommerce_thankyou', 'dpc_handle_order_placement');

    function dpc_handle_order_placement($order_id) {
        //error_log("dpc_handle_order_placement executed with order ID: " . $order_id);
        $order = wc_get_order($order_id);
        $currency = $order->get_currency();

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
            $product_price = floatval($campaign_data['product_price']*get_option('dpc_conversion_rate')*$donated_quantity);

            if ($campaign_data) {
                $client_domain = str_replace('.', '_', $_SERVER['HTTP_HOST']);
                $client_key = get_option('dpc_client_key'); // se koristi za JWT token
                $campaign_name = $campaign_data['campaign_name'];
                $required_quantity = $campaign_data['required_quantity'];

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
                        'campaign_name' => $campaign_name,
                        'required_quantity' => $required_quantity,
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
                $current_language = get_locale();
                $to = sanitize_email($campaign_data['host_email']);
                $subject = $client_domain . ": " . $campaign_name;
                if ($current_language == 'en_US' || $current_language == 'en-US') {
                    // Content for English language
                    $body = sprintf(__('Order ID: %s', 'donate-product-client'), $order->get_order_number());
                    $body .= "\n" . __('Donation: ', 'donate-product-client') . $product_price . " " . $currency;
                    $body .= "\n" . sprintf(__('Donor Name: %s', 'donate-product-client'), $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    $body .= "\n" . sprintf(__('Donor Email: %s', 'donate-product-client'), $order->get_billing_email());
                } else {
                $body = sprintf(__('Order ID: %s', 'donate-product-client'), $order->get_order_number()) . " (Order ID)";
                $body .= "\n" . __('Donation: ', 'donate-product-client') . $product_price . " " . $currency . " (Donation)";
                $body .= "\n" . sprintf(__('Donor Name: %s', 'donate-product-client'), $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . " (Donor name)";
                $body .= "\n" . sprintf(__('Donor Email: %s', 'donate-product-client'), $order->get_billing_email()) . " (Donor Email)";
                }

                wp_mail($to, $subject, $body);
            } else {
                //error_log("Campaign data not found.");
            }
        } else {
            //error_log("Donation product not found in the order or donated quantity is zero.");
        }
    }
} else {
    //error_log('Nema WooCommerce');
    add_action('wp_footer', 'dpc_show_no_woocommerce_message');
}
}

// Function to show message if WooCommerce is not active
function dpc_show_no_woocommerce_message() {
    $settings_link = '<a href="options-general.php?page=donate-product-client">' . __('Settings', 'donate-product-client') . '</a>';
    echo '<p>' . __('You don\'t have WooCommerce. But you can still participate in sharing donations! Use the shortcode you can find here: ', 'donate-product-client') . $settings_link . '</p>';
}


// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dpc_add_plugin_action_links');

function dpc_add_plugin_action_links($links) {
    $settings_link = '<a href="options-general.php?page=donate-product-client">' . __('Settings', 'donate-product-client') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
