/**
 * Connection Rules Engine for CrawlFlow
 * 
 * Định nghĩa các quy tắc kết nối giữa các node types.
 * Sử dụng Strategy Pattern để validate connections.
 * 
 * Validates both OUTGOING (from rules) and INCOMING (in validateConnection) connections.
 */

import { Node, Edge, Connection } from '@xyflow/react';

// ============================================================================
// TYPE DEFINITIONS
// ============================================================================

export type NodeType = 
  | 'start'
  | 'repository'
  | 'worker'
  | 'processor'
  | 'completion'
  | 'html-data-extractor'
  | 'csv-extractor'
  | 'json-extractor'
  | 'xml-extractor'
  | 'mysql-extractor';

export interface ConnectionRule {
  /** Source node type */
  from: NodeType;
  /** Allowed target node types */
  to: NodeType[];
  /** Rule description */
  description: string;
  /** Maximum number of outgoing connections (undefined = unlimited) */
  maxConnections?: number;
  /** Maximum number of incoming connections (undefined = unlimited) */
  maxIncomingConnections?: number;
  /** Allowed source node types for incoming (undefined = check outgoing rules) */
  allowedIncoming?: NodeType[];
  /** Is this connection required? */
  required?: boolean;
}

export interface ValidationResult {
  isValid: boolean;
  error?: string;
  rule?: ConnectionRule;
}

// ============================================================================
// CONNECTION RULES REGISTRY
// ============================================================================

/**
 * Định nghĩa TẤT CẢ các quy tắc kết nối trong hệ thống
 * 
 * OUTGOING Rules (defined here):
 * 1. Data Source → Repository (max 1 outgoing)
 * 2. Repository → Worker (unlimited outgoing)
 * 3. Worker → Processor (max 1 outgoing)
 * 4. Processor → Processor OR Completion (max 1 outgoing, max 1 incoming) ⭐
 * 5. Extractors → Worker (max 1 outgoing)
 * 6. Completion → NONE (final node)
 * 
 * INCOMING Rules (validated in validateConnection):
 * - Repository ← Data Sources ONLY (n-to-1: many sources → 1 repo)
 * - Worker ← Repository (REQUIRED) + Extractors (optional, max 1)
 * - Processor ← Worker OR Processor (REQUIRED, max 1 incoming) ⭐
 * - Completion ← Processor (auto-connected)
 * 
 * Required Incoming Connections (validated in validateFlow):
 * - Processor: MUST have exactly 1 incoming from Worker or Processor ⭐
 * - Worker: MUST have incoming from Repository
 * 
 * Max Incoming Enforcement (validated in validateFlow):
 * - Processor: Maximum 1 incoming connection ⭐
 * - Auto-remove excess incoming connections if > max
 */
export const CONNECTION_RULES: ConnectionRule[] = [
  // Rule 1: Data Sources chỉ kết nối với Repository
  {
    from: 'start',
    to: ['repository'],
    description: 'Data Source can ONLY connect to Repository',
    maxConnections: 1,
    required: true
  },

  // Rule 2: Repository chỉ kết nối với Worker
  {
    from: 'repository',
    to: ['worker'],
    description: 'Repository can ONLY connect to Worker',
    maxConnections: undefined, // Unlimited workers
    required: true
  },

  // Rule 3: Worker chỉ kết nối với Processor
  {
    from: 'worker',
    to: ['processor'],
    description: 'Worker can ONLY connect to Processor',
    maxConnections: 1,
    required: true
  },

  // Rule 4: Processor có thể chain với Processor khác hoặc kết thúc ở Completion
  {
    from: 'processor',
    to: ['processor', 'completion'],
    description: 'Processor can ONLY connect to Processor or Completion',
    maxConnections: 1,
    maxIncomingConnections: 1,  // ⭐ NEW: Max 1 incoming
    allowedIncoming: ['worker', 'processor'],  // ⭐ NEW: Only from worker or processor
    required: false
  },

  // Rule 5: Extractors PHẢI kết nối với Worker (như input phụ cho parsing)
  {
    from: 'html-data-extractor',
    to: ['worker'],
    description: 'HTML Data Extractor can ONLY connect to Worker (as parsing input)',
    maxConnections: 1,
    required: true
  },
  {
    from: 'csv-extractor',
    to: ['worker'],
    description: 'CSV Extractor can ONLY connect to Worker (as parsing input)',
    maxConnections: 1,
    required: true
  },
  {
    from: 'json-extractor',
    to: ['worker'],
    description: 'JSON Extractor can ONLY connect to Worker (as parsing input)',
    maxConnections: 1,
    required: true
  },
  {
    from: 'xml-extractor',
    to: ['worker'],
    description: 'XML Extractor can ONLY connect to Worker (as parsing input)',
    maxConnections: 1,
    required: true
  },
  {
    from: 'mysql-extractor',
    to: ['worker'],
    description: 'MySQL Extractor can ONLY connect to Worker (as parsing input)',
    maxConnections: 1,
    required: true
  },

  // Rule 6: Completion node không có outgoing connections
  {
    from: 'completion',
    to: [],
    description: 'Completion node is the final node (no outgoing connections)',
    maxConnections: 0
  }
];

