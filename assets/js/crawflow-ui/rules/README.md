# Connection Rules Engine

## 📖 Overview

Rule Engine quản lý tất cả các quy tắc kết nối giữa các node types trong CrawlFlow UI. Sử dụng **Strategy Pattern** để validate connections một cách declarative, thay thế cho if/else logic rải rác.

## 🎯 Design Pattern: Strategy Pattern

### Trước đây (❌ Anti-pattern):

```typescript
// App.tsx - if/else hell
const onConnect = (params) => {
  if (sourceNode.type === 'start' && targetNode.type !== 'repository') {
    console.warn("Invalid connection!");
    return;
  }
  
  if (sourceNode.type === 'repository' && targetNode.type !== 'worker') {
    console.warn("Invalid connection!");
    return;
  }
  
  // ... 20+ more if/else statements
  
  setEdges((eds) => addEdge(params, eds));
};
```

**Vấn đề**:
- ❌ Logic rải rác khắp nơi
- ❌ Khó maintain khi thêm node types mới
- ❌ Khó test
- ❌ Dễ miss rules khi refactor
- ❌ Không reusable

### Bây giờ (✅ Strategy Pattern):

```typescript
// ConnectionRules.ts - Declarative rules
export const CONNECTION_RULES: ConnectionRule[] = [
  {
    from: 'start',
    to: ['repository'],
    description: 'Data Source can ONLY connect to Repository',
    maxConnections: 1,
    required: true
  },
  // ... more rules
];

// App.tsx - Clean logic
const onConnect = (params) => {
  const validationResult = connectionRuleEngine.validateConnection(
    sourceNode,
    targetNode,
    edges
  );

  if (!validationResult.isValid) {
    alert(validationResult.error);
    return;
  }

  setEdges((eds) => addEdge(params, eds));
};
```

**Ưu điểm**:
- ✅ Rules là data, không phải code
- ✅ Dễ thêm/sửa rules
- ✅ Dễ test (test rules riêng, test engine riêng)
- ✅ Type-safe với TypeScript
- ✅ Reusable (dùng cho onConnect, import, validation)
- ✅ Clear error messages

## 🏗️ Architecture

```
ConnectionRules.ts
├── Type Definitions
│   ├── NodeType (union type)
│   ├── ConnectionRule (interface)
│   └── ValidationResult (interface)
│
├── Rules Registry
│   └── CONNECTION_RULES (declarative array)
│
├── Rule Engine
│   ├── ConnectionRuleEngine (class)
│   ├── validateConnection() - Single validation
│   ├── validateFlow() - Full flow validation
│   ├── getAllowedTargets() - Query allowed targets
│   └── isConnectionAllowed() - Quick check
│
└── Utility Functions
    ├── cleanInvalidEdges()
    ├── canConnect()
    └── getConnectionError()
```

## 📋 7 Connection Rules

### Rule 1: Data Source → Repository
```typescript
{
  from: 'start',
  to: ['repository'],
  description: 'Data Source can ONLY connect to Repository',
  maxConnections: 1,
  required: true
}
```

### Rule 2: Repository → Worker
```typescript
{
  from: 'repository',
  to: ['worker'],
  description: 'Repository can ONLY connect to Worker',
  maxConnections: undefined, // Unlimited workers
  required: true
}
```

### Rule 3: Worker → Processor
```typescript
{
  from: 'worker',
  to: ['processor'],
  description: 'Worker can ONLY connect to Processor',
  maxConnections: 1,
  required: true
}
```

### Rule 4: Processor → Processor or Completion
```typescript
{
  from: 'processor',
  to: ['processor', 'completion'],
  description: 'Processor can ONLY connect to Processor or Completion',
  maxConnections: 1,
  required: false
}
```

### Rule 5: Extractors connect to Worker ONLY
```typescript
{
  from: 'html-extractor',
  to: ['worker'],
  description: 'HTML Extractor can ONLY connect to Worker (as parsing input)',
  maxConnections: 1,
  required: true
}
// Same for: csv-extractor, json-extractor, xml-extractor, mysql-extractor
```

**Special Validation**: Worker can only have ONE extractor input (enforced in validateConnection)

### Rule 6: Completion is final
```typescript
{
  from: 'completion',
  to: [],
  description: 'Completion node is the final node',
  maxConnections: 0
}
```

### Rule 7: Cycle Detection (Built-in)
Rule Engine tự động detect cycles khi validate connections.

### Rule 8: Required Incoming Connections (validateFlow)
Một số node types BẮT BUỘC phải có incoming connections:

**Processor**: MUST have incoming from Worker or another Processor
```typescript
// Processor không được standalone
// Valid: Worker → Processor
// Valid: Processor → Processor (chain)
// Invalid: Processor without incoming (orphaned)
```

**Worker**: MUST have incoming from Repository
```typescript
// Worker phải kết nối với Repository
// Valid: Repository → Worker
// Invalid: Worker without Repository connection
```

## 🔧 Usage Examples

### 1. Validate Single Connection (onConnect)

```typescript
import { connectionRuleEngine } from './rules/ConnectionRules';

const onConnect = useCallback((params: Edge | Connection) => {
  const sourceNode = nodes.find(n => n.id === params.source);
  const targetNode = nodes.find(n => n.id === params.target);

  const result = connectionRuleEngine.validateConnection(
    sourceNode,
    targetNode,
    edges
  );

  if (!result.isValid) {
    alert(`⛔ ${result.error}`);
    return;
  }

  setEdges((eds) => addEdge(params, eds));
}, [nodes, edges]);
```

### 2. Validate Full Flow (Import)

