# RAKE FLOW ARCHITECTURE STATUS

## 🔍 ANALYSIS

Plugin hiện tại đang có **2 kiến trúc song song**:

### 1. Flow-Based Architecture (Hiện tại, đã implement)
```
Start Node → Repository → Worker Node → Extractor Node → Processor Node → Completion
```
- ✅ Implemented
- ✅ Tested  
- ✅ Working với React Flow UI

### 2. Rake Pattern Architecture (Cần implement đầy đủ)
```
Tooth → DataSources → DataOrigins → Reception → Workers → Parsers → Processors → Finish
```
- ⚠️ Partially implemented
- ⚠️ Needs: Reception, Worker với detection rules
- ⚠️ Needs: Integration test

---

## 📊 CURRENT STATUS

### Tables ✅
```
✓ rake_tooths (projects)
✓ rake_data_sources  
✓ rake_data_origins (raw items repository)
```

### Components

#### Implemented ✅
- `HtmlDataExtractor` (implements ParserInterface)
- `WordPressPostProcessor` (implements ProcessorInterface)
- `WordPressDatabaseAdapter` (for database operations)

#### Needs Implementation ⚠️
- `Reception` (implements ReceptionInterface)
- `Worker` (with detection rules)
- Integration between Reception → Worker → Parser → Processor

---

## 🔄 REQUIRED FLOW

### Step-by-Step:

1. **Load Tooth Config** (`rake_tooths`)
   - ✅ Can load project
   - ✅ Config stored as JSON
   
2. **Init Data Sources** (`rake_data_sources`)
   - ✅ Table exists
   - ✅ Can insert sources
   - Reference to tooth_id

3. **Fetch & Store Raw Data** (`rake_data_origins`)
   - ✅ Table exists
   - ✅ Can insert raw items
   - All data types stored here (URL, CSV, XML, MySQL rows)

4. **Reception Processes Items** ⚠️
   - ⚠️ NEEDS: Reception class
   - Gets raw items from `rake_data_origins`
   - Loads workers from project config
   - Assigns items to appropriate workers

5. **Worker Processes Item** ⚠️
   - ⚠️ NEEDS: Worker class
   - Check detection rules
   - Extract data via Parser
   - Send to Processor chain

6. **Processor Chain** ✅
   - ✅ WordPressPostProcessor implemented
   - Processes data sequentially
   - Each can transform data

7. **Finish Actions** ⚠️
   - ⚠️ NEEDS: Finish handler
   - Log results
   - Update status
   - Send notifications

---

## 🎯 RECOMMENDATION

### Option 1: Implement Rake Pattern Fully ⭐
Implement Reception + Worker để có đầy đủ 2 architectures:
- Flow-Based (UI-driven)
- Rake Pattern (programmatic)

### Option 2: Use Flow-Based Only
Continue với Flow-Based architecture hiện tại:
- Đã tested
- Đã working
- UI support

### Option 3: Hybrid Approach ⭐⭐⭐
Map Flow-Based nodes to Rake pattern:
- StartNode → DataSource
- WorkerNode → Reception + Worker
- ExtractorNode → Parser
- ProcessorNode → Processor

---

## 📝 NEXT IMPLEMENTATION TASKS

Nếu chọn Option 1 (Full Rake Pattern):

### 1. Implement Reception
```php
class Reception implements ReceptionInterface {
    public function loadWorkers(array $config);
    public function processRawItems(array $items);
    public function assignToWorker($item, array $workers);
}
```

### 2. Implement Worker
```php
class Worker {
    private array $detectionRules;
    private int $priority;
    
    public function canHandle($item): bool;
    public function process($item): ExtractedData;
}
```

### 3. Integrate Flow
```php
$reception = new Reception();
$workers = $reception->loadWorkers($config);
$rawItems = $dataOriginRepository->getItemsByProject($projectId);

foreach ($rawItems as $item) {
    $worker = $reception->assignToWorker($item, $workers);
    $extracted = $worker->extract($item);
    $processed = $processorChain->process($extracted);
}
```

### 4. Test End-to-End
```bash
php tests/test-rake-complete-flow.php
```

---

## 💡 CURRENT vs TARGET

### Current (Flow-Based) ✅
```
User designs flow in UI
→ Saves as nodes & edges
→ Flow executor runs nodes
→ Works, but UI-dependent
```

### Target (Rake Pattern) ⚠️
```
Project config defines:
→ Data sources
→ Reception rules  
→ Workers (with detection)
→ Processor chain
→ Programmatic, flexible
```

### Best: Hybrid ⭐
```
UI creates flow
→ Maps to Rake components
→ Executes via Rake pattern
→ Best of both worlds
```

---

## 📊 IMPLEMENTATION ESTIMATE

| Task | Complexity | Time | Status |
|------|------------|------|--------|
| Reception class | Medium | 2-3h | ⚠️ Needed |
| Worker class | Medium | 2-3h | ⚠️ Needed |
| Detection rules | Medium | 1-2h | ⚠️ Needed |
| Integration | High | 3-4h | ⚠️ Needed |
| Testing | Medium | 2-3h | ⚠️ Needed |
| **Total** | **High** | **10-15h** | **25% done** |

---

## ✅ WHAT'S WORKING NOW

- Tables structure correct
- Can load projects
- Can insert data sources
- Can insert raw origins
- Parser & Processor implemented
- Tests for current flow passing

## ⚠️ WHAT'S MISSING

- Reception implementation
- Worker implementation  
- Detection rule engine
- Reception ↔ Worker integration
- Complete flow test

---

**Status**: ⚠️ **PARTIAL IMPLEMENTATION**  
**Recommendation**: Implement Reception + Worker để hoàn chỉnh Rake pattern

Bạn có muốn tôi tiếp tục implement Reception và Worker không?

