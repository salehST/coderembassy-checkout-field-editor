<?php
namespace CoderEmbassy\CheckoutFieldsManager\Frontend;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;

class BlocksIntegration implements IntegrationInterface {
	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $visibilityResolver,
		private CustomerTypeManager $customerTypeManager
	) {}

	public function get_name(): string {
		return 'coderembassy-checkout-fields-manager';
	}

	public function initialize(): void {
		// Blocks checkout fields are registered via the Additional Checkout Fields API in FrontendModule.
	}

	public function get_script_handles(): array {
		return array();
	}

	public function get_editor_script_handles(): array {
		return array();
	}

	public function get_script_data(): array {
		return array();
	}
}


