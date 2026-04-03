import { useDispatch, useSelect } from '@wordpress/data';
import {
	DndContext,
	closestCenter,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
	arrayMove,
} from '@dnd-kit/sortable';
import { store } from '../../store';
import { reorderFields } from '../../api/client';
import FieldCard from './FieldCard';

export default function FieldList() {
	const fields = useSelect( ( s ) => s( store ).getFields(), [] );
	const { setFields } = useDispatch( store );
	const sensors = useSensors(
		useSensor( PointerSensor, {
			activationConstraint: { distance: 8 },
		} )
	);
	const handleDragEnd = async ( event ) => {
		const { active, over } = event;
		if ( ! over || active.id === over.id ) return;
		const oldIndex = fields.findIndex( ( f ) => f.id === active.id );
		const newIndex = fields.findIndex( ( f ) => f.id === over.id );
		if ( oldIndex < 0 || newIndex < 0 ) return;
		const reordered = arrayMove( fields, oldIndex, newIndex ).map( ( f, i ) => ( { ...f, priority: i + 1 } ) );
		setFields( reordered );
		await reorderFields( reordered.map( ( f ) => ( { id: f.id, priority: f.priority } ) ) );
	};
	if ( ! fields.length ) {
		return <div className="ca-empty-state">No fields yet. Click <strong>Add Field</strong> to get started.</div>;
	}
	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			onDragEnd={ handleDragEnd }
		>
			<SortableContext
				items={ fields.map( ( f ) => f.id ) }
				strategy={ verticalListSortingStrategy }
			>
				{ fields.map( ( field ) => <FieldCard key={ field.id } field={ field } /> ) }
			</SortableContext>
		</DndContext>
	);
}
