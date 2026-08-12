<?php
/**
 * Checkout field template.
 *
 * @var array<string,mixed> $field
 */

defined( 'ABSPATH' ) || exit;

$CECFM_field_id    = isset( $field['id'] ) ? (string) $field['id'] : '';
$CECFM_field_label = isset( $field['label'] ) ? (string) $field['label'] : '';
$CECFM_value       = isset( $field['value'] ) ? (string) $field['value'] : '';
?>
<p class="form-row form-row-wide cecfm-field">
	<label for="<?php echo esc_attr( $CECFM_field_id ); ?>"><?php echo esc_html( $CECFM_field_label ); ?></label>
	<input type="text" id="<?php echo esc_attr( $CECFM_field_id ); ?>" name="<?php echo esc_attr( $CECFM_field_id ); ?>" value="<?php echo esc_attr( $CECFM_value ); ?>" />
</p>


