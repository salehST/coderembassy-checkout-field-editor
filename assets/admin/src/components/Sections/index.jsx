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
			setError( __( 'Section key and title are required.', 'coderembassy-checkout-field-editor' ) );
			return;
		}
		setBusy( true ); setError( '' );
		try {
			const r = await createSection( form );
			setSections( [ ...sections, r?.data ] );
			setShowModal( false );
			setForm( { section_key: '', title: '', position: 'after_billing' } );
		} catch {
			setError( __( 'Could not create section. Please try again.', 'coderembassy-checkout-field-editor' ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div>
			<div className="ca-section-header">
				<div>
					<h2>{ __( 'Sections', 'coderembassy-checkout-field-editor' ) }</h2>
					<p className="ca-help-text">{ __( 'Sections let you group custom fields under a heading on the checkout page — for example "Company Details" or "Delivery Preferences". Click', 'coderembassy-checkout-field-editor' ) } <strong>{ __( 'Add Section', 'coderembassy-checkout-field-editor' ) }</strong> { __( 'to create a new section, then assign fields to it in the Field Builder.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<button
					type="button"
					className="ca-btn ca-btn--primary"
					onClick={ () => { setShowModal( true ); setError( '' ); } }
				>
					{ __( '+ Add Section', 'coderembassy-checkout-field-editor' ) }
				</button>
			</div>

			{ sections.length === 0 && (
				<div className="ca-empty-state">
					<p>{ __( 'No sections yet. Click', 'coderembassy-checkout-field-editor' ) } <strong>{ __( '+ Add Section', 'coderembassy-checkout-field-editor' ) }</strong> { __( 'above to create your first section.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
			) }

			{ sections.map( ( s ) => <SectionCard key={ s.id } section={ s } /> ) }

			{ showModal && (
				<div className="ca-modal-overlay" onClick={ ( e ) => e.target === e.currentTarget && setShowModal( false ) }>
					<div className="ca-modal">
						<div className="ca-modal__header">
							<h3>{ __( 'Add Section', 'coderembassy-checkout-field-editor' ) }</h3>
							<button className="ca-modal__close" type="button" onClick={ () => setShowModal( false ) }>✕</button>
						</div>
						<div className="ca-modal__body">
							{ error && <div className="ca-alert ca-alert--error">{ error }</div> }
							<div className="ca-field-group">
								<label>{ __( 'Section Key', 'coderembassy-checkout-field-editor' ) } <span className="ca-muted-label">{ __( '(unique identifier, e.g. "company_details")', 'coderembassy-checkout-field-editor' ) }</span></label>
								<input
									className="ca-input"
									value={ form.section_key }
									onChange={ ( e ) => setForm( { ...form, section_key: e.target.value.toLowerCase().replace( /[^a-z0-9_]/g, '_' ) } ) }
									placeholder="e.g. company_details"
									autoFocus
								/>
							</div>
							<div className="ca-field-group">
								<label>{ __( 'Title', 'coderembassy-checkout-field-editor' ) } <span className="ca-muted-label">{ __( '(heading shown on checkout)', 'coderembassy-checkout-field-editor' ) }</span></label>
								<input
									className="ca-input"
									value={ form.title }
									onChange={ ( e ) => setForm( { ...form, title: e.target.value } ) }
									placeholder="e.g. Company Details"
								/>
							</div>
							<div className="ca-field-group">
								<label>{ __( 'Position', 'coderembassy-checkout-field-editor' ) }</label>
								<select
									className="ca-select"
									value={ form.position }
									onChange={ ( e ) => setForm( { ...form, position: e.target.value } ) }
								>
									<option value="before_billing">{ __( 'Before billing fields', 'coderembassy-checkout-field-editor' ) }</option>
									<option value="after_billing">{ __( 'After billing fields', 'coderembassy-checkout-field-editor' ) }</option>
									<option value="before_order_notes">{ __( 'Before order notes', 'coderembassy-checkout-field-editor' ) }</option>
									<option value="after_order_notes">{ __( 'After order notes', 'coderembassy-checkout-field-editor' ) }</option>
								</select>
							</div>
						</div>
						<div className="ca-modal__footer">
							<button type="button" className="ca-btn" onClick={ () => setShowModal( false ) }>{ __( 'Cancel', 'coderembassy-checkout-field-editor' ) }</button>
							<button type="button" className="ca-btn ca-btn--primary" onClick={ handleAdd } disabled={ busy }>
								{ busy ? __( 'Adding…', 'coderembassy-checkout-field-editor' ) : __( 'Add Section', 'coderembassy-checkout-field-editor' ) }
							</button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
