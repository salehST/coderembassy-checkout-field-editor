import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { getTypes, toggleCompanyType } from '../../api/client';
import Toggle from '../shared/Toggle';
import Spinner from '../shared/Spinner';

export default function CustomerTypes() {
	const { setTypes } = useDispatch( store );
	const [ companyEnabled, setCompanyEnabled ] = useState( true );
	const [ loading, setLoading ]               = useState( true );
	const [ saving, setSaving ]                 = useState( false );
	const [ saved, setSaved ]                   = useState( false );

	useEffect( () => {
		getTypes()
			.then( ( res ) => {
				const list = res?.data ?? [];
				const has = list.some( ( t ) => t.slug === 'company' );
				setCompanyEnabled( has );
				setTypes( list );
			} )
			.catch( () => setCompanyEnabled( true ) )
			.finally( () => setLoading( false ) );
	}, [ setTypes ] );

	const toggle = async () => {
		const next = ! companyEnabled;
		setSaving( true );
		try {
			await toggleCompanyType( next );
			setCompanyEnabled( next );
			const refreshed = await getTypes();
			setTypes( refreshed?.data ?? [] );
			setSaved( true );
			setTimeout( () => setSaved( false ), 2500 );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <Spinner />;

	return (
		<div>
			<div className="ca-section-header">
				<div>
					<h2>{ __( 'Customer Types', 'coderembassy-checkout-field-editor' ) }</h2>
					<p className="ca-help-text">
						{ __( 'Show different checkout fields depending on whether the customer is a private individual or a company.', 'coderembassy-checkout-field-editor' ) }
					</p>
				</div>
				{ saved && <span className="ca-badge ca-badge-active">{ __( '✓ Saved', 'coderembassy-checkout-field-editor' ) }</span> }
			</div>

			<div className="ca-card">
				<h3 className="ca-card-title">{ __( 'Available Types', 'coderembassy-checkout-field-editor' ) }</h3>

				<div className="ca-settings-row">
					<div>
						<strong>{ __( 'Private', 'coderembassy-checkout-field-editor' ) }</strong>
						<p className="ca-setting-desc">{ __( 'Default type — always active.', 'coderembassy-checkout-field-editor' ) }</p>
					</div>
					<Toggle checked={ true } disabled={ true } onChange={ () => {} } label="" />
				</div>

				<div className="ca-settings-row" style={ { marginTop: 16 } }>
					<div>
						<strong>{ __( 'Company', 'coderembassy-checkout-field-editor' ) }</strong>
						<p className="ca-setting-desc">
							{ __( 'Enable to show a customer type switcher on checkout. When active, customers can switch between Private and Company.', 'coderembassy-checkout-field-editor' ) }
						</p>
					</div>
					<Toggle
						checked={ companyEnabled }
						onChange={ toggle }
						disabled={ saving }
						label=""
					/>
				</div>
			</div>
		</div>
	);
}
