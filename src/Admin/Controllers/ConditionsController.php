<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\ConditionEngine;
use WP_REST_Server;

class ConditionsController extends BaseController {
	public function __construct( private ConditionEngine $conditionEngine ) {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'conditions';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'schema' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);
	}

	public function schema() {
		return $this->success( $this->conditionEngine->getSchemas() );
	}
}