// ============================================================================
// RULE ENGINE
// ============================================================================

export class ConnectionRuleEngine {
  private rules: Map<NodeType, ConnectionRule>;

  constructor(rules: ConnectionRule[] = CONNECTION_RULES) {
    this.rules = new Map();
    rules.forEach(rule => {
      this.rules.set(rule.from, rule);
    });
  }

  /**
   * Validate một connection trước khi tạo
   * @param nodes All nodes in the flow (needed for complex validations)
   * @param currentEdgeId Optional: ID of edge being validated (for validateFlow, exclude from count)
   */
  validateConnection(
    sourceNode: Node,
    targetNode: Node,
    existingEdges: Edge[],
    nodes?: Node[],  // Optional for backward compatibility
    currentEdgeId?: string  // Optional: ID of edge being validated
  ): ValidationResult {
    const sourceType = sourceNode.type as NodeType;
    const targetType = targetNode.type as NodeType;

    // 1. Kiểm tra rule có tồn tại cho source type
    const rule = this.rules.get(sourceType);
    if (!rule) {
      return {
        isValid: false,
        error: `No connection rule defined for node type: ${sourceType}`
      };
    }

    // 2. Kiểm tra target type có được phép không
    if (!rule.to.includes(targetType)) {
      return {
        isValid: false,
        error: `${this.getNodeTypeLabel(sourceType)} can ONLY connect to ${this.formatAllowedTypes(rule.to)} (found: ${this.getNodeTypeLabel(targetType)})`,
        rule
      };
    }

    // 3. Kiểm tra max connections (per source node)
    if (rule.maxConnections !== undefined) {
      // CRITICAL: Exclude current edge from count (when validating existing edges in validateFlow)
      const existingOutgoingCount = existingEdges.filter(
        edge => edge.source === sourceNode.id && edge.id !== currentEdgeId
      ).length;

      if (existingOutgoingCount >= rule.maxConnections) {
        return {
          isValid: false,
          error: `${this.getNodeTypeLabel(sourceType)} can have maximum ${rule.maxConnections} outgoing connection(s)`,
          rule
        };
      }
    }

    // 4. Incoming connection validation: Repository chỉ nhận từ Data Sources
    if (targetType === 'repository' && sourceType !== 'start') {
      return {
        isValid: false,
        error: 'Repository can ONLY receive connections from Data Sources (not from ' + this.getNodeTypeLabel(sourceType) + ')',
        rule
      };
    }

    // 5. Incoming connection validation: Worker chỉ nhận từ Repository hoặc Extractors
    const extractorTypes: NodeType[] = ['html-data-extractor', 'csv-extractor', 'json-extractor', 'xml-extractor', 'mysql-extractor'];
    if (targetType === 'worker' && sourceType !== 'repository' && !extractorTypes.includes(sourceType)) {
      return {
        isValid: false,
        error: 'Worker can ONLY receive connections from Repository or Data Extractors (not from ' + this.getNodeTypeLabel(sourceType) + ')',
        rule
      };
    }

    // 6. Special validation: Worker chỉ nhận 1 extractor input
    if (targetType === 'worker' && extractorTypes.includes(sourceType)) {
      // Kiểm tra worker đã có extractor input chưa (exclude current edge)
      const existingExtractorInputs = existingEdges.filter(edge => {
        if (edge.target !== targetNode.id) return false;
        if (edge.id === currentEdgeId) return false; // Exclude current edge
        
        // If nodes array provided, do accurate check
        if (nodes) {
          const edgeSourceNode = nodes.find(n => n.id === edge.source);
          return edgeSourceNode && extractorTypes.includes(edgeSourceNode.type as NodeType);
        }
        
        // Fallback: approximate check by id pattern
        return extractorTypes.some(type => edge.source.includes(type));
      });

      if (existingExtractorInputs.length > 0) {
        return {
          isValid: false,
          error: 'Worker can only have ONE data extractor input. Remove existing extractor first.',
          rule
        };
      }
    }

    // 7. CRITICAL: Incoming validation for Processor (REJECT Repository → Processor)
    if (targetType === 'processor') {
      // Processor chỉ nhận từ Worker hoặc Processor khác
      // KHÔNG được nhận từ Repository, Data Source, Extractor, etc.
      if (sourceType !== 'worker' && sourceType !== 'processor') {
        return {
          isValid: false,
          error: `Processor can ONLY receive connections from Worker or another Processor (not from ${this.getNodeTypeLabel(sourceType)})`,
          rule
        };
      }
      
      // Check max incoming connections
      const targetRule = this.rules.get(targetType);
      if (targetRule?.maxIncomingConnections !== undefined) {
        // Count existing incoming connections to target (exclude current edge)
        const incomingCount = existingEdges.filter(e => 
          e.target === targetNode.id && e.id !== currentEdgeId
        ).length;

        if (incomingCount >= targetRule.maxIncomingConnections) {
          return {
            isValid: false,
            error: `${this.getNodeTypeLabel(targetType)} can have maximum ${targetRule.maxIncomingConnections} incoming connection(s). Remove existing connection first.`,
            rule: targetRule
          };
        }
      }
    }

    // 8. Kiểm tra không tạo cycle (optional, có thể bỏ nếu cho phép cycle)
    if (this.wouldCreateCycle(sourceNode.id, targetNode.id, existingEdges)) {
      return {
        isValid: false,
        error: 'Connection would create a cycle',
        rule
      };
    }

    return { isValid: true, rule };
  }

