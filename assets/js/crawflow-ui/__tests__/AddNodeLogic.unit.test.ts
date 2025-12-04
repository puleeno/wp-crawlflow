/**
 * Unit Tests for Add Node Logic
 * 
 * Tests chi tiết logic thêm node, đặc biệt là Processor chain logic.
 */

import { Node, Edge } from '@xyflow/react';
import { connectionRuleEngine } from '../rules/ConnectionRules';

describe('Add Node Logic - Unit Tests', () => {
  // ============================================================================
  // HELPER: Simulate finding last processor in chain
  // ============================================================================

  function findLastProcessorInChain(workerId: string, nodes: Node[], edges: Edge[]): Node | null {
    // Find first processor from worker
    const firstProcessorEdge = edges.find(e => 
      e.source === workerId && nodes.find(n => n.id === e.target)?.type === 'processor'
    );

    if (!firstProcessorEdge) {
      return null; // No processors yet
    }

    let lastProcessorId = firstProcessorEdge.target;

    // Traverse chain to find last
    while (true) {
      const nextEdge = edges.find(e => 
        e.source === lastProcessorId && nodes.find(n => n.id === e.target)?.type === 'processor'
      );

      if (nextEdge) {
        lastProcessorId = nextEdge.target;
      } else {
        break; // Found last
      }
    }

    return nodes.find(n => n.id === lastProcessorId) || null;
  }

  // ============================================================================
  // ADD DATA SOURCE TESTS
  // ============================================================================

  describe('Add Data Source', () => {
    test('First data source → Create repository automatically', () => {
      const nodes: Node[] = [];
      const edges: Edge[] = [];

      // Simulate adding first data source
      const dataSource: Node = {
        id: 's1',
        type: 'start',
        position: { x: 100, y: 50 },
        data: {}
      };

      const repository: Node = {
        id: 'repository-node',
        type: 'repository',
        position: { x: 100, y: 200 },
        data: {},
        deletable: false
      };

      const edge: Edge = {
        id: 'e-s1-repo',
        source: 's1',
        target: 'repository-node'
      };

      const newNodes = [...nodes, dataSource, repository];
      const newEdges = [...edges, edge];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });

    test('Second data source → Connect to existing repository', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 50, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' }
      ];

      // Add second source
      const dataSource2: Node = {
        id: 's2',
        type: 'start',
        position: { x: 150, y: 50 },
        data: {}
      };

      const edge2: Edge = {
        id: 'e-s2-repo',
        source: 's2',
        target: 'repository-node'
      };

      const newNodes = [...nodes, dataSource2];
      const newEdges = [...edges, edge2];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify both sources connect to same repo
      const repoIncoming = newEdges.filter(e => e.target === 'repository-node');
      expect(repoIncoming).toHaveLength(2);
    });
  });

  // ============================================================================
  // ADD WORKER TESTS
  // ============================================================================

  describe('Add Worker', () => {
    test('Add worker → Must connect from repository', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' }
      ];

      // Add worker
      const worker: Node = {
        id: 'w1',
        type: 'worker',
        position: { x: 100, y: 350 },
        data: {}
      };

      const workerEdge: Edge = {
        id: 'e-repo-w1',
        source: 'repository-node',  // MUST be from repository
        target: 'w1'
      };

      const newNodes = [...nodes, worker];
      const newEdges = [...edges, workerEdge];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });

    test('Add worker without repository connection → Invalid', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} }  // Orphaned!
      ];

      const edges: Edge[] = [];  // No connection!

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('Worker') && e.includes('MUST have incoming from Repository'))).toBe(true);
    });
  });

  // ============================================================================
  // ADD PROCESSOR TESTS (CRITICAL)
  // ============================================================================

  describe('Add Processor - Critical Cases', () => {
    test('Case 1: Add first processor from worker → Connect to worker', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' }
      ];

      // Selected node: w1
      const selectedNode = nodes.find(n => n.id === 'w1')!;

      // Add processor
      const processor: Node = {
        id: 'p1',
        type: 'processor',
        position: { x: 0, y: 200 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-w1-p1',
        source: 'w1',  // Connect to worker (no existing processors)
        target: 'p1'
      };

      const newNodes = [...nodes, processor];
      const newEdges = [...edges, processorEdge];

      const validation = connectionRuleEngine.validateConnection(
        selectedNode,
        processor,
        edges,
        newNodes
      );

      expect(validation.isValid).toBe(true);
    });

    test('Case 2: Add second processor from worker → Connect to end of chain', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' },
        { id: 'e2', source: 'w1', target: 'p1' }
      ];

      // User chọn w1 (worker), add processor
      const selectedNode = nodes.find(n => n.id === 'w1')!;

      // Logic SHOULD find p1 (last in chain) and connect to it
      const lastProcessor = findLastProcessorInChain('w1', nodes, edges);
      expect(lastProcessor?.id).toBe('p1');

      // Add new processor
      const processor2: Node = {
        id: 'p2',
        type: 'processor',
        position: { x: 0, y: 300 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-p1-p2',
        source: 'p1',  // Connect to LAST processor, not worker!
        target: 'p2'
      };

      const newNodes = [...nodes, processor2];
      const newEdges = [...edges, processorEdge];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify p2's incoming is from p1
      const p2Incoming = newEdges.find(e => e.target === 'p2');
      expect(p2Incoming?.source).toBe('p1');
    });

    test('Case 3: Add processor from processor → Connect directly (NO chain traversal)', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' },
        { id: 'e2', source: 'w1', target: 'p1' },
        { id: 'e3', source: 'p1', target: 'p2' }
      ];

      // User chọn p2 (processor), add new processor
      const selectedNode = nodes.find(n => n.id === 'p2')!;

      // Add new processor
      const processor3: Node = {
        id: 'p3',
        type: 'processor',
        position: { x: 0, y: 400 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-p2-p3',
        source: 'p2',  // CRITICAL: Connect to selected processor (p2), NOT w1!
        target: 'p3'
      };

      const newNodes = [...nodes, processor3];
      const newEdges = [...edges, processorEdge];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // CRITICAL VERIFICATION: p3's incoming MUST be from p2, NOT from w1
      const p3Incoming = newEdges.find(e => e.target === 'p3');
      expect(p3Incoming?.source).toBe('p2');
      expect(p3Incoming?.source).not.toBe('w1');
    });

    test('Case 4: Add processor from middle of chain → Should connect to selected', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' }
      ];

      // User chọn p2 (middle of chain), add new processor
      const selectedNode = nodes.find(n => n.id === 'p2')!;

      const processor4: Node = {
        id: 'p4',
        type: 'processor',
        position: { x: 0, y: 250 },
        data: {}
      };

      // Expected: p2 → p4 (và p2 → p3 vẫn tồn tại → p2 has 2 outgoing → INVALID!)
      // Hoặc: p2 → p4, remove p2 → p3, add p4 → p3

      // Nếu giữ cả 2 edges:
      const newEdge: Edge = {
        id: 'e-p2-p4',
        source: 'p2',
        target: 'p4'
      };

      let newNodes = [...nodes, processor4];
      let newEdges = [...edges, newEdge];

      // This would be INVALID (p2 has 2 outgoing)
      let validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('maximum 1 outgoing'))).toBe(true);

      // Fix: Remove old edge p2 → p3, then add p4 → p3
      newEdges = newEdges.filter(e => e.id !== 'e3');  // Remove p2 → p3
      newEdges = [...newEdges, newEdge];  // Add p2 → p4

      const reconnectEdge: Edge = {
        id: 'e-p4-p3',
        source: 'p4',
        target: 'p3'
      };

      newEdges = [...newEdges, reconnectEdge];  // Add p4 → p3

      // Now: w1 → p1 → p2 → p4 → p3
      validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // ADD EXTRACTOR TESTS
  // ============================================================================

  describe('Add Extractor', () => {
    test('Add first extractor to worker → Valid', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' }
      ];

      // Add HTML extractor
      const extractor: Node = {
        id: 'html1',
        type: 'html-data-extractor',
        position: { x: -50, y: 50 },
        data: {}
      };

      const extractorEdge: Edge = {
        id: 'e-html1-w1',
        source: 'html1',
        target: 'w1'
      };

      const newNodes = [...nodes, extractor];
      const newEdges = [...edges, extractorEdge];

      const validation = connectionRuleEngine.validateConnection(
        extractor,
        nodes[1],  // w1
        edges,
        newNodes
      );

      expect(validation.isValid).toBe(true);
    });

    test('Add second extractor to same worker → Rejected', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'html1', type: 'html-data-extractor', position: { x: -50, y: 50 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' },
        { id: 'e2', source: 'html1', target: 'w1' }
      ];

      // Try to add second extractor
      const extractor2: Node = {
        id: 'csv1',
        type: 'csv-extractor',
        position: { x: 50, y: 50 },
        data: {}
      };

      const extractorEdge: Edge = {
        id: 'e-csv1-w1',
        source: 'csv1',
        target: 'w1'
      };

      const newNodes = [...nodes, extractor2];

      const validation = connectionRuleEngine.validateConnection(
        extractor2,
        nodes[1],  // w1
        edges,
        newNodes
      );

      expect(validation.isValid).toBe(false);
      expect(validation.error).toContain('Worker can only have ONE data extractor input');
    });

    test('Replace extractor → Remove old, add new', () => {
      let nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'html1', type: 'html-data-extractor', position: { x: 0, y: 50 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'w1' },
        { id: 'e2', source: 'html1', target: 'w1' }
      ];

      // Remove old extractor
      nodes = nodes.filter(n => n.id !== 'html1');
      edges = edges.filter(e => e.source !== 'html1' && e.target !== 'html1');

      // Add new extractor
      const csvExtractor: Node = {
        id: 'csv1',
        type: 'csv-extractor',
        position: { x: 0, y: 50 },
        data: {}
      };

      const extractorEdge: Edge = {
        id: 'e-csv1-w1',
        source: 'csv1',
        target: 'w1'
      };

      nodes = [...nodes, csvExtractor];
      edges = [...edges, extractorEdge];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // PROCESSOR CHAIN FINDING TESTS
  // ============================================================================

  describe('Processor Chain Finding Logic', () => {
    test('Find last processor in single-processor chain', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' }
      ];

      const lastProcessor = findLastProcessorInChain('w1', nodes, edges);
      expect(lastProcessor?.id).toBe('p1');
    });

    test('Find last processor in multi-processor chain', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' }
      ];

      const lastProcessor = findLastProcessorInChain('w1', nodes, edges);
      expect(lastProcessor?.id).toBe('p3');
    });

    test('Worker without processors → Return null', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [];

      const lastProcessor = findLastProcessorInChain('w1', nodes, edges);
      expect(lastProcessor).toBeNull();
    });
  });

  // ============================================================================
  // SELECTED NODE LOGIC TESTS (CRITICAL)
  // ============================================================================

  describe('Selected Node Logic (Critical)', () => {
    test('When Worker selected → finalSourceNode should be last processor OR worker', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      const selectedNode = nodes.find(n => n.id === 'w1')!;
      const lastProcessor = findLastProcessorInChain('w1', nodes, edges);

      // finalSourceNode should be p2 (last processor)
      expect(lastProcessor?.id).toBe('p2');

      // If adding processor, it should connect to p2
      const newProcessor: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} };
      const newEdge: Edge = { id: 'e-p2-p3', source: 'p2', target: 'p3' };

      const validation = connectionRuleEngine.validateConnection(
        lastProcessor!,
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      expect(validation.isValid).toBe(true);
    });

    test('When Processor selected → finalSourceNode MUST be that processor', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      // User chọn p1 (NOT p2, NOT w1)
      const selectedNode = nodes.find(n => n.id === 'p1')!;

      // finalSourceNode MUST be p1 (NOT p2, NOT w1)
      // Because user explicitly selected p1

      const newProcessor: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 150 }, data: {} };
      
      // Connect to p1 (selected)
      const newEdge: Edge = { id: 'e-p1-p3', source: 'p1', target: 'p3' };

      // This creates: w1 → p1 → p3 AND p1 → p2 (p1 has 2 outgoing → INVALID!)
      let newEdges = [...edges, newEdge];

      let validation = connectionRuleEngine.validateFlow([...nodes, newProcessor], newEdges);
      expect(validation.isValid).toBe(false);

      // To make it valid, need to remove p1 → p2 first
      newEdges = edges.filter(e => e.id !== 'e2');  // Remove p1 → p2
      newEdges = [...newEdges, newEdge];  // Add p1 → p3

      validation = connectionRuleEngine.validateFlow([...nodes, newProcessor], newEdges);
      expect(validation.isValid).toBe(false);  // Still invalid: p2 is now orphaned

      // Need to also connect p3 → p2
      const reconnectEdge: Edge = { id: 'e-p3-p2', source: 'p3', target: 'p2' };
      newEdges = [...newEdges, reconnectEdge];

      validation = connectionRuleEngine.validateFlow([...nodes, newProcessor], newEdges);
      expect(validation.isValid).toBe(true);
    });
  });
});

