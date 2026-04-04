import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../store';
import Toggle from './shared/Toggle';

export default function Topbar() {
	const globals = useSelect( ( s ) => s( store ).getGlobals(), [] );
	const isDark = useSelect( ( s ) => s( store ).isDark(), [] );
	const { toggleDark } = useDispatch( store );
	const currentUser = globals.currentUser || {};
	return (
		<div className="ca-topbar">
			<img src={ isDark ? globals.logoDark : globals.logoLight } alt="Checkout Architect" className="ca-topbar__logo" />
			<div className="ca-topbar__right">
				<Toggle checked={ isDark } onChange={ () => toggleDark() } label={ __( 'Dark mode', 'coderembassy-checkout-field-editor' ) } />
				<div className="ca-topbar__user">
					<img src={ currentUser.avatar_url } alt="" className="ca-topbar__avatar" />
					<span>{ currentUser.display_name }</span>
				</div>
			</div>
		</div>
	);
}