```typescript
import { connectionRuleEngine, cleanInvalidEdges } from './rules/ConnectionRules';

const importConfiguration = (config) => {
  // Validate entire flow
  const validation = connectionRuleEngine.validateFlow(
    config.nodes,
    config.edges
  );

  if (!validation.isValid) {
    const confirmCleanup = window.confirm(
      `⚠️ Found ${validation.errors.length} invalid connections.\n\n` +
      `Click OK to auto-remove them.`
    );

    if (!confirmCleanup) {
      return;
    }

    // Clean invalid edges
    const cleanEdges = cleanInvalidEdges(config.nodes, config.edges);
    setNodes(config.nodes);
    setEdges(cleanEdges);
  } else {
    setNodes(config.nodes);
    setEdges(config.edges);
  }
};
```

### 3. Query Allowed Targets (UI Hints)

```typescript
import { connectionRuleEngine } from './rules/ConnectionRules';

// Get allowed targets for a node type
const allowedTargets = connectionRuleEngine.getAllowedTargets('worker');
// Returns: ['processor']

// Check if connection is allowed
const canConnect = connectionRuleEngine.isConnectionAllowed('worker', 'processor');
// Returns: true
```

### 4. Get Rule Description (Tooltips)

```typescript
import { connectionRuleEngine } from './rules/ConnectionRules';

const description = connectionRuleEngine.getRuleDescription('worker');
// Returns: "Worker can ONLY connect to Processor"
```

## 🧪 Testing

### Test Rules (Data)

```typescript
import { CONNECTION_RULES } from './ConnectionRules';

test('should have 7 main rules', () => {
  expect(CONNECTION_RULES.length).toBeGreaterThanOrEqual(7);
});

test('start node can only connect to repository', () => {
  const rule = CONNECTION_RULES.find(r => r.from === 'start');
  expect(rule.to).toEqual(['repository']);
});
```

### Test Engine (Logic)

```typescript
import { ConnectionRuleEngine } from './ConnectionRules';

const engine = new ConnectionRuleEngine();

test('should validate valid connection', () => {
  const sourceNode = { id: '1', type: 'worker' };
  const targetNode = { id: '2', type: 'processor' };
  const result = engine.validateConnection(sourceNode, targetNode, []);
  
  expect(result.isValid).toBe(true);
});

test('should reject invalid connection', () => {
  const sourceNode = { id: '1', type: 'worker' };
  const targetNode = { id: '2', type: 'repository' };
  const result = engine.validateConnection(sourceNode, targetNode, []);
  
  expect(result.isValid).toBe(false);
  expect(result.error).toContain('Worker can ONLY connect to Processor');
});
```

## 🔄 Adding New Node Types

### Step 1: Add to NodeType union

```typescript
export type NodeType = 
  | 'start'
  | 'repository'
  | 'worker'
  | 'processor'
  | 'completion'
  | 'my-new-node-type' // ← Add here
  | ...
```

### Step 2: Add rule to CONNECTION_RULES

```typescript
export const CONNECTION_RULES: ConnectionRule[] = [
  // ... existing rules
  
  // New rule
  {
    from: 'my-new-node-type',
    to: ['processor', 'completion'],
    description: 'My New Node can connect to Processor or Completion',
    maxConnections: 2,
    required: false
  }
];
```

### Step 3: Update label mapping (optional)

```typescript
private getNodeTypeLabel(type: NodeType): string {
  const labels: Record<NodeType, string> = {
    // ... existing labels
    'my-new-node-type': 'My New Node'
  };
  return labels[type] || type;
}
```

**That's it!** No code changes needed in App.tsx or other files.

## 🎨 Error Messages

Rule Engine provides clear, actionable error messages:

```
✅ Good Error Messages:
- "Worker can ONLY connect to Processor (found: repository)"
- "Processor can have maximum 1 outgoing connection(s)"
- "HTML Extractor has NO outgoing connections (input only)"
- "Connection would create a cycle"

❌ Bad Error Messages (old approach):
- "Invalid connection"
- "Cannot connect"
- "Error"
```

## 🚀 Performance

- **O(1)** for rule lookup (Map-based)
- **O(E)** for full flow validation (E = number of edges)
- **O(V + E)** for cycle detection (V = nodes, E = edges)

## 📝 Best Practices

1. **Always validate before adding edges**:
   ```typescript
   const result = connectionRuleEngine.validateConnection(...);
   if (result.isValid) {
     setEdges((eds) => addEdge(params, eds));
   }
   ```

2. **Show user-friendly error messages**:
   ```typescript
   if (!result.isValid) {
     alert(`⛔ Invalid Connection\n\n${result.error}`);
   }
   ```

3. **Clean invalid edges on import**:
   ```typescript
   const cleanEdges = cleanInvalidEdges(nodes, edges);
   setEdges(cleanEdges);
   ```

4. **Don't bypass validation**:
   ```typescript
   // ❌ BAD
   setEdges((eds) => addEdge(params, eds)); // No validation!
   
   // ✅ GOOD
   const result = connectionRuleEngine.validateConnection(...);
   if (result.isValid) {
     setEdges((eds) => addEdge(params, eds));
   }
   ```

## 🔗 Related Files

- `App.tsx` - Uses Rule Engine in `onConnect` and `importConfiguration`
- `llms.txt` - Architecture documentation
- `types.ts` - Node type definitions

## 📚 References

- **Design Pattern**: Strategy Pattern
- **Inspiration**: Rule Engine pattern, Declarative validation
- **TypeScript**: Union types, Type guards, Interfaces

---

**Created**: December 4, 2025  
**Pattern**: Strategy Pattern + Rule Engine  
**Status**: ✅ Production Ready

