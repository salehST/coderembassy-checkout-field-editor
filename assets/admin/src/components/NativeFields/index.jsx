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
	{ key: 'billing',  label: __( 'Billing', 'coderembassy-checkout-fields-manager' ) },
	{ key: 'shipping', label: __( 'Shipping', 'coderembassy-checkout-fields-manager' ) },
	{ key: 'order',    label: __( 'Additional', 'coderembassy-checkout-fields-manager' ) },
];

const WIDTH_OPTIONS = [
	{ value: 'full',       label: __( 'Full width', 'coderembassy-checkout-fields-manager' ) },
	{ value: 'half_first', label: __( 'Half (left)', 'coderembassy-checkout-fields-manager' ) },
	{ value: 'half_last',  label: __( 'Half (right)', 'coderembassy-checkout-fields-manager' ) },
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

	const widthLabel = WIDTH_OPTIONS.find( ( w ) => w.value === ( field.width || 'full' ) )?.label || __( 'Full width', 'coderembassy-checkout-fields-manager' );

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `cecfm-card cecfm-native-row${ ! field.enabled ? ' cecfm-native-row--disabled' : '' }` }
		>
			<div className="cecfm-native-row__main">
				<span className="cecfm-drag-handle" { ...attributes } { ...listeners }>⠿</span>

				<div className="cecfm-native-row__info">
					<strong>{ field.label }</strong>
					<code style={ { fontSize: 11, color: 'var(--muted)', marginLeft: 8 } }>{ field.field_key }</code>
					{ field.type !== 'text' && (
						<span className="cecfm-badge" style={ { marginLeft: 6 } }>{ field.type }</span>
					) }
					<span className="cecfm-badge" style={ { marginLeft: 6, background: 'var(--card-2)' } }>{ widthLabel }</span>
				</div>

				<div className="cecfm-native-row__actions">
					{ field.required && (
						<span className="cecfm-badge cecfm-badge--warning">
							{ __( 'Required', 'coderembassy-checkout-fields-manager' ) }
						</span>
					) }
					{ Array.isArray( field.customer_types ) && field.customer_types.length > 0 && (
						<span className="cecfm-badge" title={ field.customer_types.join( ', ' ) }>
							{ __( 'Type restricted', 'coderembassy-checkout-fields-manager' ) }
						</span>
					) }
					<label className="cecfm-toggle" title={ __( 'Enable / Disable', 'coderembassy-checkout-fields-manager' ) }>
						<input
							type="checkbox"
							checked={ field.enabled }
							onChange={ ( e ) => onUpdate( { ...field, enabled: e.target.checked } ) }
						/>
						<span className="cecfm-toggle__track" />
					</label>
					<button type="button" className="cecfm-btn cecfm-btn--secondary cecfm-btn--sm" onClick={ openEdit }>
						{ __( 'Edit', 'coderembassy-checkout-fields-manager' ) }
					</button>
				</div>
			</div>

			{ editing && (
				<div className="cecfm-native-row__edit" style={ { padding: '16px 16px 12px', borderTop: '1px solid var(--border)', marginTop: 12 } }>
					<div className="cecfm-card-grid">
						<div className="cecfm-field-group">
							<label>{ __( 'Label', 'coderembassy-checkout-fields-manager' ) }</label>
							<input
								className="cecfm-input"
								type="text"
								value={ draft.label }
								onChange={ ( e ) => set( 'label', e.target.value ) }
							/>
						</div>
						<div className="cecfm-field-group">
							<label>{ __( 'Placeholder', 'coderembassy-checkout-fields-manager' ) }</label>
							<input
								className="cecfm-input"
								type="text"
								value={ draft.placeholder }
								onChange={ ( e ) => set( 'placeholder', e.target.value ) }
							/>
						</div>
					</div>

					<div className="cecfm-card-grid" style={ { marginTop: 12 } }>
						<div className="cecfm-field-group">
							<label>{ __( 'Width', 'coderembassy-checkout-fields-manager' ) }</label>
							<select
								className="cecfm-select"
								value={ draft.width }
								onChange={ ( e ) => set( 'width', e.target.value ) }
							>
								{ WIDTH_OPTIONS.map( ( w ) => (
									<option key={ w.value } value={ w.value }>{ w.label }</option>
								) ) }
							</select>
						</div>
						<div className="cecfm-field-group">
							<label className="cecfm-checkbox-option" style={ { alignItems: 'center' } }>
								<input
									type="checkbox"
									checked={ draft.required }
									onChange={ ( e ) => set( 'required', e.target.checked ) }
								/>
								<span>{ __( 'Required field', 'coderembassy-checkout-fields-manager' ) }</span>
							</label>
						</div>
					</div>

					{ customerTypes.length > 0 && (
						<div className="cecfm-field-group" style={ { marginTop: 12 } }>
							<label>{ __( 'Visible for customer types', 'coderembassy-checkout-fields-manager' ) }</label>
							<p className="cecfm-muted-label" style={ { marginTop: 2, marginBottom: 8 } }>
								{ __( 'Leave all unchecked to show for every customer type.', 'coderembassy-checkout-fields-manager' ) }
							</p>
							<div className="cecfm-checkbox-grid">
								{ customerTypes.map( ( type ) => (
									<label key={ type.slug } className="cecfm-checkbox-option">
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
						<button type="button" className="cecfm-btn cecfm-btn--primary" onClick={ applyEdit }>
							{ __( 'Apply', 'coderembassy-checkout-fields-manager' ) }
						</button>
						<button type="button" className="cecfm-btn cecfm-btn--secondary" onClick={ () => setEditing( false ) }>
							{ __( 'Cancel', 'coderembassy-checkout-fields-manager' ) }
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
			setNotice( { type: 'success', message: __( 'Native field settings saved.', 'coderembassy-checkout-fields-manager' ) } );
		} catch {
			setNotice( { type: 'error', message: __( 'Failed to save native fields.', 'coderembassy-checkout-fields-manager' ) } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <div className="cecfm-spinner" />;

	return (
		<div className="cecfm-tab-content">
			<div className="cecfm-section-header">
				<div>
					<h2>{ __( 'Native Fields', 'coderembassy-checkout-fields-manager' ) }</h2>
					<p className="cecfm-muted-label">
						{ __( 'Edit labels, placeholders, width, and required state of built-in WooCommerce checkout fields. Drag to reorder. Toggle to hide from checkout.', 'coderembassy-checkout-fields-manager' ) }
					</p>
				</div>
				<button
					type="button"
					className="cecfm-btn cecfm-btn--primary"
					onClick={ handleSave }
					disabled={ saving }
				>
					{ saving ? __( 'Saving…', 'coderembassy-checkout-fields-manager' ) : __( 'Save Changes', 'coderembassy-checkout-fields-manager' ) }
				</button>
			</div>

			<div style={ { display: 'flex', gap: 4, marginBottom: 20 } }>
				{ GROUPS.map( ( g ) => (
					<button
						key={ g.key }
						type="button"
						className={ `cecfm-btn ${ activeGroup === g.key ? 'cecfm-btn--primary' : 'cecfm-btn--secondary' }` }
						onClick={ () => setActiveGroup( g.key ) }
					>
						{ g.label }
					</button>
				) ) }
			</div>

			{ groupFields.length === 0 ? (
				<div className="cecfm-empty-state">
					{ __( 'No fields found for this section.', 'coderembassy-checkout-fields-manager' ) }
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

