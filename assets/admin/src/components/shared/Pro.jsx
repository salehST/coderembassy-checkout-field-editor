import { __ } from '@wordpress/i18n';
import { upgradeUrl } from '../../config';

/**
 * Locked-feature UI.
 *
 * The free plugin ships no Pro logic, but it does show what Pro adds. These
 * render a description of the capability — never a disabled copy of the real
 * control — so nothing here depends on the add-on being installed.
 */

export function ProBadge( { className = '' } ) {
	return <span className={ `cecfm-pro-badge ${ className }` }>{ __( 'PRO', 'coderembassy-checkout-fields-manager' ) }</span>;
}

export function UpgradeButton( { size = '', children } ) {
	return (
		<a
			href={ upgradeUrl }
			target="_blank"
			rel="noopener noreferrer"
			className={ `cecfm-btn cecfm-btn--primary ${ size ? `cecfm-btn--${ size }` : '' }` }
		>
			{ children || __( 'Upgrade to Pro', 'coderembassy-checkout-fields-manager' ) }
		</a>
	);
}

/**
 * Full-screen teaser shown in place of a Pro-only tab.
 *
 * @param {string}   title    Feature name.
 * @param {string}   intro    One-line description of what it does.
 * @param {string[]} bullets  Concrete capabilities, written as benefits.
 */
export function ProTeaser( { title, intro, bullets = [] } ) {
	return (
		<section className="cecfm-pro-teaser">
			<div className="cecfm-section-header">
				<div>
					<h2>
						{ title } <ProBadge />
					</h2>
					<p className="cecfm-help-text">{ intro }</p>
				</div>
			</div>

			<div className="cecfm-card cecfm-pro-teaser__card">
				<ul className="cecfm-pro-teaser__list">
					{ bullets.map( ( item ) => (
						<li key={ item }>
							<span className="dashicons dashicons-yes-alt" />
							{ item }
						</li>
					) ) }
				</ul>

				<div className="cecfm-pro-teaser__cta">
					<UpgradeButton size="lg" />
					<p className="cecfm-help-text">
						{ __( 'Your existing fields and settings stay exactly as they are.', 'coderembassy-checkout-fields-manager' ) }
					</p>
				</div>
			</div>
		</section>
	);
}

/**
 * Inline locked panel, used where a Pro control would sit inside a free screen
 * (the condition builder inside the field editor, for example).
 */
export function ProPanel( { title, description } ) {
	return (
		<div className="cecfm-pro-panel">
			<div className="cecfm-pro-panel__head">
				<span className="dashicons dashicons-lock" />
				<strong>{ title }</strong>
				<ProBadge />
			</div>
			<p className="cecfm-help-text">{ description }</p>
			<UpgradeButton size="sm" />
		</div>
	);
}
