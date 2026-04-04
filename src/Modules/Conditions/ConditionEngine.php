<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class ConditionEngine {
	/**
	 * @var array<string, ConditionInterface>
	 */
	private array $condition_types = array();

	public function registerType( string $type, ConditionInterface $handler ): void {
		$this->condition_types[ $type ] = $handler;
	}

	public function evaluate( array $conditions_json, array $context ): bool {
		$groups = $conditions_json['groups'] ?? array();
		if ( empty( $conditions_json ) || ! is_array( $groups ) || empty( $groups ) ) {
			return true;
		}

		$top_logic = strtoupper( (string) ( $conditions_json['logic'] ?? 'AND' ) );
		$results   = array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				$results[] = false;
				continue;
			}

			$results[] = $this->evaluateGroup( $group, $context );
		}

		if ( 'OR' === $top_logic ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	public function buildContext(): array {
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

		$user       = wp_get_current_user();
		$role       = is_array( $user->roles ) && ! empty( $user->roles ) ? (string) $user->roles[0] : 'guest';
		$country    = '';
		$state      = '';
		$payment    = '';
		$shipping   = '';

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$country = (string) WC()->customer->get_billing_country();
			$state   = (string) WC()->customer->get_billing_state();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$payment  = (string) WC()->session->get( 'chosen_payment_method', '' );
			$shipping = (string) WC()->session->get( 'chosen_shipping_methods', array( '' ) )[0];
		}

		return array(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'customer_type'   => (string) apply_filters( 'ca_current_customer_type', '', array( 'source' => 'conditions' ) ),
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

	public function getSchemas(): array {
		$schemas = array();
		foreach ( $this->condition_types as $handler ) {
			$schemas[] = $handler->getSchema();
		}

		return $schemas;
	}

	private function evaluateGroup( array $group, array $context ): bool {
		$rules = $group['rules'] ?? array();
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return true;
		}

		$group_logic = strtoupper( (string) ( $group['logic'] ?? 'AND' ) );
		$results     = array();

		foreach ( $rules as $rule ) {
			$results[] = is_array( $rule ) ? $this->evaluateRule( $rule, $context ) : false;
		}

		if ( 'OR' === $group_logic ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	private function evaluateRule( array $rule, array $context ): bool {
		$type = (string) ( $rule['type'] ?? '' );
		if ( '' === $type || ! isset( $this->condition_types[ $type ] ) ) {
			return false;
		}

		$payload            = $rule;
		$payload['context'] = $context;

		return $this->condition_types[ $type ]->evaluate( $payload );
	}
}
