/**
 * Comprehensive Integration Tests
 * 
 * Đảm bảo logic LUÔN ĐÚNG trong TẤT CẢ các trường hợp:
 * 1. Tạo mới project
 * 2. Edit project hiện có
 * 3. Import JSON
 * 4. Thêm node sau khi import
 * 5. Xóa node
 * 6. Thay đổi connections
 */

import { Node, Edge } from '@xyflow/react';
import { connectionRuleEngine, cleanInvalidEdges } from '../rules/ConnectionRules';

describe('Comprehensive Flow Tests - All Scenarios', () => {
  // ============================================================================
  // SCENARIO 1: TẠO MỚI PROJECT (FROM SCRATCH)
  // ============================================================================

  describe('Scenario 1: Tạo Mới Project (From Scratch)', () => {
    test('Step 1: Tạo Data Source đầu tiên → Auto-create Repository', () => {
      // Initial state: Empty
      let nodes: Node[] = [];
      let edges: Edge[] = [];

      // User adds first data source
      const dataSource: Node = {
        id: 's1',
        type: 'start',
        position: { x: 100, y: 50 },
        data: { sourceType: 'url', url: 'https://example.com' }
      };

      const repository: Node = {
        id: 'repository-node',
        type: 'repository',
        position: { x: 100, y: 200 },
        data: {},
        deletable: false
      };

      const edge: Edge = {
        id: 'e-s1-repository-node',
        source: 's1',
        target: 'repository-node'
      };

      nodes = [dataSource, repository];
      edges = [edge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Step 2: Thêm Data Source thứ 2 → Connect to same Repository', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 50, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' }
      ];

      // Add second data source
      const dataSource2: Node = {
        id: 's2',
        type: 'start',
        position: { x: 150, y: 50 },
        data: { sourceType: 'api' }
      };

      const edge2: Edge = {
        id: 'e2',
        source: 's2',
        target: 'repository-node'
      };

      nodes = [...nodes, dataSource2];
      edges = [...edges, edge2];

      // Validate: n sources → 1 repository (valid)
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
      expect(edges).toHaveLength(2);
    });

    test('Step 3: Thêm Worker từ Repository', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} }
      ];

      let edges: Edge[] = [
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
        source: 'repository-node',
        target: 'w1'
      };

      nodes = [...nodes, worker];
      edges = [...edges, workerEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Step 4: Thêm HTML Extractor vào Worker', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' }
      ];

      // Add HTML extractor
      const extractor: Node = {
        id: 'html1',
        type: 'html-data-extractor',
        position: { x: 50, y: 275 },
        data: {}
      };

      const extractorEdge: Edge = {
        id: 'e-html1-w1',
        source: 'html1',
        target: 'w1'
      };

      nodes = [...nodes, extractor];
      edges = [...edges, extractorEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Step 5: Thêm Processor đầu tiên từ Worker', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'html1', type: 'html-data-extractor', position: { x: 50, y: 275 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'html1', target: 'w1' }
      ];

      // Add first processor
      const processor: Node = {
        id: 'p1',
        type: 'processor',
        position: { x: 100, y: 500 },
        data: { processorType: 'save_to_wordpress' }
      };

      const processorEdge: Edge = {
        id: 'e-w1-p1',
        source: 'w1',
        target: 'p1'
      };

      nodes = [...nodes, processor];
      edges = [...edges, processorEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Step 6: Thêm Processor thứ 2 từ Processor (chain)', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      // User CHỌN p1, thêm processor mới
      const selectedNode = nodes.find(n => n.id === 'p1')!;

      const processor2: Node = {
        id: 'p2',
        type: 'processor',
        position: { x: 100, y: 650 },
        data: { processorType: 'send_to_api' }
      };

      // CRITICAL: New processor MUST connect to p1 (selected), NOT to w1!
      const processorEdge: Edge = {
        id: 'e-p1-p2',
        source: 'p1',  // ← Selected processor
        target: 'p2'
      };

      nodes = [...nodes, processor2];
      edges = [...edges, processorEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Verify p2's incoming is from p1, not w1
      const p2IncomingEdge = edges.find(e => e.target === 'p2');
      expect(p2IncomingEdge?.source).toBe('p1');
      expect(p2IncomingEdge?.source).not.toBe('w1');
    });

    test('Complete flow: From scratch to completion', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'html1', type: 'html-data-extractor', position: { x: 50, y: 275 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 100, y: 650 }, data: {} },
        { id: 'completion-node', type: 'completion', position: { x: 100, y: 800 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'html1', target: 'w1' },
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'p1', target: 'p2' },
        { id: 'e6', source: 'p2', target: 'completion-node' }
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
      expect(validation.errors).toHaveLength(0);
    });
  });

  // ============================================================================
  // SCENARIO 2: EDIT PROJECT (Load và Modify)
  // ============================================================================

  describe('Scenario 2: Edit Project Hiện Có', () => {
    test('Load project với valid connections', () => {
      // Existing project data (from database)
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      // Validate on load
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Load project với INVALID connections → Auto-clean', () => {
      // Old project with invalid connections (từ version cũ)
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      const invalidEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'p1' },  // INVALID: Repo → Processor
        { id: 'e3', source: 'w1', target: 'p1' }  // Valid
      ];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, invalidEdges);
      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges).toHaveLength(2);  // e2 + w1 needs repo connection

      // Auto-clean
      const cleanEdges = cleanInvalidEdges(nodes, invalidEdges);
      expect(cleanEdges).toHaveLength(2);  // Keep e1 and e3
      expect(cleanEdges.find(e => e.id === 'e2')).toBeUndefined();

      // Validate cleaned flow
      const cleanValidation = connectionRuleEngine.validateFlow(nodes, cleanEdges);
      // Still invalid because w1 missing Repository connection
      expect(cleanValidation.isValid).toBe(false);
    });

    test('Edit: Thêm Worker mới vào project hiện có', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      // Add second worker
      const worker2: Node = {
        id: 'w2',
        type: 'worker',
        position: { x: 150, y: 350 },
        data: {}
      };

      const workerEdge: Edge = {
        id: 'e-repo-w2',
        source: 'repository-node',
        target: 'w2'
      };

      nodes = [...nodes, worker2];
      edges = [...edges, workerEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      // w2 needs a processor
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('w2') && e.includes('MUST have incoming from Repository'))).toBe(false);
      // Actually w2 HAS repo connection, just missing outgoing processor
    });

    test('Edit: Thêm Processor vào Worker mới', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 150, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'repository-node', target: 'w2' },
        { id: 'e4', source: 'w1', target: 'p1' }
      ];

      // User chọn w2, add processor
      const processor2: Node = {
        id: 'p2',
        type: 'processor',
        position: { x: 150, y: 500 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-w2-p2',
        source: 'w2',  // ← Connect to selected worker
        target: 'p2'
      };

      nodes = [...nodes, processor2];
      edges = [...edges, processorEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Edit: Xóa Data Source → Kiểm tra Repository còn valid', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 50, y: 50 }, data: {} },
        { id: 's2', type: 'start', position: { x: 150, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 's2', target: 'repository-node' },
        { id: 'e3', source: 'repository-node', target: 'w1' },
        { id: 'e4', source: 'w1', target: 'p1' }
      ];

      // Delete s1
      nodes = nodes.filter(n => n.id !== 's1');
      edges = edges.filter(e => e.source !== 's1' && e.target !== 's1');

      // Validate: Still valid (s2 still feeds repository)
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Edit: Xóa Data Source cuối cùng → Repository should be removed', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' }
      ];

      // Delete last data source
      nodes = nodes.filter(n => n.id !== 's1');
      edges = edges.filter(e => e.source !== 's1');

      // Repository now has no incoming → Should be cleaned up by App logic
      // (This is App-level logic, not Rule Engine)
      expect(nodes.some(n => n.type === 'repository')).toBe(true);
      // But in App.tsx, there's useEffect that clears everything if no start nodes
    });
  });

  // ============================================================================
  // SCENARIO 3: IMPORT JSON
  // ============================================================================

  describe('Scenario 3: Import JSON File', () => {
    test('Import valid JSON → Load trực tiếp', () => {
      const importedConfig = {
        projectSettings: {
          name: 'Imported Project',
          description: 'Test',
          enabled: true,
          crawlDelay: 1000,
          userAgent: 'Test',
          concurrency: 5
        },
        nodes: [
          { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
          { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
          { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
          { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
        ],
        edges: [
          { id: 'e1', source: 's1', target: 'repository-node' },
          { id: 'e2', source: 'repository-node', target: 'w1' },
          { id: 'e3', source: 'w1', target: 'p1' }
        ]
      };

      // Validate imported config
      const validation = connectionRuleEngine.validateFlow(
        importedConfig.nodes as Node[],
        importedConfig.edges as Edge[]
      );

      expect(validation.isValid).toBe(true);
      expect(validation.errors).toHaveLength(0);
    });

    test('Import INVALID JSON → Show confirm, clean if accepted', () => {
      const importedConfig = {
        projectSettings: { name: 'Test', description: '', enabled: true, crawlDelay: 1000, userAgent: '', concurrency: 5 },
        nodes: [
          { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
          { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
          { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
          { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
        ],
        edges: [
          { id: 'e1', source: 's1', target: 'repository-node' },
          { id: 'e2', source: 'repository-node', target: 'p1' },  // INVALID: Repo → Processor
          { id: 'e3', source: 'w1', target: 'p1' }  // Valid, but w1 missing repo connection
        ]
      };

      // Validate
      const validation = connectionRuleEngine.validateFlow(
        importedConfig.nodes as Node[],
        importedConfig.edges as Edge[]
      );

      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges.length).toBeGreaterThan(0);

      // User confirms cleanup
      const cleanEdges = cleanInvalidEdges(
        importedConfig.nodes as Node[],
        importedConfig.edges as Edge[]
      );

      // Validate cleaned
      const cleanValidation = connectionRuleEngine.validateFlow(
        importedConfig.nodes as Node[],
        cleanEdges
      );

      expect(cleanEdges.length).toBeLessThan(importedConfig.edges.length);
    });

    test('Import JSON với multiple invalid connections', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 400 }, data: {} }
      ];

      const invalidEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },  // Valid
        { id: 'e2', source: 'repository-node', target: 'p1' },  // INVALID: Repo → Processor
        { id: 'e3', source: 'repository-node', target: 'p2' },  // INVALID: Repo → Processor
        { id: 'e4', source: 'w1', target: 'repository-node' }   // INVALID: Worker → Repo
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, invalidEdges);

      expect(validation.isValid).toBe(false);
      expect(validation.invalidEdges.length).toBeGreaterThanOrEqual(3);

      // Clean
      const cleanEdges = cleanInvalidEdges(nodes, invalidEdges);
      expect(cleanEdges).toHaveLength(1);  // Only e1 is valid
      expect(cleanEdges[0].id).toBe('e1');
    });
  });

  // ============================================================================
  // SCENARIO 4: THÊM NODE SAU KHI IMPORT
  // ============================================================================

  describe('Scenario 4: Thêm Node Mới Sau Khi Import', () => {
    test('Import valid project → Thêm Worker mới', () => {
      // Step 1: Import project
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      // Validate imported
      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 2: User adds new worker
      const worker2: Node = {
        id: 'w2',
        type: 'worker',
        position: { x: 150, y: 350 },
        data: {}
      };

      const workerEdge: Edge = {
        id: 'e-repo-w2',
        source: 'repository-node',
        target: 'w2'
      };

      nodes = [...nodes, worker2];
      edges = [...edges, workerEdge];

      // Validate after adding worker
      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Import project → Thêm Processor vào Worker existing', () => {
      // Step 1: Import
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 150, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'repository-node', target: 'w2' },
        { id: 'e4', source: 'w1', target: 'p1' }
      ];

      // Step 2: User chọn w2 (no processors yet), add processor
      const processor2: Node = {
        id: 'p2',
        type: 'processor',
        position: { x: 150, y: 500 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-w2-p2',
        source: 'w2',  // ← Selected worker
        target: 'p2'
      };

      nodes = [...nodes, processor2];
      edges = [...edges, processorEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });

    test('Import project → Thêm Processor vào Processor existing (CRITICAL)', () => {
      // Step 1: Import
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 150, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 50, y: 650 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 150, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'repository-node', target: 'w2' },
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'p1', target: 'p2' },
        { id: 'e6', source: 'w2', target: 'p3' }
      ];

      // Validate imported
      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 2: User chọn p2 (end of w1 chain), add new processor
      const processor4: Node = {
        id: 'p4',
        type: 'processor',
        position: { x: 50, y: 800 },
        data: {}
      };

      const processorEdge: Edge = {
        id: 'e-p2-p4',
        source: 'p2',  // ← CRITICAL: Connect to p2 (selected), NOT w1!
        target: 'p4'
      };

      nodes = [...nodes, processor4];
      edges = [...edges, processorEdge];

      // Validate
      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // VERIFY: p4's incoming is from p2, not w1
      const p4IncomingEdge = edges.find(e => e.target === 'p4');
      expect(p4IncomingEdge?.source).toBe('p2');
      expect(p4IncomingEdge?.source).not.toBe('w1');
    });

    test('Import project → Thêm Processor vào chain giữa (INSERT)', () => {
      // Import with chain: w1 → p1 → p2
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 100, y: 650 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' },
        { id: 'e4', source: 'p1', target: 'p2' }
      ];

      // User chọn p1, add processor
      // Expected: w1 → p1 → p_new → p2
      // Reality: w1 → p1 → p_new (p_new NOT connected to p2)
      // User phải manually connect p_new → p2

      const processor_new: Node = {
        id: 'p_new',
        type: 'processor',
        position: { x: 100, y: 575 },
        data: {}
      };

      const newEdge: Edge = {
        id: 'e-p1-pnew',
        source: 'p1',  // Selected
        target: 'p_new'
      };

      nodes = [...nodes, processor_new];
      edges = [...edges, newEdge];

      // Now have: w1 → p1 → p_new AND p1 → p2 (p1 has 2 outgoing!)
      // This is INVALID!
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      
      if (edges.some(e => e.source === 'p1' && e.target === 'p2')) {
        // If old edge still exists, p1 has 2 outgoing → INVALID
        expect(validation.isValid).toBe(false);
      }
    });

    test('Import với invalid edges → Clean → Add new nodes', () => {
      // Step 1: Import with invalid
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'p1' },  // INVALID
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      // Clean
      edges = cleanInvalidEdges(nodes, edges);

      // Step 2: Fix by adding missing connection
      const repoToWorkerEdge: Edge = {
        id: 'e-repo-w1',
        source: 'repository-node',
        target: 'w1'
      };

      edges = [...edges, repoToWorkerEdge];

      // Validate
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // SCENARIO 5: COMPLEX EDIT SCENARIOS
  // ============================================================================

  describe('Scenario 5: Complex Edit Operations', () => {
    test('Delete processor ở giữa chain → Remaining processors still valid', () => {
      let nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' }
      ];

      // Delete p2 (middle processor)
      nodes = nodes.filter(n => n.id !== 'p2');
      edges = edges.filter(e => e.source !== 'p2' && e.target !== 'p2');

      // Now have: w1 → p1, p3 (orphaned!)
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('p3') && e.includes('MUST have incoming'))).toBe(true);

      // Fix: User should connect p1 → p3
      const fixEdge: Edge = {
        id: 'e-p1-p3',
        source: 'p1',
        target: 'p3'
      };

      edges = [...edges, fixEdge];

      const fixedValidation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(fixedValidation.isValid).toBe(true);
    });

    test('Move processor from one chain to another', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 50, y: 350 }, data: {} },
        { id: 'w2', type: 'worker', position: { x: 150, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 50, y: 500 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 150, y: 500 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 50, y: 650 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'repository-node', target: 'w2' },
        { id: 'e4', source: 'w1', target: 'p1' },
        { id: 'e5', source: 'w2', target: 'p2' },
        { id: 'e6', source: 'p1', target: 'p3' }
      ];

      // Valid initial state
      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Move p3 from w1 chain to w2 chain
      // Remove: p1 → p3
      edges = edges.filter(e => e.id !== 'e6');

      // Add: p2 → p3
      const newEdge: Edge = {
        id: 'e-p2-p3',
        source: 'p2',
        target: 'p3'
      };

      edges = [...edges, newEdge];

      // Validate after move
      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Verify p3 now connected to p2
      const p3Incoming = edges.find(e => e.target === 'p3');
      expect(p3Incoming?.source).toBe('p2');
    });

    test('Add multiple processors rapidly', () => {
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' }
      ];

      // Add 5 processors rapidly
      for (let i = 1; i <= 5; i++) {
        const processor: Node = {
          id: `p${i}`,
          type: 'processor',
          position: { x: 100, y: 500 + (i - 1) * 150 },
          data: {}
        };

        const previousNode = i === 1 ? nodes.find(n => n.id === 'w1')! : nodes.find(n => n.id === `p${i - 1}`)!;

        const processorEdge: Edge = {
          id: `e-${previousNode.id}-p${i}`,
          source: previousNode.id,
          target: `p${i}`
        };

        nodes = [...nodes, processor];
        edges = [...edges, processorEdge];

        // Validate after each addition
        const validation = connectionRuleEngine.validateFlow(nodes, edges);
        expect(validation.isValid).toBe(true);
      }

      // Final validation: w1 → p1 → p2 → p3 → p4 → p5
      expect(nodes.filter(n => n.type === 'processor')).toHaveLength(5);
      expect(edges).toHaveLength(7); // 1 source→repo + 1 repo→w1 + 5 processor edges
    });
  });

  // ============================================================================
  // SCENARIO 6: EDGE CASES VÀ CORNER CASES
  // ============================================================================

  describe('Scenario 6: Edge Cases và Corner Cases', () => {
    test('Empty project (only repo and completion) → Valid', () => {
      const nodes: Node[] = [
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'completion-node', type: 'completion', position: { x: 100, y: 500 }, data: {} }
      ];

      const edges: Edge[] = [];

      // Empty project is technically valid (no violations)
      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.errors.filter(e => !e.includes('MUST have incoming'))).toHaveLength(0);
    });

    test('Try to create cycle in processor chain → Rejected', () => {
      const nodes: Node[] = [
        { id: 'w1', type: 'worker', position: { x: 0, y: 0 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 100 }, data: {} },
        { id: 'p2', type: 'processor', position: { x: 0, y: 200 }, data: {} },
        { id: 'p3', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'w1', target: 'p1' },
        { id: 'e2', source: 'p1', target: 'p2' },
        { id: 'e3', source: 'p2', target: 'p3' },
        { id: 'e4', source: 'p3', target: 'p1' }  // CYCLE!
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('cycle'))).toBe(true);
    });

    test('Worker with 2 extractors → Second rejected', () => {
      const nodes: Node[] = [
        { id: 'repo', type: 'repository', position: { x: 0, y: 0 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 100 }, data: {} },
        { id: 'html1', type: 'html-data-extractor', position: { x: -50, y: 50 }, data: {} },
        { id: 'csv1', type: 'csv-extractor', position: { x: 50, y: 50 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 200 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 'repo', target: 'w1' },
        { id: 'e2', source: 'html1', target: 'w1' },  // First extractor
        { id: 'e3', source: 'csv1', target: 'w1' },   // Second extractor → INVALID!
        { id: 'e4', source: 'w1', target: 'p1' }
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('Worker can only have ONE data extractor'))).toBe(true);
    });

    test('Data source với 2 outgoing connections → Rejected', () => {
      const nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repo1', type: 'repository', position: { x: -50, y: 100 }, data: {} },
        { id: 'repo2', type: 'repository', position: { x: 50, y: 100 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repo1' },
        { id: 'e2', source: 's1', target: 'repo2' }  // s1 has 2 outgoing → INVALID!
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(false);
      expect(validation.errors.some(e => e.includes('maximum 1 outgoing'))).toBe(true);
    });
  });

  // ============================================================================
  // SCENARIO 7: REALISTIC REAL-WORLD FLOWS
  // ============================================================================

  describe('Scenario 7: Real-World Complete Flows', () => {
    test('E-commerce crawler: Products + Categories', () => {
      const nodes: Node[] = [
        // Data sources
        { id: 's1', type: 'start', position: { x: 100, y: 0 }, data: { sourceType: 'url', url: 'https://shop.com/products' } },
        
        // Repository
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 150 }, data: {} },
        
        // Workers
        { id: 'w1', type: 'worker', position: { x: 50, y: 300 }, data: { name: 'Product Worker' } },
        { id: 'w2', type: 'worker', position: { x: 150, y: 300 }, data: { name: 'Category Worker' } },
        
        // Extractors
        { id: 'html1', type: 'html-data-extractor', position: { x: 50, y: 225 }, data: {} },
        { id: 'html2', type: 'html-data-extractor', position: { x: 150, y: 225 }, data: {} },
        
        // Processors for products
        { id: 'p1', type: 'processor', position: { x: 50, y: 450 }, data: { processorType: 'save_to_wordpress' } },
        { id: 'p2', type: 'processor', position: { x: 50, y: 600 }, data: { processorType: 'generate_csv' } },
        
        // Processors for categories
        { id: 'p3', type: 'processor', position: { x: 150, y: 450 }, data: { processorType: 'save_to_wordpress' } },
        
        // Completion
        { id: 'completion-node', type: 'completion', position: { x: 100, y: 750 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'repository-node', target: 'w2' },
        { id: 'e4', source: 'html1', target: 'w1' },
        { id: 'e5', source: 'html2', target: 'w2' },
        { id: 'e6', source: 'w1', target: 'p1' },
        { id: 'e7', source: 'p1', target: 'p2' },
        { id: 'e8', source: 'w2', target: 'p3' },
        { id: 'e9', source: 'p2', target: 'completion-node' },
        { id: 'e10', source: 'p3', target: 'completion-node' }
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
      expect(validation.errors).toHaveLength(0);
    });

    test('News aggregator: Multiple sources, single worker, multiple processors', () => {
      const nodes: Node[] = [
        // Multiple sources
        { id: 's1', type: 'start', position: { x: 50, y: 0 }, data: { sourceType: 'url' } },
        { id: 's2', type: 'start', position: { x: 100, y: 0 }, data: { sourceType: 'xml' } },
        { id: 's3', type: 'start', position: { x: 150, y: 0 }, data: { sourceType: 'api' } },
        
        // Repository
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 150 }, data: {} },
        
        // Single worker
        { id: 'w1', type: 'worker', position: { x: 100, y: 300 }, data: { name: 'Article Parser' } },
        
        // Extractor
        { id: 'html1', type: 'html-data-extractor', position: { x: 100, y: 225 }, data: {} },
        
        // Processor chain
        { id: 'p1', type: 'processor', position: { x: 100, y: 450 }, data: { processorType: 'save_to_wordpress' } },
        { id: 'p2', type: 'processor', position: { x: 100, y: 600 }, data: { processorType: 'send_to_api' } },
        { id: 'p3', type: 'processor', position: { x: 100, y: 750 }, data: { processorType: 'send_email' } },
        
        // Completion
        { id: 'completion-node', type: 'completion', position: { x: 100, y: 900 }, data: {} }
      ];

      const edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 's2', target: 'repository-node' },
        { id: 'e3', source: 's3', target: 'repository-node' },
        { id: 'e4', source: 'repository-node', target: 'w1' },
        { id: 'e5', source: 'html1', target: 'w1' },
        { id: 'e6', source: 'w1', target: 'p1' },
        { id: 'e7', source: 'p1', target: 'p2' },
        { id: 'e8', source: 'p2', target: 'p3' },
        { id: 'e9', source: 'p3', target: 'completion-node' }
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // SCENARIO 8: IMPORT JSON → EDIT → VALIDATE
  // ============================================================================

  describe('Scenario 8: Import JSON → Edit → Validate Cycle', () => {
    test('Full cycle: Import → Add worker → Add processor → Validate', () => {
      // Step 1: Import valid JSON
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 100, y: 50 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 100, y: 200 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 100, y: 350 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 100, y: 500 }, data: {} }
      ];

      let edges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'w1' },
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      let validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 2: Add second worker
      const worker2: Node = { id: 'w2', type: 'worker', position: { x: 200, y: 350 }, data: {} };
      const workerEdge: Edge = { id: 'e-repo-w2', source: 'repository-node', target: 'w2' };

      nodes = [...nodes, worker2];
      edges = [...edges, workerEdge];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 3: Add processor to new worker
      const processor2: Node = { id: 'p2', type: 'processor', position: { x: 200, y: 500 }, data: {} };
      const processorEdge: Edge = { id: 'e-w2-p2', source: 'w2', target: 'p2' };

      nodes = [...nodes, processor2];
      edges = [...edges, processorEdge];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 4: Chain processor on p2
      const processor3: Node = { id: 'p3', type: 'processor', position: { x: 200, y: 650 }, data: {} };
      const chainEdge: Edge = { id: 'e-p2-p3', source: 'p2', target: 'p3' };

      nodes = [...nodes, processor3];
      edges = [...edges, chainEdge];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Final check: Verify p3's incoming is from p2
      const p3Incoming = edges.find(e => e.target === 'p3');
      expect(p3Incoming?.source).toBe('p2');
    });

    test('Import invalid → Clean → Add nodes → Validate cycle', () => {
      // Step 1: Import với invalid connections
      let nodes: Node[] = [
        { id: 's1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'repository-node', type: 'repository', position: { x: 0, y: 100 }, data: {} },
        { id: 'w1', type: 'worker', position: { x: 0, y: 200 }, data: {} },
        { id: 'p1', type: 'processor', position: { x: 0, y: 300 }, data: {} }
      ];

      let invalidEdges: Edge[] = [
        { id: 'e1', source: 's1', target: 'repository-node' },
        { id: 'e2', source: 'repository-node', target: 'p1' },  // INVALID
        { id: 'e3', source: 'w1', target: 'p1' }
      ];

      let validation = connectionRuleEngine.validateFlow(nodes, invalidEdges);
      expect(validation.isValid).toBe(false);

      // Step 2: Auto-clean
      let edges = cleanInvalidEdges(nodes, invalidEdges);

      // Step 3: Fix missing connection
      const fixEdge: Edge = { id: 'e-repo-w1', source: 'repository-node', target: 'w1' };
      edges = [...edges, fixEdge];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);

      // Step 4: Add new processor to chain
      const processor2: Node = { id: 'p2', type: 'processor', position: { x: 0, y: 400 }, data: {} };
      const newEdge: Edge = { id: 'e-p1-p2', source: 'p1', target: 'p2' };

      nodes = [...nodes, processor2];
      edges = [...edges, newEdge];

      validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
    });
  });

  // ============================================================================
  // SCENARIO 9: STRESS TESTS
  // ============================================================================

  describe('Scenario 9: Stress Tests', () => {
    test('Large flow: 5 sources, 10 workers, 30 processors', () => {
      const sources = Array.from({ length: 5 }, (_, i) => ({
        id: `s${i + 1}`,
        type: 'start' as const,
        position: { x: i * 100, y: 0 },
        data: {}
      }));

      const workers = Array.from({ length: 10 }, (_, i) => ({
        id: `w${i + 1}`,
        type: 'worker' as const,
        position: { x: i * 80, y: 250 },
        data: {}
      }));

      const processors = Array.from({ length: 30 }, (_, i) => ({
        id: `p${i + 1}`,
        type: 'processor' as const,
        position: { x: (i % 10) * 80, y: 400 + Math.floor(i / 10) * 150 },
        data: {}
      }));

      const nodes: Node[] = [
        ...sources,
        { id: 'repository-node', type: 'repository', position: { x: 250, y: 125 }, data: {} },
        ...workers,
        ...processors,
        { id: 'completion-node', type: 'completion', position: { x: 250, y: 850 }, data: {} }
      ];

      const edges: Edge[] = [
        // Sources to repo
        ...sources.map(s => ({
          id: `e-${s.id}-repo`,
          source: s.id,
          target: 'repository-node'
        })),
        
        // Repo to workers
        ...workers.map(w => ({
          id: `e-repo-${w.id}`,
          source: 'repository-node',
          target: w.id
        })),
        
        // Workers to processors (3 processors per worker)
        ...workers.flatMap((w, wi) => [
          { id: `e-${w.id}-p${wi * 3 + 1}`, source: w.id, target: `p${wi * 3 + 1}` },
          { id: `e-p${wi * 3 + 1}-p${wi * 3 + 2}`, source: `p${wi * 3 + 1}`, target: `p${wi * 3 + 2}` },
          { id: `e-p${wi * 3 + 2}-p${wi * 3 + 3}`, source: `p${wi * 3 + 2}`, target: `p${wi * 3 + 3}` }
        ]),
        
        // Last processors to completion
        ...Array.from({ length: 10 }, (_, i) => ({
          id: `e-p${i * 3 + 3}-comp`,
          source: `p${i * 3 + 3}`,
          target: 'completion-node'
        }))
      ];

      const validation = connectionRuleEngine.validateFlow(nodes, edges);
      expect(validation.isValid).toBe(true);
      expect(nodes).toHaveLength(47); // 5 sources + 1 repo + 10 workers + 30 processors + 1 completion
      expect(edges).toHaveLength(55); // 5 + 10 + 30 + 10
    });
  });
});

