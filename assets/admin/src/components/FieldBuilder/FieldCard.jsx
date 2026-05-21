import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { deleteField, updateField } from '../../api/client';

export default function FieldCard( { field } ) {
	const { attributes, listeners, setNodeRef, transform, transition } = useSortable( { id: field.id } );
	const style = { transform: CSS.Transform.toString( transform ), transition };
	const fields = useSelect( ( s ) => s( store ).getFields(), [] );
	const { setFields, setEditingFieldId } = useDispatch( store );

	const handleDelete = async () => {
		if ( ! window.confirm( __( 'Delete this field? This cannot be undone.', 'coderembassy-checkout-fields-manager' ) ) ) return;
		await deleteField( field.id );
		setFields( fields.filter( ( f ) => f.id !== field.id ) );
	};

	const handleToggle = async () => {
		const res = await updateField( field.id, { enabled: ! field.enabled } );
		setFields( fields.map( ( f ) => (
			f.id === field.id ? ( res?.data || { ...field, enabled: ! field.enabled } ) : f
		) ) );
	};

	return (
		<div ref={ setNodeRef } style={ style } className={ `cecfm-field-card ${ field.enabled === false ? 'cecfm-field-card--disabled' : '' }` }>
			<span className="cecfm-drag-handle" { ...attributes } { ...listeners } title="Drag to reorder">
				⠿
			</span>

			<div className="cecfm-field-card__info">
				<span className="cecfm-badge cecfm-badge--type">{ field.type }</span>
				<span className="cecfm-field-card__label">{ field.label || <em className="cecfm-muted">{ __( '(no label)', 'coderembassy-checkout-fields-manager' ) }</em> }</span>
				{ field.field_key && <code className="cecfm-field-card__key">{ field.field_key }</code> }
			</div>

			<div className="cecfm-field-card__actions">
				<button
					type="button"
					className="cecfm-btn cecfm-btn--ghost cecfm-btn--sm"
					onClick={ handleToggle }
					title={ field.enabled === false ? __( 'Enable field', 'coderembassy-checkout-fields-manager' ) : __( 'Disable field', 'coderembassy-checkout-fields-manager' ) }
				>
					{ field.enabled === false ? __( 'Enable', 'coderembassy-checkout-fields-manager' ) : __( 'Disable', 'coderembassy-checkout-fields-manager' ) }
				</button>
				<button
					type="button"
					className="cecfm-btn cecfm-btn--secondary cecfm-btn--sm"
					onClick={ () => setEditingFieldId( field.id ) }
				>
					{ __( 'Edit', 'coderembassy-checkout-fields-manager' ) }
				</button>
				<button
					type="button"
					className="cecfm-btn cecfm-btn--danger cecfm-btn--sm"
					onClick={ handleDelete }
				>
					{ __( 'Delete', 'coderembassy-checkout-fields-manager' ) }
				</button>
			</div>
		</div>
	);
}

