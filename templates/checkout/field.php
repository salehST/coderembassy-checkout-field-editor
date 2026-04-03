<?php
/**
 * Checkout field template.
 *
 * @var array<string,mixed> $field
 */

defined( 'ABSPATH' ) || exit;

$cecfe_field_id    = isset( $field['id'] ) ? (string) $field['id'] : '';
$cecfe_field_label = isset( $field['label'] ) ? (string) $field['label'] : '';
$cecfe_value       = isset( $field['value'] ) ? (string) $field['value'] : '';
?>
<p class="form-row form-row-wide checkout-architect-field">
	<label for="<?php echo esc_attr( $cecfe_field_id ); ?>"><?php echo esc_html( $cecfe_field_label ); ?></label>
	<input type="text" id="<?php echo esc_attr( $cecfe_field_id ); ?>" name="<?php echo esc_attr( $cecfe_field_id ); ?>" value="<?php echo esc_attr( $cecfe_value ); ?>" />
</p>
