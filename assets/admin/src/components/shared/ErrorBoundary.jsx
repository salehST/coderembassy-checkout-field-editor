import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default class ErrorBoundary extends Component {
	state = { hasError: false, error: null };

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	componentDidCatch( error, info ) {
		console.error( '[Checkout Fields Manager]', error, info );
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="cecfm-alert cecfm-alert--error" style={ { margin: '2rem' } }>
					<strong>{ __( 'Something went wrong loading this panel.', 'coderembassy-checkout-fields-manager' ) }</strong>
					<p>{ this.state.error?.message ?? '' }</p>
					<button
						className="cecfm-btn cecfm-btn--secondary"
						onClick={ () => this.setState( { hasError: false, error: null } ) }
					>
						{ __( 'Try Again', 'coderembassy-checkout-fields-manager' ) }
					</button>
				</div>
			);
		}
		return this.props.children;
	}
}