  /**
   * Validate toàn bộ flow (dùng khi import hoặc validate tổng thể)
   */
  validateFlow(nodes: Node[], edges: Edge[]): {
    isValid: boolean;
    errors: string[];
    invalidEdges: Edge[];
  } {
    const errors: string[] = [];
    const invalidEdges: Edge[] = [];

    // 1. Validate all edges
    edges.forEach(edge => {
      const sourceNode = nodes.find(n => n.id === edge.source);
      const targetNode = nodes.find(n => n.id === edge.target);

      if (!sourceNode || !targetNode) {
        errors.push(`Edge ${edge.id}: Source or target node not found`);
        invalidEdges.push(edge);
        return;
      }

      // Pass edge.id to exclude current edge from maxConnections count
      const result = this.validateConnection(sourceNode, targetNode, edges, nodes, edge.id);
      if (!result.isValid) {
        errors.push(`Edge ${edge.id} (${sourceNode.id} → ${targetNode.id}): ${result.error}`);
        invalidEdges.push(edge);
      }
    });

    // 2. Validate required incoming connections AND max incoming limits
    nodes.forEach(node => {
      const nodeType = node.type as NodeType;
      const rule = this.rules.get(nodeType);
      
      // Count incoming connections for this node
      const incomingEdges = edges.filter(e => e.target === node.id);
      const incomingCount = incomingEdges.length;

      // Check max incoming connections
      if (rule?.maxIncomingConnections !== undefined && incomingCount > rule.maxIncomingConnections) {
        errors.push(
          `Node ${node.id} (${this.getNodeTypeLabel(nodeType)}): ` +
          `Has ${incomingCount} incoming connection(s), but maximum allowed is ${rule.maxIncomingConnections}. ` +
          `Extra connections should be removed.`
        );
        
        // Mark excess edges as invalid (keep first N valid ones)
        if (rule.allowedIncoming) {
          const validIncoming = incomingEdges.filter(e => {
            const sourceNode = nodes.find(n => n.id === e.source);
            return sourceNode && rule.allowedIncoming!.includes(sourceNode.type as NodeType);
          });
          
          // Mark edges beyond maxIncomingConnections as invalid
          validIncoming.slice(rule.maxIncomingConnections).forEach(edge => {
            if (!invalidEdges.includes(edge)) {
              invalidEdges.push(edge);
            }
          });
        }
      }

      // Check required incoming connections
      if (node.type === 'processor') {
        if (incomingCount === 0) {
          errors.push(`Processor node ${node.id}: MUST have incoming connection from Worker or another Processor`);
        }
      }

      // Worker BẮT BUỘC phải có incoming từ Repository
      if (node.type === 'worker') {
        const hasRepositoryInput = edges.some(edge => {
          if (edge.target !== node.id) return false;
          const sourceNode = nodes.find(n => n.id === edge.source);
          return sourceNode && sourceNode.type === 'repository';
        });
        
        if (!hasRepositoryInput) {
          errors.push(`Worker node ${node.id}: MUST have incoming connection from Repository`);
        }
      }
    });

    // 3. Validate max instance of completion nodes (max 1 completion node per flow)
    const completionNodes = nodes.filter(n => n.type === 'completion');
    if (completionNodes.length > 1) {
      errors.push(
        `Flow can have maximum 1 Completion node, but found ${completionNodes.length}. ` +
        `Please remove extra completion nodes.`
      );
    }

    // 4. Validate max instance of repository nodes (max 1 repository node per flow)
    const repositoryNodes = nodes.filter(n => n.type === 'repository');
    if (repositoryNodes.length > 1) {
      errors.push(
        `Flow can have maximum 1 Raw Items Repository node, but found ${repositoryNodes.length}. ` +
        `Please remove extra repository nodes.`
      );
    }

    return {
      isValid: errors.length === 0,
      errors,
      invalidEdges
    };
  }

