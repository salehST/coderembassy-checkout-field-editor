export default function Toggle( { checked, onChange, label, disabled } ) {
	return <label className="cecfm-toggle"><input type="checkbox" checked={ !! checked } onChange={ onChange } disabled={ !! disabled } /><span className="cecfm-toggle__track" />{ label && <span className="cecfm-toggle__label">{ label }</span> }</label>;
}

