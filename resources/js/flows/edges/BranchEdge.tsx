import React from 'react';
import { BaseEdge, EdgeLabelRenderer, getBezierPath, useStore, type EdgeProps, type ReactFlowState } from '@xyflow/react';
import { useFlowStore } from '../state/useFlowStore';
import { routeEdge, type NodeRect } from './routeEdge';

function rectOf(state: ReactFlowState, id: string): NodeRect | null {
    const node = state.nodeLookup.get(id);
    if (!node) return null;
    const { x, y } = node.internals.positionAbsolute;
    return { x, y, width: node.measured?.width ?? 0, height: node.measured?.height ?? 0 };
}

const sameRect = (a: NodeRect | null, b: NodeRect | null) =>
    a === b || (!!a && !!b && a.x === b.x && a.y === b.y && a.width === b.width && a.height === b.height);

export function BranchEdge(props: EdgeProps) {
    const { id, source, target, sourceX, sourceY, targetX, targetY } = props;
    const sourceRect = useStore((s) => rectOf(s, source), sameRect);
    const targetRect = useStore((s) => rectOf(s, target), sameRect);
    const sourceType = useStore((s) => s.nodeLookup.get(source)?.type);

    const route = routeEdge({
        source: sourceRect,
        target: targetRect,
        sourceHandleId: props.sourceHandleId,
        sourceHasFailureHandle: sourceType === 'operation',
        sourceX, sourceY, targetX, targetY,
    });
    const [path, labelX, labelY] = getBezierPath(route);
    const color = props.sourceHandleId === 'failure' ? '#ef4444' : '#0ea5e9';
    return (
        <>
            <BaseEdge {...props} path={path} style={{ stroke: color, strokeWidth: 2 }} />
            <EdgeLabelRenderer>
                <button
                    aria-label="delete edge"
                    className={`nodrag nopan absolute w-5 h-5 rounded-full bg-gray-700 text-white text-xs leading-none flex items-center justify-center hover:opacity-100 focus:opacity-100 transition-opacity ${props.selected ? 'opacity-100' : 'opacity-0'}`}
                    style={{ transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`, pointerEvents: 'all' }}
                    onClick={(e) => { e.stopPropagation(); useFlowStore.getState().removeEdge(id); }}
                >×</button>
            </EdgeLabelRenderer>
        </>
    );
}
