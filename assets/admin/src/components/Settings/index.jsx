import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { saveSettings } from '../../api/client';
import Toggle from '../shared/Toggle';
import { getSettingsPanels, getSettingsTabs } from '../../extensions';

export default function Settings() {
	const settings             = useSelect( ( s ) => s( store ).getSettings(), [] );
	const { setSettings }      = useDispatch( store );
	const [ form, setForm ]    = useState( {} );
	const [ saved, setSaved ]  = useState( false );

	// An add-on can contribute whole tabs. With none registered this screen is
	// a plain stack of cards, which is all the free plugin needs.
	const addonTabs = getSettingsTabs();
	const tabbed    = addonTabs.length > 0;

	const [ activeTab, setActiveTab ] = useState( 'general' );

	useEffect( () => setForm( settings || {} ), [ settings ] );

	const set = ( k, v ) => setForm( ( p ) => ( { ...p, [ k ]: v } ) );

	const panelsFor = ( tab ) => getSettingsPanels( tab ).map( ( Panel, i ) => (
		<Panel key={ `${ tab }-${ i }` } settings={ form } setSetting={ set } />
	) );

	const handleSave = async () => {
		const r = await saveSettings( form );
		setSettings( r?.data || form );
		setSaved( true );
		setTimeout( () => setSaved( false ), 2500 );
	};

	const generalCard = (
		<div className="cecfm-card">
				<h3 className="cecfm-card-title">{ __( 'General', 'coderembassy-checkout-fields-manager' ) }</h3>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ !! form.remove_data_on_uninstall }
						onChange={ () => set( 'remove_data_on_uninstall', ! form.remove_data_on_uninstall ) }
						label={ __( 'Remove all plugin data on uninstall', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Deletes custom fields and settings when the plugin is removed.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ !! form.enable_for_all_users }
						onChange={ () => set( 'enable_for_all_users', ! form.enable_for_all_users ) }
						label={ __( 'Enable for all users', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Show custom fields to all visitors including guests.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ !! form.show_on_thankyou_page }
						onChange={ () => set( 'show_on_thankyou_page', ! form.show_on_thankyou_page ) }
						label={ __( 'Show fields on thank-you page', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Display custom field values on the order confirmation page.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ !! form.hide_billing_if_single }
						onChange={ () => set( 'hide_billing_if_single', ! form.hide_billing_if_single ) }
						label={ __( 'Hide customer type switcher when only 1 type exists', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Automatically hides the type switcher if you only have one customer type configured.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ form.show_optional_label !== false }
						onChange={ () => set( 'show_optional_label', form.show_optional_label === false ) }
						label={ __( 'Show "(optional)" text on non-required fields', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Display "(optional)" next to the label of non-required custom fields on the checkout page. Disable to keep labels clean.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
		</div>
	);

	const advancedCard = (
		<div className="cecfm-card">
				<h3 className="cecfm-card-title">{ __( 'Advanced', 'coderembassy-checkout-fields-manager' ) }</h3>
				<div className="cecfm-settings-row">
					<Toggle
						checked={ !! form.log_field_errors }
						onChange={ () => set( 'log_field_errors', ! form.log_field_errors ) }
						label={ __( 'Log field validation errors', 'coderembassy-checkout-fields-manager' ) }
					/>
					<p className="cecfm-setting-desc">{ __( 'Write field validation errors to the WordPress debug log.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
		</div>
	);

	const cssCard = (
		<div className="cecfm-card">
				<h3 className="cecfm-card-title">{ __( 'Custom CSS', 'coderembassy-checkout-fields-manager' ) }</h3>
				<p className="cecfm-setting-desc">{ __( 'Add custom CSS that will be loaded on the checkout page alongside your custom fields.', 'coderembassy-checkout-fields-manager' ) }</p>
				<textarea
					className="cecfm-input cecfm-textarea"
					rows="6"
					value={ form.custom_css || '' }
					placeholder="/* your custom styles */"
					onChange={ ( e ) => set( 'custom_css', e.target.value ) }
				/>
		</div>
	);

	// The add-on's tabs sit between General and the two housekeeping tabs, which
	// is the order the standalone Pro plugin used.
	const tabs = [
		{ id: 'general', label: __( 'General', 'coderembassy-checkout-fields-manager' ) },
		...addonTabs.map( ( t ) => ( { id: t.id, label: t.label } ) ),
		{ id: 'advanced', label: __( 'Advanced', 'coderembassy-checkout-fields-manager' ) },
		{ id: 'custom_css', label: __( 'Custom CSS', 'coderembassy-checkout-fields-manager' ) },
	];

	const renderActiveTab = () => {
		if ( 'general' === activeTab ) {
			return <>{ generalCard }{ panelsFor( 'general' ) }</>;
		}
		if ( 'advanced' === activeTab ) {
			return <>{ advancedCard }{ panelsFor( 'advanced' ) }</>;
		}
		if ( 'custom_css' === activeTab ) {
			return <>{ cssCard }{ panelsFor( 'custom_css' ) }</>;
		}

		const addon = addonTabs.find( ( t ) => t.id === activeTab );

		return addon
			? <>
				<addon.render settings={ form } setSetting={ set } />
				{ panelsFor( addon.id ) }
			</>
			: null;
	};

	return (
		<div>
			<div className="cecfm-section-header">
				<div>
					<h2>{ __( 'Settings', 'coderembassy-checkout-fields-manager' ) }</h2>
					<p className="cecfm-help-text">{ __( 'Configure global behaviour for Checkout Fields Manager. Changes take effect after saving.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<button type="button" className="cecfm-btn cecfm-btn--primary" onClick={ handleSave }>
					{ saved ? __( '✓ Saved', 'coderembassy-checkout-fields-manager' ) : __( 'Save Settings', 'coderembassy-checkout-fields-manager' ) }
				</button>
			</div>

			{ ! tabbed && (
				<>
					{ generalCard }
					{ advancedCard }
					{ cssCard }
					{ getSettingsPanels().map( ( Panel, i ) => (
						<Panel key={ i } settings={ form } setSetting={ set } />
					) ) }
				</>
			) }

			{ tabbed && (
				<>
					<div className="cecfm-settings-tabs" role="tablist">
						{ tabs.map( ( t ) => (
							<button
								key={ t.id }
								type="button"
								role="tab"
								aria-selected={ activeTab === t.id }
								className={ `cecfm-settings-tab${ activeTab === t.id ? ' is-active' : '' }` }
								onClick={ () => setActiveTab( t.id ) }
							>
								{ t.label }
							</button>
						) ) }
					</div>
					{ renderActiveTab() }
				</>
			) }
		</div>
	);
}

