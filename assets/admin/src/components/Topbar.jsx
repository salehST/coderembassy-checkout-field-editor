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
		<div className="cecfm-topbar">
			<img src={ isDark ? globals.logoDark : globals.logoLight } alt="Checkout Fields Manager" className="cecfm-topbar__logo" />
			<div className="cecfm-topbar__right">
				<Toggle checked={ isDark } onChange={ () => toggleDark() } label={ __( 'Dark mode', 'coderembassy-checkout-fields-manager' ) } />
				<div className="cecfm-topbar__user">
					<img src={ currentUser.avatar_url } alt="" className="cecfm-topbar__avatar" />
					<span>{ currentUser.display_name }</span>
				</div>
			</div>
		</div>
	);
}

