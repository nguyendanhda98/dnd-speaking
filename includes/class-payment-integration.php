<?php
/**
 * Payment integration for DND Speaking (using WooCommerce)
 */

class DND_Speaking_Payment_Integration {

    public function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'add_credits_on_purchase']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_speaking_sessions_field']);
        add_action('woocommerce_process_product_meta', [$this, 'save_speaking_sessions_field']);
    }

    public function add_speaking_sessions_field() {
        woocommerce_wp_text_input([
            'id' => '_speaking_sessions',
            'label' => __('Speaking Sessions', 'dnd-speaking'),
            'description' => __('Number of speaking sessions this product grants', 'dnd-speaking'),
            'type' => 'number',
        ]);
    }

    public function save_speaking_sessions_field($post_id) {
        $sessions = isset($_POST['_speaking_sessions']) ? intval($_POST['_speaking_sessions']) : 0;
        update_post_meta($post_id, '_speaking_sessions', $sessions);
    }

    public function add_credits_on_purchase($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        if (!$user_id) return;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $sessions = get_post_meta($product_id, '_speaking_sessions', true);
            if ($sessions) {
                DND_Speaking_Helpers::add_user_credits($user_id, intval($sessions));
            }
        }
    }
}