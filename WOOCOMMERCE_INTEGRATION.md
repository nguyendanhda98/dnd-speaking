# DND Speaking WooCommerce Integration

## Overview
This integration adds a custom product type to WooCommerce that allows you to sell lesson sessions. When a student purchases a product, the specified number of lesson sessions will be automatically added to their account.

## Features

### Custom Product Type: "DND Speaking"
- A new product type specifically designed for selling lesson sessions
- Fully integrated with WooCommerce's product management system

### Product Fields (General Tab)
When you select "DND Speaking" as the product type, the General tab includes:

1. **Regular Price** - The standard price for the product
2. **Sale Price** - Optional discounted price
3. **Schedule** - Set start and end dates for the sale price
4. **Tax Status** - Choose: Taxable, Shipping only, or None
5. **Tax Class** - Select the appropriate tax class
6. **Amount (Lesson Sessions)** - The number of lesson sessions included with this product

### Automatic Credit Management
- When an order is completed, the system automatically adds the lesson sessions to the student's account
- Credits are calculated as: (Lesson Amount × Quantity)
- Prevents duplicate credit additions for the same order
- Logs all credit additions for tracking

### Display Features
- Lesson session count is displayed on the product page
- Shows in cart and checkout pages
- Visible in order details

## How to Use

### Creating a DND Speaking Product

1. **Navigate to Products**
   - Go to WooCommerce → Products
   - Click "Add New"

2. **Set Product Type**
   - In the "Product Data" metabox, select "DND Speaking" from the dropdown

3. **Configure General Settings**
   - Click on the "General" tab (it will be visible for DND Speaking products)
   - Fill in the following fields:
     - **Regular Price**: Enter the standard price (e.g., 50.00)
     - **Sale Price**: (Optional) Enter a discounted price (e.g., 40.00)
     - **Schedule**: (Optional) Set when the sale price is active
     - **Tax Status**: Select the appropriate tax setting
     - **Tax Class**: Choose the tax class if applicable
     - **Amount (Lesson Sessions)**: Enter the number of lessons (e.g., 10)

4. **Add Product Details**
   - Fill in the product title, description, and images as usual
   - Click "Publish"

### Example Product Setup

**Product Title:** 10 Lesson Package
**Product Type:** DND Speaking
**Regular Price:** $100.00
**Sale Price:** $85.00
**Amount (Lesson Sessions):** 10

When a student purchases this product:
- They pay $85.00 (sale price)
- 10 lesson sessions are added to their account
- If they buy quantity of 2, they get 20 lesson sessions

### Order Processing

1. **Customer Places Order**
   - Customer adds DND Speaking product to cart
   - Proceeds through checkout
   - Completes payment

2. **Automatic Credit Addition**
   - When the order status changes to "Completed"
   - The system automatically calculates total sessions: `Amount × Quantity`
   - Credits are added to the customer's account
   - An order note is added for tracking

3. **Verification**
   - Check the order notes to confirm credits were added
   - Student can view their available sessions in their account

## Technical Details

### Database Integration
- Uses the existing `wp_dnd_speaking_credits` table
- Leverages `DND_Speaking_Helpers::add_user_credits()` method
- Includes logging for all credit additions

### Security
- All user inputs are sanitized and validated
- Uses WordPress/WooCommerce standard security practices
- Prevents duplicate credit additions

### Hooks and Filters Used
- `product_type_selector` - Adds the custom product type
- `woocommerce_product_data_tabs` - Modifies product data tabs
- `woocommerce_product_data_panels` - Adds custom fields
- `woocommerce_process_product_meta` - Saves custom fields
- `woocommerce_order_status_completed` - Processes completed orders
- `woocommerce_single_product_summary` - Displays lesson count
- `woocommerce_add_cart_item_data` - Adds data to cart items
- `woocommerce_get_item_data` - Displays data in cart

## Troubleshooting

### Credits Not Added
1. Check that the order status is "Completed"
2. Verify the product type is "DND Speaking"
3. Check the order notes for error messages
4. Review the error log for detailed information

### Product Fields Not Showing
1. Ensure WooCommerce is active
2. Clear browser cache
3. Check that product type is set to "DND Speaking"
4. Try refreshing the page

### Styling Issues
1. Clear WordPress cache
2. Check if `assets/css/woocommerce.css` is loaded
3. Verify file permissions

## Files Modified/Created

### New Files
- `includes/class-woocommerce.php` - Main WooCommerce integration class
- `assets/css/woocommerce.css` - Styling for WooCommerce features
- `WOOCOMMERCE_INTEGRATION.md` - This documentation file

### Modified Files
- `dnd-speaking.php` - Added WooCommerce initialization

## Future Enhancements

Potential features to consider:
- Subscription-based products for recurring lesson credits
- Bulk discount rules for larger packages
- Email notifications when credits are added
- Admin dashboard widget showing credit statistics
- Expiration dates for lesson credits
- Gift cards/vouchers for lessons

## Support

For issues or questions:
1. Check the WordPress debug log at `/wp-content/debug.log`
2. Review order notes in WooCommerce
3. Verify the `wp_dnd_speaking_credits` table in the database

## Version History

### Version 1.0.0
- Initial WooCommerce integration
- Custom "DND Speaking" product type
- Automatic credit addition on order completion
- Frontend display of lesson amounts
