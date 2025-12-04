# NODE CONNECTION RULES - STRICT ENFORCEMENT

**Last Updated**: December 4, 2025  
**Status**: ✅ Enforced in UI

---

## 🎯 STRICT CONNECTION RULES

These rules are **MANDATORY** and enforced by validation in `App.tsx`:

### Rule 1: Data Source → Repository ONLY
```
✅ ALLOWED:   [Data Source] ────→ [Repository]
⛔ FORBIDDEN: [Data Source] ────→ [Worker]
⛔ FORBIDDEN: [Data Source] ────→ [Processor]
⛔ FORBIDDEN: [Data Source] ────→ [Any other node]
```

**Validation**: Line ~350 in App.tsx

### Rule 2: Repository → Worker ONLY
```
✅ ALLOWED:   [Repository] ────→ [Worker]
⛔ FORBIDDEN: [Repository] ────→ [Processor]
⛔ FORBIDDEN: [Repository] ────→ [Data Source]
⛔ FORBIDDEN: [Repository] ────→ [Any other node]
```

**Validation**: Line ~355 in App.tsx  
**Note**: Only ONE Repository per project

### Rule 3: Data Extractor → Worker ONLY
```
✅ ALLOWED:   [HTML Extractor] ────→ [Worker]
✅ ALLOWED:   [CSV Extractor]  ────→ [Worker]
⛔ FORBIDDEN: [Extractor] ────→ [Processor]
⛔ FORBIDDEN: [Extractor] ────→ [Repository]
```

**Validation**: Line ~360 in App.tsx  
**Limit**: ONE extractor per worker

### Rule 4: Worker → Processor ONLY
```
✅ ALLOWED:   [Worker] ────→ [Processor]
⛔ FORBIDDEN: [Worker] ────→ [Worker]
⛔ FORBIDDEN: [Worker] ────→ [Repository]
⛔ FORBIDDEN: [Worker] ────→ [Data Source]
```

**Validation**: Line ~365 in App.tsx

### Rule 5: Processor → Processor (Chain) OR Finish
```
✅ ALLOWED:   [Processor] ────→ [Processor] ────→ [Processor]
✅ ALLOWED:   [Processor] ────→ [Finish Action]
⛔ FORBIDDEN: [Processor] ────→ [Worker]
⛔ FORBIDDEN: [Processor] ────→ [Repository]
⛔ FORBIDDEN: [Processor] ────→ [Data Source]
```

**Validation**: Line ~370 in App.tsx

---

## 📊 VALID FLOW EXAMPLES

### Example 1: Simple Flow
```
[URL Source] ──→ [Repository] ──→ [Worker] ──→ [Processor 1] → Finish
                                      ↑
                                      │
                              [HTML Extractor]
```

### Example 2: Multiple Sources, Multiple Workers
```
[URL 1] ─┐
[URL 2] ─┼─→ [Repository] ─┬─→ [Worker 1] ─→ [Save to DB] → Finish
[API]   ─┘                  │         ↑
                             │         └─ [HTML Extractor]
                             │
                             └─→ [Worker 2] ─→ [Transform] ─→ [Send to API] → Finish
                                      ↑
                                      └─ [CSV Extractor]
```

### Example 3: Processor Chain
```
[CSV] ──→ [Repository] ──→ [Worker] ──→ [Validate] ──→ [Transform] ──→ [Save] → Finish
                                ↑
                                └─ [CSV Extractor]
```

---

## ⛔ INVALID FLOWS (REJECTED BY UI)

### Invalid 1: Skip Repository
```
❌ [Data Source] ──→ [Worker]  // REJECTED: Must go through Repository
```

### Invalid 2: Worker to Worker
```
❌ [Worker 1] ──→ [Worker 2]  // REJECTED: Workers cannot chain
```

### Invalid 3: Processor to Worker
```
❌ [Processor] ──→ [Worker]  // REJECTED: Cannot go backwards
```

### Invalid 4: Multiple Extractors per Worker
```
❌ [HTML Extractor] ─┐
                      ├─→ [Worker]  // REJECTED: Only ONE extractor per worker
   [CSV Extractor]  ─┘
```

