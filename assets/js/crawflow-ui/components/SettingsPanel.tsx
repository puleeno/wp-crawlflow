// FIX: The content for this file was missing. This is the implementation for the SettingsPanel component.
// FIX: Corrected the import statement to include useState, useRef, ChangeEvent, and useEffect. The original line had a syntax error.
import React, { useState, useRef, ChangeEvent, useEffect, useMemo } from 'react';
import { Node } from 'reactflow';
import { XMarkIcon, Cog6ToothIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, TrashIcon, DocumentMagnifyingGlassIcon, ChevronDownIcon, ChevronUpIcon, CursorArrowRaysIcon, SquareIcon, CircleIcon, FrameIcon, FolderIcon, EllipseIcon } from './icons';
import { NodeData, StartNodeData, ClickNodeData, ExtractionRule, DataSourceType, FileInputMethod, MySQLConnection, ProjectSettings, LoopNodeData, WorkerNodeData, HTMLDataExtractorNodeData, ProcessorNodeData, WorkerRule, WorkerRuleType, URLFormatRule, HTMLContainsRule, DOMValueRule, TagAttributeRule, DataSourceTypeRule, ExtractFrom, URLSourceSettings, APISourceSettings, APIKeyAuth, BearerTokenAuth, BasicAuth, XMLSourceSettings, JSONSourceSettings, PagePagination, OffsetLimitPagination, NextURLPagination, RuleCondition, SaveToDbSettings, SendToApiSettings, GenerateCsvSettings, SendEmailSettings, CSVExtractorNodeData, ColumnMapping, JSONExtractorNodeData, PathMapping, XMLExtractorNodeData, MySQLExtractorNodeData, ShapeNodeData, ShapeType } from '../types';
import { PRESETS, PROCESSORS } from '../presets';


interface SettingsPanelProps {
  node: Node | null;
  onUpdateNode: (nodeId: string, data: NodeData) => void;
  onDeleteNode: (nodeId: string) => void;
  onClose: () => void;
  projectSettings: ProjectSettings;
  onUpdateProjectSettings: (update: Partial<ProjectSettings>) => void;
  onExport: () => void;
  onImport: (event: React.ChangeEvent<HTMLInputElement>) => void;
  isOpen: boolean;
  // Inspector-related props
  onShowInspector: (htmlContent: string) => void;
  onHideInspector: () => void;
  onStartPicking: (nodeId: string, ruleId: string) => void;
  onStopPicking: () => void;
  pickingRuleId: string | null;
  onInspectSelector: (selector: string | null) => void;
  highlightedSelector: string | null;
  onAddShapeNode: (shapeType: ShapeType) => void;
}

const commonInputClasses = "w-full p-2 bg-white text-gray-900 border border-slate-300 rounded-md shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-slate-100 disabled:text-gray-500";
const smallInputClasses = "w-full p-1.5 text-sm bg-white text-gray-900 border border-slate-300 rounded-md shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500";
const commonLabelClasses = "block text-sm font-medium text-gray-700 mb-1";
const commonButtonClasses = "w-full p-2 rounded-md font-semibold text-white transition-colors";
const smallButtonClasses = "px-2.5 py-1.5 text-sm rounded-md font-semibold text-white transition-colors";

// FIX: 'useState' was not defined. It is now imported from React.
const CollapsibleSection: React.FC<{ title: string; children: React.ReactNode; defaultOpen?: boolean }> = ({ title, children, defaultOpen = false }) => {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-slate-200">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex justify-between items-center p-3 text-left font-semibold text-gray-800 hover:bg-slate-50"
            >
                <span>{title}</span>
                {isOpen ? <ChevronUpIcon /> : <ChevronDownIcon />}
            </button>
            {isOpen && <div className="p-4 bg-slate-50/70">{children}</div>}
        </div>
    );
};


