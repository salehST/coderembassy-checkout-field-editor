<?php
namespace CoderEmbassy\CheckoutFieldEditor\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

abstract class BaseController extends WP_REST_Controller {
	protected $rest_base = ''; // phpcs:ignore -- cannot type-hint, WP_REST_Controller declares this without a type

	public function permissionsCheck( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'ca_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}

		$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' ) ?: $request->get_param( 'nonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'ca_invalid_nonce', 'Invalid REST nonce.', array( 'status' => 403 ) );
		}

		return true;
	}

	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	protected function error( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $message,
			),
			$status
		);
	}
}
