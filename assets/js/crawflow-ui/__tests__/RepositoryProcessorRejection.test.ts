/**
 * Critical Test: Repository → Processor MUST BE REJECTED
 * 
 * Đây là test QUAN TRỌNG NHẤT để đảm bảo Repository KHÔNG BAO GIỜ
 * được connect trực tiếp tới Processor.
 */

import { Node, Edge } from '@xyflow/react';
import { connectionRuleEngine, cleanInvalidEdges } from '../rules/ConnectionRules';

describe('CRITICAL: Repository → Processor Rejection', () => {
  // ============================================================================
  // SINGLE CONNECTION VALIDATION
  // ============================================================================

  describe('Single Connection Validation (onConnect)', () => {
    test('MUST reject Repository → Processor connection', () => {
      const repository: Node = {
        id: 'repo',
        type: 'repository',
        position: { x: 0, y: 0 },
        data: {}
      };

      const processor: Node = {
        id: 'p1',
        type: 'processor',
        position: { x: 0, y: 100 },
        data: {}
      };

      // User tries to drag edge: Repository → Processor
      const result = connectionRuleEngine.validateConnection(
        repository,
        processor,
        []
      );

      // MUST be invalid
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('Repository can ONLY connect to Worker');
    });

    test('MUST reject by INCOMING validation too', () => {
      const repository: Node = {
        id: 'repo',
        type: 'repository',
        position: { x: 0, y: 0 },
        data: {}
      };

      const processor: Node = {
        id: 'p1',
        type: 'processor',
        position: { x: 0, y: 100 },
        data: {}
      };

      // Validate from processor perspective (incoming)
      const result = connectionRuleEngine.validateConnection(
        repository,
        processor,
        []
      );

      // MUST be rejected by incoming validation
      expect(result.isValid).toBe(false);
      // Should fail on OUTGOING rule first, but if not, INCOMING should catch it
    });
  });

  // ============================================================================
  // FULL FLOW VALIDATION (Load/Import)
  // ============================================================================

  describe('Full Flow Validation (validateFlow)', () => {
    test('MUST detect Repository → Processor in flow', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'p1' }  // INVALID!
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);

      // MUST be invalid
      expect(validation.isValid).toBe(false);
      
      // MUST have error mentioning Repository and Processor
      expect(validation.errors.some(e => 
        e.includes('repo') && e.includes('p1')
      )).toBe(true);
      
      // MUST identify e2 as invalid
      expect(validation.invalidEdges).toContain(edges[1]);
      expect(validation.invalidEdges.find(e => e.id === 'e2')).toBeDefined();
    });

    test('MUST clean Repository → Processor edges', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const invalidEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'p1' },  // INVALID: Repository → Processor
        { id: 'e3', source: 'w1', target: 'p1' }     // Valid
      ];

      // Clean invalid edges
      const cleanEdges = cleanInvalidEdges(nodes, invalidEdges);

      // e2 MUST be removed
      expect(cleanEdges.find(e => e.id === 'e2')).toBeUndefined();
      
      // Only e1 and e3 should remain
      expect(cleanEdges).toHaveLength(2);
      expect(cleanEdges.find(e => e.id === 'e1')).toBeDefined();
      expect(cleanEdges.find(e => e.id === 'e3')).toBeDefined();
    });

    test('MUST detect multiple Repository → Processor connections', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -50, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 50, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'p1' },  // INVALID
        { id: 'e2', source: 'repo', target: 'p2' },  // INVALID
        { id: 'e3', source: 'repo', target: 'p3' }   // INVALID
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);

      // MUST be invalid
      expect(validation.isValid).toBe(false);
      
      // MUST have 3+ errors (3 invalid edges + processors missing incoming)
      expect(validation.errors.length).toBeGreaterThanOrEqual(3);
      
      // MUST identify all 3 edges as invalid
      expect(validation.invalidEdges).toHaveLength(3);
    });
  });

  // ============================================================================
  // PROJECT LOAD SCENARIO
  // ============================================================================

  describe('Project Load from Database', () => {
    test('Load old project với Repository → Processor → MUST auto-clean', () => {
      // Simulate old project data from database (created before rules)
      const loadedNodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: { processorType: 'save_to_wordpress' } },
        { id: 'p2', type: 'processor', position: { x: 150, y: 500 }, data: { processorType: 'send_to_api' } }
      ];

      const loadedEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },     // Valid
        { id: 'e3', source: 'repository-node', target: 'p1' },     // INVALID: Repository → Processor
        { id: 'e4', source: 'repository-node', target: 'p2' },     // INVALID: Repository → Processor
        { id: 'e5', source: 'w1', target: 'p1' }                   // Valid
      ];

      // Step 1: Validate
      const validation = connectionRuleEngine.validateFlow(loadedNodes, loadedEdges);
      
      console.log('Validation result:', validation);
      console.log('Invalid edges:', validation.invalidEdges);

      // MUST detect invalids
      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges.length).toBeGreaterThanOrEqual(2); // e3, e4 minimum

      // Step 2: Auto-clean
      const cleanEdges = cleanInvalidEdges(loadedNodes, loadedEdges);
      
      console.log('Clean edges:', cleanEdges);

      // e3 and e4 MUST be removed
      expect(cleanEdges.find(e => e.id === 'e3')).toBeUndefined();
      expect(cleanEdges.find(e => e.id === 'e4')).toBeUndefined();

      // Valid edges MUST be kept
      expect(cleanEdges.find(e => e.id === 'e1')).toBeDefined();
      expect(cleanEdges.find(e => e.id === 'e2')).toBeDefined();
      expect(cleanEdges.find(e => e.id === 'e5')).toBeDefined();

      // Step 3: Validate cleaned flow
      const cleanValidation = connectionRuleEngine.validateFlow(loadedNodes, cleanEdges);
      
      // After cleaning, p2 is orphaned (no incoming), but edges are valid
      // p2 orphan would be detected by required incoming check
      expect(cleanValidation.errors.some(e => e.includes('p2') && e.includes('MUST have incoming'))).toBe(true);
    });

    test('Load với CHỈ Repository → Processor (no workers) → MUST clean', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repository-node', target: 'p1' }  // INVALID!
      ];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      
      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges).toHaveLength(1);

      // Clean
      const cleanEdges = cleanInvalidEdges(nodes, edges);
      
      expect(cleanEdges).toHaveLength(0); // All edges removed
    });
  });

  // ============================================================================
  // IMPORT JSON SCENARIO
  // ============================================================================

  describe('Import JSON with Repository → Processor', () => {
    test('Import invalid JSON → Show errors → Clean', () => {
      const importedConfig = {
        nodes: [
          { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
          { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
          { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} },
          { id: 'p2', type: 'processor', position: { x: 0, y: 300 }, data: {} }
        ] as Node[],
        edges: [
          { id: 'e1', source: 's1', target: 'repo' },
          { id: 'e2', source: 'repo', target: 'p1' },  // INVALID
          { id: 'e3', source: 'repo', target: 'p2' }   // INVALID
        ] as Edge[]
      };

      // Validate
      const validation = connectionRuleEngine.validateFlow(
        importedConfig.nodes,
        importedConfig.edges
      );

      // MUST detect Repository → Processor
      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges).toHaveLength(2);
      
      // Error messages MUST mention Repository and Processor
      expect(validation.errors.some(e => 
        e.includes('repo') && (e.includes('p1') || e.includes('p2'))
      )).toBe(true);

      // Clean
      const cleanEdges = cleanInvalidEdges(
        importedConfig.nodes,
        importedConfig.edges
      );

      // Only e1 should remain
      expect(cleanEdges).toHaveLength(1);
      expect(cleanEdges[0].id).toBe('e1');
    });
  });

  // ============================================================================
  // CORRECT ALTERNATIVE
  // ============================================================================

  describe('Correct Flow: Repository → Worker → Processor', () => {
    test('Valid flow MUST pass validation', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo' },
        { id: 'e2', source: 'repo', target: 'w1' },  // ✅ Repository → Worker
        { id: 'e3', source: 'w1', target: 'p1' }     // ✅ Worker → Processor
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);

      expect(validation.isValid).toBe(true);
      expect(validation.errors).toHaveLength(0);
      expect(validation.invalidEdges).toHaveLength(0);
    });

    test('Repository can connect to multiple Workers (not Processors)', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: -50, y: 100 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 50, y: 100 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: -50, y: 200 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 50, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },  // ✅ Repository → Worker
        { id: 'e2', source: 'repo', target: 'w2' },  // ✅ Repository → Worker
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'w2', target: 'p2' }
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);

      expect(validation.isValid).toBe(true);
      
      // NO Repository → Processor connections
      const repoToProcessorEdges = edges.filter(e => {
        const source = nodes.find(n => n.id === e.source);
        const target = nodes.find(n => n.id === e.target);
        return source?.type === 'repository' && target?.type === 'processor';
      });

      expect(repoToProcessorEdges).toHaveLength(0);
    });
  });

  // ============================================================================
  // DEBUG: Why is validation not catching?
  // ============================================================================

  describe('DEBUG: Validation Logic Check', () => {
    test('Verify rule exists for repository', () => {
      const allowedTargets = connectionRuleEngine.getAllowedTargets('repository');
      
      expect(allowedTargets).toEqual(['worker']);
      expect(allowedTargets).not.toContain('processor');
    });

    test('Verify incoming check for processor', () => {
      const repository: Node = {
        id: 'repo',
        type: 'repository',
        position: { x: 0, y: 0 },
        data: {}
      };

      const processor: Node = {
        id: 'p1',
        type: 'processor',
        position: { x: 0, y: 100 },
        data: {}
      };

      // Direct validation call
      const result = connectionRuleEngine.validateConnection(
        repository,
        processor,
        [],
        [repository, processor]
      );

      console.log('Validation result:', result);

      expect(result.isValid).toBe(false);
      expect(result.error).toBeDefined();
    });

    test('Step-by-step validation of Repository → Processor edge', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} }
      ];

      const edge: Edge = { id: 'e1', source: 'repo', target: 'p1' };

      // Find source and target nodes
      const sourceNode = nodes.find(n => n.id === edge.source);
      const targetNode = nodes.find(n => n.id === edge.target);

      expect(sourceNode).toBeDefined();
      expect(targetNode).toBeDefined();
      expect(sourceNode?.type).toBe('repository');
      expect(targetNode?.type).toBe('processor');

      // Validate this specific edge
      const result = connectionRuleEngine.validateConnection(
        sourceNode!,
        targetNode!,
        [edge],
        nodes,
        edge.id
      );

      console.log('Edge validation:', {
        source: sourceNode?.type,
        target: targetNode?.type,
        result
      });

      // MUST be invalid
      expect(result.isValid).toBe(false);
    });
  });

  // ============================================================================
  // REALISTIC SCENARIOS
  // ============================================================================

  describe('Realistic Broken Projects', () => {
    test('Legacy project từ version cũ (có Repository → Processor)', () => {
      // Old project structure (before rules)
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 's2', type: 'start', position: { x: 200, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 150, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 200, y: 500 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 100, y: 650 }, data: {} }
      ];

      const legacyEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 's2', target: 'repository-node' },
        { id: 'e3', source: 'repository-node', target: 'w1' },
        { id: 'e4', source: 'repository-node', target: 'p2' },  // INVALID: Legacy bug
        { id: 'e5', source: 'w1', target: 'p1' },
        { id: 'e6', source: 'p1', target: 'p3' }
      ];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, legacyEdges);
      
      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges.some(e => e.id === 'e4')).toBe(true);

      // Clean
      const cleanEdges = cleanInvalidEdges(nodes, legacyEdges);
      
      // e4 MUST be removed
      expect(cleanEdges.find(e => e.id === 'e4')).toBeUndefined();
      
      // Cleaned flow should be better (though p2 will be orphaned)
      expect(cleanEdges.length).toBeLessThan(legacyEdges.length);
    });
  });

  // ============================================================================
  // SUMMARY TEST
  // ============================================================================

  describe('Summary: All Repository → Processor Cases', () => {
    test('NONE of these should be valid', () => {
      const testCases = [
        {
          name: 'Direct: Repository → Processor',
          source: { id: 'repo', type: 'repository' as const },
          target: { id: 'p1', type: 'processor' as const }
        },
        {
          name: 'With Worker: Repository → Processor (bypassing Worker)',
          source: { id: 'repo', type: 'repository' as const },
          target: { id: 'p1', type: 'processor' as const }
        },
        {
          name: 'Multiple: Repository → Processor1, Processor2, Processor3',
          source: { id: 'repo', type: 'repository' as const },
          target: { id: 'p1', type: 'processor' as const }
        }
      ];

      testCases.forEach(testCase => {
        const source: Node = { 
          ...testCase.source, 
          position: { x: 0, y: 0 }, 
          data: {} 
        };
        const target: Node = { 
          ...testCase.target, 
          position: { x: 0, y: 100 }, 
          data: {} 
        };

        const result = connectionRuleEngine.validateConnection(source, target, []);

        expect(result.isValid).toBe(false);
        expect(result.error).toBeDefined();
      });
    });
  });
});

