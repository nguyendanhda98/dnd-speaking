<?php
/**
 * WooCommerce Integration for DND Speaking plugin
 */

class DND_Speaking_WooCommerce {

    public function __construct() {
        // Add custom product type
        add_filter('product_type_selector', [$this, 'add_product_type']);
        
        // Add custom tab and fields to product data
        add_filter('woocommerce_product_data_tabs', [$this, 'add_custom_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_custom_product_fields']);
        
        // Save custom product fields
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_product_fields']);
        
        // Handle order completion to add lesson sessions
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);
        
        // Display lesson amount on product page
        add_action('woocommerce_single_product_summary', [$this, 'display_lesson_amount'], 25);
        
        // Add lesson amount to cart item data
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 2);
        
        // Display lesson amount in cart
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    /**
     * Add DND Speaking product type to WooCommerce
     */
    public function add_product_type($types) {
        $types['dnd_speaking'] = __('DND Speaking', 'dnd-speaking');
        return $types;
    }

    /**
     * Add custom tab to product data metabox
     */
    public function add_custom_product_tab($tabs) {
        // Add a new "Main" tab for DND Speaking products
        $tabs['dnd_main'] = [
            'label' => __('Main', 'dnd-speaking'),
            'target' => 'dnd_main_product_data',
            'class' => ['show_if_dnd_speaking'],
            'priority' => 20,
        ];
        
        // Hide Shipping tab for DND Speaking products
        if (isset($tabs['shipping'])) {
            $tabs['shipping']['class'][] = 'hide_if_dnd_speaking';
        }
        
        return $tabs;
    }

    /**
     * Add custom fields to the Main tab
     */
    public function add_custom_product_fields() {
        global $post;
        
        echo '<div id="dnd_main_product_data" class="panel woocommerce_options_panel hidden">';
        
        echo '<div class="options_group">';
        
        // Regular Price
        woocommerce_wp_text_input([
            'id' => '_regular_price',
            'label' => __('Regular price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')',
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Set the regular price for this product.', 'dnd-speaking'),
        ]);
        
        // Sale Price
        woocommerce_wp_text_input([
            'id' => '_sale_price',
            'label' => __('Sale price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')',
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Set the sale price for this product.', 'dnd-speaking'),
        ]);
        
        // Schedule Sale
        $sale_price_dates_from = get_post_meta($post->ID, '_sale_price_dates_from', true);
        $sale_price_dates_to = get_post_meta($post->ID, '_sale_price_dates_to', true);
        
        echo '<p class="form-field sale_price_dates_fields">
            <label for="_sale_price_dates_from">' . __('Schedule', 'woocommerce') . '</label>
            <input type="text" class="short" name="_sale_price_dates_from" id="_sale_price_dates_from" 
                   value="' . ($sale_price_dates_from ? date_i18n('Y-m-d', $sale_price_dates_from) : '') . '" 
                   placeholder="' . _x('From&hellip;', 'placeholder', 'woocommerce') . ' YYYY-MM-DD" maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />
            <input type="text" class="short" name="_sale_price_dates_to" id="_sale_price_dates_to" 
                   value="' . ($sale_price_dates_to ? date_i18n('Y-m-d', $sale_price_dates_to) : '') . '" 
                   placeholder="' . _x('To&hellip;', 'placeholder', 'woocommerce') . ' YYYY-MM-DD" maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />
        </p>';
        
        // Tax Status
        woocommerce_wp_select([
            'id' => '_tax_status',
            'label' => __('Tax status', 'woocommerce'),
            'options' => [
                'taxable' => __('Taxable', 'woocommerce'),
                'shipping' => __('Shipping only', 'woocommerce'),
                'none' => __('None', 'woocommerce'),
            ],
            'desc_tip' => true,
            'description' => __('Define whether or not the product is taxable.', 'dnd-speaking'),
        ]);
        
        // Tax Class
        $tax_classes = WC_Tax::get_tax_classes();
        $tax_class_options = ['' => __('Standard', 'woocommerce')];
        
        if ($tax_classes) {
            foreach ($tax_classes as $class) {
                $tax_class_options[sanitize_title($class)] = $class;
            }
        }
        
        woocommerce_wp_select([
            'id' => '_tax_class',
            'label' => __('Tax class', 'woocommerce'),
            'options' => $tax_class_options,
            'desc_tip' => true,
            'description' => __('Choose a tax class for this product.', 'dnd-speaking'),
        ]);
        
        echo '</div>';
        
        echo '<div class="options_group">';
        
        // Custom Amount field - Number of lesson sessions
        woocommerce_wp_text_input([
            'id' => '_dnd_lesson_amount',
            'label' => __('Amount (Lesson Sessions)', 'dnd-speaking'),
            'desc_tip' => true,
            'description' => __('Enter the number of lesson sessions included with this product.', 'dnd-speaking'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '1',
                'min' => '0',
            ],
        ]);
        
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript to show/hide tabs based on product type
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function toggleDNDSpeakingTabs() {
                    var product_type = $('#product-type').val();
                    
                    if (product_type === 'dnd_speaking') {
                        // Hide shipping tab for DND Speaking
                        $('.shipping_tab').hide();
                        // Show the Main tab content
                        $('#dnd_main_product_data').show();
                    } else {
                        // Show shipping tab for other product types
                        $('.shipping_tab').show();
                        // Hide the Main tab content
                        $('#dnd_main_product_data').hide();
                    }
                }
                
                // Run on page load
                toggleDNDSpeakingTabs();
                
                // Run when product type changes
                $('#product-type').on('change', function() {
                    toggleDNDSpeakingTabs();
                });
                
                // Handle tab clicks - show/hide panels properly
                $('.product_data_tabs').on('click', 'li', function() {
                    var target = $(this).find('a').attr('href');
                    if (target === '#dnd_main_product_data') {
                        $('.woocommerce_options_panel').hide();
                        $('#dnd_main_product_data').show();
                        $('.product_data_tabs li').removeClass('active');
                        $(this).addClass('active');
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Save custom product fields
     */
    public function save_custom_product_fields($post_id) {
        // Get the product
        $product = wc_get_product($post_id);
        
        if (!$product) {
            return;
        }
        
        // Only save if this is a DND Speaking product
        if ($product->get_type() !== 'dnd_speaking') {
            return;
        }
        
        // Save regular price
        if (isset($_POST['_regular_price'])) {
            $regular_price = wc_format_decimal($_POST['_regular_price']);
            update_post_meta($post_id, '_regular_price', $regular_price);
            $product->set_regular_price($regular_price);
        }
        
        // Save sale price
        if (isset($_POST['_sale_price'])) {
            $sale_price = wc_format_decimal($_POST['_sale_price']);
            update_post_meta($post_id, '_sale_price', $sale_price);
            $product->set_sale_price($sale_price);
        }
        
        // Save sale price dates
        if (isset($_POST['_sale_price_dates_from'])) {
            $sale_price_dates_from = $_POST['_sale_price_dates_from'];
            update_post_meta($post_id, '_sale_price_dates_from', $sale_price_dates_from ? strtotime($sale_price_dates_from) : '');
            $product->set_date_on_sale_from($sale_price_dates_from);
        }
        
        if (isset($_POST['_sale_price_dates_to'])) {
            $sale_price_dates_to = $_POST['_sale_price_dates_to'];
            update_post_meta($post_id, '_sale_price_dates_to', $sale_price_dates_to ? strtotime($sale_price_dates_to) : '');
            $product->set_date_on_sale_to($sale_price_dates_to);
        }
        
        // Save tax status
        if (isset($_POST['_tax_status'])) {
            update_post_meta($post_id, '_tax_status', sanitize_text_field($_POST['_tax_status']));
            $product->set_tax_status(sanitize_text_field($_POST['_tax_status']));
        }
        
        // Save tax class
        if (isset($_POST['_tax_class'])) {
            update_post_meta($post_id, '_tax_class', sanitize_text_field($_POST['_tax_class']));
            $product->set_tax_class(sanitize_text_field($_POST['_tax_class']));
        }
        
        // Save lesson amount
        if (isset($_POST['_dnd_lesson_amount'])) {
            $lesson_amount = absint($_POST['_dnd_lesson_amount']);
            update_post_meta($post_id, '_dnd_lesson_amount', $lesson_amount);
        }
        
        // Save the product
        $product->save();
    }

    /**
     * Handle order completion - Add lesson sessions to user credits
     */
    public function handle_order_completed($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Get the user ID from the order
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            error_log("DND Speaking: Order {$order_id} completed but no user ID found.");
            return;
        }
        
        // Check if credits have already been added for this order
        $credits_added = get_post_meta($order_id, '_dnd_credits_added', true);
        if ($credits_added) {
            return; // Credits already added
        }
        
        // Loop through order items
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            // Check if this is a DND Speaking product
            if ($product && $product->get_type() === 'dnd_speaking') {
                $lesson_amount = get_post_meta($product_id, '_dnd_lesson_amount', true);
                $quantity = $item->get_quantity();
                
                if ($lesson_amount && $quantity) {
                    $total_credits = (int)$lesson_amount * (int)$quantity;
                    
                    // Add credits to user
                    $result = DND_Speaking_Helpers::add_user_credits($user_id, $total_credits);
                    
                    if ($result) {
                        error_log("DND Speaking: Added {$total_credits} lesson sessions to user {$user_id} from order {$order_id}");
                        
                        // Add order note
                        $order->add_order_note(
                            sprintf(
                                __('Added %d lesson session(s) to user account.', 'dnd-speaking'),
                                $total_credits
                            )
                        );
                    }
                }
            }
        }
        
        // Mark that credits have been added
        update_post_meta($order_id, '_dnd_credits_added', true);
    }

    /**
     * Display lesson amount on product page
     */
    public function display_lesson_amount() {
        global $product;
        
        if ($product && $product->get_type() === 'dnd_speaking') {
            $lesson_amount = get_post_meta($product->get_id(), '_dnd_lesson_amount', true);
            
            if ($lesson_amount) {
                echo '<div class="dnd-lesson-amount">';
                echo '<strong>' . __('Lesson Sessions:', 'dnd-speaking') . '</strong> ';
                echo esc_html($lesson_amount);
                echo '</div>';
            }
        }
    }

    /**
     * Add lesson amount to cart item data
     */
    public function add_cart_item_data($cart_item_data, $product_id) {
        $product = wc_get_product($product_id);
        
        if ($product && $product->get_type() === 'dnd_speaking') {
            $lesson_amount = get_post_meta($product_id, '_dnd_lesson_amount', true);
            if ($lesson_amount) {
                $cart_item_data['dnd_lesson_amount'] = $lesson_amount;
            }
        }
        
        return $cart_item_data;
    }

    /**
     * Display lesson amount in cart
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['dnd_lesson_amount'])) {
            $item_data[] = [
                'name' => __('Lesson Sessions', 'dnd-speaking'),
                'value' => esc_html($cart_item['dnd_lesson_amount']),
            ];
        }
        
        return $item_data;
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'dnd-woocommerce-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/woocommerce.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Only load on product edit pages
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('product' === $post_type) {
                wp_enqueue_style(
                    'dnd-woocommerce-admin-style',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/woocommerce.css',
                    [],
                    '1.0.0'
                );
            }
        }
    }
}
