import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { createSection } from '../../api/client';
import SectionCard from './SectionCard';

export default function Sections() {
	const sections = useSelect( ( s ) => s( store ).getSections(), [] );
	const { setSections } = useDispatch( store );

	const [ showModal, setShowModal ] = useState( false );
	const [ form, setForm ]           = useState( { section_key: '', title: '', position: 'after_billing' } );
	const [ busy, setBusy ]           = useState( false );
	const [ error, setError ]         = useState( '' );

	const handleAdd = async () => {
		if ( ! form.section_key.trim() || ! form.title.trim() ) {
			setError( __( 'Section key and title are required.', 'coderembassy-checkout-fields-manager' ) );
			return;
		}
		setBusy( true ); setError( '' );
		try {
			const r = await createSection( form );
			setSections( [ ...sections, r?.data ] );
			setShowModal( false );
			setForm( { section_key: '', title: '', position: 'after_billing' } );
		} catch {
			setError( __( 'Could not create section. Please try again.', 'coderembassy-checkout-fields-manager' ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div>
			<div className="cecfm-section-header">
				<div>
					<h2>{ __( 'Sections', 'coderembassy-checkout-fields-manager' ) }</h2>
					<p className="cecfm-help-text">{ __( 'Sections let you group custom fields under a heading on the checkout page — for example "Company Details" or "Delivery Preferences". Click', 'coderembassy-checkout-fields-manager' ) } <strong>{ __( 'Add Section', 'coderembassy-checkout-fields-manager' ) }</strong> { __( 'to create a new section, then assign fields to it in the Field Builder.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<button
					type="button"
					className="cecfm-btn cecfm-btn--primary"
					onClick={ () => { setShowModal( true ); setError( '' ); } }
				>
					{ __( '+ Add Section', 'coderembassy-checkout-fields-manager' ) }
				</button>
			</div>

			{ sections.length === 0 && (
				<div className="cecfm-empty-state">
					<p>{ __( 'No sections yet. Click', 'coderembassy-checkout-fields-manager' ) } <strong>{ __( '+ Add Section', 'coderembassy-checkout-fields-manager' ) }</strong> { __( 'above to create your first section.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
			) }

			{ sections.map( ( s ) => <SectionCard key={ s.id } section={ s } /> ) }

			{ showModal && (
				<div className="cecfm-modal-overlay" onClick={ ( e ) => e.target === e.currentTarget && setShowModal( false ) }>
					<div className="cecfm-modal">
						<div className="cecfm-modal__header">
							<h3>{ __( 'Add Section', 'coderembassy-checkout-fields-manager' ) }</h3>
							<button className="cecfm-modal__close" type="button" onClick={ () => setShowModal( false ) }>✕</button>
						</div>
						<div className="cecfm-modal__body">
							{ error && <div className="cecfm-alert cecfm-alert--error">{ error }</div> }
							<div className="cecfm-field-group">
								<label>{ __( 'Section Key', 'coderembassy-checkout-fields-manager' ) } <span className="cecfm-muted-label">{ __( '(unique identifier, e.g. "company_details")', 'coderembassy-checkout-fields-manager' ) }</span></label>
								<input
									className="cecfm-input"
									value={ form.section_key }
									onChange={ ( e ) => setForm( { ...form, section_key: e.target.value.toLowerCase().replace( /[^a-z0-9_]/g, '_' ) } ) }
									placeholder="e.g. company_details"
									autoFocus
								/>
							</div>
							<div className="cecfm-field-group">
								<label>{ __( 'Title', 'coderembassy-checkout-fields-manager' ) } <span className="cecfm-muted-label">{ __( '(heading shown on checkout)', 'coderembassy-checkout-fields-manager' ) }</span></label>
								<input
									className="cecfm-input"
									value={ form.title }
									onChange={ ( e ) => setForm( { ...form, title: e.target.value } ) }
									placeholder="e.g. Company Details"
								/>
							</div>
							<div className="cecfm-field-group">
								<label>{ __( 'Position', 'coderembassy-checkout-fields-manager' ) }</label>
								<select
									className="cecfm-select"
									value={ form.position }
									onChange={ ( e ) => setForm( { ...form, position: e.target.value } ) }
								>
									<option value="before_billing">{ __( 'Before billing fields', 'coderembassy-checkout-fields-manager' ) }</option>
									<option value="after_billing">{ __( 'After billing fields', 'coderembassy-checkout-fields-manager' ) }</option>
									<option value="before_order_notes">{ __( 'Before order notes', 'coderembassy-checkout-fields-manager' ) }</option>
									<option value="after_order_notes">{ __( 'After order notes', 'coderembassy-checkout-fields-manager' ) }</option>
								</select>
							</div>
						</div>
						<div className="cecfm-modal__footer">
							<button type="button" className="cecfm-btn" onClick={ () => setShowModal( false ) }>{ __( 'Cancel', 'coderembassy-checkout-fields-manager' ) }</button>
							<button type="button" className="cecfm-btn cecfm-btn--primary" onClick={ handleAdd } disabled={ busy }>
								{ busy ? __( 'Adding…', 'coderembassy-checkout-fields-manager' ) : __( 'Add Section', 'coderembassy-checkout-fields-manager' ) }
							</button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}

