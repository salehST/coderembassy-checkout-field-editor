import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default class ErrorBoundary extends Component {
	state = { hasError: false, error: null };

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	componentDidCatch( error, info ) {
		console.error( '[Checkout Architect]', error, info );
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="ca-alert ca-alert--error" style={ { margin: '2rem' } }>
					<strong>{ __( 'Something went wrong loading this panel.', 'coderembassy-checkout-field-editor' ) }</strong>
					<p>{ this.state.error?.message ?? '' }</p>
					<button
						className="ca-btn ca-btn--secondary"
						onClick={ () => this.setState( { hasError: false, error: null } ) }
					>
						{ __( 'Try Again', 'coderembassy-checkout-field-editor' ) }
					</button>
				</div>
			);
		}
		return this.props.children;
	}
}
