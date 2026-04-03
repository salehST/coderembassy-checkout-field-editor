import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import FieldList from './FieldList';
import FieldEditor from './FieldEditor';

export default function FieldBuilder() {
	const editingFieldId = useSelect( ( s ) => s( store ).getEditingFieldId(), [] );
	const { setEditingFieldId } = useDispatch( store );

	return (
		<section>
			<div className="ca-section-header">
				<div>
					<h2>{ __( 'Field Builder', 'coderembassy-checkout-field-editor' ) }</h2>
					<p className="ca-help-text">{ __( 'Add custom fields to your WooCommerce checkout. Drag cards to reorder them.', 'coderembassy-checkout-field-editor' ) }</p>
				</div>
				<div style={ { display: 'flex', gap: 8, flexWrap: 'wrap' } }>
					<button
						type="button"
						className="ca-btn ca-btn--primary ca-btn--lg"
						onClick={ () => setEditingFieldId( 'new:classic' ) }
					>
						<span className="dashicons dashicons-plus-alt2" />
						{ __( 'Add Classic Field', 'coderembassy-checkout-field-editor' ) }
					</button>

					<button
						type="button"
						className="ca-btn ca-btn--ghost ca-btn--lg"
						onClick={ () => setEditingFieldId( 'new:block' ) }
					>
						<span className="dashicons dashicons-plus-alt2" />
						{ __( 'Add Block Field', 'coderembassy-checkout-field-editor' ) }
					</button>
				</div>
			</div>

			<FieldList />

			{ editingFieldId !== null && <FieldEditor /> }
		</section>
	);
}
