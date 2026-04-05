<?php
namespace CoderEmbassy\CheckoutFieldEditor\Models;

defined( 'ABSPATH' ) || exit;

class CA_CustomerType {
	public int $id = 0;
	public string $slug = '';
	public string $label = '';
	public string $description = '';
	public bool $is_default = false;
	public int $priority = 10;
	public array $meta = array();
	public string $created_at = '';

	public function toArray(): array {
		return array(
			'id'          => $this->id,
			'slug'        => $this->slug,
			'label'       => $this->label,
			'description' => $this->description,
			'is_default'  => $this->is_default,
			'priority'    => $this->priority,
			'meta'        => wp_json_encode( $this->meta ),
			'created_at'  => $this->created_at,
		);
	}

	public static function fromArray( array $data ): static {
		$model              = new static();
		$model->id          = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$model->slug        = (string) ( $data['slug'] ?? '' );
		$model->label       = (string) ( $data['label'] ?? '' );
		$model->description = (string) ( $data['description'] ?? '' );
		$model->is_default  = ! empty( $data['is_default'] );
		$model->priority    = isset( $data['priority'] ) ? (int) $data['priority'] : 10;
		$model->meta        = self::decodeJsonArray( $data['meta'] ?? array() );
		$model->created_at  = (string) ( $data['created_at'] ?? '' );

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
