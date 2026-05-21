export default function Alert( { type = 'success', children } ) { return <div className={ `cecfm-alert cecfm-alert--${ type }` }>{ children }</div>; }
export default function Alert( { type = 'success', children } ) {
	return <div className={ `cecfm-alert cecfm-alert--${ type }` }>{ children }</div>;
}

