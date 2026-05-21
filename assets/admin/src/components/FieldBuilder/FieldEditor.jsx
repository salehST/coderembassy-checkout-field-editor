import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { DndContext, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, arrayMove, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { store } from '../../store';
import { createField, updateField, getRevisions, rollbackRevision } from '../../api/client';
import ConditionEditor from './ConditionEditor';

const FIELD_TYPES = [
	{ value: 'text',     label: 'Text' },
	{ value: 'textarea', label: 'Textarea' },
	{ value: 'select',   label: 'Select (dropdown)' },
	{ value: 'radio',    label: 'Radio buttons' },
	{ value: 'checkbox', label: 'Checkbox' },
	{ value: 'multiselect',    label: 'Multi-select' },
	{ value: 'checkbox_group', label: 'Checkbox group' },
	{ value: 'number',   label: 'Number' },
	{ value: 'email',    label: 'Email' },
	{ value: 'phone',    label: 'Phone' },
	{ value: 'date',     label: 'Date picker' },
	{ value: 'file',     label: 'File upload' },
	{ value: 'hidden',   label: 'Hidden' },
	{ value: 'heading',  label: 'Heading / Divider' },
	{ value: 'repeater', label: 'Repeater' },
];

const BLOCK_FIELD_TYPES = [ 'text', 'select', 'checkbox' ];

const WIDTHS    = [ 'full', '1/2', '1/3', '2/3' ];
const POSITIONS = [ 'after_address', 'before_address', 'after_order_notes', 'before_order_notes' ];
const CHECKOUT_SECTIONS = [
	{ value: 'billing',  label: __( 'Billing', 'coderembassy-checkout-fields-manager' ) },
	{ value: 'shipping', label: __( 'Shipping', 'coderembassy-checkout-fields-manager' ) },
	{ value: 'order',    label: __( 'Order', 'coderembassy-checkout-fields-manager' ) },
];

const blank = {
	label: '', field_key: '', type: 'text', placeholder: '', description: '',
	required: false, width: 'full', position: 'after_address', section: 'billing', section_id: 0,
	options: [],
	customer_types: [],
	conditions: { logic: 'AND', groups: [] },
};

const OPTION_TYPES = [ 'select', 'radio', 'multiselect', 'checkbox_group' ];

function optionSlug( label ) {
	return String( label || '' ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' );
}

function OptionRow( { idx, option, onLabelChange, onValueChange, onRemove } ) {
	const { attributes, listeners, setNodeRef, transform, transition } = useSortable( { id: String( idx ) } );
	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
	};

	return (
		<div ref={ setNodeRef } className="cecfm-options-row" style={ style }>
			<span className="cecfm-options-row__handle" title={ __( 'Drag to reorder', 'coderembassy-checkout-fields-manager' ) } { ...attributes } { ...listeners }>⋮⋮</span>
			<input
				className="cecfm-input"
				type="text"
				value={ option?.label || '' }
				placeholder={ __( 'Option label', 'coderembassy-checkout-fields-manager' ) }
				onChange={ ( e ) => onLabelChange( idx, e.target.value ) }
			/>
			<input
				className="cecfm-input"
				type="text"
				value={ option?.value || '' }
				placeholder={ __( 'option_value', 'coderembassy-checkout-fields-manager' ) }
				onChange={ ( e ) => onValueChange( idx, e.target.value ) }
			/>
			<button
				type="button"
				className="cecfm-btn cecfm-btn--danger cecfm-btn--sm"
				onClick={ () => onRemove( idx ) }
				title={ __( 'Remove option', 'coderembassy-checkout-fields-manager' ) }
			>
				✕
			</button>
		</div>
	);
}

export default function FieldEditor() {
	const editingFieldId         = useSelect( ( s ) => s( store ).getEditingFieldId(), [] );
	const fields                 = useSelect( ( s ) => s( store ).getFields(), [] );
	const customerTypes          = useSelect( ( s ) => s( store ).getTypes(), [] );
	const { setEditingFieldId, setFields } = useDispatch( store );

	const isNewField   = typeof editingFieldId === 'string' && editingFieldId.startsWith( 'new' );
	const newFieldMode = editingFieldId === 'new:block' ? 'block' : 'classic';
	const isBlockMode  = isNewField && newFieldMode === 'block';
	const isExistingBlock = ! isNewField && local.meta?.blocks_enabled === true;
	const formTitle = isNewField
		? ( isBlockMode ? __( 'Add New Block Field', 'coderembassy-checkout-fields-manager' ) : __( 'Add New Classic Field', 'coderembassy-checkout-fields-manager' ) )
		: ( isExistingBlock ? __( 'Edit Block Field', 'coderembassy-checkout-fields-manager' ) : __( 'Edit Classic Field', 'coderembassy-checkout-fields-manager' ) );

	const current = isNewField ? null : fields.find( ( f ) => String( f.id ) === String( editingFieldId ) );
	const [ local, setLocal ]    = useState( blank );
	const [ touched, setTouched ] = useState( false );
	const [ valueTouched, setValueTouched ] = useState( [] );
	const [busy, setBusy] = useState( false );
	const [showRevisions, setShowRevisions] = useState( false );
	const [revisions, setRevisions] = useState( [] );
	const sensors = useSensors(
		useSensor( PointerSensor, {
			activationConstraint: { distance: 8 },
		} )
	);

	useEffect( () => {
		const next = current ? { ...blank, ...current } : blank;
		next.options = Array.isArray( next.options ) ? next.options : [];
		next.meta = next.meta && typeof next.meta === 'object' ? next.meta : {};

		if ( isNewField ) {
			next.meta = {
				...next.meta,
				blocks_enabled: isBlockMode,
				blocks_location: next.meta?.blocks_location ?? 'contact',
			};
			if ( isBlockMode && ! BLOCK_FIELD_TYPES.includes( next.type ) ) {
				next.type = 'text';
			}
		}
		setLocal( next );
		setValueTouched( next.options.map( () => false ) );
		setTouched( false );
	}, [ current, isNewField, isBlockMode ] );

useEffect( () => {
    if ( !isNewField && showRevisions ) {
        ( async () => {
            const res = await getRevisions( editingFieldId );
            setRevisions( res?.data || [] );
        } )();
    }
}, [ editingFieldId, showRevisions ] );

	const set = ( k, v ) => setLocal( ( p ) => ( { ...p, [ k ]: v } ) );
	const toggleCustomerType = ( slug ) => {
		setLocal( ( p ) => {
			const currentTypes = Array.isArray( p.customer_types ) ? p.customer_types : [];
			return {
				...p,
				customer_types: currentTypes.includes( slug )
					? currentTypes.filter( ( item ) => item !== slug )
					: [ ...currentTypes, slug ],
			};
		} );
	};

	const setOptions = ( nextOptions ) => {
		setLocal( ( p ) => ( { ...p, options: nextOptions } ) );
	};

	const addOption = () => {
		const options = Array.isArray( local.options ) ? local.options : [];
		setOptions( [ ...options, { label: '', value: '' } ] );
		setValueTouched( ( prev ) => [ ...prev, false ] );
	};

	const removeOption = ( idx ) => {
		const options = Array.isArray( local.options ) ? local.options : [];
		setOptions( options.filter( ( _, i ) => i !== idx ) );
		setValueTouched( ( prev ) => prev.filter( ( _, i ) => i !== idx ) );
	};

	const handleOptionLabel = ( idx, label ) => {
		const options = Array.isArray( local.options ) ? [ ...local.options ] : [];
		const next = { ...( options[ idx ] || { label: '', value: '' } ), label };
		if ( ! valueTouched[ idx ] ) {
			next.value = optionSlug( label );
		}
		options[ idx ] = next;
		setOptions( options );
	};

	const handleOptionValue = ( idx, value ) => {
		const options = Array.isArray( local.options ) ? [ ...local.options ] : [];
		options[ idx ] = { ...( options[ idx ] || { label: '', value: '' } ), value };
		setOptions( options );
		setValueTouched( ( prev ) => {
			const next = [ ...prev ];
			next[ idx ] = true;
			return next;
		} );
	};

	const handleOptionDragEnd = ( event ) => {
		const { active, over } = event;
		if ( ! over || active.id === over.id ) {
			return;
		}
		const oldIndex = Number( active.id );
		const newIndex = Number( over.id );
		if ( Number.isNaN( oldIndex ) || Number.isNaN( newIndex ) ) {
			return;
		}
		const options = Array.isArray( local.options ) ? local.options : [];
		setOptions( arrayMove( options, oldIndex, newIndex ) );
		setValueTouched( ( prev ) => arrayMove( prev, oldIndex, newIndex ) );
	};

	const handleSave = async () => {
		setBusy( true );
		try {
			const currentMeta = local.meta && typeof local.meta === 'object' ? local.meta : {};
			const payload = {
				...local,
				conditions: local.conditions,
				pricing_rules: [],
				meta: {
					...currentMeta,
					blocks_enabled: isNewField ? isBlockMode : ( currentMeta.blocks_enabled ?? false ),
					blocks_location: currentMeta.blocks_location ?? ( isBlockMode ? 'contact' : '' ),
				},
			};
			const res = isNewField
				? await createField( payload )
				: await updateField( editingFieldId, payload );
			const saved = res?.data || payload;
			if ( isNewField ) setFields( [ ...fields, saved ] );
			else setFields( fields.map( ( f ) => ( String( f.id ) === String( editingFieldId ) ? saved : f ) ) );
			setEditingFieldId( null );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className="cecfm-editor-panel">
			<h3 className="cecfm-editor-title">
				{ formTitle }
			</h3>

			{ /* Row 1: Label + Field Key */ }
			<div className="cecfm-card-grid">
				<div className="cecfm-field-group">
					<label>{ __( 'Field Label', 'coderembassy-checkout-fields-manager' ) } <span className="cecfm-required">*</span></label>
					<input
						className="cecfm-input"
						value={ local.label }
						placeholder={ __( 'e.g. Company Name', 'coderembassy-checkout-fields-manager' ) }
						onChange={ ( e ) => {
							const label = e.target.value;
							const next  = { ...local, label };
							if ( isNewField && ! touched ) {
								next.field_key = label.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' );
							}
							setLocal( next );
						} }
					/>
				</div>
				<div className="cecfm-field-group">
					<label>{ __( 'Field Key', 'coderembassy-checkout-fields-manager' ) } <span className="cecfm-muted-label">{ __( '(unique, no spaces)', 'coderembassy-checkout-fields-manager' ) }</span></label>
					<input
						className="cecfm-input"
						value={ local.field_key }
						placeholder="e.g. company_name"
						onChange={ ( e ) => { setTouched( true ); set( 'field_key', e.target.value ); } }
					/>
				</div>
			</div>

			{ /* Row 2: Type + Width */ }
			<div className="cecfm-card-grid">
				<div className="cecfm-field-group">
					<label>{ __( 'Field Type', 'coderembassy-checkout-fields-manager' ) }</label>
					<select className="cecfm-select" value={ local.type } onChange={ ( e ) => set( 'type', e.target.value ) }>
						{ ( isBlockMode ? FIELD_TYPES.filter( ( ft ) => BLOCK_FIELD_TYPES.includes( ft.value ) ) : FIELD_TYPES )
							.map( ( ft ) => <option key={ ft.value } value={ ft.value }>{ ft.label }</option> ) }
					</select>
				</div>
				{ ! isBlockMode && (
					<div className="cecfm-field-group">
						<label>{ __( 'Width', 'coderembassy-checkout-fields-manager' ) }</label>
						<select className="cecfm-select" value={ local.width } onChange={ ( e ) => set( 'width', e.target.value ) }>
							{ WIDTHS.map( ( w ) => <option key={ w } value={ w }>{ w }</option> ) }
						</select>
					</div>
				) }
			</div>

			{ /* Row 3: Placeholder + Position */ }
			<div className="cecfm-card-grid">
				<div className="cecfm-field-group">
					<label>{ __( 'Placeholder', 'coderembassy-checkout-fields-manager' ) }</label>
					<input className="cecfm-input" value={ local.placeholder || '' } placeholder={ __( 'Optional hint text inside the field', 'coderembassy-checkout-fields-manager' ) } onChange={ ( e ) => set( 'placeholder', e.target.value ) } />
				</div>
				{ ! isBlockMode && (
					<div className="cecfm-field-group">
						<label>{ __( 'Position on checkout', 'coderembassy-checkout-fields-manager' ) }</label>
						<select className="cecfm-select" value={ local.position } onChange={ ( e ) => set( 'position', e.target.value ) }>
							{ POSITIONS.map( ( p ) => <option key={ p } value={ p }>{ p.replace( /_/g, ' ' ) }</option> ) }
						</select>
					</div>
				) }
			</div>

			{ ! isBlockMode && (
				<div className="cecfm-card-grid">
					<div className="cecfm-field-group">
						<label>{ __( 'Checkout area', 'coderembassy-checkout-fields-manager' ) }</label>
						<select className="cecfm-select" value={ local.section || 'billing' } onChange={ ( e ) => set( 'section', e.target.value ) }>
							{ CHECKOUT_SECTIONS.map( ( item ) => <option key={ item.value } value={ item.value }>{ item.label }</option> ) }
						</select>
					</div>
				</div>
			) }

			{ isBlockMode && (
				<div className="cecfm-field-group">
					<label>{ __( 'Blocks checkout location', 'coderembassy-checkout-fields-manager' ) }</label>
					<p className="cecfm-help-text">
						{ __( 'Where this field appears in the WooCommerce block checkout.', 'coderembassy-checkout-fields-manager' ) }
					</p>
					<select
						className="cecfm-select"
						value={ local.meta?.blocks_location || 'contact' }
						onChange={ ( e ) => setLocal( ( p ) => ( { ...p, meta: { ...( p.meta || {} ), blocks_location: e.target.value } } ) ) }
					>
						<option value="contact">{ __( 'Contact (above billing)', 'coderembassy-checkout-fields-manager' ) }</option>
						<option value="address">{ __( 'Address', 'coderembassy-checkout-fields-manager' ) }</option>
						<option value="order">{ __( 'Order information', 'coderembassy-checkout-fields-manager' ) }</option>
					</select>
				</div>
			) }

			<div className="cecfm-field-group">
				<label>{ __( 'Customer types', 'coderembassy-checkout-fields-manager' ) }</label>
				<p className="cecfm-setting-desc">{ __( 'Leave empty to show this field for every customer type.', 'coderembassy-checkout-fields-manager' ) }</p>
				<div className="cecfm-checkbox-grid">
					{ customerTypes.length ? customerTypes.map( ( type ) => (
						<label key={ type.slug } className="cecfm-checkbox-option">
							<input
								type="checkbox"
								checked={ Array.isArray( local.customer_types ) && local.customer_types.includes( type.slug ) }
								onChange={ () => toggleCustomerType( type.slug ) }
							/>
							<span>{ type.label }</span>
						</label>
					) ) : <span className="cecfm-muted-label">{ __( 'No customer types created yet.', 'coderembassy-checkout-fields-manager' ) }</span> }
				</div>
			</div>

			{ /* Description */ }
			<div className="cecfm-field-group">
				<label>{ __( 'Description / Help text', 'coderembassy-checkout-fields-manager' ) }</label>
				<input className="cecfm-input" value={ local.description || '' } placeholder={ __( 'Shown below the field on checkout (optional)', 'coderembassy-checkout-fields-manager' ) } onChange={ ( e ) => set( 'description', e.target.value ) } />
			</div>

			{ /* Required toggle */ }
			<div className="cecfm-field-group cecfm-field-group--inline">
				<label>
					<input type="checkbox" checked={ !! local.required } onChange={ () => set( 'required', ! local.required ) } />
					{ ' ' }{ __( 'Required field', 'coderembassy-checkout-fields-manager' ) }
				</label>
			</div>

			{ OPTION_TYPES.includes( local.type ) && (
				<div className="cecfm-field-group">
					<label>{ __( 'Options', 'coderembassy-checkout-fields-manager' ) }</label>
					<div className="cecfm-options-editor">
						<DndContext sensors={ sensors } collisionDetection={ closestCenter } onDragEnd={ handleOptionDragEnd }>
							<SortableContext
								items={ ( Array.isArray( local.options ) ? local.options : [] ).map( ( _, idx ) => String( idx ) ) }
								strategy={ verticalListSortingStrategy }
							>
								{ ( Array.isArray( local.options ) ? local.options : [] ).map( ( option, idx ) => (
									<OptionRow
										key={ `${ idx }-${ option?.value || '' }-${ option?.label || '' }` }
										idx={ idx }
										option={ option }
										onLabelChange={ handleOptionLabel }
										onValueChange={ handleOptionValue }
										onRemove={ removeOption }
									/>
								) ) }
							</SortableContext>
						</DndContext>
						<button type="button" className="cecfm-btn cecfm-btn--ghost cecfm-btn--sm" onClick={ addOption }>
							<span className="dashicons dashicons-plus-alt2" />
							{ __( 'Add option', 'coderembassy-checkout-fields-manager' ) }
						</button>
					</div>
				</div>
			) }

			{ /* Actions */ }
			<div className="cecfm-editor-actions">
				<button type="button" className="cecfm-btn cecfm-btn--primary" onClick={ handleSave } disabled={ busy }>
					{ busy ? __( 'Saving…', 'coderembassy-checkout-fields-manager' ) : __( 'Save Field', 'coderembassy-checkout-fields-manager' ) }
				</button>
				<button type="button" className="cecfm-btn" onClick={ () => setEditingFieldId( null ) }>
					{ __( 'Cancel', 'coderembassy-checkout-fields-manager' ) }
				</button>
				{ ! isNewField && (
					<button type="button" className="cecfm-btn cecfm-btn--secondary" onClick={ () => setShowRevisions( !showRevisions ) }>
						{ showRevisions ? __( 'Hide Revisions', 'coderembassy-checkout-fields-manager' ) : __( 'Revision History', 'coderembassy-checkout-fields-manager' ) }
					</button>
				) }
			</div>
			{ showRevisions && (
				<div className="cecfm-revisions" style={ { marginTop: 12 } }>
					{ revisions.map( rev => (
						<div key={ rev.id } className="cecfm-revision-item" style={ { display: 'flex', alignItems: 'center', marginBottom: 8 } }>
							<span style={ { flexGrow: 1 } }>{ rev.created_at }</span>
							<button type="button" className="cecfm-btn cecfm-btn--ghost cecfm-btn--sm" onClick={ async () => {
								setBusy( true );
								await rollbackRevision( editingFieldId, rev.id );
								const refreshed = await getRevisions( editingFieldId );
								setRevisions( refreshed?.data || [] );
								setBusy( false );
							} }>{ __( 'Rollback', 'coderembassy-checkout-fields-manager' ) }</button>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
