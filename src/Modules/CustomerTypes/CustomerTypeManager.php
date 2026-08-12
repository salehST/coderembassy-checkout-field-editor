<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_CustomerType;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Licensing\FeatureGate;

class CustomerTypeManager {
	public function __construct(
		private CustomerTypeRepository $repository,
		private ?FieldRepository $fieldRepository = null
	) {}

	public function getCurrentType(): ?CECFM_CustomerType {
		$session_slug = '';
		if ( function_exists( 'WC' ) && \WC()->session ) {
			$session_slug = (string) \WC()->session->get( 'cecfm_customer_type', '' );
		}

		$user_slug = '';
		if ( \is_user_logged_in() ) {
			$user_slug = (string) \get_user_meta( \get_current_user_id(), 'cecfm_customer_type', true );
		}

		$current_slug = $session_slug ?: $user_slug;
		if ( '' === $current_slug ) {
			$default = $this->repository->getDefault();
			if ( $default instanceof CECFM_CustomerType ) {
				$current_slug = $default->slug;
			}
		}

		if ( '' === $current_slug ) {
			// Only fall back to a type this install actually manages.
			$all = $this->getAllTypes();
			if ( ! empty( $all ) ) {
				$current_slug = $all[0]->slug;
			}
		}

		if ( '' === $current_slug ) {
			return null;
		}

		$context      = array( 'source' => 'runtime' );
		$filtered_slug = (string) \apply_filters( 'cecfm_current_customer_type', $current_slug, $context );

		// A session can still hold a type an add-on created. Without the add-on
		// that type is not manageable here, so fall back to the default rather
		// than leaving a Pro-only type active.
		$allowed = FeatureGate::allowedCustomerTypes();
		if ( null !== $allowed && ! in_array( $filtered_slug, $allowed, true ) ) {
			$default       = $this->repository->getDefault();
			$filtered_slug = $default instanceof CECFM_CustomerType ? $default->slug : '';
		}

		if ( '' === $filtered_slug ) {
			return null;
		}

		return $this->repository->findBySlug( $filtered_slug );
	}

	/**
	 * @return CECFM_CustomerType[]
	 */
	/**
	 * Types this install may present to shoppers.
	 *
	 * Gated for the same reason the admin list is: without the add-on only the
	 * built-in pair is offered, so a checkout never shows a type this install
	 * cannot manage. Rows an add-on created stay in the database untouched.
	 *
	 * @return array<int, CECFM_CustomerType>
	 */
	public function getAllTypes(): array {
		$types   = $this->repository->findAll();
		$allowed = FeatureGate::allowedCustomerTypes();

		if ( null === $allowed ) {
			return $types;
		}

		return array_values(
			array_filter(
				$types,
				static fn ( $type ): bool => in_array( $type->slug, $allowed, true )
			)
		);
	}

	public function getCurrentTypeSlug(): string {
		$current = $this->getCurrentType();
		return $current?->slug ?? '';
	}

	public function setCurrentType( string $slug ): void {
		$old_slug = '';
		$current  = $this->getCurrentType();
		if ( $current instanceof CECFM_CustomerType ) {
			$old_slug = $current->slug;
		}

		if ( function_exists( 'WC' ) && \WC()->session ) {
			\WC()->session->set( 'cecfm_customer_type', $slug );
		}

		if ( \is_user_logged_in() ) {
			\update_user_meta( \get_current_user_id(), 'cecfm_customer_type', $slug );
		}

		\do_action( 'cecfm_customer_type_changed', $slug, $old_slug );
	}

	public function getFieldsForType( string $slug ): array {
		if ( ! $this->fieldRepository instanceof FieldRepository ) {
			return array();
		}

		$fields = $this->fieldRepository->findAll( array( 'enabled' => true ) );
		$result = array();

		foreach ( $fields as $field ) {
			if ( $this->fieldBelongsToType( $field, $slug ) ) {
				$result[] = $field;
			}
		}

		return $result;
	}

	public function fieldBelongsToType( CECFM_Field $field, string $type_slug ): bool {
		if ( empty( $field->customer_types ) ) {
			return true;
		}

		return in_array( $type_slug, $field->customer_types, true );
	}

	public function getTypeSelectorConfig(): array {
		$types  = $this->getAllTypes();
		$items  = array();
		$current = $this->getCurrentType();

		foreach ( $types as $type ) {
			$items[] = array(
				'slug'  => $type->slug,
				'label' => $type->label,
			);
		}

		return array(
			'field_key' => 'customer_type_selector',
			'type'      => 'select',
			'label'     => \__( 'Customer Type', 'coderembassy-checkout-fields-manager' ),
			'options'   => $items,
			'current'   => $current?->slug ?? '',
		);
	}

	public function isTypeCreationAllowed(): bool {
		return count( $this->repository->findAll() ) < 2;
	}

	public function getLabelForType( CECFM_Field $field, string $type_slug ): string {
		if ( isset( $field->meta['labels_by_type'][ $type_slug ] ) && is_string( $field->meta['labels_by_type'][ $type_slug ] ) ) {
			return $field->meta['labels_by_type'][ $type_slug ];
		}

		return $field->label;
	}
}


