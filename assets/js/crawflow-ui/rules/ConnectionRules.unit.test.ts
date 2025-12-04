/**
 * Unit Tests for Connection Rules Engine
 * 
 * Tests các quy tắc kết nối, validation logic, và edge cases.
 */

import { ConnectionRuleEngine, CONNECTION_RULES, cleanInvalidEdges, canConnect, getConnectionError } from './ConnectionRules';
import { Node, Edge } from '@xyflow/react';

describe('ConnectionRuleEngine - Unit Tests', () => {
  let engine: ConnectionRuleEngine;

  beforeEach(() => {
    engine = new ConnectionRuleEngine();
  });

  // ============================================================================
  // RULE DEFINITIONS TESTS
  // ============================================================================

  describe('Rule Definitions', () => {
    test('should have all required rules defined', () => {
      expect(CONNECTION_RULES.length).toBeGreaterThanOrEqual(10);
    });

    test('start node can only connect to repository', () => {
      const rule = CONNECTION_RULES.find(r => r.from === 'start');
      expect(rule).toBeDefined();
      expect(rule?.to).toEqual(['repository']);
      expect(rule?.maxConnections).toBe(1);
    });

    test('repository can only connect to worker', () => {
      const rule = CONNECTION_RULES.find(r => r.from === 'repository');
      expect(rule).toBeDefined();
      expect(rule?.to).toEqual(['worker']);
      expect(rule?.maxConnections).toBeUndefined(); // Unlimited
    });

    test('worker can only connect to processor', () => {
      const rule = CONNECTION_RULES.find(r => r.from === 'worker');
      expect(rule).toBeDefined();
      expect(rule?.to).toEqual(['processor']);
      expect(rule?.maxConnections).toBe(1);
    });

    test('processor can connect to processor or completion', () => {
      const rule = CONNECTION_RULES.find(r => r.from === 'processor');
      expect(rule).toBeDefined();
      expect(rule?.to).toContain('processor');
      expect(rule?.to).toContain('completion');
    });

    test('extractors can only connect to worker', () => {
      const extractorTypes = ['html-data-extractor', 'csv-extractor', 'json-extractor', 'xml-extractor', 'mysql-extractor'];
      
      extractorTypes.forEach(type => {
        const rule = CONNECTION_RULES.find(r => r.from === type);
        expect(rule).toBeDefined();
        expect(rule?.to).toEqual(['worker']);
        expect(rule?.maxConnections).toBe(1);
      });
    });

    test('completion has no outgoing connections', () => {
      const rule = CONNECTION_RULES.find(r => r.from === 'completion');
      expect(rule).toBeDefined();
      expect(rule?.to).toEqual([]);
      expect(rule?.maxConnections).toBe(0);
    });
  });

  // ============================================================================
  // SINGLE CONNECTION VALIDATION TESTS
  // ============================================================================

  describe('validateConnection - Valid Connections', () => {
    test('should allow Data Source → Repository', () => {
      const sourceNode: Node = { id: '1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow Repository → Worker', () => {
      const sourceNode: Node = { id: '1', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow Worker → Processor', () => {
      const sourceNode: Node = { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow Processor → Processor (chain)', () => {
      const sourceNode: Node = { id: '1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow HTML Extractor → Worker', () => {
      const sourceNode: Node = { id: '1', type: 'html-data-extractor', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow multiple Data Sources → same Repository', () => {
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const source1: Node = { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const source2: Node = { id: 's2', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const source3: Node = { id: 's3', type: 'start', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' }
      ];
      
      const result1 = engine.validateConnection(source2, repo, existingEdges);
      expect(result1.isValid).toBe(true);
      
      const result2 = engine.validateConnection(source3, repo, [...existingEdges, { id: 'e2', source: 's2', target: 'repo' }]);
      expect(result2.isValid).toBe(true);
    });

    test('should allow Repository → multiple Workers', () => {
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const worker1: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const worker2: Node = { id: 'w2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const worker3: Node = { id: 'w3', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' }
      ];
      
      const result1 = engine.validateConnection(repo, worker2, existingEdges);
      expect(result1.isValid).toBe(true);
      
      const result2 = engine.validateConnection(repo, worker3, [...existingEdges, { id: 'e2', source: 'repo', target: 'w2' }]);
      expect(result2.isValid).toBe(true);
    });
  });

  describe('validateConnection - Invalid Connections', () => {
    test('should reject Data Source → Worker', () => {
      const sourceNode: Node = { id: '1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Data Source can ONLY connect to Repository');
    });

    test('should reject Repository → Processor', () => {
      const sourceNode: Node = { id: '1', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Repository can ONLY connect to Worker');
    });

    test('should reject Worker → Repository', () => {
      const sourceNode: Node = { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Worker can ONLY connect to Processor');
    });

    test('should reject Processor → Worker', () => {
      const sourceNode: Node = { id: '1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
    });

    test('should reject HTML Extractor → Processor', () => {
      const sourceNode: Node = { id: '1', type: 'html-data-extractor', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('HTML Data Extractor can ONLY connect to Worker');
    });

    test('should reject Completion → any node', () => {
      const sourceNode: Node = { id: '1', type: 'completion', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
    });
  });

  // ============================================================================
  // INCOMING VALIDATION TESTS
  // ============================================================================

  describe('Incoming Connection Validation', () => {
    test('should reject Worker → Repository (incoming validation)', () => {
      const sourceNode: Node = { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Repository can ONLY receive connections from Data Sources');
    });

    test('should reject Processor → Repository', () => {
      const sourceNode: Node = { id: '1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Repository can ONLY receive connections from Data Sources');
    });

    test('should reject Data Source → Processor (incoming validation)', () => {
      const sourceNode: Node = { id: '1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Processor can ONLY receive connections from Worker or another Processor');
    });

    test('should reject Repository → Worker when Repository tries to receive from non-start', () => {
      const workerNode: Node = { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const repoNode: Node = { id: '2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(workerNode, repoNode, []);
      
      expect(result.isValid).toBe(false);
    });
  });

  // ============================================================================
  // MAX CONNECTIONS TESTS
  // ============================================================================

  describe('Max Connections Enforcement', () => {
    test('should reject Data Source with 2 outgoing connections', () => {
      const source: Node = { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const repo1: Node = { id: 'r1', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const repo2: Node = { id: 'r2', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'r1' }
      ];
      
      const result = engine.validateConnection(source, repo2, existingEdges);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('can have maximum 1 outgoing connection');
    });

    test('should reject Worker with 2 outgoing connections', () => {
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const proc1: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc2: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' }
      ];
      
      const result = engine.validateConnection(worker, proc2, existingEdges);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('can have maximum 1 outgoing connection');
    });

    test('should allow multiple Data Sources to same Repository (n-to-1)', () => {
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const source1: Node = { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const source2: Node = { id: 's2', type: 'start', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' }
      ];
      
      // Each source has maxConnections: 1, but Repository can receive unlimited
      const result = engine.validateConnection(source2, repo, existingEdges);
      
      expect(result.isValid).toBe(true);
    });

    test('should allow Repository to connect to multiple Workers (unlimited)', () => {
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const worker1: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const worker2: Node = { id: 'w2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const worker3: Node = { id: 'w3', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const existingEdges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'repo', target: 'w2' }
      ];
      
      const result = engine.validateConnection(repo, worker3, existingEdges);
      
      expect(result.isValid).toBe(true);
    });
  });

  // ============================================================================
  // EXTRACTOR SPECIAL VALIDATION TESTS
  // ============================================================================

  describe('Worker Extractor Validation', () => {
    test('should allow first extractor to worker', () => {
      const extractor: Node = { id: 'e1', type: 'html-data-extractor', position: { x: 0, y: 0 }, data: {} };
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const nodes = [extractor, worker];
      
      const result = engine.validateConnection(extractor, worker, [], nodes);
      
      expect(result.isValid).toBe(true);
    });

    test('should reject second extractor to same worker', () => {
      const extractor1: Node = { id: 'e1', type: 'html-data-extractor', position: { x: 0, y: 0 }, data: {} };
      const extractor2: Node = { id: 'e2', type: 'csv-extractor', position: { x: 0, y: 0 }, data: {} };
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const nodes = [extractor1, extractor2, worker];
      
      const existingEdges: Edge[] = [
        { id: 'edge1', source: 'e1', target: 'w1' }
      ];
      
      const result = engine.validateConnection(extractor2, worker, existingEdges, nodes);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Worker can only have ONE data extractor input');
    });

    test('should allow different extractors to different workers', () => {
      const extractor1: Node = { id: 'e1', type: 'html-data-extractor', position: { x: 0, y: 0 }, data: {} };
      const extractor2: Node = { id: 'e2', type: 'csv-extractor', position: { x: 0, y: 0 }, data: {} };
      const worker1: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const worker2: Node = { id: 'w2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const nodes = [extractor1, extractor2, worker1, worker2];
      
      const existingEdges: Edge[] = [
        { id: 'edge1', source: 'e1', target: 'w1' }
      ];
      
      const result = engine.validateConnection(extractor2, worker2, existingEdges, nodes);
      
      expect(result.isValid).toBe(true);
    });
  });

  // ============================================================================
  // CYCLE DETECTION TESTS
  // ============================================================================

  describe('Cycle Detection', () => {
    test('should reject connection that creates cycle', () => {
      const proc1: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc2: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc3: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      // Chain: p1 → p2 → p3
      const existingEdges: Edge[] = [
        { id: 'e1', source: 'p1', target: 'p2' },
        { id: 'e2', source: 'p2', target: 'p3' }
      ];
      
      // Try to connect p3 → p1 (would create cycle)
      const result = engine.validateConnection(proc3, proc1, existingEdges);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('cycle');
    });

    test('should allow linear chain without cycle', () => {
      const proc1: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc2: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc3: Node = { id: 'p3', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      const proc4: Node = { id: 'p4', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      // Chain: p1 → p2 → p3
      const existingEdges: Edge[] = [
        { id: 'e1', source: 'p1', target: 'p2' },
        { id: 'e2', source: 'p2', target: 'p3' }
      ];
      
      // Connect p3 → p4 (extends chain, no cycle)
      const result = engine.validateConnection(proc3, proc4, existingEdges);
      
      expect(result.isValid).toBe(true);
    });
  });

  // ============================================================================
  // FULL FLOW VALIDATION TESTS
  // ============================================================================

  describe('validateFlow - Full Flow Validation', () => {
    test('should validate valid complete flow', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} },
        { id: 'comp', type: 'completion', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'p1', target: 'comp' }
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
      expect(result.invalidEdges).toHaveLength(0);
    });

    test('should detect invalid Repository → Processor connection', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'p1' }  // INVALID!
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.length).toBeGreaterThan(0);
      expect(result.invalidEdges).toContain(edges[0]);
      expect(result.errors[0]).toContain('Repository can ONLY connect to Worker');
    });

    test('should detect orphaned Processor (no incoming connection)', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} }  // Orphaned!
      ];

      const edges: Edge[] = [];  // No edges

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => e.includes('Processor') && e.includes('MUST have incoming'))).toBe(true);
    });

    test('should detect Worker without Repository connection', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} }  // Not connected!
      ];

      const edges: Edge[] = [];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors.some(e => e.includes('Worker') && e.includes('MUST have incoming'))).toBe(true);
    });

    test('should validate n-to-1 (multiple sources to one repository)', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 's2', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 's3', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 's2', target: 'repo' },
        { id: 'e3', source: 's3', target: 'repo' },
        { id: 'e4', source: 'repo', target: 'w1' },
        { id: 'e5', source: 'w1', target: 'p1' }
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
    });

    test('should validate processor chain', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 0 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' }
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
    });
  });

  // ============================================================================
  // CURRENTEDGEID EXCLUSION TESTS
  // ============================================================================

  describe('Current Edge Exclusion (validateFlow)', () => {
    test('should exclude current edge from maxConnections count', () => {
      const source: Node = { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} };
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };
      const nodes = [source, repo];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' }  // The only edge from s1
      ];

      // When validating edge e1 in validateFlow, it should NOT count itself
      const result = engine.validateFlow(nodes, edges);

      // Should be valid because e1 is excluded from count
      expect(result.isValid).toBe(true);
    });

    test('should validate multiple sources with 1 edge each', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 's2', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },  // s1 has 1 edge (max: 1) ✅
        { id: 'e2', source: 's2', target: 'repo' },  // s2 has 1 edge (max: 1) ✅
        { id: 'e3', source: 'repo', target: 'w1' },
        { id: 'e4', source: 'w1', target: 'p1' }
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
    });
  });

  // ============================================================================
  // UTILITY FUNCTIONS TESTS
  // ============================================================================

  describe('Utility Functions', () => {
    test('cleanInvalidEdges should remove invalid edges', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'p1' },  // INVALID
        { id: 'e2', source: 'repo', target: 'w1' }   // VALID
      ];

      const cleanEdges = cleanInvalidEdges(nodes, edges);

      expect(cleanEdges).toHaveLength(1);
      expect(cleanEdges[0].id).toBe('e2');
    });

    test('canConnect should return true for valid connection', () => {
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const processor: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} };

      const result = canConnect(worker, processor, []);

      expect(result).toBe(true);
    });

    test('canConnect should return false for invalid connection', () => {
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };

      const result = canConnect(worker, repo, []);

      expect(result).toBe(false);
    });

    test('getConnectionError should return error message for invalid connection', () => {
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const repo: Node = { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} };

      const error = getConnectionError(worker, repo, []);

      expect(error).toBeTruthy();
      expect(error).toContain('Worker can ONLY connect to Processor');
    });

    test('getConnectionError should return null for valid connection', () => {
      const worker: Node = { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const processor: Node = { id: 'p1', type: 'processor', position: { x: 0, y: 0 }, data: {} };

      const error = getConnectionError(worker, processor, []);

      expect(error).toBeNull();
    });
  });

  // ============================================================================
  // QUERY METHODS TESTS
  // ============================================================================

  describe('Query Methods', () => {
    test('getAllowedTargets should return correct targets for worker', () => {
      const targets = engine.getAllowedTargets('worker');
      
      expect(targets).toEqual(['processor']);
    });

    test('getAllowedTargets should return correct targets for repository', () => {
      const targets = engine.getAllowedTargets('repository');
      
      expect(targets).toEqual(['worker']);
    });

    test('getAllowedTargets should return empty array for completion', () => {
      const targets = engine.getAllowedTargets('completion');
      
      expect(targets).toEqual([]);
    });

    test('isConnectionAllowed should return true for valid connection type', () => {
      const result = engine.isConnectionAllowed('worker', 'processor');
      
      expect(result).toBe(true);
    });

    test('isConnectionAllowed should return false for invalid connection type', () => {
      const result = engine.isConnectionAllowed('worker', 'repository');
      
      expect(result).toBe(false);
    });

    test('getRuleDescription should return rule description', () => {
      const description = engine.getRuleDescription('worker');
      
      expect(description).toContain('Worker');
      expect(description).toContain('Processor');
    });
  });

  // ============================================================================
  // EDGE CASES TESTS
  // ============================================================================

  describe('Edge Cases', () => {
    test('should handle undefined node type gracefully', () => {
      const sourceNode: Node = { id: '1', type: 'unknown-type' as any, position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('No connection rule defined');
    });

    test('should handle empty edges array', () => {
      const sourceNode: Node = { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} };
      const targetNode: Node = { id: '2', type: 'processor', position: { x: 0, y: 0 }, data: {} };
      
      const result = engine.validateConnection(sourceNode, targetNode, []);
      
      expect(result.isValid).toBe(true);
    });

    test('should handle missing source or target node in validateFlow', () => {
      const nodes: Node[] = [
        { id: '1', type: 'worker', position: { x: 0, y: 0 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: '1', target: 'missing-node' }  // Target doesn't exist
      ];

      const result = engine.validateFlow(nodes, edges);

      expect(result.isValid).toBe(false);
      expect(result.errors[0]).toContain('Source or target node not found');
    });
  });
});

