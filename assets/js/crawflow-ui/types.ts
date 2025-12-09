
// FIX: The content for this file was missing. These are the type definitions for the application.
import type { Node, NodeProps } from 'reactflow';
import type { ReactNode } from 'react';

export type DataSourceType = 'url' | 'api' | 'xml' | 'csv' | 'json' | 'mysql';
export type FileInputMethod = 'paste' | 'upload' | 'cloudUrl';

export interface MySQLConnection {
  host?: string;
  port?: string;
  user?: string;
  password?: string;
  database?: string;
}

export interface ProjectSettings {
  name: string;
  description: string;
  enabled: boolean;
  crawlDelay: number;
  userAgent: string;
  concurrency: number;
  httpClient?: string; // HTTP client name from registry
}


// --- NEW TYPES FOR DATA SOURCE SETTINGS ---

export type CrawlScope = 'current-url' | 'entire-website';
export type DomainImportPolicy = 'all' | 'whitelist-only';

export interface URLSourceSettings {
  scope: CrawlScope;
  excludeExtensions: string[];
  excludePatterns: string[];
  whitelistPatterns: string[];
  domainPolicy: DomainImportPolicy;
  domainWhitelist: string[];
}

export type APIAuthType = 'none' | 'api-key' | 'bearer' | 'basic';
export type APIKeyLocation = 'header' | 'query';

export interface APIKeyAuth {
  location: APIKeyLocation;
  keyName: string;
  keyValue: string;
}

export interface BearerTokenAuth {
  token: string;
}

export interface BasicAuth {
  username: string;
  password: string;
}

export type APIPaginationType = 'none' | 'page' | 'offset-limit' | 'next-url';

export interface PagePagination {
    paramName: string;
    startsAt: number;
}

export interface OffsetLimitPagination {
    offsetParam: string;
    limitParam: string;
    limitValue: number;
    startsAt: number;
}

export interface NextURLPagination {
    jsonPath: string; // Path to the next URL in the response
}

export interface APISourceSettings {
  authType: APIAuthType;
  authDetails: APIKeyAuth | BearerTokenAuth | BasicAuth | {};
  paginationType: APIPaginationType;
  paginationDetails: PagePagination | OffsetLimitPagination | NextURLPagination | {};
}

export interface XMLSourceSettings {
  scanUrls: boolean;
  domainPolicy: DomainImportPolicy;
  domainWhitelist: string[];
}

export type JSONDataHandling = 'raw' | 'scan-urls';
export type JSONURLSource = 'all-values' | 'specific-key';

export interface JSONSourceSettings {
  dataHandling: JSONDataHandling;
  urlSource?: JSONURLSource;
  urlKey?: string;
  domainPolicy?: DomainImportPolicy;
  domainWhitelist?: string[];
}

export interface StartNodeData {
  sourceType: DataSourceType;
  sourceValue: string | MySQLConnection;
  inputMethod?: FileInputMethod; // Relevant for xml, csv, json
  fileName?: string; // For file uploads
  
  // New detailed settings
  urlSettings?: URLSourceSettings;
  apiSettings?: APISourceSettings;
  xmlSettings?: XMLSourceSettings;
  jsonSettings?: JSONSourceSettings;
  
  // Phase 1 actions to execute
  phase1Actions?: string[]; // Array of action IDs
}
// --- END OF NEW TYPES ---


export interface ClickNodeData {
  selector: string;
}

export type ExtractFrom = 'html-element' | 'json-ld' | 'html-comment';

export interface ExtractionRule {
    id: string;
    name: string;
    extractFrom: ExtractFrom;
    // For 'html-element'
    selector?: string;
    extract?: 'text' | 'attribute' | 'regex' | 'html';
    attribute?: string; // e.g., 'href', 'src', 'content'
    regexPattern?: string;
    regexGroup?: number;
    extractMultiple?: boolean;
    // For 'json-ld' and 'html-comment'
    jsonPath?: string;
}


export interface LoopNodeData {
  iteratorSelector: string;
}

export interface RepositoryNodeData {
  // This node serves as a structural element and may not need specific data.
}

// FIX: Added missing ReceptionNodeData and ReceptionRule types to resolve import errors.
export interface ReceptionRule {
    id: string;
    // The exact properties are unknown as this feature seems incomplete.
    // Defining `id` is a safe assumption based on other rule types.
}

export interface ReceptionNodeData {
    rules: ReceptionRule[];
    logic: 'and' | 'or';
}

export type RuleCondition = 'exists' | 'not-exists' | 'contains' | 'not-contains' | 'matches-regex';

export type PresetType = 'woocommerce-product' | 'blog-post' | 'seo-metadata' | 'open-graph';

export interface HTMLDataExtractorNodeData {
    presets: PresetType[];
    customRules: ExtractionRule[];
    // Inspector related fields
    inspectorUrl?: string;
    inspectorHtmlContent?: string;
    inspectorLoading?: boolean;
    inspectorError?: string;
}

// --- NEW EXTRACTOR TYPES ---
export interface ColumnMapping {
    id: string;
    source: string; // Column Index for CSV, Column Name for MySQL
    fieldName: string;
}

