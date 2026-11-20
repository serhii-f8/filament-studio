import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import { createFlowStore, isEditingChange, useFlowStore } from '../state/useFlowStore';
import { FlowCanvasApp } from '../flow-canvas';
import * as apiMod from '../api/useFlowApi';

const graph = {
    nodes: [
        { id: 't', type: 'trigger', position: { x: 0, y: 0 }, data: { triggerType: 'manual' } },
        { id: 'o', type: 'operation', position: { x: 0, y: 200 }, data: { operationType: 'log' } },
    ],
    edges: [{ id: 'e', source: 't', target: 'o', sourceHandle: 'success', type: 'branch' }],
} as any;

function loaded() {
    const s = createFlowStore();
    s.getState().loadFlow({ draft_graph: structuredClone(graph), draft_updated_at: null, published_version: null });
    return s;
}

describe('isEditingChange', () => {
    it('ignores React Flow bookkeeping changes', () => {
        expect(isEditingChange({ type: 'dimensions', id: 't', dimensions: { width: 1, height: 1 } } as any)).toBe(false);
        expect(isEditingChange({ type: 'select', id: 't', selected: true } as any)).toBe(false);
        expect(isEditingChange({ type: 'position', id: 't' } as any)).toBe(false);
    });

    it('counts real edits', () => {
        expect(isEditingChange({ type: 'position', id: 't', position: { x: 5, y: 5 }, dragging: true } as any)).toBe(true);
        expect(isEditingChange({ type: 'remove', id: 't' } as any)).toBe(true);
        expect(isEditingChange({ type: 'add', item: {} } as any)).toBe(true);
        expect(isEditingChange({ type: 'replace', id: 't', item: {} } as any)).toBe(true);
    });
});

describe('applyNodeChanges / applyEdgeChanges dirty tracking', () => {
    it('does not mark dirty for measurement and selection on mount', () => {
        const s = loaded();
        s.getState().applyNodeChanges([
            { type: 'dimensions', id: 't', dimensions: { width: 120, height: 40 }, setAttributes: true },
            { type: 'dimensions', id: 'o', dimensions: { width: 120, height: 40 }, setAttributes: true },
            { type: 'select', id: 't', selected: true },
        ] as any);
        s.getState().applyEdgeChanges([{ type: 'select', id: 'e', selected: true }] as any);
        expect(s.getState().dirty).toBe(false);
        expect((s.getState().nodes[0] as any).measured).toEqual({ width: 120, height: 40 });
        expect(s.getState().nodes[0].selected).toBe(true);
    });

    it('marks dirty when a node is dragged', () => {
        const s = loaded();
        s.getState().applyNodeChanges([{ type: 'position', id: 'o', position: { x: 50, y: 250 }, dragging: true }] as any);
        expect(s.getState().dirty).toBe(true);
        expect(s.getState().nodes[1].position).toEqual({ x: 50, y: 250 });
    });

    it('marks dirty when an edge is removed', () => {
        const s = loaded();
        s.getState().applyEdgeChanges([{ type: 'remove', id: 'e' }] as any);
        expect(s.getState().dirty).toBe(true);
        expect(s.getState().edges).toHaveLength(0);
    });

    it('keeps an existing dirty flag when only bookkeeping changes follow', () => {
        const s = loaded();
        s.getState().updateNodeConfig('o', { message: 'hi' });
        s.getState().applyNodeChanges([{ type: 'select', id: 'o', selected: false }] as any);
        expect(s.getState().dirty).toBe(true);
    });
});

describe('server-side draft detection', () => {
    it('treats a draft identical to the published graph as no unpublished draft', () => {
        const s = createFlowStore();
        s.getState().loadFlow({
            draft_graph: structuredClone(graph),
            draft_updated_at: '2026-11-20T10:00:00Z',
            published_version: { version: 2, published_at: '2026-11-19T00:00:00Z', graph: structuredClone(graph) } as any,
        });
        expect(s.getState().draftSavedAt).toBeNull();
        expect(s.getState().publishedVersion?.version).toBe(2);
    });

    it('keeps draftSavedAt when the draft genuinely differs from the published graph', () => {
        const changed = structuredClone(graph);
        changed.nodes[1].data.config = { message: 'new' };
        const s = createFlowStore();
        s.getState().loadFlow({
            draft_graph: changed,
            draft_updated_at: '2026-11-20T10:00:00Z',
            published_version: { version: 2, published_at: '2026-11-19T00:00:00Z', graph: structuredClone(graph) } as any,
        });
        expect(s.getState().draftSavedAt).toBe('2026-11-20T10:00:00Z');
    });

    it('records a save time when marked clean and clears the draft once published', () => {
        const s = loaded();
        s.getState().updateNodeConfig('o', { message: 'hi' });
        s.getState().markClean();
        expect(s.getState().dirty).toBe(false);
        expect(s.getState().draftSavedAt).not.toBeNull();
        s.getState().markPublished({ version: 3, published_at: '2026-11-20T11:00:00Z' });
        expect(s.getState().draftSavedAt).toBeNull();
        expect(s.getState().publishedVersion?.version).toBe(3);
    });
});

describe('opening a flow in the designer', () => {
    beforeEach(() => {
        useFlowStore.setState({ nodes: [], edges: [], selectedNodeId: null, dirty: false, draftSavedAt: null, publishedVersion: null });
    });

    it('does not show "Draft (unsaved)" or autosave before any edit', async () => {
        const saveGraph = vi.fn().mockResolvedValue(undefined);
        vi.spyOn(apiMod, 'createFlowApi').mockReturnValue({
            loadGraph: vi.fn().mockResolvedValue({
                draft_graph: structuredClone(graph),
                draft_updated_at: '2026-11-20T10:00:00Z',
                published_version: { version: 4, published_at: '2026-11-19T00:00:00Z', graph: structuredClone(graph) },
            }),
            saveGraph,
            publish: vi.fn(),
            loadMeta: vi.fn().mockResolvedValue({ triggers: [], operations: [], collections: [] }),
            testRun: vi.fn(),
            pollRun: vi.fn(),
        } as any);

        const { queryByText, findByText } = render(<FlowCanvasApp flowId="f" apiBase="/api/studio" apiKey="" flowName="X" />);
        await waitFor(() => expect(useFlowStore.getState().nodes.length).toBe(2));

        expect(await findByText('Published v4')).toBeTruthy();
        // Give React Flow a chance to emit its mount-time dimension/select changes.
        await new Promise((r) => setTimeout(r, 50));
        expect(queryByText(/Draft \(unsaved\)/)).toBeNull();
        expect(useFlowStore.getState().dirty).toBe(false);
        expect(saveGraph).not.toHaveBeenCalled();
    });
});
