import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	DndContext,
	closestCenter,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
	arrayMove,
	useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { store } from '../../store';
import { getNativeFields, saveNativeFields } from '../../api/client';

const GROUPS = [
	{ key: 'billing',  label: __( 'Billing',  'coderembassy-checkout-field-editor' ) },
	{ key: 'shipping', label: __( 'Shipping', 'coderembassy-checkout-field-editor' ) },
	{ key: 'order',    label: __( 'Additional', 'coderembassy-checkout-field-editor' ) },
];

const WIDTH_OPTIONS = [
	{ value: 'full',       label: __( 'Full width',   'coderembassy-checkout-field-editor' ) },
	{ value: 'half_first', label: __( 'Half (left)',  'coderembassy-checkout-field-editor' ) },
	{ value: 'half_last',  label: __( 'Half (right)', 'coderembassy-checkout-field-editor' ) },
];

function FieldRow( { field, customerTypes, onUpdate } ) {
	const [ editing, setEditing ]     = useState( false );
	const [ draft, setDraft ]         = useState( {} );

	const { attributes, listeners, setNodeRef, transform, transition } =
		useSortable( { id: field.field_key } );
	const style = { transform: CSS.Transform.toString( transform ), transition };

	const openEdit = () => {
		setDraft( {
			label:          field.label,
			placeholder:    field.placeholder || '',
			required:       field.required,
			width:          field.width || 'full',
			customer_types: Array.isArray( field.customer_types ) ? [ ...field.customer_types ] : [],
		} );
		setEditing( true );
	};

	const set = ( k, v ) => setDraft( ( d ) => ( { ...d, [ k ]: v } ) );

	const toggleType = ( slug ) =>
		set(
			'customer_types',
			draft.customer_types.includes( slug )
				? draft.customer_types.filter( ( s ) => s !== slug )
				: [ ...draft.customer_types, slug ]
		);

	const applyEdit = () => {
		onUpdate( { ...field, ...draft } );
		setEditing( false );
	};

	const widthLabel = WIDTH_OPTIONS.find( ( w ) => w.value === ( field.width || 'full' ) )?.label || __( 'Full width', 'coderembassy-checkout-field-editor' );

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `ca-card ca-native-row${ ! field.enabled ? ' ca-native-row--disabled' : '' }` }
		>
			<div className="ca-native-row__main">
				<span className="ca-drag-handle" { ...attributes } { ...listeners }>⠿</span>

				<div className="ca-native-row__info">
					<strong>{ field.label }</strong>
					<code style={ { fontSize: 11, color: 'var(--muted)', marginLeft: 8 } }>{ field.field_key }</code>
					{ field.type !== 'text' && (
						<span className="ca-badge" style={ { marginLeft: 6 } }>{ field.type }</span>
					) }
					<span className="ca-badge" style={ { marginLeft: 6, background: 'var(--card-2)' } }>{ widthLabel }</span>
				</div>

				<div className="ca-native-row__actions">
					{ field.required && (
						<span className="ca-badge ca-badge--warning">
							{ __( 'Required', 'coderembassy-checkout-field-editor' ) }
						</span>
					) }
					{ Array.isArray( field.customer_types ) && field.customer_types.length > 0 && (
						<span className="ca-badge" title={ field.customer_types.join( ', ' ) }>
							{ __( 'Type restricted', 'coderembassy-checkout-field-editor' ) }
						</span>
					) }
					<label className="ca-toggle" title={ __( 'Enable / Disable', 'coderembassy-checkout-field-editor' ) }>
						<input
							type="checkbox"
							checked={ field.enabled }
							onChange={ ( e ) => onUpdate( { ...field, enabled: e.target.checked } ) }
						/>
						<span className="ca-toggle__track" />
					</label>
					<button type="button" className="ca-btn ca-btn--secondary ca-btn--sm" onClick={ openEdit }>
						{ __( 'Edit', 'coderembassy-checkout-field-editor' ) }
					</button>
				</div>
			</div>

			{ editing && (
				<div className="ca-native-row__edit" style={ { padding: '16px 16px 12px', borderTop: '1px solid var(--border)', marginTop: 12 } }>
					<div className="ca-card-grid">
						<div className="ca-field-group">
							<label>{ __( 'Label', 'coderembassy-checkout-field-editor' ) }</label>
							<input
								className="ca-input"
								type="text"
								value={ draft.label }
								onChange={ ( e ) => set( 'label', e.target.value ) }
							/>
						</div>
						<div className="ca-field-group">
							<label>{ __( 'Placeholder', 'coderembassy-checkout-field-editor' ) }</label>
							<input
								className="ca-input"
								type="text"
								value={ draft.placeholder }
								onChange={ ( e ) => set( 'placeholder', e.target.value ) }
							/>
						</div>
					</div>

					<div className="ca-card-grid" style={ { marginTop: 12 } }>
						<div className="ca-field-group">
							<label>{ __( 'Width', 'coderembassy-checkout-field-editor' ) }</label>
							<select
								className="ca-select"
								value={ draft.width }
								onChange={ ( e ) => set( 'width', e.target.value ) }
							>
								{ WIDTH_OPTIONS.map( ( w ) => (
									<option key={ w.value } value={ w.value }>{ w.label }</option>
								) ) }
							</select>
						</div>
						<div className="ca-field-group">
							<label className="ca-checkbox-option" style={ { alignItems: 'center' } }>
								<input
									type="checkbox"
									checked={ draft.required }
									onChange={ ( e ) => set( 'required', e.target.checked ) }
								/>
								<span>{ __( 'Required field', 'coderembassy-checkout-field-editor' ) }</span>
							</label>
						</div>
					</div>

					{ customerTypes.length > 0 && (
						<div className="ca-field-group" style={ { marginTop: 12 } }>
							<label>{ __( 'Visible for customer types', 'coderembassy-checkout-field-editor' ) }</label>
							<p className="ca-muted-label" style={ { marginTop: 2, marginBottom: 8 } }>
								{ __( 'Leave all unchecked to show for every customer type.', 'coderembassy-checkout-field-editor' ) }
							</p>
							<div className="ca-checkbox-grid">
								{ customerTypes.map( ( type ) => (
									<label key={ type.slug } className="ca-checkbox-option">
										<input
											type="checkbox"
											checked={ draft.customer_types.includes( type.slug ) }
											onChange={ () => toggleType( type.slug ) }
										/>
										<span>{ type.label }</span>
									</label>
								) ) }
							</div>
						</div>
					) }

					<div style={ { display: 'flex', gap: 8, marginTop: 14 } }>
						<button type="button" className="ca-btn ca-btn--primary" onClick={ applyEdit }>
							{ __( 'Apply', 'coderembassy-checkout-field-editor' ) }
						</button>
						<button type="button" className="ca-btn ca-btn--secondary" onClick={ () => setEditing( false ) }>
							{ __( 'Cancel', 'coderembassy-checkout-field-editor' ) }
						</button>
					</div>
				</div>
			) }
		</div>
	);
}