export interface CSVExtractorNodeData {
    presets: string[];
    mappings: ColumnMapping[];
    hasHeader: boolean;
}

export interface PathMapping {
    id: string;
    path: string; // JSONPath or XPath
    fieldName: string;
}

export interface JSONExtractorNodeData {
    presets: string[];
    mappings: PathMapping[];
}

export interface XMLExtractorNodeData {
    presets: string[];
    mappings: PathMapping[];
}

export interface MySQLExtractorNodeData {
    presets: string[];
    mappings: ColumnMapping[];
}


// --- PROCESSOR SETTINGS ---
export interface SaveToDbSettings {
    connectionType: 'mysql' | 'postgresql';
    host?: string;
    port?: string;
    user?: string;
    password?: string;
    database?: string;
    tableName?: string;
    conflictStrategy: 'insert' | 'upsert' | 'skip';
}

export interface SendToApiSettings {
    endpointUrl: string;
    method: 'POST' | 'PUT' | 'PATCH';
    authType: 'none' | 'api-key' | 'bearer' | 'basic';
    authDetails: APIKeyAuth | BearerTokenAuth | BasicAuth | {};
    headers: { id: string; key: string; value: string }[];
}

export interface GenerateCsvSettings {
    fileName: string; 
    delimiter: ',' | ';' | '\t';
    includeHeader: boolean;
}

export interface SendEmailSettings {
    recipients: string; // comma-separated
    subject: string;
    body: string;
}

// Field Mapping for processors
export interface FieldMapping {
  [extractedField: string]: string; // extractedField => targetField
}

// Base processor settings with field mapping support
export interface BaseProcessorSettings {
  autoMapFields?: boolean;
  fieldMappings?: FieldMapping;
}

// Extended settings types
export interface SaveToDbSettingsWithMapping extends SaveToDbSettings, BaseProcessorSettings {}
export interface SendToApiSettingsWithMapping extends SendToApiSettings, BaseProcessorSettings {}
export interface GenerateCsvSettingsWithMapping extends GenerateCsvSettings, BaseProcessorSettings {}

// Discriminated Union for ProcessorNodeData
export type ProcessorNodeData = 
    | { processorType: 'save_to_database' | 'save-to-database'; settings: SaveToDbSettingsWithMapping; }
    | { processorType: 'send_to_api' | 'send-to-api'; settings: SendToApiSettingsWithMapping; }
    | { processorType: 'generate_csv_file' | 'generate-csv-file'; settings: GenerateCsvSettingsWithMapping; }
    | { processorType: 'send_email_notification' | 'send-email-notification'; settings: SendEmailSettings; }
    | { processorType: 'save_to_wordpress'; settings: any; };


export type WorkerRuleType = 'url-format' | 'html-contains' | 'dom-value' | 'tag-attribute' | 'data-source-type';

export interface BaseWorkerRule {
    id: string;
    type: WorkerRuleType;
}

export interface URLFormatRule extends BaseWorkerRule {
    type: 'url-format';
    pattern: string;
}

export interface HTMLContainsRule extends BaseWorkerRule {
    type: 'html-contains';
    text: string;
}

export interface DOMValueRule extends BaseWorkerRule {
    type: 'dom-value';
    selector: string;
    condition: RuleCondition;
    value: string;
}

export interface TagAttributeRule extends BaseWorkerRule {
    type: 'tag-attribute';
    selector: string;
    attribute: string;
    condition: RuleCondition;
    value: string;
}

export interface DataSourceTypeRule extends BaseWorkerRule {
    type: 'data-source-type';
    sourceType: DataSourceType;
}

export type WorkerRule = URLFormatRule | HTMLContainsRule | DOMValueRule | TagAttributeRule | DataSourceTypeRule;

export interface WorkerNodeData {
  detectionRules: WorkerRule[];
  detectionLogic: 'and' | 'or';
  priority: number;
  isArchive?: boolean; // Flag indicating if this worker handles archive pages (category pages, listing pages, etc.)
}

// FIX: Added CompletionNodeData interface to fix import error.
export interface CompletionNodeData {
    // This node marks the end of a flow, no specific data is needed.
}

// --- DIAGRAM SHAPE NODES ---
export type ShapeType = 'rectangle' | 'circle' | 'ellipse' | 'frame' | 'package';

export interface ShapeNodeData {
  shapeType: ShapeType;
  label: string;
  width: number;
  height: number;
  backgroundColor: string;
  borderColor: string;
  textColor: string;
}


// FIX: Added ReceptionNodeData to the NodeData union type.
// FIX: Added CompletionNodeData to the NodeData union type to resolve import error.
export type NodeData = 
    | StartNodeData 
    | ClickNodeData 
    | LoopNodeData 
    | RepositoryNodeData 
    | ReceptionNodeData 
    | WorkerNodeData 
    | HTMLDataExtractorNodeData 
    | CSVExtractorNodeData
    | JSONExtractorNodeData
    | XMLExtractorNodeData
    | MySQLExtractorNodeData
    | ProcessorNodeData
    | CompletionNodeData
    | ShapeNodeData;


export type CustomNodeProps<T = NodeData> = NodeProps<T> & {
    title?: ReactNode;
};

export interface CustomNode extends Node<NodeData> {
    data: NodeData;
}