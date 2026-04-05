import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { saveSettings } from '../../api/client';
import Toggle from '../shared/Toggle';

export default function Settings() {
	const settings             = useSelect( ( s ) => s( store ).getSettings(), [] );
	const { setSettings }      = useDispatch( store );
	const [ form, setForm ]    = useState( {} );
	const [ saved, setSaved ]  = useState( false );

	useEffect( () => setForm( settings || {} ), [ settings ] );

	const set = ( k, v ) => setForm( ( p ) => ( { ...p, [ k ]: v } ) );

	const handleSave = async () => {
		const r = await saveSettings( form );
		setSettings( r?.data || form );
		setSaved( true );
		setTimeout( () => setSaved( false ), 2500 );
	};

	return (
		<div>
			<div className="ca-section-header">
				<div>
					<h2>{ __( 'Settings', 'coderembassy-checkout-field-editor' ) }</h2>
					<p className="ca-help-text">{ __( 'Configure global behaviour for Checkout Architect. Changes take effect after saving.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<button type="button" className="ca-btn ca-btn--primary" onClick={ handleSave }>
					{ saved ? __( '✓ Saved', 'coderembassy-checkout-field-editor' ) : __( 'Save Settings', 'coderembassy-checkout-field-editor' ) }
				</button>
			</div>

			<div className="ca-card">
				<h3 className="ca-card-title">{ __( 'General', 'coderembassy-checkout-field-editor' ) }</h3>
				<div className="ca-settings-row">
					<Toggle
						checked={ !! form.remove_data_on_uninstall }
						onChange={ () => set( 'remove_data_on_uninstall', ! form.remove_data_on_uninstall ) }
						label={ __( 'Remove all plugin data on uninstall', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Deletes custom fields and settings when the plugin is removed.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<div className="ca-settings-row">
					<Toggle
						checked={ !! form.enable_for_all_users }
						onChange={ () => set( 'enable_for_all_users', ! form.enable_for_all_users ) }
						label={ __( 'Enable for all users', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Show custom fields to all visitors including guests.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<div className="ca-settings-row">
					<Toggle
						checked={ !! form.show_on_thankyou_page }
						onChange={ () => set( 'show_on_thankyou_page', ! form.show_on_thankyou_page ) }
						label={ __( 'Show fields on thank-you page', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Display custom field values on the order confirmation page.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<div className="ca-settings-row">
					<Toggle
						checked={ !! form.hide_billing_if_single }
						onChange={ () => set( 'hide_billing_if_single', ! form.hide_billing_if_single ) }
						label={ __( 'Hide customer type switcher when only 1 type exists', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Automatically hides the type switcher if you only have one customer type configured.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<div className="ca-settings-row">
					<Toggle
						checked={ form.show_optional_label !== false }
						onChange={ () => set( 'show_optional_label', form.show_optional_label === false ) }
						label={ __( 'Show "(optional)" text on non-required fields', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Display "(optional)" next to the label of non-required custom fields on the checkout page. Disable to keep labels clean.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
			</div>

			<div className="ca-card">
				<h3 className="ca-card-title">{ __( 'Advanced', 'coderembassy-checkout-field-editor' ) }</h3>
				<div className="ca-settings-row">
					<Toggle
						checked={ !! form.log_field_errors }
						onChange={ () => set( 'log_field_errors', ! form.log_field_errors ) }
						label={ __( 'Log field validation errors', 'coderembassy-checkout-field-editor' ) }
					/>
					<p className="ca-setting-desc">{ __( 'Write field validation errors to the WordPress debug log.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
			</div>

			<div className="ca-card">
				<h3 className="ca-card-title">{ __( 'Custom CSS', 'coderembassy-checkout-field-editor' ) }</h3>
				<p className="ca-setting-desc">{ __( 'Add custom CSS that will be loaded on the checkout page alongside your custom fields.', 'coderembassy-checkout-field-editor' ) }</p>
				<textarea
					className="ca-input ca-textarea"
					rows="6"
					value={ form.custom_css || '' }
					placeholder="/* your custom styles */"
					onChange={ ( e ) => set( 'custom_css', e.target.value ) }
				/>
			</div>
		</div>
	);
}
