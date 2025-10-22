<?php
/**
 * WooCommerce Integration for DND Speaking plugin
 */

class DND_Speaking_WooCommerce {

    public function __construct() {
        // Don't add custom product type - use Simple product instead
        // add_filter('product_type_selector', [$this, 'add_product_type']);
        
        // Add custom tab and fields to product data for Simple products
        add_filter('woocommerce_product_data_tabs', [$this, 'add_custom_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_custom_product_fields']);
        
        // Save custom product fields
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_product_fields']);
        
        // Handle order completion to add lesson sessions
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_changed'], 10, 4);
        
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
        // Add a new "DND Speaking" tab for all simple products
        $tabs['dnd_speaking'] = [
            'label' => __('DND Speaking', 'dnd-speaking'),
            'target' => 'dnd_speaking_product_data',
            'class' => ['show_if_simple'], // Show for simple products
            'priority' => 21,
        ];
        
        return $tabs;
    }

    /**
     * Add custom fields to the DND Speaking tab
     */
    public function add_custom_product_fields() {
        global $post;
        
        echo '<div id="dnd_speaking_product_data" class="panel woocommerce_options_panel hidden">';
        
        echo '<div class="options_group">';
        
        // Checkbox to enable DND Speaking features
        woocommerce_wp_checkbox([
            'id' => '_is_dnd_speaking',
            'label' => __('DND Speaking Product', 'dnd-speaking'),
            'description' => __('Check this box if this is a DND Speaking lesson package.', 'dnd-speaking'),
        ]);
        
        echo '</div>';
        
        echo '<div class="options_group dnd_speaking_fields">';
        
        // Custom Amount field - Number of lesson sessions
        woocommerce_wp_text_input([
            'id' => '_dnd_lesson_amount',
            'label' => __('Lesson Sessions', 'dnd-speaking'),
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
        
        // Add JavaScript to show/hide fields based on checkbox
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Show/hide lesson amount field based on checkbox
                function toggleDNDFields() {
                    if ($('#_is_dnd_speaking').is(':checked')) {
                        $('.dnd_speaking_fields').show();
                    } else {
                        $('.dnd_speaking_fields').hide();
                    }
                }
                
                // Run on page load
                toggleDNDFields();
                
                // Run when checkbox changes
                $('#_is_dnd_speaking').on('change', function() {
                    toggleDNDFields();
                });
            });
        </script>
        <?php
    }

    /**
     * Save custom product fields
     */
    public function save_custom_product_fields($post_id) {
        // Save the DND Speaking checkbox
        $is_dnd_speaking = isset($_POST['_is_dnd_speaking']) ? 'yes' : 'no';
        update_post_meta($post_id, '_is_dnd_speaking', $is_dnd_speaking);
        
        error_log("DND Speaking: Saving product {$post_id}, is_dnd_speaking: {$is_dnd_speaking}");
        
        // Only save lesson amount if this is marked as a DND Speaking product
        if ($is_dnd_speaking === 'yes') {
            if (isset($_POST['_dnd_lesson_amount'])) {
                $lesson_amount = absint($_POST['_dnd_lesson_amount']);
                update_post_meta($post_id, '_dnd_lesson_amount', $lesson_amount);
                error_log("DND Speaking: Saved lesson amount {$lesson_amount} for product {$post_id}");
            } else {
                error_log("DND Speaking: _dnd_lesson_amount not found in POST data for product {$post_id}");
            }
        } else {
            // Remove lesson amount if not a DND Speaking product
            delete_post_meta($post_id, '_dnd_lesson_amount');
            error_log("DND Speaking: Removed lesson amount for product {$post_id} (not a DND Speaking product)");
        }
        
        error_log("DND Speaking: Product {$post_id} saved successfully");
    }

    /**
     * Handle order completion - Add lesson sessions to user credits
     */
    public function handle_order_completed($order_id) {
        error_log("DND Speaking: handle_order_completed called for order {$order_id}");
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log("DND Speaking: Order {$order_id} not found");
            return;
        }
        
        // Get the user ID from the order
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            error_log("DND Speaking: Order {$order_id} completed but no user ID found.");
            return;
        }
        
        error_log("DND Speaking: Processing order {$order_id} for user {$user_id}");
        
        // Check if credits have already been added for this order
        $credits_added = get_post_meta($order_id, '_dnd_credits_added', true);
        if ($credits_added) {
            error_log("DND Speaking: Credits already added for order {$order_id}");
            return; // Credits already added
        }
        
        $total_credits_added = 0;
        
        // Loop through order items
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            error_log("DND Speaking: Processing item {$item_id}, product ID: {$product_id}");
            
            if (!$product) {
                error_log("DND Speaking: Product not found for product ID {$product_id}");
                continue;
            }
            
            $product_type = $product->get_type();
            $lesson_amount = get_post_meta($product_id, '_dnd_lesson_amount', true);
            
            error_log("DND Speaking: Product type: {$product_type}, Lesson amount meta: {$lesson_amount}");
            
            // Check if this is a DND Speaking product OR has lesson amount meta
            // This handles both official DND Speaking products and products created before the product type existed
            if ($product_type === 'dnd_speaking' || (!empty($lesson_amount) && $lesson_amount > 0)) {
                $quantity = $item->get_quantity();
                
                error_log("DND Speaking: DND Speaking product found - Lesson amount: {$lesson_amount}, Quantity: {$quantity}");
                
                if ($lesson_amount && $quantity) {
                    $total_credits = (int)$lesson_amount * (int)$quantity;
                    
                    error_log("DND Speaking: Attempting to add {$total_credits} credits to user {$user_id}");
                    
                    // Add credits to user
                    $result = DND_Speaking_Helpers::add_user_credits($user_id, $total_credits);
                    
                    if ($result) {
                        $total_credits_added += $total_credits;
                        error_log("DND Speaking: Successfully added {$total_credits} lesson sessions to user {$user_id} from order {$order_id}");
                    } else {
                        error_log("DND Speaking: FAILED to add credits to user {$user_id}");
                    }
                } else {
                    error_log("DND Speaking: Missing lesson amount or quantity - Amount: {$lesson_amount}, Quantity: {$quantity}");
                }
            } else {
                error_log("DND Speaking: Not a DND Speaking product and no lesson amount found, skipping");
            }
        }
        
        if ($total_credits_added > 0) {
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Added %d lesson session(s) to user account.', 'dnd-speaking'),
                    $total_credits_added
                )
            );
        }
        
        // Mark that credits have been added
        update_post_meta($order_id, '_dnd_credits_added', true);
        error_log("DND Speaking: Finished processing order {$order_id}, total credits added: {$total_credits_added}");
    }

    /**
     * Handle order status change - specifically for manual status changes by admin
     */
    public function handle_order_status_changed($order_id, $old_status, $new_status, $order) {
        // Only process when status changes TO completed
        if ($new_status !== 'completed') {
            return;
        }
        
        // Call the main handler
        $this->handle_order_completed($order_id);
    }

    /**
     * Display lesson amount on product page
     */
    public function display_lesson_amount() {
        global $product;
        
        if ($product) {
            $is_dnd_speaking = get_post_meta($product->get_id(), '_is_dnd_speaking', true);
            $lesson_amount = get_post_meta($product->get_id(), '_dnd_lesson_amount', true);
            
            if ($is_dnd_speaking === 'yes' && $lesson_amount) {
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
        $is_dnd_speaking = get_post_meta($product_id, '_is_dnd_speaking', true);
        
        if ($is_dnd_speaking === 'yes') {
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
