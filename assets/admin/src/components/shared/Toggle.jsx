export default function Toggle( { checked, onChange, label, disabled } ) {
	return <label className="ca-toggle"><input type="checkbox" checked={ !! checked } onChange={ onChange } disabled={ !! disabled } /><span className="ca-toggle__track" />{ label && <span className="ca-toggle__label">{ label }</span> }</label>;
}
