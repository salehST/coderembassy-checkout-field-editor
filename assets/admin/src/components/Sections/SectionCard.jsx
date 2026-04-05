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
		if ( ! window.confirm( __( 'Delete this section? This cannot be undone.', 'coderembassy-checkout-field-editor' ) ) ) return;
		await deleteSection( section.id );
		setSections( sections.filter( ( s ) => s.id !== section.id ) );
	};

	return (
		<div className="ca-card">
			<div className="ca-card-grid">
				<div className="ca-field-group">
					<label>{ __( 'Section Key', 'coderembassy-checkout-field-editor' ) }</label>
					<input
						className="ca-input"
						value={ local.section_key }
						onChange={ ( e ) => setLocal( { ...local, section_key: e.target.value } ) }
					/>
				</div>
				<div className="ca-field-group">
					<label>{ __( 'Title', 'coderembassy-checkout-field-editor' ) }</label>
					<input
						className="ca-input"
						value={ local.title }
						onChange={ ( e ) => setLocal( { ...local, title: e.target.value } ) }
					/>
				</div>
			</div>
			<div className="ca-card-actions">
				<button type="button" className="ca-btn ca-btn--primary ca-btn--sm" onClick={ handleSave }>
					{ saved ? __( '✓ Saved', 'coderembassy-checkout-field-editor' ) : __( 'Save', 'coderembassy-checkout-field-editor' ) }
				</button>
				<button type="button" className="ca-btn ca-btn--danger ca-btn--sm" onClick={ handleDelete }>
					{ __( 'Delete', 'coderembassy-checkout-field-editor' ) }
				</button>
			</div>
		</div>
	);
}
