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
			<div className="cecfm-section-header">
				<div>
					<h2>{ __( 'Field Builder', 'coderembassy-checkout-fields-manager' ) }</h2>
					<p className="cecfm-help-text">{ __( 'Add custom fields to your WooCommerce checkout. Drag cards to reorder them.', 'coderembassy-checkout-fields-manager' ) }</p>
				</div>
				<div style={ { display: 'flex', gap: 8, flexWrap: 'wrap' } }>
					<button
						type="button"
						className="cecfm-btn cecfm-btn--primary cecfm-btn--lg"
						onClick={ () => setEditingFieldId( 'new:classic' ) }
					>
						<span className="dashicons dashicons-plus-alt2" />
						{ __( 'Add Classic Field', 'coderembassy-checkout-fields-manager' ) }
					</button>

					<button
						type="button"
						className="cecfm-btn cecfm-btn--ghost cecfm-btn--lg"
						onClick={ () => setEditingFieldId( 'new:block' ) }
					>
						<span className="dashicons dashicons-plus-alt2" />
						{ __( 'Add Block Field', 'coderembassy-checkout-fields-manager' ) }
					</button>
				</div>
			</div>

			<FieldList />

			{ editingFieldId !== null && <FieldEditor /> }
		</section>
	);
}

