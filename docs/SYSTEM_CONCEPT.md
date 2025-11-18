# CRAWLFLOW SYSTEM CONCEPT
**Phiên bản:** 2.0  
**Ngày cập nhật:** 2025  
**Kiến trúc:** Flow-Based Architecture

---

## 📋 MỤC LỤC

1. [Tổng quan](#1-tổng-quan)
2. [Kiến trúc Flow-Based](#2-kiến-trúc-flow-based)
3. [Node Types và Workflow](#3-node-types-và-workflow)
4. [Flow Execution Model](#4-flow-execution-model)
5. [Data Flow Pipeline](#5-data-flow-pipeline)
6. [Mapping với Rake Framework](#6-mapping-với-rake-framework)

---

## 1. TỔNG QUAN

CrawlFlow 2.0 sử dụng **Flow-Based Architecture** - một kiến trúc mới hoàn toàn dựa trên visual flow để xác định quy trình crawl và xử lý dữ liệu. Thay vì sử dụng cấu trúc cũ (Tooth, Reception, Parser, FeedItem), hệ thống mới sử dụng các **nodes** và **edges** để xây dựng flow xử lý dữ liệu.

### 1.1 Nguyên tắc thiết kế

- **Visual-First**: Mọi workflow được định nghĩa bằng visual flow editor
- **Node-Based**: Mỗi bước xử lý được đại diện bởi một node
- **Flow-Driven**: Dữ liệu chảy qua flow theo edges giữa các nodes
- **Modular**: Mỗi node có trách nhiệm rõ ràng và độc lập
- **Extensible**: Dễ dàng thêm node types mới

---

## 2. KIẾN TRÚC FLOW-BASED

### 2.1 Flow Structure

```
┌─────────────────────────────────────────────────────────────┐
│                    CRAWFLOW FLOW                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────┐      ┌──────────┐      ┌──────────┐         │
│  │  START   │─────▶│ PROCESS  │─────▶│COMPLETION│         │
│  │  NODE    │      │  NODES   │      │   NODE   │         │
│  └──────────┘      └──────────┘      └──────────┘         │
│       │                 │                                   │
│       │                 ▼                                   │
│       │            ┌──────────┐                            │
│       │            │EXTRACTOR │                            │
│       │            │  NODES   │                            │
│       │            └──────────┘                            │
│       │                 │                                   │
│       ▼                 ▼                                   │
│  ┌──────────┐      ┌──────────┐                            │
│  │REPOSITORY│◀─────│  WORKER  │                            │
│  │   NODE   │      │   NODE   │                            │
│  └──────────┘      └──────────┘                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Core Components

#### Flow Engine
- **Flow Executor**: Thực thi flow theo thứ tự nodes
- **Node Executor**: Thực thi từng node
- **Data Pipeline**: Quản lý data flow giữa các nodes
- **State Manager**: Quản lý state của flow execution

#### Node Registry
- Đăng ký và quản lý tất cả node types
- Cung cấp node factory để tạo node instances
- Validate node configuration

#### Edge Manager
- Quản lý connections giữa nodes
- Validate flow integrity
- Route data giữa nodes

---

## 3. NODE TYPES VÀ WORKFLOW

### 3.1 Start Node (Data Sources)

**Mục đích**: Entry point của flow, định nghĩa nguồn dữ liệu ban đầu

**Types**:
- **URL**: Crawl từ website
  - Settings: scope, exclude patterns, domain policy
- **API**: Lấy dữ liệu từ REST API
  - Settings: auth type, pagination
- **MySQL**: Kết nối database
  - Settings: connection details
- **CSV**: Import từ CSV file
  - Settings: file input method
- **JSON**: Import từ JSON
  - Settings: data handling, URL scanning
- **XML**: Import từ XML
  - Settings: URL scanning, domain policy

**Output**: URLs hoặc data items

**Rake Mapping**: Thay thế Tooth initialization, định nghĩa data source

---

### 3.2 Repository Node

**Mục đích**: Collect và lưu trữ tạm thời URLs/resources từ Start nodes

**Features**:
- Auto-created và unique trong flow
- Lưu trữ URLs đã crawl
- Cung cấp queue cho Worker nodes

**Output**: Repository với collection of resources

**Rake Mapping**: Tương đương Resource collection trong Rake

---

### 3.3 Click Node

**Mục đích**: Thực hiện click actions để navigate

**Settings**:
- CSS selector để click
- Wait time sau khi click
- Navigate behavior

**Input**: URL từ Start hoặc Repository
**Output**: URLs sau khi click/navigate

**Rake Mapping**: Navigation logic trong Tooth

---

### 3.4 Loop Node

**Mục đích**: Iterate qua một list of elements

**Settings**:
- Iterator selector (CSS selector)
- Loop behavior (sequential/parallel)

**Input**: URL với HTML content
**Output**: Multiple URLs hoặc data items (một cho mỗi element)

**Rake Mapping**: Iterator logic trong Parser

---

### 3.5 Reception Node (Reception Rules)

**Mục đích**: Filter và validate URLs/resources trước khi vào Repository

**Settings**:
- Reception rules (URL patterns, conditions)
- Logic (AND/OR)

**Input**: URLs từ Start hoặc Click nodes
**Output**: Filtered URLs

**Rake Mapping**: Tương đương ReceptionManager trong Rake cũ

---

### 3.6 Worker Node

**Mục đích**: Detect và process specific pages/resources

**Settings**:
- Detection rules (URL format, HTML contains, DOM value, Tag attribute, Data source type)
- Detection logic (AND/OR)
- Priority

**Input**: Repository (URLs)
**Output**: Trigger Extractor nodes

**Rake Mapping**: ResourceWorker trong Rake cũ

---

### 3.7 Extractor Nodes

**Mục đích**: Extract structured data từ content

**Types**:

#### HTML Data Extractor
- Extract từ HTML elements
- Support: text, attribute, regex, HTML
- Presets: WooCommerce, Blog Post, SEO, Open Graph
- Custom rules với selector inspector

#### CSV Extractor
- Parse CSV files
- Column mappings
- Header detection

#### JSON Extractor
- Parse JSON data
- JSONPath mappings
- URL scanning từ JSON

#### XML Extractor
- Parse XML files
- XPath mappings

#### MySQL Extractor
- Query database
- Column mappings

**Input**: Content từ Worker node
**Output**: Structured data items

**Rake Mapping**: Tương đương ParserManager và FeedItemBuilderManager

---

### 3.8 Processor Node

**Mục đích**: Process và output extracted data

**Types**:

#### Save to Database
- Connection: MySQL, PostgreSQL
- Table mapping
- Conflict strategy: insert, upsert, skip

#### Send to API
- Endpoint URL
- Method: POST, PUT, PATCH
- Authentication
- Custom headers

#### Generate CSV File
- File name
- Delimiter
- Include header

#### Send Email Notification
- Recipients
- Subject
- Body template

**Input**: Extracted data từ Extractor nodes
**Output**: Processed results

**Rake Mapping**: Tương đương ProcessorManager

---

### 3.9 Completion Node

**Mục đích**: End point của flow, đánh dấu flow hoàn thành

**Features**:
- Auto-created (optional)
- Trigger cleanup actions
- Generate reports

---

### 3.10 Shape Nodes (Visual Only)

**Mục đích**: Diagram elements để visualize flow structure

**Types**: Rectangle, Circle, Ellipse, Frame, Package

**Note**: Không tham gia vào flow execution, chỉ để visualization

---

## 4. FLOW EXECUTION MODEL

### 4.1 Execution Stages

```
┌─────────────────────────────────────────────────────────┐
│              FLOW EXECUTION PIPELINE                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  1. INITIALIZATION                                     │
│     ├─ Load flow configuration                         │
│     ├─ Validate flow integrity                         │
│     └─ Initialize Flow Engine                          │
│                                                         │
│  2. START PHASE                                        │
│     ├─ Execute Start Nodes                             │
│     ├─ Collect URLs/resources                          │
│     └─ Populate Repository                             │
│                                                         │
│  3. RECEPTION PHASE                                    │
│     ├─ Apply Reception Rules                           │
│     ├─ Filter URLs                                     │
│     └─ Update Repository                               │
│                                                         │
│  4. NAVIGATION PHASE                                   │
│     ├─ Execute Click Nodes                             │
│     ├─ Execute Loop Nodes                              │
│     └─ Collect new URLs                                │
│                                                         │
│  5. WORKER PHASE                                       │
│     ├─ Worker detects matching resources               │
│     ├─ Trigger Extractor Nodes                         │
│     └─ Extract data                                    │
│                                                         │
│  6. PROCESSING PHASE                                   │
│     ├─ Execute Processor Nodes                         │
│     ├─ Save/Transform data                             │
│     └─ Generate outputs                                │
│                                                         │
│  7. COMPLETION                                         │
│     ├─ Cleanup resources                               │
│     ├─ Generate reports                                │
│     └─ Update flow status                              │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 4.2 Data Flow

```
Start Node
    │
    ├─ URLs ────────────────────┐
    │                           │
    ▼                           ▼
Reception Node          Click/Loop Node
    │                           │
    ├─ Filtered URLs ───────────┤
    │                           │
    ▼                           ▼
Repository ─────────────────────┘
    │
    ▼
Worker Node
    │
    ├─ Detect matching ─────────┐
    │                           │
    ▼                           ▼
Extractor Node ────────────────┐
    │                           │
    ├─ Extracted Data ──────────┤
    │                           │
    ▼                           ▼
Processor Node
    │
    ▼
Completion Node
```

### 4.3 Parallel Execution

- Multiple Start nodes: Chạy song song
- Multiple Worker nodes: Xử lý song song từ Repository
- Multiple Extractor nodes: Extract song song cho mỗi Worker
- Processor Chain: Xử lý tuần tự theo edges

---

## 5. DATA FLOW PIPELINE

### 5.1 Data Types

#### Resource
```php
[
    'id' => string,
    'url' => string,
    'type' => 'html' | 'api' | 'file',
    'content' => string | array,
    'metadata' => array,
    'source_node_id' => string,
    'timestamp' => datetime
]
```

#### Extracted Data Item
```php
[
    'id' => string,
    'resource_id' => string,
    'data' => array, // Structured data
    'extractor_id' => string,
    'timestamp' => datetime
]
```

#### Processed Result
```php
[
    'id' => string,
    'data_item_id' => string,
    'processor_id' => string,
    'status' => 'success' | 'failed',
    'result' => mixed,
    'timestamp' => datetime
]
```

### 5.2 Pipeline Stages

1. **Source Stage**: Start nodes tạo resources
2. **Filter Stage**: Reception nodes filter resources
3. **Navigation Stage**: Click/Loop nodes tạo thêm resources
4. **Detection Stage**: Worker nodes detect relevant resources
5. **Extraction Stage**: Extractor nodes extract data
6. **Processing Stage**: Processor nodes process data
7. **Output Stage**: Completion node finalizes

---

## 6. MAPPING VỚI RAKE FRAMEWORK

### 6.1 Legacy Rake Components → New Flow Nodes

| Rake Component | Flow Node | Notes |
|----------------|-----------|-------|
| Tooth | Start Node | Entry point định nghĩa data source |
| ReceptionManager | Reception Node | Filter rules |
| ParserManager | Extractor Nodes | Parse content thành structured data |
| FeedItemBuilderManager | Extractor Nodes | Build data items |
| FeedItemManager | Repository Node | Manage collected items |
| ProcessorManager | Processor Node | Process data |
| ResourceWorker | Worker Node | Detect và process resources |
| Resource | Repository Node | Resource collection |

### 6.2 Migration Path

#### Old Configuration
```php
$tooth = new Tooth();
$tooth->registerReception($receptionRules);
$tooth->registerParser($parserRules);
$tooth->registerProcessor($processorRules);
```

#### New Flow Configuration
```json
{
    "nodes": [
        {
            "id": "start-1",
            "type": "start",
            "data": {
                "sourceType": "url",
                "sourceValue": "https://example.com",
                "urlSettings": { ... }
            }
        },
        {
            "id": "reception-1",
            "type": "reception",
            "data": {
                "rules": [ ... ],
                "logic": "and"
            }
        },
        {
            "id": "worker-1",
            "type": "worker",
            "data": {
                "detectionRules": [ ... ],
                "detectionLogic": "and"
            }
        },
        {
            "id": "extractor-1",
            "type": "html-data-extractor",
            "data": {
                "customRules": [ ... ]
            }
        },
        {
            "id": "processor-1",
            "type": "processor",
            "data": {
                "processorType": "save-to-database",
                "settings": { ... }
            }
        }
    ],
    "edges": [
        { "source": "start-1", "target": "reception-1" },
        { "source": "reception-1", "target": "repository-node" },
        { "source": "repository-node", "target": "worker-1" },
        { "source": "worker-1", "target": "extractor-1" },
        { "source": "extractor-1", "target": "processor-1" }
    ]
}
```

### 6.3 Backward Compatibility

Hệ thống mới vẫn có thể load và convert configurations cũ:
- Convert Tooth config → Flow nodes
- Convert Reception rules → Reception node
- Convert Parser rules → Extractor nodes
- Convert Processor rules → Processor node

---

## 7. IMPLEMENTATION ARCHITECTURE

### 7.1 Flow Executor

```php
class FlowExecutor
{
    public function execute(FlowConfig $flow): ExecutionResult
    {
        // 1. Initialize
        $context = new ExecutionContext($flow);
        
        // 2. Execute Start nodes
        $this->executeStartNodes($context);
        
        // 3. Execute Reception nodes
        $this->executeReceptionNodes($context);
        
        // 4. Execute Navigation nodes
        $this->executeNavigationNodes($context);
        
        // 5. Execute Worker nodes
        $this->executeWorkerNodes($context);
        
        // 6. Execute Extractor nodes
        $this->executeExtractorNodes($context);
        
        // 7. Execute Processor nodes
        $this->executeProcessorNodes($context);
        
        // 8. Complete
        $this->complete($context);
        
        return $context->getResult();
    }
}
```

### 7.2 Node Executor Interface

```php
interface NodeExecutorInterface
{
    public function execute(NodeConfig $node, ExecutionContext $context): NodeResult;
    
    public function supports(string $nodeType): bool;
}
```

### 7.3 Node Registry

```php
class NodeRegistry
{
    private array $executors = [];
    
    public function register(string $nodeType, NodeExecutorInterface $executor): void
    {
        $this->executors[$nodeType] = $executor;
    }
    
    public function getExecutor(string $nodeType): NodeExecutorInterface
    {
        if (!isset($this->executors[$nodeType])) {
            throw new NodeExecutorNotFoundException($nodeType);
        }
        
        return $this->executors[$nodeType];
    }
}
```

---

## 8. ADVANTAGES OF NEW ARCHITECTURE

### 8.1 Flexibility
- Dễ dàng tạo flow phức tạp với visual editor
- Không bị giới hạn bởi structure cũ
- Có thể combine nhiều data sources

### 8.2 Maintainability
- Clear separation of concerns
- Mỗi node type có responsibility rõ ràng
- Dễ test từng node type

### 8.3 Extensibility
- Dễ dàng thêm node types mới
- Plugin system cho custom nodes
- Reusable node components

### 8.4 User Experience
- Visual flow editor dễ sử dụng
- Real-time validation
- Preview và testing trong editor

---

## 9. FUTURE ENHANCEMENTS

- **Conditional Branches**: Support if/else logic trong flow
- **Parallel Processing**: Automatic parallelization
- **Error Handling**: Built-in error handling nodes
- **Loop Control**: Advanced loop control (break, continue)
- **Sub-Flows**: Reusable sub-flow components
- **Flow Templates**: Pre-built flow templates
- **Version Control**: Flow versioning và rollback
- **A/B Testing**: Test different flow configurations

---

## 10. CONCLUSION

Flow-Based Architecture mang lại sự linh hoạt và mạnh mẽ cho CrawlFlow 2.0. Thay vì bị giới hạn bởi cấu trúc cũ (Tooth, Reception, Parser), giờ đây users có thể tạo bất kỳ workflow nào họ muốn thông qua visual editor.

Hệ thống mới vẫn tương thích với Rake framework core (HttpClient, DatabaseDriver, EventBus) nhưng sử dụng flow-based approach để orchestrate các components này.

