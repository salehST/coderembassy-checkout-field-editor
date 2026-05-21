import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { deleteSection, updateSection } from '../../api/client';

export default function SectionCard( { section } ) {
	const [ local, setLocal ] = useState( section );
	const [ saved, setSaved ] = useState( false );
	const sections             = useSelect( ( s ) => s( store ).getSections(), [] );
	const { setSections }      = useDispatch( store );

	const handleSave = async () => {
		const r = await updateSection( section.id, local );
		setSections( sections.map( ( s ) => ( s.id === section.id ? ( r?.data || local ) : s ) ) );
		setSaved( true );
		setTimeout( () => setSaved( false ), 2000 );
	};

	const handleDelete = async () => {
		if ( ! window.confirm( __( 'Delete this section? This cannot be undone.', 'coderembassy-checkout-fields-manager' ) ) ) return;
		await deleteSection( section.id );
		setSections( sections.filter( ( s ) => s.id !== section.id ) );
	};

	return (
		<div className="cecfm-card">
			<div className="cecfm-card-grid">
				<div className="cecfm-field-group">
					<label>{ __( 'Section Key', 'coderembassy-checkout-fields-manager' ) }</label>
					<input
						className="cecfm-input"
						value={ local.section_key }
						onChange={ ( e ) => setLocal( { ...local, section_key: e.target.value } ) }
					/>
				</div>
				<div className="cecfm-field-group">
					<label>{ __( 'Title', 'coderembassy-checkout-fields-manager' ) }</label>
					<input
						className="cecfm-input"
						value={ local.title }
						onChange={ ( e ) => setLocal( { ...local, title: e.target.value } ) }
					/>
				</div>
			</div>
			<div className="cecfm-card-actions">
				<button type="button" className="cecfm-btn cecfm-btn--primary cecfm-btn--sm" onClick={ handleSave }>
					{ saved ? __( '✓ Saved', 'coderembassy-checkout-fields-manager' ) : __( 'Save', 'coderembassy-checkout-fields-manager' ) }
				</button>
				<button type="button" className="cecfm-btn cecfm-btn--danger cecfm-btn--sm" onClick={ handleDelete }>
					{ __( 'Delete', 'coderembassy-checkout-fields-manager' ) }
				</button>
			</div>
		</div>
	);
}

