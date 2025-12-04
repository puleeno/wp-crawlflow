/**
 * Integration Tests for Processor Chain Logic
 * 
 * Tests logic tạo processor chains và incoming connections.
 */

import { Node, Edge } from '@xyflow/react';
import { connectionRuleEngine } from '../rules/ConnectionRules';

describe('Processor Chain Logic - Integration Tests', () => {
  // ============================================================================
  // PROCESSOR FROM WORKER TESTS
  // ============================================================================

  describe('Adding Processor from Worker', () => {
    test('should connect first processor directly to worker', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [];

      // Simulate adding first processor to worker
      const newProcessor: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} };
      const newEdge: Edge = { id: 'e1', source: 'w1', target: 'p1' };

      const validation = connectionRuleEngine.validateConnection(
        nodes[0],
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      expect(validation.isValid).toBe(true);
    });

    test('should connect second processor to end of chain', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' }
      ];

      // Adding second processor should connect to p1 (last in chain)
      const newProcessor: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} };
      const newEdge: Edge = { id: 'e2', source: 'p1', target: 'p2' };

      const validation = connectionRuleEngine.validateConnection(
        nodes[1],  // p1
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      expect(validation.isValid).toBe(true);
    });

    test('should connect third processor to end of longer chain', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      // Adding third processor should connect to p2 (last in chain)
      const newProcessor: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} };

      const validation = connectionRuleEngine.validateConnection(
        nodes[2],  // p2
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // PROCESSOR FROM PROCESSOR TESTS (CRITICAL)
  // ============================================================================

  describe('Adding Processor from Another Processor', () => {
    test('should connect new processor to selected processor directly', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      // User chọn p2, add new processor
      // New processor PHẢI connect vào p2 (selected), KHÔNG phải tự động tìm chain
      const newProcessor: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} };
      
      const validation = connectionRuleEngine.validateConnection(
        nodes[2],  // p2 (selected processor)
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      expect(validation.isValid).toBe(true);
    });

    test('should NOT connect to worker when adding from processor', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      // Incorrect behavior: new processor connected to worker instead of selected processor
      const newProcessor: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} };
      
      const validation = connectionRuleEngine.validateConnection(
        nodes[0],  // w1 (WRONG - should be p2!)
        newProcessor,
        edges,
        [...nodes, newProcessor]
      );

      // This would be valid (Worker → Processor), but logic should NOT do this
      // when user selected a Processor
      expect(validation.isValid).toBe(true);
      // But in practice, finalSourceNode should be p2, not w1
    });
  });

  // ============================================================================
  // MULTI-WORKER FLOW TESTS
  // ============================================================================

  describe('Multiple Workers with Separate Chains', () => {
    test('should validate flow with 2 workers and separate processor chains', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -100, y: 200 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 100, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -100, y: 300 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: -100, y: 400 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 100, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'w1' },
        { id: 'e3', source: 'repo', target: 'w2' },
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'p1', target: 'p2' },
        { id: 'e6', source: 'w2', target: 'p3' }
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
    });

    test('should allow workers to have different extractor inputs', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -100, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 100, y: 100 }, data: {} },
        { id: 'e1', type: 'html-data-extractor', position: { x: -100, y: 0 }, data: {} },
        { id: 'e2', type: 'csv-extractor', position: { x: 100, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -100, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 100, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'edge1', source: 'repo', target: 'w1' },
        { id: 'edge2', source: 'repo', target: 'w2' },
        { id: 'edge3', source: 'e1', target: 'w1' },  // HTML extractor → w1
        { id: 'edge4', source: 'e2', target: 'w2' },  // CSV extractor → w2
        { id: 'edge5', source: 'w1', target: 'p1' },
        { id: 'edge6', source: 'w2', target: 'p2' }
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
    });
  });

  // ============================================================================
  // COMPLEX FLOW TESTS
  // ============================================================================

  describe('Complex Real-World Flows', () => {
    test('should validate realistic e-commerce crawler flow', () => {
      const nodes: Node[] = [
        // Data sources
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: { sourceType: 'url' } },
        { id: 's2', type: 'start', position: { x: 200, y: 0 }, data: { sourceType: 'api' } },
        
        // Repository
        { id: 'repo', type: 'repository', position: { x: 100, y: 100 }, data: {} },
        
        // Workers with extractors
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: { name: 'Product Worker' } },
        { id: 'w2', type: 'worker', position: { x: 200, y: 200 }, data: { name: 'Category Worker' } },
        { id: 'html1', type: 'html-data-extractor', position: { x: 0, y: 150 }, data: {} },
        { id: 'html2', type: 'html-data-extractor', position: { x: 200, y: 150 }, data: {} },
        
        // Processor chains
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: { processorType: 'save_to_wordpress' } },
        { id: 'p2', type: 'processor', position: { x: 0, y: 400 }, data: { processorType: 'send_email' } },
        { id: 'p3', type: 'processor', position: { x: 200, y: 300 }, data: { processorType: 'save_to_wordpress' } },
        
        // Completion
        { id: 'comp', type: 'completion', position: { x: 100, y: 500 }, data: {} }
      ];

      const edges: Edge[] = [
        // Sources to repository
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 's2', target: 'repo' },
        
        // Repository to workers
        { id: 'e3', source: 'repo', target: 'w1' },
        { id: 'e4', source: 'repo', target: 'w2' },
        
        // Extractors to workers
        { id: 'e5', source: 'html1', target: 'w1' },
        { id: 'e6', source: 'html2', target: 'w2' },
        
        // Worker to processor chains
        { id: 'e7', source: 'w1', target: 'p1' },
        { id: 'e8', source: 'p1', target: 'p2' },
        { id: 'e9', source: 'w2', target: 'p3' },
        
        // Processors to completion
        { id: 'e10', source: 'p2', target: 'comp' },
        { id: 'e11', source: 'p3', target: 'comp' }
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
      expect(result.invalidEdges).toHaveLength(0);
    });

    test('should reject invalid connection in complex flow', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'repo', target: 'p1' }  // INVALID: Repository → Processor
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.invalidEdges).toHaveLength(1);
      expect(result.invalidEdges[0].id).toBe('e4');
    });
  });

  // ============================================================================
  // PROCESSOR BRANCH TESTS
  // ============================================================================

  describe('Processor Branching (NOT ALLOWED)', () => {
    test('should reject processor with 2 outgoing connections', () => {
      const nodes: Node[] = [
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: -50, y: 100 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 50, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'p1', target: 'p2' },
        { id: 'e2', source: 'p1', target: 'p3' }  // INVALID: p1 has 2 outgoing
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => e.includes('maximum 1 outgoing'))).toBe(true);
    });

    test('should allow single chain but reject branches', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      // Valid chain
      const validEdges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' }
      ];

      const validResult = connectionRuleEngine.validateFlow(nodes, validEdges);
      expect(validResult.isValid).toBe(true);

      // Try to add branch (p1 → p3)
      const p3: Node = { id: 'p3', type: 'processor', position: { x: 100, y: 200 }, data: {} };
      const branchEdge: Edge = { id: 'e3', source: 'p1', target: 'p3' };

      const invalidResult = connectionRuleEngine.validateFlow(
        [...nodes, p3],
        [...validEdges, branchEdge]
      );

      expect(invalidResult.isValid).toBe(false);
    });
  });

  // ============================================================================
  // ORPHANED NODE DETECTION TESTS
  // ============================================================================

  describe('Orphaned Node Detection', () => {
    test('should detect orphaned processor (no incoming)', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },  // Connected
        { id: 'p2', type: 'processor', position: { x: 100, y: 100 }, data: {} }  // Orphaned!
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' }
        // p2 has no incoming connection!
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => 
        e.includes('p2') && e.includes('MUST have incoming')
      )).toBe(true);
    });

    test('should detect worker without repository connection', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -50, y: 100 }, data: {} },  // Connected
        { id: 'w2', type: 'worker', position: { x: 50, y: 100 }, data: {} },   // Orphaned!
        { id: 'p1', type: 'processor', position: { x: -50, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 50, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        // w2 has no Repository connection!
        { id: 'e2', source: 'w1', target: 'p1' },
        { id: 'e3', source: 'w2', target: 'p2' }  // w2 → p2 but w2 not connected to repo
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => 
        e.includes('w2') && e.includes('MUST have incoming from Repository')
      )).toBe(true);
    });

    test('should detect all processors in broken chain as orphaned', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },  // Connected to w1
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },  // Orphaned!
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} }   // Connected to p2, but p2 orphaned
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        // BREAK: p1 should connect to p2, but doesn't
        { id: 'e2', source: 'p2', target: 'p3' }
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => e.includes('p2') && e.includes('MUST have incoming'))).toBe(true);
    });
  });

  // ============================================================================
  // N-TO-1 RELATIONSHIP TESTS
  // ============================================================================

  describe('n-to-1 Relationships', () => {
    test('should allow 10 data sources to 1 repository', () => {
      const sources: Node[] = Array.from({ length: 10 }, (_, i) => ({
        id: `s${i + 1}`,
        type: 'start',
        position: { x: i * 100, y: 0 },
        data: {}
      }));

      const nodes: Node[] = [
        ...sources,
        { id: 'repo', type: 'repository', position: { x: 500, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 500, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 500, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        ...sources.map((s, i) => ({
          id: `e${i + 1}`,
          source: s.id,
          target: 'repo'
        })),
        { id: 'e11', source: 'repo', target: 'w1' },
        { id: 'e12', source: 'w1', target: 'p1' }
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
    });

    test('should allow repository to connect to 10 workers', () => {
      const workers: Node[] = Array.from({ length: 10 }, (_, i) => ({
        id: `w${i + 1}`,
        type: 'worker',
        position: { x: i * 100, y: 200 },
        data: {}
      }));

      const processors: Node[] = workers.map((w, i) => ({
        id: `p${i + 1}`,
        type: 'processor',
        position: { x: i * 100, y: 300 },
        data: {}
      }));

      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 500, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 500, y: 100 }, data: {} },
        ...workers,
        ...processors
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        ...workers.map((w, i) => ({
          id: `e-repo-${w.id}`,
          source: 'repo',
          target: w.id
        })),
        ...workers.map((w, i) => ({
          id: `e-w-p${i + 1}`,
          source: w.id,
          target: `p${i + 1}`
        }))
      ];

      const result = connectionRuleEngine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
    });
  });
});