export default function NativeFields() {
	const [ allFields, setAllFields ] = useState( [] );
	const [ activeGroup, setActiveGroup ] = useState( 'billing' );
	const [ loading, setLoading ]     = useState( true );
	const [ saving, setSaving ]       = useState( false );
	const { setNotice }               = useDispatch( store );
	const customerTypes               = useSelect( ( s ) => s( store ).getTypes(), [] );

	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 8 } } )
	);

	useEffect( () => {
		setLoading( true );
		getNativeFields()
			.then( ( res ) => setAllFields( res?.data ?? [] ) )
			.catch( () => setAllFields( [] ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const groupFields = allFields.filter( ( f ) => f.group === activeGroup );

	const handleUpdate = useCallback( ( updated ) => {
		setAllFields( ( prev ) =>
			prev.map( ( f ) => ( f.field_key === updated.field_key ? updated : f ) )
		);
	}, [] );

	const handleDragEnd = ( { active, over } ) => {
		if ( ! over || active.id === over.id ) return;
		setAllFields( ( prev ) => {
			const nonGroup   = prev.filter( ( f ) => f.group !== activeGroup );
			const groupItems = prev.filter( ( f ) => f.group === activeGroup );
			const oldIndex   = groupItems.findIndex( ( f ) => f.field_key === active.id );
			const newIndex   = groupItems.findIndex( ( f ) => f.field_key === over.id );
			const reordered  = arrayMove( groupItems, oldIndex, newIndex ).map( ( f, i ) => ( {
				...f,
				priority: ( i + 1 ) * 10,
			} ) );
			return [ ...nonGroup, ...reordered ];
		} );
	};

	const handleSave = async () => {
		setSaving( true );
		try {
			await saveNativeFields( allFields );
			setNotice( { type: 'success', message: __( 'Native field settings saved.', 'coderembassy-checkout-field-editor' ) } );
		} catch {
			setNotice( { type: 'error', message: __( 'Failed to save native fields.', 'coderembassy-checkout-field-editor' ) } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <div className="ca-spinner" />;

	return (
		<div className="ca-tab-content">
			<div className="ca-section-header">
				<div>
					<h2>{ __( 'Native Fields', 'coderembassy-checkout-field-editor' ) }</h2>
					<p className="ca-muted-label">
						{ __( 'Edit labels, placeholders, width, and required state of built-in WooCommerce checkout fields. Drag to reorder. Toggle to hide from checkout.', 'coderembassy-checkout-field-editor' ) }
					</p>
				</div>
				<button
					type="button"
					className="ca-btn ca-btn--primary"
					onClick={ handleSave }
					disabled={ saving }
				>
					{ saving ? __( 'Saving…', 'coderembassy-checkout-field-editor' ) : __( 'Save Changes', 'coderembassy-checkout-field-editor' ) }
				</button>
			</div>

			<div style={ { display: 'flex', gap: 4, marginBottom: 20 } }>
				{ GROUPS.map( ( g ) => (
					<button
						key={ g.key }
						type="button"
						className={ `ca-btn ${ activeGroup === g.key ? 'ca-btn--primary' : 'ca-btn--secondary' }` }
						onClick={ () => setActiveGroup( g.key ) }
					>
						{ g.label }
					</button>
				) ) }
			</div>

			{ groupFields.length === 0 ? (
				<div className="ca-empty-state">
					{ __( 'No fields found for this section.', 'coderembassy-checkout-field-editor' ) }
				</div>
			) : (
				<DndContext
					sensors={ sensors }
					collisionDetection={ closestCenter }
					onDragEnd={ handleDragEnd }
				>
					<SortableContext
						items={ groupFields.map( ( f ) => f.field_key ) }
						strategy={ verticalListSortingStrategy }
					>
						{ groupFields.map( ( field ) => (
							<FieldRow
								key={ field.field_key }
								field={ field }
								customerTypes={ customerTypes }
								onUpdate={ handleUpdate }
							/>
						) ) }
					</SortableContext>
				</DndContext>
			) }
		</div>
	);
}
