import { describe, it, expect } from 'vitest';
import { Position } from '@xyflow/react';
import { routeEdge } from '../edges/routeEdge';
import { edgeTypes } from '../flow-canvas';
import { BranchEdge } from '../edges/BranchEdge';

const W = 120;
const H = 50;
const rect = (x: number, y: number) => ({ x, y, width: W, height: H });
// Handle coordinates React Flow would hand the edge (right-side source, left-side target).
const handles = (s: { x: number; y: number }, t: { x: number; y: number }, sy = 18) => ({
    sourceX: s.x + W, sourceY: s.y + sy, targetX: t.x, targetY: t.y + H / 2,
});

describe('routeEdge', () => {
    it('keeps the stored side handles for a left-to-right layout', () => {
        const s = rect(0, 0); const t = rect(300, 20);
        const r = routeEdge({ source: s, target: t, sourceHandleId: 'success', sourceHasFailureHandle: true, ...handles(s, t) });
        expect(r.sourcePosition).toBe(Position.Right);
        expect(r.targetPosition).toBe(Position.Left);
        expect([r.sourceX, r.sourceY, r.targetX, r.targetY]).toEqual([W, 18, 300, 45]);
    });

    it('routes bottom-to-top when the target is stacked below the source', () => {
        const s = rect(0, 0); const t = rect(10, 200);
        const r = routeEdge({ source: s, target: t, sourceHandleId: null, sourceHasFailureHandle: false, ...handles(s, t) });
        expect(r.sourcePosition).toBe(Position.Bottom);
        expect(r.targetPosition).toBe(Position.Top);
        expect(r.sourceX).toBe(W / 2);
        expect(r.sourceY).toBe(H);
        expect(r.targetX).toBe(10 + W / 2);
        expect(r.targetY).toBe(200);
        // The edge moves strictly downward: no loop back up and around.
        expect(r.targetY).toBeGreaterThan(r.sourceY);
    });

    it('separates success and failure exits along the bottom edge', () => {
        const s = rect(0, 0); const t = rect(0, 200);
        const ok = routeEdge({ source: s, target: t, sourceHandleId: 'success', sourceHasFailureHandle: true, ...handles(s, t) });
        const ko = routeEdge({ source: s, target: t, sourceHandleId: 'failure', sourceHasFailureHandle: true, ...handles(s, t, 38) });
        expect(ok.sourceX).toBeLessThan(ko.sourceX);
        expect(ok.sourceY).toBe(H);
        expect(ko.sourceY).toBe(H);
    });

    it('routes top-to-bottom when the target is stacked above the source', () => {
        const s = rect(0, 300); const t = rect(0, 0);
        const r = routeEdge({ source: s, target: t, sourceHandleId: 'success', sourceHasFailureHandle: false, ...handles(s, t) });
        expect(r.sourcePosition).toBe(Position.Top);
        expect(r.targetPosition).toBe(Position.Bottom);
        expect(r.sourceY).toBe(300);
        expect(r.targetY).toBe(H);
    });

    it('routes right-to-left for a back edge instead of looping around', () => {
        const s = rect(400, 0); const t = rect(0, 10);
        const r = routeEdge({ source: s, target: t, sourceHandleId: 'success', sourceHasFailureHandle: true, ...handles(s, t) });
        expect(r.sourcePosition).toBe(Position.Left);
        expect(r.targetPosition).toBe(Position.Right);
        expect(r.sourceX).toBe(400);
        expect(r.targetX).toBe(W);
    });

    it('falls back to the handle coordinates when node geometry is unknown', () => {
        const r = routeEdge({ source: null, target: null, sourceHandleId: null, sourceHasFailureHandle: false, sourceX: 1, sourceY: 2, targetX: 3, targetY: 4 });
        expect(r).toEqual({ sourceX: 1, sourceY: 2, targetX: 3, targetY: 4, sourcePosition: Position.Right, targetPosition: Position.Left });
    });
});

describe('edge types', () => {
    it('renders stored edges without a type through the routed BranchEdge too', () => {
        expect(edgeTypes.branch).toBe(BranchEdge);
        expect(edgeTypes.default).toBe(BranchEdge);
    });
});
