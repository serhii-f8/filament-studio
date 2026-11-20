import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent } from '@testing-library/react';
import { ReactFlowProvider } from '@xyflow/react';
import { BranchEdge } from '../edges/BranchEdge';
import { useFlowStore } from '../state/useFlowStore';

// Mock EdgeLabelRenderer to render children directly (no portal needed in JSDOM)
vi.mock('@xyflow/react', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@xyflow/react')>();
    return {
        ...actual,
        EdgeLabelRenderer: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    };
});

const defaultProps = {
    id: 'e1',
    source: 'a',
    target: 'b',
    sourceX: 0,
    sourceY: 0,
    targetX: 200,
    targetY: 0,
    selected: false,
    animated: false,
    data: {},
    style: {},
    markerEnd: '',
    markerStart: '',
    sourceHandleId: null,
    targetHandleId: null,
    interactionWidth: 20,
    label: undefined,
    labelStyle: {},
    labelShowBg: false,
    labelBgStyle: {},
    labelBgPadding: [2, 4] as [number, number],
    labelBgBorderRadius: 2,
    sourcePosition: 'bottom' as any,
    targetPosition: 'top' as any,
    pathOptions: undefined,
};

describe('BranchEdge delete', () => {
    beforeEach(() => {
        useFlowStore.setState({
            nodes: [],
            edges: [{ id: 'e1', source: 'a', target: 'b', type: 'branch' } as any],
            selectedNodeId: null,
            dirty: false,
        });
    });

    it('removes the edge from the store when × is clicked', () => {
        const { getByLabelText } = render(
            <ReactFlowProvider><svg>
                <BranchEdge {...defaultProps} />
            </svg></ReactFlowProvider>
        );
        fireEvent.click(getByLabelText('delete edge'));
        expect(useFlowStore.getState().edges).toHaveLength(0);
    });

    it('shows the × (opacity-100) when the edge is selected, hidden otherwise', () => {
        const hidden = render(<ReactFlowProvider><svg><BranchEdge {...defaultProps} selected={false} /></svg></ReactFlowProvider>);
        expect(hidden.getByLabelText('delete edge').getAttribute('class')).toContain('opacity-0');
        hidden.unmount();
        const shown = render(<ReactFlowProvider><svg><BranchEdge {...defaultProps} selected={true} /></svg></ReactFlowProvider>);
        expect(shown.getByLabelText('delete edge').getAttribute('class')).toContain('opacity-100');
    });
});

describe('BranchEdge routing', () => {
    const stacked = [
        { id: 'a', type: 'trigger', position: { x: 0, y: 0 }, measured: { width: 120, height: 50 }, data: {} },
        { id: 'b', type: 'operation', position: { x: 0, y: 200 }, measured: { width: 120, height: 50 }, data: {} },
    ] as any;

    it('draws a vertically stacked edge from the source bottom to the target top', () => {
        const { container } = render(
            <ReactFlowProvider initialNodes={stacked}>
                <svg>
                    {/* React Flow hands the edge the right/left handle coordinates. */}
                    <BranchEdge {...defaultProps} sourceX={120} sourceY={25} targetX={0} targetY={225} sourceHandleId="success" />
                </svg>
            </ReactFlowProvider>
        );
        const d = container.querySelector('path')!.getAttribute('d')!;
        expect(d.startsWith('M60,50')).toBe(true);
        expect(d.endsWith('60,200')).toBe(true);
    });

    it('falls back to the handle coordinates when the nodes are not measured yet', () => {
        const { container } = render(
            <ReactFlowProvider>
                <svg><BranchEdge {...defaultProps} sourceX={120} sourceY={25} targetX={300} targetY={25} /></svg>
            </ReactFlowProvider>
        );
        const d = container.querySelector('path')!.getAttribute('d')!;
        expect(d.startsWith('M120,25')).toBe(true);
        expect(d.endsWith('300,25')).toBe(true);
    });
});
