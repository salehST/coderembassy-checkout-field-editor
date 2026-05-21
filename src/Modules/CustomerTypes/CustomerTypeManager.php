<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_CustomerType;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;

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
			$all = $this->repository->findAll();
			if ( ! empty( $all ) ) {
				$current_slug = $all[0]->slug;
			}
		}

		if ( '' === $current_slug ) {
			return null;
		}

		$context      = array( 'source' => 'runtime' );
		$filtered_slug = (string) \apply_filters( 'CECFM_current_customer_type', $current_slug, $context );
		return $this->repository->findBySlug( $filtered_slug );
	}

	/**
	 * @return CECFM_CustomerType[]
	 */
	public function getAllTypes(): array {
		return $this->repository->findAll();
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

		\do_action( 'CECFM_customer_type_changed', $slug, $old_slug );
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
		$types  = $this->repository->findAll();
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


