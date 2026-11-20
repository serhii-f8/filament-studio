import { create, StoreApi, UseBoundStore } from 'zustand';
import { applyEdgeChanges as applyRfEdgeChanges, applyNodeChanges as applyRfNodeChanges } from '@xyflow/react';
import type { Edge, EdgeChange, Node, NodeChange } from '@xyflow/react';

type Graph = { nodes: Node[]; edges: Edge[] };

type PublishedVersion = { version: number; published_at: string; graph?: Graph | null };

/**
 * React Flow reports bookkeeping through the same change stream as user edits:
 * on mount it measures every node (`dimensions`) and syncs selection (`select`),
 * and a `position` change without a position is only a drag start/stop marker.
 * Only changes that alter the saved graph count as edits.
 */
export function isEditingChange(change: NodeChange | EdgeChange): boolean {
    switch (change.type) {
        case 'add':
        case 'remove':
        case 'replace':
            return true;
        case 'position':
            return (change as any).position !== undefined;
        default:
            return false;
    }
}

// Key-order-insensitive serialisation, so a draft hydrated from the published graph
// compares equal even if PHP and JSON round-trips reorder object keys.
function stableStringify(value: unknown): string {
    if (Array.isArray(value)) return `[${value.map(stableStringify).join(',')}]`;
    if (value && typeof value === 'object') {
        return `{${Object.keys(value as object).sort()
            .filter((k) => (value as any)[k] !== undefined)
            .map((k) => `${JSON.stringify(k)}:${stableStringify((value as any)[k])}`)
            .join(',')}}`;
    }
    return JSON.stringify(value) ?? 'null';
}

function sameGraph(a: Graph | null | undefined, b: Graph | null | undefined): boolean {
    if (!a || !b) return false;
    return stableStringify({ nodes: a.nodes ?? [], edges: a.edges ?? [] })
        === stableStringify({ nodes: b.nodes ?? [], edges: b.edges ?? [] });
}

type FlowState = {
    nodes: Node[];
    edges: Edge[];
    selectedNodeId: string | null;
    dirty: boolean;
    publishedVersion: PublishedVersion | null;
    draftSavedAt: string | null;
    pausedRunId: string | null;
    lastStepNodeId: string | null;
    loadGraph: (g: Graph) => void;
    loadFlow: (resp: {
        draft_graph: Graph | null;
        draft_updated_at: string | null;
        published_version: PublishedVersion | null;
    }) => void;
    setNodes: (nodes: Node[]) => void;
    setEdges: (edges: Edge[]) => void;
    applyNodeChanges: (changes: NodeChange[]) => void;
    applyEdgeChanges: (changes: EdgeChange[]) => void;
    addNode: (n: Node) => void;
    removeNode: (id: string) => void;
    removeEdge: (id: string) => void;
    selectNode: (id: string | null) => void;
    updateNodeConfig: (id: string, patch: Record<string, unknown>) => void;
    markClean: () => void;
    markPublished: (version: PublishedVersion | null | undefined) => void;
    setPausedRun: (runId: string | null, nodeId: string | null) => void;
    clearPausedRun: () => void;
};

export function createFlowStore(): UseBoundStore<StoreApi<FlowState>> {
    return create<FlowState>((set) => ({
        nodes: [],
        edges: [],
        selectedNodeId: null,
        dirty: false,
        publishedVersion: null,
        draftSavedAt: null,
        pausedRunId: null,
        lastStepNodeId: null,
        loadGraph: (g) => set({ nodes: g.nodes, edges: g.edges, dirty: false }),
        loadFlow: (resp) => {
            const graph = resp.draft_graph ?? { nodes: [], edges: [] };
            // The server hydrates a draft from the published version on first open
            // (and stamps draft_updated_at). That is not an unpublished draft.
            const hydratedCopy = sameGraph(resp.draft_graph, resp.published_version?.graph);
            set({
                nodes: graph.nodes ?? [],
                edges: graph.edges ?? [],
                dirty: false,
                publishedVersion: resp.published_version ?? null,
                draftSavedAt: hydratedCopy ? null : (resp.draft_updated_at ?? null),
            });
        },
        setNodes: (nodes) => set({ nodes, dirty: true }),
        setEdges: (edges) => set({ edges, dirty: true }),
        applyNodeChanges: (changes) => set((s) => ({
            nodes: applyRfNodeChanges(changes, s.nodes),
            dirty: s.dirty || changes.some(isEditingChange),
        })),
        applyEdgeChanges: (changes) => set((s) => ({
            edges: applyRfEdgeChanges(changes, s.edges),
            dirty: s.dirty || changes.some(isEditingChange),
        })),
        addNode: (n) => set((s) => ({ nodes: [...s.nodes, n], dirty: true })),
        removeEdge: (id) => set((s) => ({
            edges: s.edges.filter((e) => e.id !== id),
            dirty: true,
        })),
        removeNode: (id) => set((s) => ({
            nodes: s.nodes.filter((x) => x.id !== id),
            edges: s.edges.filter((e) => e.source !== id && e.target !== id),
            dirty: true,
        })),
        selectNode: (id) => set({ selectedNodeId: id }),
        updateNodeConfig: (id, patch) => set((s) => ({
            nodes: s.nodes.map((n) => n.id === id
                ? { ...n, data: { ...(n.data as any), config: { ...((n.data as any).config ?? {}), ...patch } } }
                : n),
            dirty: true,
        })),
        markClean: () => set({ dirty: false, draftSavedAt: new Date().toISOString() }),
        markPublished: (version) => set((s) => ({
            draftSavedAt: null,
            publishedVersion: version ?? s.publishedVersion,
        })),
        setPausedRun: (runId, nodeId) => set({ pausedRunId: runId, lastStepNodeId: nodeId }),
        clearPausedRun: () => set({ pausedRunId: null, lastStepNodeId: null }),
    }));
}

export const useFlowStore = createFlowStore();
