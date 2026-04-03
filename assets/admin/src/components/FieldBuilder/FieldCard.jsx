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
		if ( ! window.confirm( __( 'Delete this field? This cannot be undone.', 'coderembassy-checkout-field-editor' ) ) ) return;
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
		<div ref={ setNodeRef } style={ style } className={ `ca-field-card ${ field.enabled === false ? 'ca-field-card--disabled' : '' }` }>
			<span className="ca-drag-handle" { ...attributes } { ...listeners } title="Drag to reorder">
				⠿
			</span>

			<div className="ca-field-card__info">
				<span className="ca-badge ca-badge--type">{ field.type }</span>
				<span className="ca-field-card__label">{ field.label || <em className="ca-muted">{ __( '(no label)', 'coderembassy-checkout-field-editor' ) }</em> }</span>
				{ field.field_key && <code className="ca-field-card__key">{ field.field_key }</code> }
			</div>

			<div className="ca-field-card__actions">
				<button
					type="button"
					className="ca-btn ca-btn--ghost ca-btn--sm"
					onClick={ handleToggle }
					title={ field.enabled === false ? __( 'Enable field', 'coderembassy-checkout-field-editor' ) : __( 'Disable field', 'coderembassy-checkout-field-editor' ) }
				>
					{ field.enabled === false ? __( 'Enable', 'coderembassy-checkout-field-editor' ) : __( 'Disable', 'coderembassy-checkout-field-editor' ) }
				</button>
				<button
					type="button"
					className="ca-btn ca-btn--secondary ca-btn--sm"
					onClick={ () => setEditingFieldId( field.id ) }
				>
					{ __( 'Edit', 'coderembassy-checkout-field-editor' ) }
				</button>
				<button
					type="button"
					className="ca-btn ca-btn--danger ca-btn--sm"
					onClick={ handleDelete }
				>
					{ __( 'Delete', 'coderembassy-checkout-field-editor' ) }
				</button>
			</div>
		</div>
	);
}
