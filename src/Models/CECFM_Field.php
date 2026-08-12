<?php
namespace CoderEmbassy\CheckoutFieldsManager\Models;

defined( 'ABSPATH' ) || exit;

class CECFM_Field {
	public int $id = 0;
	public int $section_id = 0;
	public string $field_key = '';
	public string $type = '';
	public string $label = '';
	public string $placeholder = '';
	public string $description = '';
	public string $section = 'billing';
	public string $position = 'after_billing';
	public int $priority = 10;
	public string $width = 'full';
	public bool $required = false;
	public bool $enabled = true;
	public array $conditions            = array();
	public array $required_conditions  = array();
	public array $customer_types       = array();
	public array $pricing_rules        = array();
	public array $validation_rules     = array();
	public array $options = array();
	public array $meta = array();
	public string $created_at = '';
	public string $updated_at = '';

	public function toArray(): array {
		return array(
			'id'                  => $this->id,
			'section_id'          => $this->section_id,
			'field_key'           => $this->field_key,
			'type'                => $this->type,
			'label'               => $this->label,
			'placeholder'         => $this->placeholder,
			'description'         => $this->description,
			'section'             => $this->section,
			'position'            => $this->position,
			'priority'            => $this->priority,
			'width'               => $this->width,
			'required'            => $this->required,
			'enabled'             => $this->enabled,
			'conditions'          => \wp_json_encode( $this->conditions ),
			'required_conditions' => \wp_json_encode( $this->required_conditions ),
			'customer_types'      => \wp_json_encode( $this->customer_types ),
			'pricing_rules'       => \wp_json_encode( $this->pricing_rules ),
			'validation_rules'    => \wp_json_encode( $this->validation_rules ),
			'options'             => \wp_json_encode( $this->options ),
			'meta'                => \wp_json_encode( $this->meta ),
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		);
	}

	public static function fromArray( array $data ): static {
		$model                   = new static();
		$model->id               = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$model->section_id       = isset( $data['section_id'] ) ? (int) $data['section_id'] : 0;
		$model->field_key        = (string) ( $data['field_key'] ?? '' );
		$model->type             = (string) ( $data['type'] ?? '' );
		$model->label            = (string) ( $data['label'] ?? '' );
		$model->placeholder      = (string) ( $data['placeholder'] ?? '' );
		$model->description      = (string) ( $data['description'] ?? '' );
		$model->section          = (string) ( $data['section'] ?? 'billing' );
		$model->position         = (string) ( $data['position'] ?? 'after_billing' );
		$model->priority         = isset( $data['priority'] ) ? (int) $data['priority'] : 10;
		$model->width            = (string) ( $data['width'] ?? 'full' );
		$model->required         = ! empty( $data['required'] );
		$model->enabled          = ! array_key_exists( 'enabled', $data ) || ! empty( $data['enabled'] );
		$model->conditions            = self::decodeJsonArray( $data['conditions'] ?? array() );
		$model->required_conditions   = self::decodeJsonArray( $data['required_conditions'] ?? array() );
		$model->customer_types        = self::decodeJsonArray( $data['customer_types'] ?? array() );
		$model->pricing_rules         = self::decodeJsonArray( $data['pricing_rules'] ?? array() );
		$model->validation_rules      = self::decodeJsonArray( $data['validation_rules'] ?? array() );
		$model->options          = self::decodeJsonArray( $data['options'] ?? array() );
		$model->meta             = self::decodeJsonArray( $data['meta'] ?? array() );
		$model->created_at       = (string) ( $data['created_at'] ?? '' );
		$model->updated_at       = (string) ( $data['updated_at'] ?? '' );

		return $model;
	}

	public static function fromRow( array $row ): static {
		return static::fromArray( $row );
	}

	private static function decodeJsonArray( mixed $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}