  /**
   * Lấy danh sách node types được phép kết nối từ một node type
   */
  getAllowedTargets(sourceType: NodeType): NodeType[] {
    const rule = this.rules.get(sourceType);
    return rule ? rule.to : [];
  }

  /**
   * Kiểm tra xem một connection có hợp lệ không (dùng cho UI highlighting)
   */
  isConnectionAllowed(sourceType: NodeType, targetType: NodeType): boolean {
    const allowedTargets = this.getAllowedTargets(sourceType);
    return allowedTargets.includes(targetType);
  }

  /**
   * Lấy rule description cho một node type
   */
  getRuleDescription(nodeType: NodeType): string {
    const rule = this.rules.get(nodeType);
    return rule ? rule.description : 'No rule defined';
  }

  // ============================================================================
  // PRIVATE HELPERS
  // ============================================================================

  private wouldCreateCycle(
    sourceId: string,
    targetId: string,
    edges: Edge[]
  ): boolean {
    // BFS để kiểm tra cycle
    const visited = new Set<string>();
    const queue = [targetId];

    while (queue.length > 0) {
      const currentId = queue.shift()!;
      
      if (currentId === sourceId) {
        return true; // Found cycle
      }

      if (visited.has(currentId)) {
        continue;
      }
      visited.add(currentId);

      // Tìm tất cả nodes mà currentId kết nối tới
      const outgoingEdges = edges.filter(e => e.source === currentId);
      outgoingEdges.forEach(e => queue.push(e.target));
    }

    return false;
  }

  private getNodeTypeLabel(type: NodeType): string {
    const labels: Record<NodeType, string> = {
      'start': 'Data Source',
      'repository': 'Repository',
      'worker': 'Worker',
      'processor': 'Processor',
      'completion': 'Completion',
      'html-data-extractor': 'HTML Data Extractor',
      'csv-extractor': 'CSV Extractor',
      'json-extractor': 'JSON Extractor',
      'xml-extractor': 'XML Extractor',
      'mysql-extractor': 'MySQL Extractor'
    };
    return labels[type] || type;
  }

  private formatAllowedTypes(types: NodeType[]): string {
    if (types.length === 0) return 'NONE';
    if (types.length === 1) return this.getNodeTypeLabel(types[0]);
    
    const labels = types.map(t => this.getNodeTypeLabel(t));
    const last = labels.pop();
    return `${labels.join(', ')} or ${last}`;
  }
}

// ============================================================================
// SINGLETON INSTANCE
// ============================================================================

export const connectionRuleEngine = new ConnectionRuleEngine();

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Clean invalid edges từ flow
 */
export function cleanInvalidEdges(nodes: Node[], edges: Edge[]): Edge[] {
  const validation = connectionRuleEngine.validateFlow(nodes, edges);
  return edges.filter(edge => !validation.invalidEdges.includes(edge));
}

/**
 * Kiểm tra xem có thể kết nối 2 nodes không
 */
export function canConnect(sourceNode: Node, targetNode: Node, edges: Edge[]): boolean {
  const result = connectionRuleEngine.validateConnection(sourceNode, targetNode, edges);
  return result.isValid;
}

/**
 * Lấy error message khi connection không hợp lệ
 */
export function getConnectionError(sourceNode: Node, targetNode: Node, edges: Edge[]): string | null {
  const result = connectionRuleEngine.validateConnection(sourceNode, targetNode, edges);
  return result.error || null;
}

