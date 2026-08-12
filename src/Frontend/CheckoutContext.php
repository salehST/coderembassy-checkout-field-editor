<?php
namespace CoderEmbassy\CheckoutFieldsManager\Frontend;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\ExtensionPoints;

/**
 * Snapshot of the current checkout request.
 *
 * This is the payload handed to every extension point, so an add-on can make
 * visibility, required and pricing decisions without re-querying WooCommerce.
 * It is deliberately free of any rule-evaluation logic — it only describes
 * what is true right now.
 */
class CheckoutContext {

	/**
	 * @return array<string, mixed>
	 */
	public function build(): array {
		$cart_products   = array();
		$cart_categories = array();
		$coupon_codes    = array();
		$cart_total      = 0.0;
		$cart_subtotal   = 0.0;

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_total    = (float) WC()->cart->get_total( 'edit' );
			$cart_subtotal = (float) WC()->cart->get_subtotal();
			$coupon_codes  = WC()->cart->get_applied_coupons();

			foreach ( WC()->cart->get_cart() as $item ) {
				$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
				if ( $product_id > 0 ) {
					$cart_products[] = $product_id;
					$terms           = get_the_terms( $product_id, 'product_cat' );
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$cart_categories[] = (int) $term->term_id;
						}
					}
				}
			}
		}

		$user     = wp_get_current_user();
		$role     = is_array( $user->roles ) && ! empty( $user->roles ) ? (string) $user->roles[0] : 'guest';
		$country  = '';
		$state    = '';
		$payment  = '';
		$shipping = '';

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$country = (string) WC()->customer->get_billing_country();
			$state   = (string) WC()->customer->get_billing_state();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$payment  = (string) WC()->session->get( 'chosen_payment_method', '' );
			$shipping = (string) WC()->session->get( 'chosen_shipping_methods', array( '' ) )[0];
		}

		return array(
			// Free has no customer type engine; the add-on answers this filter.
			'customer_type'   => (string) apply_filters( ExtensionPoints::CURRENT_CUSTOMER_TYPE, '', array( 'source' => 'context' ) ),
			'cart_total'      => $cart_total,
			'cart_subtotal'   => $cart_subtotal,
			'cart_products'   => array_values( array_unique( $cart_products ) ),
			'cart_categories' => array_values( array_unique( $cart_categories ) ),
			'user_role'       => $role,
			'user_id'         => get_current_user_id(),
			'country'         => $country,
			'state'           => $state,
			'payment_method'  => $payment,
			'shipping_method' => $shipping,
			'coupon_codes'    => array_map( 'strval', $coupon_codes ),
			'field_values'    => array(),
		);
	}
}
