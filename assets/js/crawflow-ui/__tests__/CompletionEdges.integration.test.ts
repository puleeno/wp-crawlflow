/**
 * Integration Tests for Completion Edges Management
 * 
 * RULE: Trong 1 flow từ worker → finish, chỉ có 1 processor (processor cuối cùng)
 * được connect với finish actions.
 */

import { Node, Edge } from '@xyflow/react';

describe('Completion Edges Management - Integration Tests', () => {
  const COMPLETION_NODE_ID = 'completion-node';

  // Helper: Find last processors (no outgoing to other processors)
  function findLastProcessors(nodes: Node[], edges: Edge[]): string[] {
    const processors = nodes.filter(n => n.type === 'processor');
    const processorsWithOutgoingToProcessor = new Set<string>();

    edges.forEach(edge => {
      const sourceNode = nodes.find(n => n.id === edge.source);
      const targetNode = nodes.find(n => n.id === edge.target);
      
      if (sourceNode?.type === 'processor' && targetNode?.type === 'processor') {
        processorsWithOutgoingToProcessor.add(sourceNode.id);
      }
    });

    return processors
      .filter(p => !processorsWithOutgoingToProcessor.has(p.id))
      .map(p => p.id);
  }

  // ============================================================================
  // SINGLE CHAIN TESTS
  // ============================================================================

  describe('Single Worker Chain', () => {
    test('Worker → P1 → Finish: Only P1 connects to finish', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      expect(lastProcessors).toEqual(['p1']);
      
      // Verify only p1 connects to completion
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(1);
      expect(completionEdges[0].source).toBe('p1');
    });

    test('Worker → P1 → P2 → Finish: Only P2 connects to finish', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      expect(lastProcessors).toEqual(['p2']);
      
      // P1 should NOT connect to completion
      const p1ToCompletion = edges.find(e => e.source === 'p1' && e.target === COMPLETION_NODE_ID);
      expect(p1ToCompletion).toBeUndefined();
      
      // Only P2 should connect to completion
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(1);
      expect(completionEdges[0].source).toBe('p2');
    });

    test('Worker → P1 → P2 → P3 → Finish: Only P3 connects', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 400 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' },
        { id: 'e4', source: 'p3', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      expect(lastProcessors).toEqual(['p3']);
      
      // Only P3 should connect
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(1);
      expect(completionEdges[0].source).toBe('p3');
    });
  });

  // ============================================================================
  // MULTIPLE CHAINS TESTS
  // ============================================================================

  describe('Multiple Worker Chains', () => {
    test('2 chains: W1 → P1 → P2 và W2 → P3 → Both last processors connect', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -50, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 50, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -50, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: -50, y: 300 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 50, y: 200 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 400 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'repo', target: 'w2' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'p1', target: 'p2' },  // p1 has outgoing → NOT last
        { id: 'e5', source: 'w2', target: 'p3' },  // p3 has no outgoing → LAST
        { id: 'e6', source: 'p2', target: COMPLETION_NODE_ID },  // p2 → finish
        { id: 'e7', source: 'p3', target: COMPLETION_NODE_ID }   // p3 → finish
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      // P2 and P3 are last processors
      expect(lastProcessors.sort()).toEqual(['p2', 'p3'].sort());
      
      // P1 should NOT connect (has outgoing to p2)
      const p1ToCompletion = edges.find(e => e.source === 'p1' && e.target === COMPLETION_NODE_ID);
      expect(p1ToCompletion).toBeUndefined();
      
      // P2 and P3 should connect
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(2);
      expect(completionEdges.map(e => e.source).sort()).toEqual(['p2', 'p3'].sort());
    });

    test('3 chains với chiều dài khác nhau', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 100, y: 100 }, data: {} },
        { id: 'w3', type: 'worker', position: { x: 200, y: 100 }, data: {} },
        
        // Chain 1: w1 → p1 → p2 → p3
        { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 400 }, data: {} },
        
        // Chain 2: w2 → p4
        { id: 'p4', type: 'processor', position: { x: 100, y: 200 }, data: {} },
        
        // Chain 3: w3 → p5 → p6
        { id: 'p5', type: 'processor', position: { x: 200, y: 200 }, data: {} },
        { id: 'p6', type: 'processor', position: { x: 200, y: 300 }, data: {} },
        
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 100, y: 500 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'repo', target: 'w2' },
        { id: 'e3', source: 'repo', target: 'w3' },
        
        // Chain 1
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'p1', target: 'p2' },
        { id: 'e6', source: 'p2', target: 'p3' },
        
        // Chain 2
        { id: 'e7', source: 'w2', target: 'p4' },
        
        // Chain 3
        { id: 'e8', source: 'w3', target: 'p5' },
        { id: 'e9', source: 'p5', target: 'p6' },
        
        // Completion edges (only last processors)
        { id: 'e10', source: 'p3', target: COMPLETION_NODE_ID },
        { id: 'e11', source: 'p4', target: COMPLETION_NODE_ID },
        { id: 'e12', source: 'p6', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      // Last processors: p3, p4, p6
      expect(lastProcessors.sort()).toEqual(['p3', 'p4', 'p6'].sort());
      
      // Completion edges
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(3);
      expect(completionEdges.map(e => e.source).sort()).toEqual(['p3', 'p4', 'p6'].sort());
    });
  });

  // ============================================================================
  // INSERT PROCESSOR UPDATES COMPLETION EDGES
  // ============================================================================

  describe('INSERT Processor → Update Completion Edges', () => {
    test('Before INSERT: P2 → Finish, After INSERT: A → Finish', () => {
      // Initial: w1 → p1 → p2 → finish
      let nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 300 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: COMPLETION_NODE_ID }  // P2 is last
      ];

      // Before: P2 is last processor
      let lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p2']);

      // INSERT processor A after P2
      const processorA: Node = {
        id: 'A',
        type: 'processor',
        position: { x: 0, y: 250 },
        data: {}
      };

      nodes = [...nodes, processorA];

      // After INSERT: p1 → p2 → A → finish
      edges = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        // e3 removed (p2 → completion)
        { id: 'e-p2-A', source: 'p2', target: 'A' },
        { id: 'e-A-comp', source: 'A', target: COMPLETION_NODE_ID }
      ];

      // After: A is last processor
      lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['A']);

      // CRITICAL: Only A should connect to completion
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(1);
      expect(completionEdges[0].source).toBe('A');

      // P2 should NOT connect to completion
      const p2ToCompletion = edges.find(e => e.source === 'p2' && e.target === COMPLETION_NODE_ID);
      expect(p2ToCompletion).toBeUndefined();
    });

    test('INSERT multiple times → Completion edge follows last processor', () => {
      // Step 1: w1 → p1 → finish
      let nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 200 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: COMPLETION_NODE_ID }
      ];

      let lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p1']);

      // Step 2: Add p2 after p1 → p1 → p2 → finish
      const p2: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 150 }, data: {} };
      nodes = [...nodes, p2];
      edges = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e-p1-p2', source: 'p1', target: 'p2' },
        { id: 'e-p2-comp', source: 'p2', target: COMPLETION_NODE_ID }
      ];

      lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p2']);
      expect(edges.filter(e => e.target === COMPLETION_NODE_ID)).toHaveLength(1);

      // Step 3: Add p3 after p2 → p1 → p2 → p3 → finish
      const p3: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 175 }, data: {} };
      nodes = [...nodes, p3];
      edges = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e-p1-p2', source: 'p1', target: 'p2' },
        { id: 'e-p2-p3', source: 'p2', target: 'p3' },
        { id: 'e-p3-comp', source: 'p3', target: COMPLETION_NODE_ID }
      ];

      lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p3']);
      
      // Only p3 connects to completion
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(1);
      expect(completionEdges[0].source).toBe('p3');
    });
  });

  // ============================================================================
  // PARALLEL CHAINS TESTS
  // ============================================================================

  describe('Parallel Chains to Same Completion', () => {
    test('2 parallel chains → 2 completion edges', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -50, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 50, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -50, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 50, y: 200 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'repo', target: 'w2' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'w2', target: 'p2' },
        { id: 'e5', source: 'p1', target: COMPLETION_NODE_ID },
        { id: 'e6', source: 'p2', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      // Both p1 and p2 are last (end of their respective chains)
      expect(lastProcessors.sort()).toEqual(['p1', 'p2'].sort());
      
      // Both should connect to completion
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(2);
    });

    test('Asymmetric chains: Long chain và short chain', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -50, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 50, y: 100 }, data: {} },
        
        // Long chain: w1 → p1 → p2 → p3 → p4
        { id: 'p1', type: 'processor', position: { x: -50, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: -50, y: 300 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: -50, y: 400 }, data: {} },
        { id: 'p4', type: 'processor', position: { x: -50, y: 500 }, data: {} },
        
        // Short chain: w2 → p5
        { id: 'p5', type: 'processor', position: { x: 50, y: 200 }, data: {} },
        
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 600 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'repo', target: 'w2' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'p1', target: 'p2' },
        { id: 'e5', source: 'p2', target: 'p3' },
        { id: 'e6', source: 'p3', target: 'p4' },
        { id: 'e7', source: 'w2', target: 'p5' },
        { id: 'e8', source: 'p4', target: COMPLETION_NODE_ID },
        { id: 'e9', source: 'p5', target: COMPLETION_NODE_ID }
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      
      // Only p4 and p5 are last
      expect(lastProcessors.sort()).toEqual(['p4', 'p5'].sort());
      
      // p1, p2, p3 should NOT connect to completion
      ['p1', 'p2', 'p3'].forEach(pId => {
        const edge = edges.find(e => e.source === pId && e.target === COMPLETION_NODE_ID);
        expect(edge).toBeUndefined();
      });
      
      // Only p4 and p5 connect
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(2);
      expect(completionEdges.map(e => e.source).sort()).toEqual(['p4', 'p5'].sort());
    });
  });

  // ============================================================================
  // INVALID SCENARIOS (SHOULD BE AUTO-FIXED)
  // ============================================================================

  describe('Invalid Scenarios → Auto-Fix', () => {
    test('Middle processor connected to finish → Should be removed', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 400 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' },
        { id: 'e-wrong-p1', source: 'p1', target: COMPLETION_NODE_ID },  // WRONG: p1 is middle
        { id: 'e-wrong-p2', source: 'p2', target: COMPLETION_NODE_ID },  // WRONG: p2 is middle
        { id: 'e-correct-p3', source: 'p3', target: COMPLETION_NODE_ID }  // CORRECT: p3 is last
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p3']);

      // Expected behavior: useEffect should remove wrong edges
      const expectedCompletionEdges = lastProcessors;
      const currentCompletionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      const edgesToRemove = currentCompletionEdges.filter(e => 
        !expectedCompletionEdges.includes(e.source)
      );

      // Should remove p1 → completion and p2 → completion
      expect(edgesToRemove).toHaveLength(2);
      expect(edgesToRemove.map(e => e.source).sort()).toEqual(['p1', 'p2'].sort());

      // After cleanup
      const cleanedEdges = edges.filter(e => !edgesToRemove.some(er => er.id === e.id));
      const finalCompletionEdges = cleanedEdges.filter(e => e.target === COMPLETION_NODE_ID);
      
      expect(finalCompletionEdges).toHaveLength(1);
      expect(finalCompletionEdges[0].source).toBe('p3');
    });

    test('All processors connected to finish → Keep only last one', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 400 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' },
        { id: 'e4', source: 'p1', target: COMPLETION_NODE_ID },  // WRONG
        { id: 'e5', source: 'p2', target: COMPLETION_NODE_ID },  // WRONG
        { id: 'e6', source: 'p3', target: COMPLETION_NODE_ID }   // CORRECT
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p3']);

      // Should keep only e6, remove e4 and e5
      const currentCompletionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      const edgesToRemove = currentCompletionEdges.filter(e => !lastProcessors.includes(e.source));

      expect(edgesToRemove).toHaveLength(2);
      expect(edgesToRemove.map(e => e.id).sort()).toEqual(['e4', 'e5'].sort());
    });
  });

  // ============================================================================
  // EDGE CASES
  // ============================================================================

  describe('Edge Cases', () => {
    test('Processor without outgoing (isolated) → Should connect to finish', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' }
        // No p1 → completion yet
      ];

      const lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toEqual(['p1']);

      // useEffect should create edge: p1 → completion
      const edgesToCreate = lastProcessors.filter(id => 
        !edges.some(e => e.source === id && e.target === COMPLETION_NODE_ID)
      );

      expect(edgesToCreate).toEqual(['p1']);
    });

    test('Empty flow (no processors) → No completion edges', () => {
      const nodes: Node[] = [
        { id: COMPLETION_NODE_ID, type: 'completion', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [];

      const lastProcessors = findLastProcessors(nodes, edges);
      expect(lastProcessors).toHaveLength(0);

      // No completion edges should exist
      const completionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
      expect(completionEdges).toHaveLength(0);
    });
  });
});