// FIX: 'useState' was not defined. It is now imported from React.
const TagInput: React.FC<{ label: string; tags: string[]; onChange: (tags: string[]) => void; placeholder: string }> = ({ label, tags, onChange, placeholder }) => {
    const [inputValue, setInputValue] = useState('');

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const newTag = inputValue.trim();
            if (newTag && !tags.includes(newTag)) {
                onChange([...tags, newTag]);
            }
            setInputValue('');
        }
    };

    const removeTag = (tagToRemove: string) => {
        onChange(tags.filter(tag => tag !== tagToRemove));
    };

    return (
        <div>
            <label className={commonLabelClasses}>{label}</label>
            <div className={`${commonInputClasses} flex flex-wrap items-center gap-2 h-auto`}>
                {tags.map((tag, index) => (
                    <span key={index} className="flex items-center gap-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded-full">
                        {tag}
                        <button onClick={() => removeTag(tag)} className="text-blue-600 hover:text-blue-900">
                            <XMarkIcon />
                        </button>
                    </span>
                ))}
                <input
                    type="text"
                    value={inputValue}
                    onChange={(e) => setInputValue(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    className="flex-grow bg-transparent outline-none border-none p-0 text-sm"
                />
            </div>
        </div>
    );
};


const DiagramElementsPanel: React.FC<{ onAddShapeNode: (shapeType: ShapeType) => void }> = ({ onAddShapeNode }) => {
    return (
        <CollapsibleSection title="Diagram Elements" defaultOpen>
            <p className="text-sm text-gray-600 mb-4">Click an element to add it to the canvas.</p>
            <div className="grid grid-cols-3 gap-3">
                 <button
                    onClick={() => onAddShapeNode('rectangle')}
                    className="flex flex-col items-center justify-center text-center gap-2 p-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 shadow-md"
                >
                    <SquareIcon />
                    <span className="text-sm font-semibold">Rectangle</span>
                </button>
                 <button
                    onClick={() => onAddShapeNode('circle')}
                    className="flex flex-col items-center justify-center text-center gap-2 p-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 shadow-md"
                >
                    <CircleIcon />
                    <span className="text-sm font-semibold">Circle</span>
                </button>
                 <button
                    onClick={() => onAddShapeNode('ellipse')}
                    className="flex flex-col items-center justify-center text-center gap-2 p-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 shadow-md"
                >
                    <EllipseIcon />
                    <span className="text-sm font-semibold">Ellipse</span>
                </button>
                 <button
                    onClick={() => onAddShapeNode('frame')}
                    className="flex flex-col items-center justify-center text-center gap-2 p-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 shadow-md"
                >
                    <FrameIcon />
                    <span className="text-sm font-semibold">Frame</span>
                </button>
                <button
                    onClick={() => onAddShapeNode('package')}
                    className="flex flex-col items-center justify-center text-center gap-2 p-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 shadow-md"
                >
                    <FolderIcon />
                    <span className="text-sm font-semibold">Package</span>
                </button>
            </div>
        </CollapsibleSection>
    );
};


// --- Start Node Settings ---
// FIX: 'useRef' and 'ChangeEvent' were not defined. They are now imported from React.
const StartNodeSettings: React.FC<{ node: Node<StartNodeData>; onUpdate: (data: StartNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleUpdate = <K extends keyof StartNodeData>(key: K, value: StartNodeData[K]) => {
        onUpdate({ ...data, [key]: value });
    };
    
    const handleNestedUpdate = (settingsKey: 'urlSettings' | 'apiSettings' | 'xmlSettings' | 'jsonSettings', update: any) => {
        onUpdate({
            ...data,
            [settingsKey]: {
                ...data[settingsKey],
                ...update,
            }
        });
    };

    const renderFileBasedInputs = () => {
        const inputMethod = data.inputMethod || 'paste';
        const fileInputRef = useRef<HTMLInputElement>(null);

        const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    handleUpdate('sourceValue', ev.target?.result as string);
                    handleUpdate('fileName', file.name);
                };
                reader.readAsText(file);
            }
        };

        return (
            <div className="space-y-4">
                <div className="flex bg-slate-100 rounded-lg p-1">
                    {(['paste', 'upload', 'cloudUrl'] as FileInputMethod[]).map(method => (
                        <button
                            key={method}
                            onClick={() => handleUpdate('inputMethod', method)}
                            className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${inputMethod === method ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}
                        >
                            {method === 'paste' ? 'Paste' : method === 'upload' ? 'Upload' : 'Cloud URL'}
                        </button>
                    ))}
                </div>

                {inputMethod === 'paste' && (
                    <textarea
                        value={String(data.sourceValue)}
                        onChange={(e) => handleUpdate('sourceValue', e.target.value)}
                        placeholder={`Paste ${data.sourceType.toUpperCase()} content here`}
                        className={`${commonInputClasses} h-32 font-mono text-sm`}
                    />
                )}
                {inputMethod === 'upload' && (
                    <div>
                        <input type="file" ref={fileInputRef} onChange={handleFileChange} className="hidden" accept={`.${data.sourceType}`} />
                        <button onClick={() => fileInputRef.current?.click()} className={`${commonButtonClasses} bg-gray-600 hover:bg-gray-700 flex items-center justify-center gap-2`}>
                            <ArrowUpTrayIcon />
                            <span>{data.fileName || 'Choose a file'}</span>
                        </button>
                    </div>
                )}
                {inputMethod === 'cloudUrl' && (
                    <input
                        type="url"
                        value={String(data.sourceValue)}
                        onChange={(e) => handleUpdate('sourceValue', e.target.value)}
                        placeholder="https://example.com/data.json"
                        className={commonInputClasses}
                    />
                )}
            </div>
        );
    };

    const renderURLSettings = () => {
        const settings = data.urlSettings || {} as URLSourceSettings;
        return (
            <CollapsibleSection title="Crawl Settings" defaultOpen>
                <div className="space-y-4">
                    <div>
                        <label className={commonLabelClasses}>Crawl Scope</label>
                        <div className="flex bg-slate-100 rounded-lg p-1">
                            <button onClick={() => handleNestedUpdate('urlSettings', { scope: 'current-url' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.scope === 'current-url' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Current URL Only</button>
                            <button onClick={() => handleNestedUpdate('urlSettings', { scope: 'entire-website' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.scope === 'entire-website' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Entire Website</button>
                        </div>
                    </div>
                    
                    <TagInput 
                        label="Exclude Extensions"
                        tags={settings.excludeExtensions || []}
                        onChange={(tags) => handleNestedUpdate('urlSettings', { excludeExtensions: tags })}
                        placeholder="e.g., pdf, jpg, zip..."
                    />

                    <div>
                        <label className={commonLabelClasses}>Domain Import Policy</label>
                        <div className="flex bg-slate-100 rounded-lg p-1">
                            <button onClick={() => handleNestedUpdate('urlSettings', { domainPolicy: 'all' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>All Domains</button>
                            <button onClick={() => handleNestedUpdate('urlSettings', { domainPolicy: 'whitelist-only' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'whitelist-only' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Whitelist Only</button>
                        </div>
                    </div>

                    {settings.domainPolicy === 'whitelist-only' && (
                        <TagInput
                            label="Domain Whitelist"
                            tags={settings.domainWhitelist || []}
                            onChange={(tags) => handleNestedUpdate('urlSettings', { domainWhitelist: tags })}
                            placeholder="e.g., example.com..."
                        />
                    )}
                </div>
            </CollapsibleSection>
        );
    };
    
    const renderAPISettings = () => {
        const settings = data.apiSettings || {} as APISourceSettings;
        const authDetails = settings.authDetails || {};

        return (
            <>
                <CollapsibleSection title="Authentication" defaultOpen>
                    <div className="space-y-3">
                         <label className={commonLabelClasses}>Auth Type</label>
                         <select value={settings.authType} onChange={(e) => handleNestedUpdate('apiSettings', { authType: e.target.value as any, authDetails: {} })} className={commonInputClasses}>
                            <option value="none">None</option>
                            <option value="api-key">API Key</option>
                            <option value="bearer">Bearer Token</option>
                            <option value="basic">Basic Auth</option>
                        </select>
                        {settings.authType === 'api-key' && (
                            <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                <label className={commonLabelClasses}>Location</label>
                                <select value={(authDetails as APIKeyAuth).location} onChange={(e) => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as APIKeyAuth), location: e.target.value as any } })} className={commonInputClasses}>
                                    <option value="header">Header</option>
                                    <option value="query">Query Parameter</option>
                                </select>
                                <label className={commonLabelClasses}>Key Name</label>
                                <input type="text" value={(authDetails as APIKeyAuth).keyName || ''} onChange={e => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as APIKeyAuth), keyName: e.target.value }})} placeholder="X-API-KEY" className={commonInputClasses} />
                                <label className={commonLabelClasses}>Key Value</label>
                                <input type="password" value={(authDetails as APIKeyAuth).keyValue || ''} onChange={e => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as APIKeyAuth), keyValue: e.target.value }})} placeholder="your-api-key" className={commonInputClasses} />
                            </div>
                        )}
                        {settings.authType === 'bearer' && (
                             <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                <label className={commonLabelClasses}>Bearer Token</label>
                                <input type="password" value={(authDetails as BearerTokenAuth).token || ''} onChange={e => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as BearerTokenAuth), token: e.target.value }})} placeholder="your-bearer-token" className={commonInputClasses} />
                            </div>
                        )}
                        {settings.authType === 'basic' && (
                             <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                <label className={commonLabelClasses}>Username</label>
                                <input type="text" value={(authDetails as BasicAuth).username || ''} onChange={e => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as BasicAuth), username: e.target.value }})} placeholder="username" className={commonInputClasses} />
                                <label className={commonLabelClasses}>Password</label>
                                <input type="password" value={(authDetails as BasicAuth).password || ''} onChange={e => handleNestedUpdate('apiSettings', { authDetails: { ...(authDetails as BasicAuth), password: e.target.value }})} placeholder="password" className={commonInputClasses} />
                            </div>
                        )}
                    </div>
                </CollapsibleSection>
                <CollapsibleSection title="Pagination">
                     <div className="space-y-3">
                         <label className={commonLabelClasses}>Pagination Type</label>
                         <select value={settings.paginationType} onChange={(e) => handleNestedUpdate('apiSettings', { paginationType: e.target.value as any, paginationDetails: {} })} className={commonInputClasses}>
                            <option value="none">None</option>
                            <option value="page">Page Number</option>
                            <option value="offset-limit">Offset/Limit</option>
                            <option value="next-url">Next URL Path</option>
                        </select>
                        {settings.paginationType === 'page' && (
                            <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                {/* FIX: Replaced `{page}` with a string literal inside a JSX expression to prevent it from being parsed as a variable. */}
                                <p className="text-xs text-gray-600">Use {'`{{page}}`'} tag in the API URL.</p>
                                <label className={commonLabelClasses}>Parameter Name</label>
                                <input type="text" value={(settings.paginationDetails as PagePagination).paramName || 'page'} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as PagePagination), paramName: e.target.value }})} className={commonInputClasses} />
                                <label className={commonLabelClasses}>Starts At</label>
                                <input type="number" value={(settings.paginationDetails as PagePagination).startsAt || 1} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as PagePagination), startsAt: parseInt(e.target.value) }})} className={commonInputClasses} />
                            </div>
                        )}
                         {settings.paginationType === 'offset-limit' && (
                            <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                {/* FIX: Replaced `{offset}` and `{limit}` with string literals inside JSX expressions to prevent them from being parsed as variables. */}
                                <p className="text-xs text-gray-600">Use {'`{{offset}}`'} and {'`{{limit}}`'} tags in the API URL.</p>
                                <label className={commonLabelClasses}>Offset Param Name</label>
                                <input type="text" value={(settings.paginationDetails as OffsetLimitPagination).offsetParam || 'offset'} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as OffsetLimitPagination), offsetParam: e.target.value }})} className={commonInputClasses} />
                                 <label className={commonLabelClasses}>Limit Param Name</label>
                                <input type="text" value={(settings.paginationDetails as OffsetLimitPagination).limitParam || 'limit'} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as OffsetLimitPagination), limitParam: e.target.value }})} className={commonInputClasses} />
                                <label className={commonLabelClasses}>Limit Value</label>
                                <input type="number" value={(settings.paginationDetails as OffsetLimitPagination).limitValue || 100} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as OffsetLimitPagination), limitValue: parseInt(e.target.value) }})} className={commonInputClasses} />
                                <label className={commonLabelClasses}>Starts At</label>
                                <input type="number" value={(settings.paginationDetails as OffsetLimitPagination).startsAt || 0} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as OffsetLimitPagination), startsAt: parseInt(e.target.value) }})} className={commonInputClasses} />
                            </div>
                        )}
                         {settings.paginationType === 'next-url' && (
                            <div className="p-3 bg-slate-100 rounded-md space-y-3">
                                <label className={commonLabelClasses}>JSON Path to Next URL</label>
                                <input type="text" value={(settings.paginationDetails as NextURLPagination).jsonPath || ''} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as NextURLPagination), jsonPath: e.target.value }})} placeholder="e.g., meta.pagination.next_url" className={commonInputClasses} />
                            </div>
                        )}
                     </div>
                </CollapsibleSection>
            </>
        );
    };

    const renderXMLSettings = () => {
        const settings = data.xmlSettings || {} as XMLSourceSettings;
        return (
            <CollapsibleSection title="XML Settings" defaultOpen>
                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="scanUrls"
                            checked={settings.scanUrls}
                            onChange={(e) => handleNestedUpdate('xmlSettings', { scanUrls: e.target.checked })}
                            className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label htmlFor="scanUrls" className="text-sm text-gray-700">Scan for URLs from sitemap/feed</label>
                    </div>

                    <div>
                        <label className={commonLabelClasses}>Domain Import Policy</label>
                        <div className="flex bg-slate-100 rounded-lg p-1">
                            <button onClick={() => handleNestedUpdate('xmlSettings', { domainPolicy: 'all' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>All Domains</button>
                            <button onClick={() => handleNestedUpdate('xmlSettings', { domainPolicy: 'whitelist-only' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'whitelist-only' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Whitelist Only</button>
                        </div>
                    </div>
                    
                    {settings.domainPolicy === 'whitelist-only' && (
                        <TagInput
                            label="Domain Whitelist"
                            tags={settings.domainWhitelist || []}
                            onChange={(tags) => handleNestedUpdate('xmlSettings', { domainWhitelist: tags })}
                            placeholder="e.g., example.com..."
                        />
                    )}
                </div>
            </CollapsibleSection>
        )
    };
    
    const renderJSONSettings = () => {
        const settings = data.jsonSettings || {} as JSONSourceSettings;
        return (
            <CollapsibleSection title="JSON Settings" defaultOpen>
                <div className="space-y-4">
                    <div>
                        <label className={commonLabelClasses}>Data Handling</label>
                        <div className="flex bg-slate-100 rounded-lg p-1">
                            <button onClick={() => handleNestedUpdate('jsonSettings', { dataHandling: 'raw' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.dataHandling === 'raw' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Treat as Raw Data</button>
                            <button onClick={() => handleNestedUpdate('jsonSettings', { dataHandling: 'scan-urls' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.dataHandling === 'scan-urls' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Scan for URLs</button>
                        </div>
                    </div>
                    {settings.dataHandling === 'scan-urls' && (
                        <div className="p-3 bg-slate-100 rounded-md space-y-4">
                             <div>
                                <label className={commonLabelClasses}>URL Source</label>
                                <div className="flex bg-slate-100 rounded-lg p-1">
                                    <button onClick={() => handleNestedUpdate('jsonSettings', { urlSource: 'all-values' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.urlSource === 'all-values' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Scan All Values</button>
                                    <button onClick={() => handleNestedUpdate('jsonSettings', { urlSource: 'specific-key' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.urlSource === 'specific-key' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>From Specific Key</button>
                                </div>
                            </div>
                            {settings.urlSource === 'specific-key' && (
                                <div>
                                    <label htmlFor="urlKey" className={commonLabelClasses}>URL Key Name</label>
                                    <input id="urlKey" type="text" value={settings.urlKey || ''} onChange={e => handleNestedUpdate('jsonSettings', { urlKey: e.target.value })} placeholder="e.g., productUrl" className={commonInputClasses} />
                                </div>
                            )}

                            <div>
                                <label className={commonLabelClasses}>Domain Import Policy</label>
                                <div className="flex bg-slate-100 rounded-lg p-1">
                                    <button onClick={() => handleNestedUpdate('jsonSettings', { domainPolicy: 'all' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>All Domains</button>
                                    <button onClick={() => handleNestedUpdate('jsonSettings', { domainPolicy: 'whitelist-only' })} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${settings.domainPolicy === 'whitelist-only' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Whitelist Only</button>
                                </div>
                            </div>
                            
                            {settings.domainPolicy === 'whitelist-only' && (
                                <TagInput
                                    label="Domain Whitelist"
                                    tags={settings.domainWhitelist || []}
                                    onChange={(tags) => handleNestedUpdate('jsonSettings', { domainWhitelist: tags })}
                                    placeholder="e.g., example.com..."
                                />
                            )}
                        </div>
                    )}
                </div>
            </CollapsibleSection>
        )
    };


    const isFileBased = ['xml', 'csv', 'json'].includes(data.sourceType);

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Start Node Settings</h3>
            {isFileBased ? (
                renderFileBasedInputs()
            ) : data.sourceType === 'mysql' ? (
                <div className="space-y-2">
                    {(Object.keys(data.sourceValue) as Array<keyof MySQLConnection>).map(key => (
                        <div key={key}>
                            <label htmlFor={key} className={commonLabelClasses}>{key.charAt(0).toUpperCase() + key.slice(1)}</label>
                            <input
                                id={key}
                                type={key === 'password' ? 'password' : 'text'}
                                value={(data.sourceValue as MySQLConnection)[key] || ''}
                                onChange={e => handleUpdate('sourceValue', { ...(data.sourceValue as MySQLConnection), [key]: e.target.value })}
                                className={commonInputClasses}
                            />
                        </div>
                    ))}
                </div>
            ) : (
                <div>
                    <label htmlFor="sourceValue" className={commonLabelClasses}>Start URL / API Endpoint</label>
                    <input
                        id="sourceValue"
                        type="text"
                        value={String(data.sourceValue)}
                        onChange={e => handleUpdate('sourceValue', e.target.value)}
                        className={commonInputClasses}
                    />
                </div>
            )}
            
            {data.sourceType === 'url' && renderURLSettings()}
            {data.sourceType === 'api' && renderAPISettings()}
            {data.sourceType === 'xml' && renderXMLSettings()}
            {data.sourceType === 'json' && renderJSONSettings()}
        </div>
    );
};

// --- Other Node Settings ---
const ClickNodeSettings: React.FC<{ node: Node<ClickNodeData>; onUpdate: (data: ClickNodeData) => void }> = ({ node, onUpdate }) => {
    return (
        <div className="space-y-4">
             <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Click Node Settings</h3>
             <div>
                <label htmlFor="selector" className={commonLabelClasses}>CSS Selector</label>
                <input
                    id="selector"
                    type="text"
                    value={node.data.selector}
                    onChange={e => onUpdate({ ...node.data, selector: e.target.value })}
                    className={commonInputClasses}
                    placeholder="e.g., a.next-page, button#load-more"
                />
            </div>
        </div>
    );
};

const LoopNodeSettings: React.FC<{ node: Node<LoopNodeData>; onUpdate: (data: LoopNodeData) => void }> = ({ node, onUpdate }) => {
     return (
        <div className="space-y-4">
             <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Loop Node Settings</h3>
             <div>
                <label htmlFor="iteratorSelector" className={commonLabelClasses}>Iterator CSS Selector</label>
                <input
                    id="iteratorSelector"
                    type="text"
                    value={node.data.iteratorSelector}
                    onChange={e => onUpdate({ ...node.data, iteratorSelector: e.target.value })}
                    className={commonInputClasses}
                    placeholder="e.g., .product-list .item"
                />
                <p className="text-xs text-gray-500 mt-1">This selector defines the elements to loop over. Other nodes can be dragged inside this node on the canvas.</p>
            </div>
        </div>
    );
};

// FIX: 'useEffect' was not defined. It is now imported from React.
const HTMLDataExtractorSettings: React.FC<{
    node: Node<HTMLDataExtractorNodeData>;
    onUpdate: (data: HTMLDataExtractorNodeData) => void;
    props: Omit<SettingsPanelProps, 'onAddNode'>;
}> = ({ node, onUpdate, props }) => {
    const { data } = node;
    const [inspectorInputMethod, setInspectorInputMethod] = useState<'url' | 'paste'>('url');
    const [pastedHtml, setPastedHtml] = useState(data.inspectorHtmlContent || '');

    const handleRuleChange = (ruleId: string, field: keyof ExtractionRule, value: any) => {
        const newRules = data.customRules.map(r => r.id === ruleId ? { ...r, [field]: value } : r);
        onUpdate({ ...data, customRules: newRules });
    };

    const addRule = () => {
        const newRule: ExtractionRule = { id: `${Date.now()}`, name: `field_${data.customRules.length + 1}`, extractFrom: 'html-element', selector: '', extract: 'text' };
        onUpdate({ ...data, customRules: [...data.customRules, newRule] });
    };

    const removeRule = (ruleId: string) => {
        onUpdate({ ...data, customRules: data.customRules.filter(r => r.id !== ruleId) });
    };

    const togglePreset = (presetKey: string) => {
        const currentPresets = data.presets || [];
        const preset = PRESETS[presetKey]?.html;
        if (!preset) return;

        const isCurrentlySelected = currentPresets.includes(presetKey as any);
        
        let newRules = [...(data.customRules || [])];

        if (isCurrentlySelected) {
            // Deselecting: remove this preset's rules
            const presetRuleIds = new Set(preset.rules.map(r => r.id));
            newRules = newRules.filter(r => !presetRuleIds.has(r.id));
            const newPresets = currentPresets.filter(p => p !== (presetKey as any));
            onUpdate({ ...data, presets: newPresets, customRules: newRules });
        } else {
            // Selecting: add this preset's rules (if not already present by ID)
            const existingRuleIds = new Set(newRules.map(r => r.id));
            const rulesToAdd = preset.rules.filter(r => !existingRuleIds.has(r.id));
            newRules = [...newRules, ...rulesToAdd];
            const newPresets = [...currentPresets, presetKey as any];
            onUpdate({ ...data, presets: newPresets, customRules: newRules });
        }
    };

    // Memoized set of preset rule IDs for efficient lookup during render
    const presetRuleIds = useMemo(() => {
        const ids = new Set<string>();
        (data.presets || []).forEach(presetKey => {
            PRESETS[presetKey]?.html?.rules.forEach(rule => {
                ids.add(rule.id);
            });
        });
        return ids;
    }, [data.presets]);
    
    const handleFetchHtml = async () => {
        if (!data.inspectorUrl) {
            onUpdate({ ...data, inspectorError: 'Please enter a URL to inspect.' });
            return;
        }
        onUpdate({ ...data, inspectorLoading: true, inspectorError: undefined, inspectorHtmlContent: undefined });
        try {
            // Use a CORS proxy to bypass browser security restrictions for development.
            // In a production environment, this request should be routed through a dedicated backend.
            const proxyUrl = `https://api.allorigins.win/raw?url=${encodeURIComponent(data.inspectorUrl)}`;
            const response = await fetch(proxyUrl);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const htmlContent = await response.text();
            
            onUpdate({ ...data, inspectorLoading: false, inspectorHtmlContent: htmlContent });
            props.onShowInspector(htmlContent);

        } catch (error) {
            console.error("Failed to fetch HTML:", error);
            const errorMessage = 'Failed to fetch HTML. The URL may be invalid, the site may be down, or it might be blocking requests. Please try the "Paste HTML" option instead.';
            onUpdate({ ...data, inspectorLoading: false, inspectorError: errorMessage });
        }
    };

    const handleLoadPastedHtml = () => {
        if (!pastedHtml) {
            onUpdate({ ...data, inspectorError: 'Please paste HTML content to load.' });
            return;
        }
        onUpdate({ ...data, inspectorLoading: false, inspectorHtmlContent: pastedHtml, inspectorError: undefined });
        props.onShowInspector(pastedHtml);
    };
    
    useEffect(() => {
        // If panel is closed while inspector is open, hide it
        return () => {
            props.onHideInspector();
        }
    }, []);

    const availablePresets = useMemo(() => {
        return Object.entries(PRESETS).filter(([_, preset]) => !!preset.html);
    }, []);

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">HTML Data Extractor Settings</h3>
            
            <CollapsibleSection title="Inspector Tool" defaultOpen>
                <div className="space-y-2">
                    <p className="text-xs text-gray-600 mb-2">
                        Load HTML to visually select elements. Fetching from a URL may fail due to browser security (CORS). If that happens, use the Paste HTML option.
                    </p>
                    <div className="flex bg-slate-100 rounded-lg p-1">
                        <button
                            onClick={() => setInspectorInputMethod('url')}
                            className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${inspectorInputMethod === 'url' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}
                        >
                            Fetch from URL
                        </button>
                        <button
                            onClick={() => setInspectorInputMethod('paste')}
                            className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${inspectorInputMethod === 'paste' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}
                        >
                            Paste HTML
                        </button>
                    </div>

                    {inspectorInputMethod === 'url' ? (
                        <div className="flex gap-2 pt-2">
                            <input
                                type="url"
                                placeholder="https://example.com/product/123"
                                value={data.inspectorUrl || ''}
                                onChange={(e) => onUpdate({ ...data, inspectorUrl: e.target.value })}
                                className={commonInputClasses}
                            />
                            <button onClick={handleFetchHtml} disabled={data.inspectorLoading} className={`${smallButtonClasses} bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300`}>
                                {data.inspectorLoading ? 'Loading...' : 'Fetch'}
                            </button>
                        </div>
                    ) : (
                        <div className="pt-2">
                            <textarea
                                placeholder="Paste the full HTML source code here"
                                value={pastedHtml}
                                onChange={(e) => setPastedHtml(e.target.value)}
                                className={`${commonInputClasses} h-32 font-mono text-sm`}
                            />
                            <button onClick={handleLoadPastedHtml} className={`${commonButtonClasses} bg-blue-600 hover:bg-blue-700 mt-2`}>
                                Load HTML
                            </button>
                        </div>
                    )}
                     {data.inspectorError && <p className="text-sm text-red-600 mt-2">{data.inspectorError}</p>}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Extraction Presets">
                <div className="grid grid-cols-2 gap-2">
                    {availablePresets.map(([key, preset]) => (
                        <div key={key} className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id={`preset-${key}`}
                                checked={(data.presets || []).includes(key as any)}
                                onChange={() => togglePreset(key)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label htmlFor={`preset-${key}`} className="text-sm text-gray-700">{preset.name}</label>
                        </div>
                    ))}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Custom Extraction Rules" defaultOpen>
                 <div className="space-y-3">
                    {data.customRules.map((rule) => {
                        const isPresetRule = presetRuleIds.has(rule.id);
                        return (
                            <div key={rule.id} className={`p-3 border rounded-lg space-y-2 relative ${isPresetRule ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200'}`}>
                                {isPresetRule ? (
                                    <span className="absolute top-2 right-2 text-xs font-semibold text-blue-700 bg-blue-200 px-2 py-0.5 rounded-full">Preset</span>
                                ) : (
                                    <button onClick={() => removeRule(rule.id)} className="absolute top-2 right-2 text-gray-400 hover:text-red-500"><TrashIcon /></button>
                                )}
                                <div className="grid grid-cols-2 gap-2">
                                    <input type="text" placeholder="Field Name" value={rule.name} onChange={e => handleRuleChange(rule.id, 'name', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetRule} title={isPresetRule ? 'Preset field names cannot be changed' : ''} />
                                    <select value={rule.extractFrom} onChange={e => handleRuleChange(rule.id, 'extractFrom', e.target.value as ExtractFrom)} className={smallInputClasses}>
                                        <option value="html-element">HTML Element</option>
                                        <option value="json-ld">JSON-LD</option>
                                        <option value="html-comment">HTML Comment</option>
                                    </select>
                                </div>
                                {rule.extractFrom === 'html-element' && (
                                    <>
                                        <div className="flex items-center gap-1">
                                            <input
                                                type="text"
                                                placeholder="CSS Selector"
                                                value={rule.selector || ''}
                                                onChange={e => handleRuleChange(rule.id, 'selector', e.target.value)}
                                                onFocus={() => props.onInspectSelector(rule.selector || null)}
                                                onBlur={() => props.onInspectSelector(null)}
                                                className={`${smallInputClasses} ${props.highlightedSelector === rule.selector ? 'outline outline-2 outline-orange-500' : ''}`}
                                            />
                                            <button
                                                onClick={() => {
                                                    if (props.pickingRuleId === rule.id) {
                                                        props.onStopPicking();
                                                    } else {
                                                        props.onStartPicking(node.id, rule.id);
                                                    }
                                                }}
                                                className={`p-1.5 rounded-md ${props.pickingRuleId === rule.id ? 'bg-blue-600 text-white animate-pulse' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                                                title="Pick element from inspector"
                                                disabled={!data.inspectorHtmlContent}
                                            >
                                                <CursorArrowRaysIcon />
                                            </button>
                                        </div>
                                        <select value={rule.extract || 'text'} onChange={e => handleRuleChange(rule.id, 'extract', e.target.value)} className={smallInputClasses}>
                                            <option value="text">Extract Text</option>
                                            <option value="attribute">Extract Attribute</option>
                                            <option value="html">Extract HTML</option>
                                            <option value="regex">Extract via Regex</option>
                                        </select>
                                        {rule.extract === 'attribute' && <input type="text" placeholder="Attribute Name (e.g., href)" value={rule.attribute || ''} onChange={e => handleRuleChange(rule.id, 'attribute', e.target.value)} className={smallInputClasses} />}
                                        {rule.extract === 'regex' && (
                                            <div className="grid grid-cols-3 gap-2">
                                                <input type="text" placeholder="Regex Pattern" value={rule.regexPattern || ''} onChange={e => handleRuleChange(rule.id, 'regexPattern', e.target.value)} className={`${smallInputClasses} col-span-2`} />
                                                <input type="number" placeholder="Group" value={rule.regexGroup || 0} onChange={e => handleRuleChange(rule.id, 'regexGroup', parseInt(e.target.value))} className={smallInputClasses} />
                                            </div>
                                        )}
                                    </>
                                )}
                                {(rule.extractFrom === 'json-ld' || rule.extractFrom === 'html-comment') && (
                                    <input type="text" placeholder="JSON Path (e.g., offers.price)" value={rule.jsonPath || ''} onChange={e => handleRuleChange(rule.id, 'jsonPath', e.target.value)} className={smallInputClasses} />
                                )}
                            </div>
                        )
                    })}
                </div>
                <button onClick={addRule} className={`${commonButtonClasses} bg-teal-600 hover:bg-teal-700 mt-3`}>Add Custom Rule</button>
            </CollapsibleSection>
        </div>
    );
};

// --- NEW EXTRACTOR SETTINGS ---

const CSVExtractorSettings: React.FC<{ node: Node<CSVExtractorNodeData>; onUpdate: (data: CSVExtractorNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleMappingChange = (id: string, key: keyof ColumnMapping, value: any) => {
        const newMappings = data.mappings.map(m => m.id === id ? { ...m, [key]: value } : m);
        onUpdate({ ...data, mappings: newMappings });
    };
    const addMapping = () => {
        const newMapping: ColumnMapping = { id: `${Date.now()}`, source: String(data.mappings.length), fieldName: `field_${data.mappings.length + 1}` };
        onUpdate({ ...data, mappings: [...data.mappings, newMapping] });
    };
    const removeMapping = (id: string) => {
        onUpdate({ ...data, mappings: data.mappings.filter(m => m.id !== id) });
    };
    
    const togglePreset = (presetKey: string) => {
        const currentPresets = data.presets || [];
        const preset = PRESETS[presetKey]?.csv;
        if (!preset) return;

        const isCurrentlySelected = currentPresets.includes(presetKey);
        let newMappings = [...data.mappings];

        if (isCurrentlySelected) {
            const presetMappingIds = new Set(preset.mappings.map(m => m.id));
            newMappings = newMappings.filter(m => !presetMappingIds.has(m.id));
            const newPresets = currentPresets.filter(p => p !== presetKey);
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        } else {
            const existingMappingIds = new Set(newMappings.map(m => m.id));
            const mappingsToAdd = preset.mappings.filter(m => !existingMappingIds.has(m.id));
            newMappings = [...newMappings, ...mappingsToAdd];
            const newPresets = [...currentPresets, presetKey];
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        }
    };
    
    const presetMappingIds = useMemo(() => {
        const ids = new Set<string>();
        (data.presets || []).forEach(presetKey => {
            PRESETS[presetKey]?.csv?.mappings.forEach(mapping => {
                ids.add(mapping.id);
            });
        });
        return ids;
    }, [data.presets]);

    const availablePresets = useMemo(() => {
        return Object.entries(PRESETS).filter(([_, preset]) => !!preset.csv);
    }, []);

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">CSV Extractor Settings</h3>
            <div className="flex items-center gap-2">
                <input
                    type="checkbox"
                    id="hasHeader"
                    checked={data.hasHeader}
                    onChange={(e) => onUpdate({ ...data, hasHeader: e.target.checked })}
                    className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <label htmlFor="hasHeader" className="text-sm text-gray-700">First row is header</label>
            </div>
            
            <CollapsibleSection title="Extraction Presets">
                <div className="grid grid-cols-2 gap-2">
                    {availablePresets.map(([key, preset]) => (
                        <div key={key} className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id={`preset-${key}`}
                                checked={(data.presets || []).includes(key)}
                                onChange={() => togglePreset(key)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label htmlFor={`preset-${key}`} className="text-sm text-gray-700">{preset.name}</label>
                        </div>
                    ))}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Column Mappings" defaultOpen>
                <div className="space-y-2">
                    {data.mappings.map(m => {
                        const isPresetMapping = presetMappingIds.has(m.id);
                        return (
                            <div key={m.id} className={`flex items-center gap-2 p-2 border rounded-md relative ${isPresetMapping ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200'}`}>
                                {isPresetMapping && <span className="absolute top-1 right-2 text-xs font-semibold text-blue-700 bg-blue-200 px-2 py-0.5 rounded-full">Preset</span>}
                                <input type={data.hasHeader ? 'text' : 'number'} placeholder={data.hasHeader ? "Header Name" : "Column Index"} value={m.source} onChange={e => handleMappingChange(m.id, 'source', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping} />
                                <span>-&gt;</span>
                                <input type="text" placeholder="Field Name" value={m.fieldName} onChange={e => handleMappingChange(m.id, 'fieldName', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping}/>
                                {!isPresetMapping && <button onClick={() => removeMapping(m.id)} className="text-gray-400 hover:text-red-500"><TrashIcon /></button>}
                            </div>
                        );
                    })}
                </div>
                <button onClick={addMapping} className={`${commonButtonClasses} bg-teal-600 hover:bg-teal-700 mt-3`}>Add Mapping</button>
            </CollapsibleSection>
        </div>
    );
};

const PathBasedExtractorSettings: React.FC<{
    node: Node<JSONExtractorNodeData | XMLExtractorNodeData>;
    onUpdate: (data: JSONExtractorNodeData | XMLExtractorNodeData) => void;
    title: string;
    pathPlaceholder: string;
    presetKey: 'json' | 'xml';
}> = ({ node, onUpdate, title, pathPlaceholder, presetKey }) => {
    const { data } = node;
    
    const handleMappingChange = (id: string, key: keyof PathMapping, value: any) => {
        const newMappings = data.mappings.map(m => m.id === id ? { ...m, [key]: value } : m);
        onUpdate({ ...data, mappings: newMappings });
    };
    const addMapping = () => {
        const newMapping: PathMapping = { id: `${Date.now()}`, path: '', fieldName: `field_${data.mappings.length + 1}` };
        onUpdate({ ...data, mappings: [...data.mappings, newMapping] });
    };
    const removeMapping = (id: string) => {
        onUpdate({ ...data, mappings: data.mappings.filter(m => m.id !== id) });
    };

    const togglePreset = (key: string) => {
        const currentPresets = data.presets || [];
        const preset = PRESETS[key]?.[presetKey];
        if (!preset) return;

        const isCurrentlySelected = currentPresets.includes(key);
        let newMappings = [...data.mappings];

        if (isCurrentlySelected) {
            const presetMappingIds = new Set(preset.mappings.map(m => m.id));
            newMappings = newMappings.filter(m => !presetMappingIds.has(m.id));
            const newPresets = currentPresets.filter(p => p !== key);
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        } else {
            const existingMappingIds = new Set(newMappings.map(m => m.id));
            const mappingsToAdd = preset.mappings.filter(m => !existingMappingIds.has(m.id));
            newMappings = [...newMappings, ...mappingsToAdd];
            const newPresets = [...currentPresets, key];
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        }
    };
    
    const presetMappingIds = useMemo(() => {
        const ids = new Set<string>();
        (data.presets || []).forEach(key => {
            PRESETS[key]?.[presetKey]?.mappings.forEach(mapping => {
                ids.add(mapping.id);
            });
        });
        return ids;
    }, [data.presets, presetKey]);

    const availablePresets = useMemo(() => {
        return Object.entries(PRESETS).filter(([_, preset]) => !!preset[presetKey]);
    }, [presetKey]);
    
    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">{title}</h3>

            <CollapsibleSection title="Extraction Presets">
                <div className="grid grid-cols-2 gap-2">
                    {availablePresets.map(([key, preset]) => (
                        <div key={key} className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id={`preset-${key}`}
                                checked={(data.presets || []).includes(key)}
                                onChange={() => togglePreset(key)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label htmlFor={`preset-${key}`} className="text-sm text-gray-700">{preset.name}</label>
                        </div>
                    ))}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Path Mappings" defaultOpen>
                <div className="space-y-2">
                    {data.mappings.map(m => {
                         const isPresetMapping = presetMappingIds.has(m.id);
                         return (
                            <div key={m.id} className={`flex items-center gap-2 p-2 border rounded-md relative ${isPresetMapping ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200'}`}>
                                {isPresetMapping && <span className="absolute top-1 right-2 text-xs font-semibold text-blue-700 bg-blue-200 px-2 py-0.5 rounded-full">Preset</span>}
                                <input type="text" placeholder={pathPlaceholder} value={m.path} onChange={e => handleMappingChange(m.id, 'path', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping} />
                                <span>-&gt;</span>
                                <input type="text" placeholder="Field Name" value={m.fieldName} onChange={e => handleMappingChange(m.id, 'fieldName', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping} />
                                {!isPresetMapping && <button onClick={() => removeMapping(m.id)} className="text-gray-400 hover:text-red-500"><TrashIcon /></button>}
                            </div>
                         );
                    })}
                </div>
                <button onClick={addMapping} className={`${commonButtonClasses} bg-teal-600 hover:bg-teal-700 mt-3`}>Add Mapping</button>
            </CollapsibleSection>
        </div>
    );
};

const MySQLExtractorSettings: React.FC<{ node: Node<MySQLExtractorNodeData>; onUpdate: (data: MySQLExtractorNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleMappingChange = (id: string, key: keyof ColumnMapping, value: any) => {
        const newMappings = data.mappings.map(m => m.id === id ? { ...m, [key]: value } : m);
        onUpdate({ ...data, mappings: newMappings });
    };
    const addMapping = () => {
        const newMapping: ColumnMapping = { id: `${Date.now()}`, source: `column_${data.mappings.length + 1}`, fieldName: `field_${data.mappings.length + 1}` };
        onUpdate({ ...data, mappings: [...data.mappings, newMapping] });
    };
    const removeMapping = (id: string) => {
        onUpdate({ ...data, mappings: data.mappings.filter(m => m.id !== id) });
    };

    const togglePreset = (presetKey: string) => {
        const currentPresets = data.presets || [];
        const preset = PRESETS[presetKey]?.mysql;
        if (!preset) return;

        const isCurrentlySelected = currentPresets.includes(presetKey);
        let newMappings = [...data.mappings];

        if (isCurrentlySelected) {
            const presetMappingIds = new Set(preset.mappings.map(m => m.id));
            newMappings = newMappings.filter(m => !presetMappingIds.has(m.id));
            const newPresets = currentPresets.filter(p => p !== presetKey);
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        } else {
            const existingMappingIds = new Set(newMappings.map(m => m.id));
            const mappingsToAdd = preset.mappings.filter(m => !existingMappingIds.has(m.id));
            newMappings = [...newMappings, ...mappingsToAdd];
            const newPresets = [...currentPresets, presetKey];
            onUpdate({ ...data, presets: newPresets, mappings: newMappings });
        }
    };
    
    const presetMappingIds = useMemo(() => {
        const ids = new Set<string>();
        (data.presets || []).forEach(presetKey => {
            PRESETS[presetKey]?.mysql?.mappings.forEach(mapping => {
                ids.add(mapping.id);
            });
        });
        return ids;
    }, [data.presets]);

    const availablePresets = useMemo(() => {
        return Object.entries(PRESETS).filter(([_, preset]) => !!preset.mysql);
    }, []);
    
    return (
         <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">MySQL Extractor Settings</h3>
            
            <CollapsibleSection title="Extraction Presets">
                <div className="grid grid-cols-2 gap-2">
                    {availablePresets.map(([key, preset]) => (
                        <div key={key} className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id={`preset-${key}`}
                                checked={(data.presets || []).includes(key)}
                                onChange={() => togglePreset(key)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label htmlFor={`preset-${key}`} className="text-sm text-gray-700">{preset.name}</label>
                        </div>
                    ))}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Column Mappings" defaultOpen>
                <div className="space-y-2">
                    {data.mappings.map(m => {
                        const isPresetMapping = presetMappingIds.has(m.id);
                        return (
                            <div key={m.id} className={`flex items-center gap-2 p-2 border rounded-md relative ${isPresetMapping ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200'}`}>
                                {isPresetMapping && <span className="absolute top-1 right-2 text-xs font-semibold text-blue-700 bg-blue-200 px-2 py-0.5 rounded-full">Preset</span>}
                                <input type="text" placeholder="Column Name" value={m.source} onChange={e => handleMappingChange(m.id, 'source', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping} />
                                <span>-&gt;</span>
                                <input type="text" placeholder="Field Name" value={m.fieldName} onChange={e => handleMappingChange(m.id, 'fieldName', e.target.value)} className={`${smallInputClasses} disabled:bg-slate-200 disabled:text-gray-600 disabled:cursor-not-allowed`} disabled={isPresetMapping} />
                                {!isPresetMapping && <button onClick={() => removeMapping(m.id)} className="text-gray-400 hover:text-red-500"><TrashIcon /></button>}
                            </div>
                        );
                    })}
                </div>
                <button onClick={addMapping} className={`${commonButtonClasses} bg-teal-600 hover:bg-teal-700 mt-3`}>Add Mapping</button>
            </CollapsibleSection>
        </div>
    );
}

// --- Processor Settings Forms ---

const SaveToDbSettingsForm: React.FC<{ settings: SaveToDbSettings; onUpdate: (update: Partial<SaveToDbSettings>) => void }> = ({ settings, onUpdate }) => (
    <div className="space-y-3">
        <h4 className="font-semibold text-gray-700">Database Connection</h4>
        <div className="grid grid-cols-2 gap-3">
            <div>
                <label className={commonLabelClasses}>Host</label>
                <input type="text" value={settings.host} onChange={e => onUpdate({ host: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>Port</label>
                <input type="text" value={settings.port} onChange={e => onUpdate({ port: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>User</label>
                <input type="text" value={settings.user} onChange={e => onUpdate({ user: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>Password</label>
                <input type="password" value={settings.password} onChange={e => onUpdate({ password: e.target.value })} className={commonInputClasses} />
            </div>
        </div>
        <div>
            <label className={commonLabelClasses}>Database</label>
            <input type="text" value={settings.database} onChange={e => onUpdate({ database: e.target.value })} className={commonInputClasses} />
        </div>
        <div className="border-t pt-3 mt-3">
            <h4 className="font-semibold text-gray-700">Table Settings</h4>
            <div className="mt-2">
                <label className={commonLabelClasses}>Table Name</label>
                <input type="text" value={settings.tableName} onChange={e => onUpdate({ tableName: e.target.value })} className={commonInputClasses} />
            </div>
            <div className="mt-2">
                <label className={commonLabelClasses}>On Conflict</label>
                <select value={settings.conflictStrategy} onChange={e => onUpdate({ conflictStrategy: e.target.value as any })} className={commonInputClasses}>
                    <option value="insert">Insert (Fail on duplicate)</option>
                    <option value="upsert">Update or Insert (Upsert)</option>
                    <option value="skip">Skip (Ignore duplicate)</option>
                </select>
            </div>
        </div>
    </div>
);

const SendToApiSettingsForm: React.FC<{ settings: SendToApiSettings; onUpdate: (update: Partial<SendToApiSettings>) => void }> = ({ settings, onUpdate }) => {
    // A simplified version for now. A real implementation might reuse the API auth settings from StartNode.
    return (
        <div className="space-y-3">
            <h4 className="font-semibold text-gray-700">API Endpoint</h4>
            <div>
                <label className={commonLabelClasses}>URL</label>
                <input type="url" value={settings.endpointUrl} onChange={e => onUpdate({ endpointUrl: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>HTTP Method</label>
                <select value={settings.method} onChange={e => onUpdate({ method: e.target.value as any })} className={commonInputClasses}>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                </select>
            </div>
            <p className="text-xs text-gray-500 italic">Authentication and custom header settings would be configured here.</p>
        </div>
    );
};

const GenerateCsvSettingsForm: React.FC<{ settings: GenerateCsvSettings; onUpdate: (update: Partial<GenerateCsvSettings>) => void }> = ({ settings, onUpdate }) => (
    <div className="space-y-3">
        <h4 className="font-semibold text-gray-700">CSV File Options</h4>
        <div>
            <label className={commonLabelClasses}>File Name</label>
            <input type="text" value={settings.fileName} onChange={e => onUpdate({ fileName: e.target.value })} className={commonInputClasses} />
            <p className="text-xs text-gray-500 mt-1">You can use placeholders like {'`{{date}}`'} or {'`{{time}}`'}.</p>
        </div>
        <div>
            <label className={commonLabelClasses}>Delimiter</label>
            <select value={settings.delimiter} onChange={e => onUpdate({ delimiter: e.target.value as any })} className={commonInputClasses}>
                <option value=",">Comma (,)</option>
                <option value=";">Semicolon (;)</option>
                <option value="\t">Tab</option>
            </select>
        </div>
        <div className="flex items-center gap-2">
            <input
                type="checkbox"
                id="includeHeader"
                checked={settings.includeHeader}
                onChange={e => onUpdate({ includeHeader: e.target.checked })}
                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor="includeHeader" className="text-sm text-gray-700">Include header row</label>
        </div>
    </div>
);

const SendEmailSettingsForm: React.FC<{ settings: SendEmailSettings; onUpdate: (update: Partial<SendEmailSettings>) => void }> = ({ settings, onUpdate }) => (
    <div className="space-y-3">
        <h4 className="font-semibold text-gray-700">Email Notification</h4>
        <div>
            <label className={commonLabelClasses}>Recipient(s)</label>
            <input type="text" value={settings.recipients} onChange={e => onUpdate({ recipients: e.target.value })} className={commonInputClasses} placeholder="admin@example.com, user@test.com" />
             <p className="text-xs text-gray-500 mt-1">Separate multiple emails with a comma.</p>
        </div>
        <div>
            <label className={commonLabelClasses}>Subject</label>
            <input type="text" value={settings.subject} onChange={e => onUpdate({ subject: e.target.value })} className={commonInputClasses} />
             <p className="text-xs text-gray-500 mt-1">You can use placeholders like {'`{{title}}`'} from your extracted data.</p>
        </div>
        <div>
            <label className={commonLabelClasses}>Body</label>
            <textarea value={settings.body} onChange={e => onUpdate({ body: e.target.value })} className={`${commonInputClasses} h-32`} />
             <p className="text-xs text-gray-500 mt-1">Placeholders are also supported here.</p>
        </div>
    </div>
);


const ProcessorSettings: React.FC<{ node: Node<ProcessorNodeData>; onUpdate: (data: ProcessorNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleTypeChange = (newType: string) => {
        const newProcessor = PROCESSORS.find(p => p.id === newType);
        if (newProcessor) {
            onUpdate({
                processorType: newProcessor.id,
                settings: newProcessor.defaultSettings
            } as ProcessorNodeData);
        }
    };

    const handleSettingsUpdate = (update: any) => {
        onUpdate({
            ...data,
            settings: {
                ...data.settings,
                ...update
            }
        });
    };

    const renderSettingsForm = () => {
        switch (data.processorType) {
            case 'save-to-database':
                return <SaveToDbSettingsForm settings={data.settings} onUpdate={handleSettingsUpdate} />;
            case 'send-to-api':
                return <SendToApiSettingsForm settings={data.settings} onUpdate={handleSettingsUpdate} />;
            case 'generate-csv-file':
                return <GenerateCsvSettingsForm settings={data.settings} onUpdate={handleSettingsUpdate} />;
            case 'send-email-notification':
                return <SendEmailSettingsForm settings={data.settings} onUpdate={handleSettingsUpdate} />;
            default:
                return <p className="text-sm text-gray-500 text-center py-4">This processor type has no specific settings.</p>;
        }
    };

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Processor Settings</h3>
            <div>
                <label htmlFor="processorType" className={commonLabelClasses}>Processor Type</label>
                <select
                    id="processorType"
                    value={data.processorType}
                    onChange={(e) => handleTypeChange(e.target.value)}
                    className={commonInputClasses}
                >
                    {PROCESSORS.map(p => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </select>
            </div>
            <div className="border-t pt-4 mt-4">
                {renderSettingsForm()}
            </div>
        </div>
    );
}

const WorkerNodeSettings: React.FC<{ node: Node<WorkerNodeData>; onUpdate: (data: WorkerNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleUpdate = <K extends keyof WorkerNodeData>(key: K, value: WorkerNodeData[K]) => {
        onUpdate({ ...data, [key]: value });
    };

    const handleRuleChange = (ruleId: string, update: Partial<WorkerRule>) => {
        const newRules = data.detectionRules.map(r => 
            r.id === ruleId ? { ...r, ...update } : r
        );
        handleUpdate('detectionRules', newRules);
    };

    const addRule = (type: WorkerRuleType) => {
        if (!type) return;
        const newRule: WorkerRule = {
            id: `${Date.now()}`,
            type,
            ...(type === 'url-format' && { pattern: '' }),
            ...(type === 'html-contains' && { text: '' }),
            ...(type === 'dom-value' && { selector: '', condition: 'exists', value: '' }),
            ...(type === 'tag-attribute' && { selector: '', attribute: '', condition: 'exists', value: '' }),
            ...(type === 'data-source-type' && { sourceType: 'url' }),
        } as WorkerRule;
        handleUpdate('detectionRules', [...data.detectionRules, newRule]);
    };

    const removeRule = (ruleId: string) => {
        handleUpdate('detectionRules', data.detectionRules.filter(r => r.id !== ruleId));
    };

    const renderRuleInputs = (rule: WorkerRule) => {
        switch (rule.type) {
            case 'url-format':
                return (
                    <input type="text" placeholder="URL Regex Pattern" value={(rule as URLFormatRule).pattern} onChange={e => handleRuleChange(rule.id, { pattern: e.target.value })} className={smallInputClasses} />
                );
            case 'html-contains':
                return (
                    <input type="text" placeholder="Text to find in HTML" value={(rule as HTMLContainsRule).text} onChange={e => handleRuleChange(rule.id, { text: e.target.value })} className={smallInputClasses} />
                );
            case 'dom-value': {
                const domRule = rule as DOMValueRule;
                return (
                    <div className="grid grid-cols-2 gap-2">
                        <input type="text" placeholder="CSS Selector" value={domRule.selector} onChange={e => handleRuleChange(rule.id, { selector: e.target.value })} className={smallInputClasses} />
                        <select value={domRule.condition} onChange={e => handleRuleChange(rule.id, { condition: e.target.value as RuleCondition })} className={smallInputClasses}>
                           <option value="exists">Exists</option>
                           <option value="not-exists">Not Exists</option>
                           <option value="contains">Contains</option>
                           <option value="not-contains">Not Contains</option>
                           <option value="matches-regex">Matches Regex</option>
                        </select>
                        {(domRule.condition !== 'exists' && domRule.condition !== 'not-exists') && (
                            <input type="text" placeholder="Value / Regex" value={domRule.value} onChange={e => handleRuleChange(rule.id, { value: e.target.value })} className={`${smallInputClasses} col-span-2`} />
                        )}
                    </div>
                );
            }
            case 'tag-attribute': {
                 const attrRule = rule as TagAttributeRule;
                return (
                     <div className="space-y-2">
                        <input type="text" placeholder="CSS Selector" value={attrRule.selector} onChange={e => handleRuleChange(rule.id, { selector: e.target.value })} className={smallInputClasses} />
                        <div className="grid grid-cols-2 gap-2">
                             <input type="text" placeholder="Attribute (e.g. href)" value={attrRule.attribute} onChange={e => handleRuleChange(rule.id, { attribute: e.target.value })} className={smallInputClasses} />
                             <select value={attrRule.condition} onChange={e => handleRuleChange(rule.id, { condition: e.target.value as RuleCondition })} className={smallInputClasses}>
                                <option value="exists">Exists</option>
                                <option value="not-exists">Not Exists</option>
                                <option value="contains">Contains</option>
                                <option value="not-contains">Not Contains</option>
                                <option value="matches-regex">Matches Regex</option>
                            </select>
                        </div>
                        {(attrRule.condition !== 'exists' && attrRule.condition !== 'not-exists') && (
                            <input type="text" placeholder="Value / Regex" value={attrRule.value} onChange={e => handleRuleChange(rule.id, { value: e.target.value })} className={smallInputClasses} />
                        )}
                    </div>
                );
            }
            case 'data-source-type': {
                 const srcRule = rule as DataSourceTypeRule;
                 return (
                    <select value={srcRule.sourceType} onChange={e => handleRuleChange(rule.id, { sourceType: e.target.value as DataSourceType })} className={smallInputClasses}>
                        <option value="url">URL</option>
                        <option value="api">API</option>
                        <option value="xml">XML</option>
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                        <option value="mysql">MySQL</option>
                    </select>
                 );
            }
            default:
                return null;
        }
    };

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Worker Settings</h3>

            <div>
                <label className={commonLabelClasses}>Priority</label>
                <input
                    type="number"
                    value={data.priority}
                    onChange={e => handleUpdate('priority', parseInt(e.target.value, 10) || 1)}
                    className={commonInputClasses}
                    min="1"
                />
                <p className="text-xs text-gray-500 mt-1">Workers with higher priority run first.</p>
            </div>

            <CollapsibleSection title="Detection Rules" defaultOpen>
                 <div>
                    <label className={commonLabelClasses}>Detection Logic</label>
                    <div className="flex bg-slate-100 rounded-lg p-1 mb-4">
                        <button onClick={() => handleUpdate('detectionLogic', 'and')} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${data.detectionLogic === 'and' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Match All (AND)</button>
                        <button onClick={() => handleUpdate('detectionLogic', 'or')} className={`flex-1 p-2 text-sm font-semibold rounded-md transition-colors ${data.detectionLogic === 'or' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-white/60'}`}>Match Any (OR)</button>
                    </div>
                </div>

                <div className="space-y-3">
                    {data.detectionRules.map(rule => (
                        <div key={rule.id} className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2 relative">
                            <button onClick={() => removeRule(rule.id)} className="absolute top-2 right-2 text-gray-400 hover:text-red-500"><TrashIcon /></button>
                            <p className="text-xs font-semibold text-gray-600 capitalize">
                                {rule.type.replace(/-/g, ' ')} Rule
                            </p>
                            {renderRuleInputs(rule)}
                        </div>
                    ))}
                    {data.detectionRules.length === 0 && (
                        <p className="text-sm text-gray-500 text-center py-4">No detection rules defined. This worker will not process any items.</p>
                    )}
                </div>
                 
                 <div className="mt-4">
                    <label className={commonLabelClasses} htmlFor="new-rule-type-select">Add New Rule</label>
                     <select 
                        id="new-rule-type-select"
                        className={commonInputClasses} 
                        onChange={e => {
                            addRule(e.target.value as WorkerRuleType);
                            e.target.value = "";
                        }} 
                        value=""
                    >
                         <option value="" disabled>Select a rule type...</option>
                         <option value="url-format">URL Format</option>
                         <option value="html-contains">HTML Contains</option>
                         <option value="dom-value">DOM Value</option>
                         <option value="tag-attribute">Tag Attribute</option>
                         <option value="data-source-type">Data Source Type</option>
                    </select>
                 </div>
            </CollapsibleSection>
        </div>
    );
};

const ShapeNodeSettings: React.FC<{ node: Node<ShapeNodeData>; onUpdate: (data: ShapeNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const handleUpdate = <K extends keyof ShapeNodeData>(key: K, value: ShapeNodeData[K]) => {
        onUpdate({ ...data, [key]: value });
    };

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Shape Settings</h3>
            <div>
                <label htmlFor="shapeLabel" className={commonLabelClasses}>Label</label>
                <input
                    id="shapeLabel"
                    type="text"
                    value={data.label}
                    onChange={e => handleUpdate('label', e.target.value)}
                    className={commonInputClasses}
                />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label htmlFor="backgroundColor" className={commonLabelClasses}>Background</label>
                    <input
                        id="backgroundColor"
                        type="color"
                        value={data.backgroundColor}
                        onChange={e => handleUpdate('backgroundColor', e.target.value)}
                        className="w-full h-10 p-1 bg-white border border-slate-300 rounded-md cursor-pointer"
                    />
                </div>
                 <div>
                    <label htmlFor="textColor" className={commonLabelClasses}>Text Color</label>
                    <input
                        id="textColor"
                        type="color"
                        value={data.textColor}
                        onChange={e => handleUpdate('textColor', e.target.value)}
                        className="w-full h-10 p-1 bg-white border border-slate-300 rounded-md cursor-pointer"
                    />
                </div>
            </div>
             <div>
                <label htmlFor="borderColor" className={commonLabelClasses}>Border Color</label>
                <input
                    id="borderColor"
                    type="color"
                    value={data.borderColor}
                    onChange={e => handleUpdate('borderColor', e.target.value)}
                    className="w-full h-10 p-1 bg-white border border-slate-300 rounded-md cursor-pointer"
                />
            </div>
        </div>
    );
};


// FIX: 'useRef' was not defined. It is now imported from React.
const ProjectSettingsPanel: React.FC<{
  settings: ProjectSettings;
  onUpdate: (update: Partial<ProjectSettings>) => void;
  onExport: () => void;
  onImport: (event: React.ChangeEvent<HTMLInputElement>) => void;
}> = ({ settings, onUpdate, onExport, onImport }) => {
  const fileInputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Cog6ToothIcon />
          <h3 className="text-lg font-bold text-gray-800">Project Settings</h3>
        </div>
      <div>
        <label htmlFor="projectName" className={commonLabelClasses}>Project Name</label>
        <input id="projectName" type="text" value={settings.name} onChange={e => onUpdate({ name: e.target.value })} className={commonInputClasses} />
      </div>
      <div>
        <label htmlFor="projectDesc" className={commonLabelClasses}>Description</label>
        <textarea id="projectDesc" value={settings.description} onChange={e => onUpdate({ description: e.target.value })} className={`${commonInputClasses} h-24`} />
      </div>
      <div>
        <label htmlFor="crawlDelay" className={commonLabelClasses}>Crawl Delay (ms)</label>
        <input id="crawlDelay" type="number" value={settings.crawlDelay} onChange={e => onUpdate({ crawlDelay: parseInt(e.target.value) || 0 })} className={commonInputClasses} />
      </div>
      <div>
        <label htmlFor="userAgent" className={commonLabelClasses}>User Agent</label>
        <input id="userAgent" type="text" value={settings.userAgent} onChange={e => onUpdate({ userAgent: e.target.value })} className={commonInputClasses} />
      </div>
      <div>
        <label htmlFor="concurrency" className={commonLabelClasses}>Concurrency</label>
        <input id="concurrency" type="number" value={settings.concurrency} onChange={e => onUpdate({ concurrency: parseInt(e.target.value) || 1 })} className={commonInputClasses} />
      </div>

      <div className="border-t pt-4 space-y-3">
        <h4 className="font-semibold text-gray-700">Configuration</h4>
        <div className="flex gap-2">
            <input type="file" ref={fileInputRef} onChange={onImport} className="hidden" accept=".json" />
            <button onClick={() => fileInputRef.current?.click()} className={`${commonButtonClasses} bg-gray-600 hover:bg-gray-700 w-1/2 flex items-center justify-center gap-2`}>
                <ArrowUpTrayIcon />
                <span>Import</span>
            </button>
             <button onClick={onExport} className={`${commonButtonClasses} bg-blue-600 hover:bg-blue-700 w-1/2 flex items-center justify-center gap-2`}>
                <ArrowDownTrayIcon />
                <span>Export</span>
            </button>
        </div>
      </div>
    </div>
  );
};


// Main Panel Component
const SettingsPanel: React.FC<SettingsPanelProps> = (props) => {
  const { node, onUpdateNode, onDeleteNode, onClose, isOpen, projectSettings, onUpdateProjectSettings, onExport, onImport, onAddShapeNode } = props;

  const renderNodeSettings = () => {
    if (!node) return null;

    switch (node.type) {
      case 'start':
        return <StartNodeSettings node={node as Node<StartNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'click':
        return <ClickNodeSettings node={node as Node<ClickNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'loop':
        return <LoopNodeSettings node={node as Node<LoopNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'html-data-extractor':
         return <HTMLDataExtractorSettings node={node as Node<HTMLDataExtractorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} props={props} />;
      case 'csv-extractor':
         return <CSVExtractorSettings node={node as Node<CSVExtractorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'json-extractor':
         return <PathBasedExtractorSettings node={node as Node<JSONExtractorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} title="JSON Extractor Settings" pathPlaceholder="JSONPath (e.g., $.products[*].name)" presetKey="json" />;
      case 'xml-extractor':
         return <PathBasedExtractorSettings node={node as Node<XMLExtractorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} title="XML Extractor Settings" pathPlaceholder="XPath (e.g., //item/title)" presetKey="xml" />;
      case 'mysql-extractor':
        return <MySQLExtractorSettings node={node as Node<MySQLExtractorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'processor':
        return <ProcessorSettings node={node as Node<ProcessorNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'worker':
        return <WorkerNodeSettings node={node as Node<WorkerNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'shape':
        return <ShapeNodeSettings node={node as Node<ShapeNodeData>} onUpdate={(data) => onUpdateNode(node.id, data)} />;
      case 'repository':
        return <p className="text-sm text-gray-600 p-4 text-center">This is the Raw Items Repository. It collects items from all data sources. No configuration needed.</p>;
      default:
        return <p className="text-sm text-gray-600 p-4 text-center">Settings for this node type are not available.</p>;
    }
  };

  return (
     <aside className={`fixed top-0 right-0 h-full w-96 bg-white p-4 border-l border-slate-200 shadow-lg z-40 flex flex-col gap-4 transform transition-transform duration-300 ease-in-out md:relative md:w-96 md:transform-none md:z-20 ${isOpen ? 'translate-x-0' : 'translate-x-full'}`}>
      <div className="flex justify-between items-center border-b pb-3">
        <h2 className="text-xl font-bold text-gray-800">{node ? 'Node Settings' : 'Project Settings'}</h2>
        <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800">
          <XMarkIcon />
        </button>
      </div>
      <div className="flex-1 overflow-y-auto pr-2 -mr-2">
        <DiagramElementsPanel onAddShapeNode={onAddShapeNode} />
        <div className="mt-4">
            {node ? renderNodeSettings() : <ProjectSettingsPanel settings={projectSettings} onUpdate={onUpdateProjectSettings} onExport={onExport} onImport={onImport} />}
        </div>
      </div>
      {node && node.deletable !== false && (
        <div className="mt-auto pt-4 border-t">
          <button
            onClick={() => onDeleteNode(node.id)}
            className={`${commonButtonClasses} bg-red-600 hover:bg-red-700 flex items-center justify-center gap-2`}
          >
            <TrashIcon />
            <span>Delete Node</span>
          </button>
        </div>
      )}
    </aside>
  );
};

export default SettingsPanel;