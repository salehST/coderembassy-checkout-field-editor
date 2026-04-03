<?php
namespace CoderEmbassy\CheckoutFieldEditor\Frontend;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use CoderEmbassy\CheckoutFieldEditor\Models\CA_Field;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;

class BlocksIntegration implements IntegrationInterface {
	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $visibilityResolver,
		private CustomerTypeManager $customerTypeManager
	) {}

	public function get_name(): string {
		return 'coderembassy-checkout-field-editor';
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
