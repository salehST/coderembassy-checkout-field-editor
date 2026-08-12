import { __ } from '@wordpress/i18n';
import { ProTeaser } from './shared/Pro';

/**
 * Teasers for tabs the add-on provides.
 *
 * When the add-on is active it replaces these through the SlotFill registry in
 * App.jsx, so the free plugin never needs to know how the real screens work.
 */

export function ConditionsTeaser() {
	return (
		<ProTeaser
			title={ __( 'Conditional Logic', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Show fields only when they are relevant, based on what is in the cart and who is checking out.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Ten rule types: cart total, product, category, country, user role, coupon, payment method, shipping method, customer type, and other field values', 'coderembassy-checkout-fields-manager' ),
				__( 'Combine rules into AND/OR groups for precise targeting', 'coderembassy-checkout-fields-manager' ),
				__( 'Make a field required only in certain situations', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function PricingTeaser() {
	return (
		<ProTeaser
			title={ __( 'Pricing & Fees', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Turn checkout fields into revenue by attaching a cost to what customers select.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Fixed fees, percentage fees, or customer-entered amounts', 'coderembassy-checkout-fields-manager' ),
				__( 'Charge for gift wrapping, express handling, or installation', 'coderembassy-checkout-fields-manager' ),
				__( 'Fees appear in the cart totals and on the order automatically', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function TemplatesTeaser() {
	return (
		<ProTeaser
			title={ __( 'Templates', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Save a whole checkout configuration and reuse it.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Snapshot every field, section and rule as a named template', 'coderembassy-checkout-fields-manager' ),
				__( 'Apply a saved setup to another store in one click', 'coderembassy-checkout-fields-manager' ),
				__( 'Roll back to a previous configuration when an experiment does not work', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function ImportExportTeaser() {
	return (
		<ProTeaser
			title={ __( 'Import / Export', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Move your checkout setup between sites as a JSON file.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Export fields, sections and settings in one file', 'coderembassy-checkout-fields-manager' ),
				__( 'Import into staging or a client site without rebuilding by hand', 'coderembassy-checkout-fields-manager' ),
				__( 'Choose exactly what to overwrite on import', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function PreviewTeaser() {
	return (
		<ProTeaser
			title={ __( 'Checkout Preview', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'See your checkout the way a specific customer will, without placing a test order.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Preview by customer type, country, or cart contents', 'coderembassy-checkout-fields-manager' ),
				__( 'Confirm your conditional rules fire before going live', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function AnalyticsTeaser() {
	return (
		<ProTeaser
			title={ __( 'Analytics', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Find out which checkout fields help conversion and which get abandoned.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Completion rate per field', 'coderembassy-checkout-fields-manager' ),
				__( 'Spot the fields customers skip or drop out on', 'coderembassy-checkout-fields-manager' ),
				__( 'Revenue attributed to paid field options', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}

export function SectionsTeaser() {
	return (
		<ProTeaser
			title={ __( 'Sections', 'coderembassy-checkout-fields-manager' ) }
			intro={ __( 'Group custom fields under their own heading on the checkout page — "Company Details", "Delivery Preferences", and so on.', 'coderembassy-checkout-fields-manager' ) }
			bullets={ [
				__( 'Unlimited sections, each with its own heading and position', 'coderembassy-checkout-fields-manager' ),
				__( 'Assign any custom field to a section', 'coderembassy-checkout-fields-manager' ),
				__( 'Show a section only to certain customer types', 'coderembassy-checkout-fields-manager' ),
				__( 'Show or hide a section with conditional rules', 'coderembassy-checkout-fields-manager' ),
			] }
		/>
	);
}
