import { Position } from '@xyflow/react';

export type NodeRect = { x: number; y: number; width: number; height: number };

export type EdgeRoute = {
    sourceX: number; sourceY: number; targetX: number; targetY: number;
    sourcePosition: Position; targetPosition: Position;
};

/**
 * Picks which sides of the two nodes an edge leaves and enters from, based on
 * where the target sits relative to the source.
 *
 * Nodes only expose right-side source handles and a left-side target handle, and
 * those handle ids ("success"/"failure") are persisted and drive the engine's
 * branching (GraphWalker reads edge.sourceHandle). So the handles stay as they
 * are; only the drawn geometry floats to the side that faces the other node:
 *
 * - target mostly to the right → the real handle coordinates (right → left)
 * - target mostly below        → source bottom → target top
 * - target mostly above        → source top → target bottom
 * - target mostly to the left  → source left → target right
 */
export function routeEdge(args: {
    source: NodeRect | null;
    target: NodeRect | null;
    sourceHandleId: string | null | undefined;
    sourceHasFailureHandle: boolean;
    sourceX: number; sourceY: number; targetX: number; targetY: number;
}): EdgeRoute {
    const { source, target, sourceX, sourceY, targetX, targetY } = args;
    const handleRoute: EdgeRoute = {
        sourceX, sourceY, targetX, targetY,
        sourcePosition: Position.Right, targetPosition: Position.Left,
    };
    if (!source || !target || !source.width || !source.height || !target.width || !target.height) {
        return handleRoute;
    }

    const dx = (target.x + target.width / 2) - (source.x + source.width / 2);
    const dy = (target.y + target.height / 2) - (source.y + source.height / 2);

    // Distinguish success/failure exits along a horizontal side the same way the
    // handles are stacked on the right side (success first, failure second).
    const exitX = !args.sourceHasFailureHandle
        ? source.x + source.width / 2
        : source.x + source.width * (args.sourceHandleId === 'failure' ? 0.65 : 0.35);
    const targetCenterX = target.x + target.width / 2;

    if (Math.abs(dy) > Math.abs(dx)) {
        return dy > 0
            ? {
                sourceX: exitX, sourceY: source.y + source.height,
                targetX: targetCenterX, targetY: target.y,
                sourcePosition: Position.Bottom, targetPosition: Position.Top,
            }
            : {
                sourceX: exitX, sourceY: source.y,
                targetX: targetCenterX, targetY: target.y + target.height,
                sourcePosition: Position.Top, targetPosition: Position.Bottom,
            };
    }

    if (dx >= 0) {
        return handleRoute;
    }

    return {
        sourceX: source.x, sourceY,
        targetX: target.x + target.width, targetY,
        sourcePosition: Position.Left, targetPosition: Position.Right,
    };
}
