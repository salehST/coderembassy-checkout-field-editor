export default function Alert( { type = 'success', children } ) { return <div className={ `ca-alert ca-alert--${ type }` }>{ children }</div>; }
export default function Alert( { type = 'success', children } ) {
	return <div className={ `ca-alert ca-alert--${ type }` }>{ children }</div>;
}
