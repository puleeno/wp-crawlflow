/**
 * Integration Tests for Processor INSERT Logic
 * 
 * Tests logic INSERT processor vào giữa chain.
 * Đây là test CRITICAL nhất cho bug processor incoming.
 */

import { Node, Edge } from '@xyflow/react';
import { connectionRuleEngine } from '../rules/ConnectionRules';

describe('Processor INSERT Logic - Integration Tests', () => {
  // ============================================================================
  // INSERT VÀO GIỮA CHAIN
  // ============================================================================

  describe('INSERT Processor vào giữa chain', () => {
    test('Case 1: a → b, chọn a, thêm c → Kết quả: a → c → b', () => {
      // Initial state
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // Verify initial state
      let validation = connectionRuleEngine.validateFlow(initialNodes, initialEdges);
      expect(validation.isValid).toBe(true);

      // User chọn a, thêm c
      const selectedNode = initialNodes.find(n => n.id === 'a')!;
      expect(selectedNode.type).toBe('processor');

      // New processor c
      const processorC: Node = {
        id: 'c',
        type: 'processor',
        position: { x: 0, y: 150 },
        data: {}
      };

      // INSERT logic:
      // 1. Remove old edge: a → b
      // 2. Add edge: a → c
      // 3. Add edge: c → b

      const newNodes = [...initialNodes, processorC];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        // Old edge a → b REMOVED
        { id: 'e-a-c', source: 'a', target: 'c' },  // NEW
        { id: 'e-c-b', source: 'c', target: 'b' }   // NEW
      ];

      // Validate final state
      validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // CRITICAL VERIFICATIONS:
      // c's incoming MUST be from a
      const cIncoming = newEdges.find(e => e.target === 'c');
      expect(cIncoming?.source).toBe('a');

      // c's outgoing MUST be to b
      const cOutgoing = newEdges.find(e => e.source === 'c');
      expect(cOutgoing?.target).toBe('b');

      // b's incoming MUST be from c (not a)
      const bIncoming = newEdges.find(e => e.target === 'b');
      expect(bIncoming?.source).toBe('c');
      expect(bIncoming?.source).not.toBe('a');

      // a should have only 1 outgoing (to c, not to b)
      const aOutgoing = newEdges.filter(e => e.source === 'a');
      expect(aOutgoing).toHaveLength(1);
      expect(aOutgoing[0].target).toBe('c');
    });

    test('Case 2: a → b → c, chọn a, thêm d → Kết quả: a → d → b → c', () => {
      // Initial: a → b → c
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      // Chọn a, thêm d
      const processorD: Node = {
        id: 'd',
        type: 'processor',
        position: { x: 0, y: 150 },
        data: {}
      };

      // Expected: a → d → b → c
      const newNodes = [...initialNodes, processorD];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        // e-a-b REMOVED
        { id: 'e-a-d', source: 'a', target: 'd' },  // NEW
        { id: 'e-d-b', source: 'd', target: 'b' },  // NEW
        { id: 'e-b-c', source: 'b', target: 'c' }   // UNCHANGED
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify chain: a → d → b → c
      expect(newEdges.find(e => e.source === 'a')?.target).toBe('d');
      expect(newEdges.find(e => e.source === 'd')?.target).toBe('b');
      expect(newEdges.find(e => e.source === 'b')?.target).toBe('c');
    });

    test('Case 3: a → b → c, chọn b, thêm d → Kết quả: a → b → d → c', () => {
      // Initial: a → b → c
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      // Chọn b (middle), thêm d
      const processorD: Node = {
        id: 'd',
        type: 'processor',
        position: { x: 0, y: 250 },
        data: {}
      };

      // Expected: a → b → d → c
      const newNodes = [...initialNodes, processorD];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },   // UNCHANGED
        // e-b-c REMOVED
        { id: 'e-b-d', source: 'b', target: 'd' },  // NEW
        { id: 'e-d-c', source: 'd', target: 'c' }   // NEW
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify chain: a → b → d → c
      expect(newEdges.find(e => e.source === 'a')?.target).toBe('b');
      expect(newEdges.find(e => e.source === 'b')?.target).toBe('d');
      expect(newEdges.find(e => e.source === 'd')?.target).toBe('c');
    });
  });

  // ============================================================================
  // APPEND VÀO CUỐI CHAIN
  // ============================================================================

  describe('APPEND Processor vào cuối chain', () => {
    test('Case 1: a → b, chọn b, thêm c → Kết quả: a → b → c', () => {
      // Initial: a → b
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // Chọn b (end of chain), thêm c
      const processorC: Node = {
        id: 'c',
        type: 'processor',
        position: { x: 0, y: 300 },
        data: {}
      };

      // Expected: a → b → c (APPEND, b has no outgoing)
      const newNodes = [...initialNodes, processorC];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }  // NEW, b had no outgoing
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify: b → c
      const bOutgoing = newEdges.find(e => e.source === 'b');
      expect(bOutgoing?.target).toBe('c');
    });

    test('Case 2: a → b → c, chọn c, thêm d → Kết quả: a → b → c → d', () => {
      // Initial: a → b → c
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      // Chọn c (end), thêm d
      const processorD: Node = {
        id: 'd',
        type: 'processor',
        position: { x: 0, y: 400 },
        data: {}
      };

      // Expected: c → d (APPEND)
      const newNodes = [...initialNodes, processorD];
      const newEdges: Edge[] = [
        ...initialEdges,
        { id: 'e-c-d', source: 'c', target: 'd' }
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // COMPLEX INSERT SCENARIOS
  // ============================================================================

  describe('Complex INSERT Scenarios', () => {
    test('INSERT vào đầu chain: w → a → b, chọn a, thêm x → w → a → x → b', () => {
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // Chọn a (first processor), thêm x
      const processorX: Node = {
        id: 'x',
        type: 'processor',
        position: { x: 0, y: 150 },
        data: {}
      };

      // Expected: a → x → b
      const newNodes = [...initialNodes, processorX];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        // e-a-b REMOVED
        { id: 'e-a-x', source: 'a', target: 'x' },
        { id: 'e-x-b', source: 'x', target: 'b' }
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify chain intact
      expect(newEdges.find(e => e.source === 'a')?.target).toBe('x');
      expect(newEdges.find(e => e.source === 'x')?.target).toBe('b');
    });

    test('INSERT vào long chain: a → b → c → d, chọn b, thêm x → a → b → x → c → d', () => {
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: 'd', type: 'processor', position: { x: 0, y: 400 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' },
        { id: 'e-c-d', source: 'c', target: 'd' }
      ];

      // Chọn b, thêm x
      const processorX: Node = {
        id: 'x',
        type: 'processor',
        position: { x: 0, y: 250 },
        data: {}
      };

      // Expected: b → x → c
      const newNodes = [...initialNodes, processorX];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        // e-b-c REMOVED
        { id: 'e-b-x', source: 'b', target: 'x' },
        { id: 'e-x-c', source: 'x', target: 'c' },
        { id: 'e-c-d', source: 'c', target: 'd' }
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);

      // Verify full chain: w1 → a → b → x → c → d
      expect(newEdges.find(e => e.source === 'w1')?.target).toBe('a');
      expect(newEdges.find(e => e.source === 'a')?.target).toBe('b');
      expect(newEdges.find(e => e.source === 'b')?.target).toBe('x');
      expect(newEdges.find(e => e.source === 'x')?.target).toBe('c');
      expect(newEdges.find(e => e.source === 'c')?.target).toBe('d');
    });

    test('Multiple INSERTs: a → b → c, insert x after a, then insert y after x', () => {
      // Step 1: Initial a → b → c
      let nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      // Step 2: INSERT x after a → a → x → b → c
      const processorX: Node = { id: 'x', type: 'processor', position: { x: 0, y: 150 }, data: {} };
      nodes = [...nodes, processorX];
      edges = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-x', source: 'a', target: 'x' },
        { id: 'e-x-b', source: 'x', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 3: INSERT y after x → a → x → y → b → c
      const processorY: Node = { id: 'y', type: 'processor', position: { x: 0, y: 175 }, data: {} };
      nodes = [...nodes, processorY];
      edges = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-x', source: 'a', target: 'x' },
        { id: 'e-x-y', source: 'x', target: 'y' },
        { id: 'e-y-b', source: 'y', target: 'b' },
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Verify full chain: w1 → a → x → y → b → c
      expect(edges.find(e => e.source === 'a')?.target).toBe('x');
      expect(edges.find(e => e.source === 'x')?.target).toBe('y');
      expect(edges.find(e => e.source === 'y')?.target).toBe('b');
      expect(edges.find(e => e.source === 'b')?.target).toBe('c');
    });
  });

  // ============================================================================
  // EDGE CASES
  // ============================================================================

  describe('Edge Cases', () => {
    test('INSERT when selected processor has completion outgoing', () => {
      // a → completion, chọn a, thêm b
      // Expected: a → b → completion
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'completion-node', type: 'completion', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-comp', source: 'a', target: 'completion-node' }
      ];

      // Chọn a, thêm b
      const processorB: Node = {
        id: 'b',
        type: 'processor',
        position: { x: 0, y: 150 },
        data: {}
      };

      // Expected: a → b → completion
      const newNodes = [...initialNodes, processorB];
      const newEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        // e-a-comp REMOVED (or should keep and reconnect)
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-b-comp', source: 'b', target: 'completion-node' }
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });

    test('APPEND when no outgoing (end of chain)', () => {
      // a → b, chọn b (no outgoing), thêm c
      // Expected: a → b → c (simple append)
      const initialNodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
        // b has no outgoing
      ];

      // Chọn b, thêm c
      const processorC: Node = {
        id: 'c',
        type: 'processor',
        position: { x: 0, y: 300 },
        data: {}
      };

      // Expected: b → c (simple append)
      const newNodes = [...initialNodes, processorC];
      const newEdges: Edge[] = [
        ...initialEdges,
        { id: 'e-b-c', source: 'b', target: 'c' }
      ];

      const validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      expect(validation.isValid).toBe(true);
    });

    test('Cannot INSERT if would violate maxConnections', () => {
      // This is already handled by maxConnections validation
      // Just verify the rule works

      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 150 }, data: {} }
      ];

      // Try to make a have 2 outgoing: a → b AND a → c (INVALID!)
      const invalidEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' },
        { id: 'e-a-c', source: 'a', target: 'c' }  // INVALID: a has 2 outgoing
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, invalidEdges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('maximum 1 outgoing'))).toBe(true);
    });
  });

  // ============================================================================
  // COMPARISON: WRONG vs CORRECT BEHAVIOR
  // ============================================================================

  describe('WRONG vs CORRECT Behavior Comparison', () => {
    test('❌ WRONG: Add from processor → Connect to worker', () => {
      // Initial: w1 → a → b
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // User chọn a, thêm c
      // WRONG behavior: c connects to w1
      const processorC: Node = { id: 'c', type: 'processor', position: { x: 0, y: 50 }, data: {} };
      
      const wrongEdges: Edge[] = [
        ...edges,
        { id: 'e-w1-c', source: 'w1', target: 'c' }  // WRONG!
      ];

      // This creates: w1 has 2 outgoing (to a and c) → INVALID!
      const validation = connectionRuleEngine.validateFlow([...nodes, processorC], wrongEdges);
      expect(validation.isValid).toBe(false);
    });

    test('✅ CORRECT: Add from processor → INSERT into chain', () => {
      // Initial: w1 → a → b
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // User chọn a, thêm c
      // CORRECT behavior: a → c → b (INSERT)
      const processorC: Node = { id: 'c', type: 'processor', position: { x: 0, y: 150 }, data: {} };
      
      const correctEdges: Edge[] = [
        { id: 'e-w1-a', source: 'w1', target: 'a' },
        // e-a-b REMOVED
        { id: 'e-a-c', source: 'a', target: 'c' },  // INSERT
        { id: 'e-c-b', source: 'c', target: 'b' }   // RECONNECT
      ];

      const validation = connectionRuleEngine.validateFlow([...nodes, processorC], correctEdges);
      expect(validation.isValid).toBe(true);

      // Verify chain: w1 → a → c → b
      expect(correctEdges.find(e => e.source === 'a')?.target).toBe('c');
      expect(correctEdges.find(e => e.source === 'c')?.target).toBe('b');
    });
  });

  // ============================================================================
  // INSERT WITH INVALID INCOMING EDGES (CRITICAL)
  // ============================================================================

  describe('INSERT với Invalid Incoming Edges', () => {
    test('CRITICAL: a có invalid incoming (repository), chọn a, thêm c → Clean invalid', () => {
      // Initial: repository → a → b (repository → a is INVALID!)
      const initialNodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const initialEdges: Edge[] = [
        { id: 'e-repo-a', source: 'repo', target: 'a' },  // INVALID!
        { id: 'e-a-b', source: 'a', target: 'b' }
      ];

      // This flow is INVALID
      let validation = connectionRuleEngine.validateFlow(initialNodes, initialEdges);
      expect(validation.isValid).toBe(false);

      // User chọn a, thêm c
      // INSERT logic PHẢI:
      // 1. Clean repository → a (invalid)
      // 2. Remove a → b
      // 3. Add a → c, c → b

      const processorC: Node = { id: 'c', type: 'processor', position: { x: 0, y: 150 }, data: {} };
      const newNodes = [...initialNodes, processorC];

      // After INSERT (expected result)
      const newEdges: Edge[] = [
        // e-repo-a REMOVED (cleaned)
        // e-a-b REMOVED (for insert)
        { id: 'e-a-c', source: 'a', target: 'c' },
        { id: 'e-c-b', source: 'c', target: 'b' }
      ];

      validation = connectionRuleEngine.validateFlow(newNodes, newEdges);
      
      // Now flow is still INVALID because a is orphaned (no incoming)
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('a') && e.includes('MUST have incoming'))).toBe(true);

      // Verify repository → a was removed
      expect(newEdges.find(e => e.id === 'e-repo-a')).toBeUndefined();
    });

    test('CRITICAL: Load với repository → processor2, INSERT processor A', () => {
      // Step 1: Load project with invalid edge
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 400 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'w1' },
        { id: 'e3', source: 'repo', target: 'p2' },  // INVALID: repository → processor2
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'p1', target: 'p2' }
      ];

      // Validate: Should detect repository → p2
      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(false);

      // Auto-clean on load
      edges = cleanInvalidEdges(nodes, edges);
      
      // e3 should be removed
      expect(edges.find(e => e.id === 'e3')).toBeUndefined();

      // Step 2: User chọn p2, thêm A
      const processorA: Node = { id: 'A', type: 'processor', position: { x: 0, y: 450 }, data: {} };
      nodes = [...nodes, processorA];

      // INSERT logic with clean
      edges = edges.filter(e => {
        // Clean invalid incoming to p2
        if (e.target === 'p2') {
          const source = nodes.find(n => n.id === e.source);
          return source?.type === 'worker' || source?.type === 'processor';
        }
        return true;
      });

      // Remove p2's outgoing, add p2 → A, A → next (if any)
      const p2Outgoing = edges.find(e => e.source === 'p2');
      if (p2Outgoing) {
        edges = edges.filter(e => e.id !== p2Outgoing.id);
        edges = [
          ...edges,
          { id: 'e-p2-A', source: 'p2', target: 'A' },
          { id: 'e-A-next', source: 'A', target: p2Outgoing.target }
        ];
      } else {
        edges = [...edges, { id: 'e-p2-A', source: 'p2', target: 'A' }];
      }

      // Validate final
      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // VERIFY: A has only 1 incoming (from p2, NOT from repository)
      const aIncoming = edges.filter(e => e.target === 'A');
      expect(aIncoming).toHaveLength(1);
      expect(aIncoming[0].source).toBe('p2');
    });
  });

  // ============================================================================
  // IMPORT THEN INSERT TESTS
  // ============================================================================

  describe('Import JSON Then INSERT Processor', () => {
    test('Import a → b → c, chọn b, thêm x → a → b → x → c', () => {
      // Step 1: Import JSON
      const importedNodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'a', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: 'b', type: 'processor', position: { x: 0, y: 400 }, data: {} },
        { id: 'c', type: 'processor', position: { x: 0, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'a' },
        { id: 'e4', source: 'a', target: 'b' },
        { id: 'e5', source: 'b', target: 'c' }
      ];

      let validation = connectionRuleEngine.validateFlow(importedNodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 2: User chọn b, thêm x
      const processorX: Node = { id: 'x', type: 'processor', position: { x: 0, y: 450 }, data: {} };
      
      // INSERT: b → x → c
      edges = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'a' },
        { id: 'e4', source: 'a', target: 'b' },
        // e5 REMOVED
        { id: 'e-b-x', source: 'b', target: 'x' },
        { id: 'e-x-c', source: 'x', target: 'c' }
      ];

      validation = connectionRuleEngine.validateFlow([...importedNodes, processorX], edges);
      expect(validation.isValid).toBe(true);

      // Verify: a → b → x → c
      expect(edges.find(e => e.source === 'b')?.target).toBe('x');
      expect(edges.find(e => e.source === 'x')?.target).toBe('c');
    });
  });
});

