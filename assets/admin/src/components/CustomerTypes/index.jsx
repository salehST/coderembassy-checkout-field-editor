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
			<div className="cecfm-section-header">
				<div>
					<h2>{ __( 'Customer Types', 'coderembassy-checkout-fields-manager' ) }</h2>
					<p className="cecfm-help-text">
						{ __( 'Show different checkout fields depending on whether the customer is a private individual or a company.', 'coderembassy-checkout-fields-manager' ) }
					</p>
				</div>
				{ saved && <span className="cecfm-badge cecfm-badge-active">{ __( '✓ Saved', 'coderembassy-checkout-fields-manager' ) }</span> }
			</div>

			<div className="cecfm-card">
				<h3 className="cecfm-card-title">{ __( 'Available Types', 'coderembassy-checkout-fields-manager' ) }</h3>

				<div className="cecfm-settings-row">
					<div>
						<strong>{ __( 'Private', 'coderembassy-checkout-fields-manager' ) }</strong>
						<p className="cecfm-setting-desc">{ __( 'Default type — always active.', 'coderembassy-checkout-fields-manager' ) }</p>
					</div>
					<Toggle checked={ true } disabled={ true } onChange={ () => {} } label="" />
				</div>

				<div className="cecfm-settings-row" style={ { marginTop: 16 } }>
					<div>
						<strong>{ __( 'Company', 'coderembassy-checkout-fields-manager' ) }</strong>
						<p className="cecfm-setting-desc">
							{ __( 'Enable to show a customer type switcher on checkout. When active, customers can switch between Private and Company.', 'coderembassy-checkout-fields-manager' ) }
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