### Invalid 5: Data Source to Processor
```
❌ [Data Source] ──→ [Processor]  // REJECTED: Must process through Worker
```

---

## 🔧 IMPLEMENTATION DETAILS

### Location
File: `wp-content/plugins/wp-crawlflow/assets/js/crawflow-ui/App.tsx`

### Key Functions

#### 1. `onConnect` (Line ~341-395)
Validates ALL manual connections made by dragging edges in the UI.

```typescript
const onConnect = useCallback((params: Edge | Connection) => {
  const sourceNode = nodes.find(n => n.id === params.source);
  const targetNode = nodes.find(n => n.id === params.target);

  // 7 strict rules enforced here
  // Each rule shows warning and returns if violated
  
  setEdges((eds) => addEdge(params, eds));
}, [nodes, edges, setEdges]);
```

#### 2. `addNode` - Worker Creation (Line ~514-547)
Automatically connects new Workers to Repository.

```typescript
if (type === 'worker') {
  const repositoryNode = nodes.find(n => n.id === REPOSITORY_NODE_ID);
  
  if (!repositoryNode) {
    alert("⛔ Cannot create Worker: Repository must exist first");
    return;
  }

  // Always connect from Repository to Worker
  const repoToWorkerEdge: Edge = {
    source: REPOSITORY_NODE_ID,
    target: newNodeId,
  };
  
  setEdges((eds) => addEdge(repoToWorkerEdge, eds));
}
```

#### 3. `addNode` - Processor Creation (Line ~579-592)
Validates that Processor is created from Worker or another Processor.

```typescript
if (type === 'processor') {
  if (!sourceNode || 
      (sourceNode.type !== 'worker' && sourceNode.type !== 'processor')) {
    alert("⛔ Cannot create Processor: Must connect from Worker or Processor");
    return;
  }
}
```

---

## 🧪 TESTING

### Manual Test Cases

1. **Test: Data Source → Worker** (should fail)
   - Add URL source
   - Try to connect directly to Worker
   - Expected: Warning message + connection rejected

2. **Test: Repository → Processor** (should fail)
   - Try to connect Repository to Processor
   - Expected: Warning message + connection rejected

3. **Test: Worker with 2 Extractors** (should fail)
   - Connect HTML Extractor to Worker
   - Try to connect CSV Extractor to same Worker
   - Expected: Warning message + connection rejected

4. **Test: Valid Flow** (should succeed)
   - Add URL Source → auto-connects to Repository
   - Add Worker → auto-connects from Repository
   - Add HTML Extractor → connect to Worker
   - Add Processor → connect from Worker
   - Expected: All connections succeed

---

## 📝 ERROR MESSAGES

All error messages use ⛔ emoji for visibility:

- "⛔ Connection prevented: Data Source can ONLY connect to Raw Items Repository."
- "⛔ Connection prevented: Raw Items Repository can ONLY connect to Worker nodes."
- "⛔ Connection prevented: Data Extractor can ONLY connect to Worker nodes."
- "⛔ Connection prevented: Worker can ONLY connect to Processor nodes."
- "⛔ Connection prevented: Processor can ONLY connect to another Processor."
- "⛔ Connection prevented: Worker can only have ONE Data Extractor."
- "⛔ Cannot create Worker: Raw Items Repository must exist first."
- "⛔ Cannot create Processor: Must be connected from Worker or Processor."

---

## 🎯 KEY CONSTANTS

```typescript
const REPOSITORY_NODE_ID = 'repository';
const EXTRACTOR_NODE_TYPES = [
  'html-data-extractor',
  'csv-extractor', 
  'json-extractor',
  'xml-extractor',
  'mysql-extractor'
];
```

---

## 📚 RELATED DOCUMENTATION

- `llms.txt` - Complete architecture documentation (updated with connection rules)
- `types.ts` - Node data type definitions
- `Sidebar.tsx` - Node creation UI

---

**Rules Status**: ✅ **FULLY ENFORCED**  
**UI Protection**: ✅ **ACTIVE**  
**User Experience**: Prevents invalid workflows with clear error messages

