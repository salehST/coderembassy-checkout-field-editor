<?php
namespace CoderEmbassy\CheckoutFieldEditor\Models;

defined( 'ABSPATH' ) || exit;

class CA_Section {
	public int $id = 0;
	public string $section_key = '';
	public string $title = '';
	public string $description = '';
	public string $position = 'before_order_notes';
	public int $priority = 10;
	public bool $enabled = true;
	public array $conditions = array();
	public array $customer_types = array();
	public array $meta = array();
	public string $created_at = '';

	public function toArray(): array {
		return array(
			'id'             => $this->id,
			'section_key'    => $this->section_key,
			'title'          => $this->title,
			'description'    => $this->description,
			'position'       => $this->position,
			'priority'       => $this->priority,
			'enabled'        => $this->enabled,
			'conditions'     => wp_json_encode( $this->conditions ),
			'customer_types' => wp_json_encode( $this->customer_types ),
			'meta'           => wp_json_encode( $this->meta ),
			'created_at'     => $this->created_at,
		);
	}

	public static function fromArray( array $data ): static {
		$model                 = new static();
		$model->id             = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$model->section_key    = (string) ( $data['section_key'] ?? '' );
		$model->title          = (string) ( $data['title'] ?? '' );
		$model->description    = (string) ( $data['description'] ?? '' );
		$model->position       = (string) ( $data['position'] ?? 'before_order_notes' );
		$model->priority       = isset( $data['priority'] ) ? (int) $data['priority'] : 10;
		$model->enabled        = ! array_key_exists( 'enabled', $data ) || ! empty( $data['enabled'] );
		$model->conditions     = self::decodeJsonArray( $data['conditions'] ?? array() );
		$model->customer_types = self::decodeJsonArray( $data['customer_types'] ?? array() );
		$model->meta           = self::decodeJsonArray( $data['meta'] ?? array() );
		$model->created_at     = (string) ( $data['created_at'] ?? '' );

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
