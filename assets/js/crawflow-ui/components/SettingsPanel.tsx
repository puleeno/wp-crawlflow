
import React, { useState, useRef, ChangeEvent, useEffect, useMemo } from 'react';
import { Node } from 'reactflow';
import { XMarkIcon, Cog6ToothIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, TrashIcon, ChevronDownIcon, ChevronUpIcon, CursorArrowRaysIcon, CloudIcon } from './icons';
import { NodeData, StartNodeData, ClickNodeData, ExtractionRule, FileInputMethod, MySQLConnection, ProjectSettings, LoopNodeData, WorkerNodeData, HTMLDataExtractorNodeData, ProcessorNodeData, WorkerRule, WorkerRuleType, URLFormatRule, HTMLContainsRule, DOMValueRule, TagAttributeRule, ExtractFrom, URLSourceSettings, APISourceSettings, APIKeyAuth, BearerTokenAuth, BasicAuth, XMLSourceSettings, JSONSourceSettings, PagePagination, OffsetLimitPagination, NextURLPagination, RuleCondition, SaveToDbSettings, SendToApiSettings, GenerateCsvSettings, SendEmailSettings, CSVExtractorNodeData, ColumnMapping, JSONExtractorNodeData, PathMapping, XMLExtractorNodeData, MySQLExtractorNodeData, ShapeNodeData, DataSourceTypeRule, PresetType } from '../types';
import { PRESETS } from '../presets';
import { useRegistry } from '../hooks/useRegistry';


interface SettingsPanelProps {
  node: Node | null;
  onUpdateNode: (nodeId: string, data: NodeData) => void;
  onDeleteNode: (nodeId: string) => void;
  onClose: () => void;
  projectSettings: ProjectSettings;
  onUpdateProjectSettings: (update: Partial<ProjectSettings>) => void;
  onExport: () => void;
  onSave: () => void;
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
  // For field mapping
  nodes: Node[];
  edges: any[];
}

const commonInputClasses = "w-full p-2 bg-white text-gray-900 border border-slate-300 rounded-md shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-slate-100 disabled:text-gray-500";
const smallInputClasses = "w-full p-1.5 text-sm bg-white text-gray-900 border border-slate-300 rounded-md shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500";
const commonLabelClasses = "block text-sm font-medium text-gray-700 mb-1";
const commonButtonClasses = "w-full p-2 rounded-md font-semibold text-white transition-colors";
const smallButtonClasses = "px-2.5 py-1.5 text-sm rounded-md font-semibold text-white transition-colors";

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


// --- Start Node Settings ---
const StartNodeSettings: React.FC<{ node: Node<StartNodeData>; onUpdate: (data: StartNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;
    const { phase1ExtraActions } = useRegistry();

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

                    <TagInput 
                        label="Exclude Patterns"
                        tags={settings.excludePatterns || []}
                        onChange={(tags) => handleNestedUpdate('urlSettings', { excludePatterns: tags })}
                        placeholder="e.g., /\/admin\//i, /\/api\//i..."
                    />

                    <TagInput 
                        label="Whitelist Patterns"
                        tags={settings.whitelistPatterns || []}
                        onChange={(tags) => handleNestedUpdate('urlSettings', { whitelistPatterns: tags })}
                        placeholder="e.g., /products_list/, /products_detail/..."
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
                                <p className="text-xs text-gray-600">Use {'`{{page}}`'} tag in the API URL.</p>
                                <label className={commonLabelClasses}>Parameter Name</label>
                                <input type="text" value={(settings.paginationDetails as PagePagination).paramName || 'page'} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as PagePagination), paramName: e.target.value }})} className={commonInputClasses} />
                                <label className={commonLabelClasses}>Starts At</label>
                                <input type="number" value={(settings.paginationDetails as PagePagination).startsAt || 1} onChange={e => handleNestedUpdate('apiSettings', { paginationDetails: { ...(settings.paginationDetails as PagePagination), startsAt: parseInt(e.target.value) }})} className={commonInputClasses} />
                            </div>
                        )}
                         {settings.paginationType === 'offset-limit' && (
                            <div className="p-3 bg-slate-100 rounded-md space-y-3">
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

            {/* Phase 1 Extra Actions (data-source scoped) */}
            {phase1ExtraActions.length > 0 && (
                <CollapsibleSection title="Phase 1 Extra Actions" defaultOpen>
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600 mb-3">
                            Chọn actions chạy cho data source này ở Bonus/Phase 1.
                        </p>
                        {phase1ExtraActions.map((action) => {
                            const selectedList = data.phase1ExtraActions || [];
                            const isSelected = selectedList.includes(action.id);
                            return (
                                <div
                                    key={action.id}
                                    className={`p-3 rounded-lg border transition-colors ${
                                        isSelected
                                            ? 'bg-blue-50 border-blue-200'
                                            : 'bg-white border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <label className="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onChange={() => {
                                                const next = isSelected
                                                    ? selectedList.filter(id => id !== action.id)
                                                    : [...selectedList, action.id];
                                                handleUpdate('phase1ExtraActions', next);
                                            }}
                                            className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">
                                                {action.label}
                                            </div>
                                            {action.description && (
                                                <div className="text-sm text-gray-600 mt-1">
                                                    {action.description}
                                                </div>
                                            )}
                                            <div className="text-xs text-gray-500 mt-1">
                                                Priority: {action.priority}
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                </CollapsibleSection>
            )}
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

const HTMLDataExtractorSettings: React.FC<{
    node: Node<HTMLDataExtractorNodeData>;
    onUpdate: (data: HTMLDataExtractorNodeData) => void;
    props: Omit<SettingsPanelProps, 'onAddNode' | 'onAddShapeNode'>;
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
        const currentPresets: PresetType[] = Array.isArray(data.presets) ? data.presets : [];
        const preset = PRESETS[presetKey]?.html;
        if (!preset) return;

        const isCurrentlySelected = currentPresets.includes(presetKey as PresetType);
        
        let newRules = [...(data.customRules || [])];

        if (isCurrentlySelected) {
            // Deselecting: remove this preset's rules
            const presetRuleIds = new Set(preset.rules.map(r => r.id));
            newRules = newRules.filter(r => !presetRuleIds.has(r.id));
            const newPresets = currentPresets.filter(p => p !== presetKey);
            onUpdate({ ...data, presets: newPresets, customRules: newRules });
        } else {
            // Selecting: add this preset's rules (if not already present by ID)
            const existingRuleIds = new Set(newRules.map(r => r.id));
            const rulesToAdd = preset.rules.filter(r => !existingRuleIds.has(r.id));
            newRules = [...newRules, ...rulesToAdd];
            const newPresets: PresetType[] = [...currentPresets, presetKey as PresetType];
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
    
    /**
     * Inject <base> tag into HTML with the domain from the URL
     */
    const injectBaseTag = (html: string, url: string): string => {
        try {
            const urlObj = new URL(url);
            // Create base URL (scheme + host + path directory, not filename)
            // urlObj.host already includes port if present
            let baseUrl = `${urlObj.protocol}//${urlObj.host}`;
            
            // Add path (directory part, not filename)
            if (urlObj.pathname) {
                const path = urlObj.pathname;
                // Remove filename if exists (check if last segment has extension)
                const pathParts = path.split('/').filter(p => p);
                if (pathParts.length > 0) {
                    const lastPart = pathParts[pathParts.length - 1];
                    // If last part looks like a filename (has extension), remove it
                    if (lastPart.includes('.') && !lastPart.startsWith('.')) {
                        pathParts.pop();
                    }
                }
                const dirPath = '/' + pathParts.join('/');
                baseUrl += dirPath.endsWith('/') ? dirPath : dirPath + '/';
            } else {
                baseUrl += '/';
            }
            
            const baseTag = `<base href="${baseUrl}">`;
            
            // Check if base tag already exists
            if (html.toLowerCase().includes('<base')) {
                // Replace existing base tag
                return html.replace(/<base[^>]*>/i, baseTag);
            }
            
            // Try to inject into <head>
            const headMatch = html.match(/<head[^>]*>/i);
            if (headMatch && headMatch.index !== undefined) {
                const insertPos = headMatch.index + headMatch[0].length;
                return html.slice(0, insertPos) + '\n    ' + baseTag + '\n' + html.slice(insertPos);
            }
            
            // Try to inject after <html> tag
            const htmlMatch = html.match(/<html[^>]*>/i);
            if (htmlMatch && htmlMatch.index !== undefined) {
                const insertPos = htmlMatch.index + htmlMatch[0].length;
                return html.slice(0, insertPos) + '\n' + baseTag + '\n' + html.slice(insertPos);
            }
            
            // If no head or html tag, prepend to body
            return baseTag + '\n' + html;
        } catch (error) {
            console.error("Failed to inject base tag:", error);
            return html;
        }
    };

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
            let htmlContent = await response.text();
            
            // Inject <base> tag before loading into inspector
            htmlContent = injectBaseTag(htmlContent, data.inspectorUrl);
            
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
                                        {rule.extract === 'attribute' && (
                                            <>
                                                <input type="text" placeholder="Attribute Name (e.g., href)" value={rule.attribute || ''} onChange={e => handleRuleChange(rule.id, 'attribute', e.target.value)} className={smallInputClasses} />
                                                <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={rule.extractMultiple || false}
                                                        onChange={e => handleRuleChange(rule.id, 'extractMultiple', e.target.checked)}
                                                        className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                    />
                                                    <span>Extract Multiple (array)</span>
                                                </label>
                                            </>
                                        )}
                                        {(rule.extract === 'text' || rule.extract === 'html') && (
                                            <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={rule.extractMultiple || false}
                                                    onChange={e => handleRuleChange(rule.id, 'extractMultiple', e.target.checked)}
                                                    className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                />
                                                <span>Extract Multiple (array)</span>
                                            </label>
                                        )}
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
};


// --- Processor Node Settings ---
const ProcessorNodeSettings: React.FC<{ 
    node: Node<ProcessorNodeData>; 
    onUpdate: (data: ProcessorNodeData) => void;
    nodes: Node[];
    edges: any[];
}> = ({ node, onUpdate, nodes, edges }) => {
    const { data } = node;
    const { processors } = useRegistry();

    const handleTypeChange = (type: string) => {
        // When changing processor type, reset settings to empty object
        // Backend will provide default settings
        onUpdate({ 
            processorType: type as any, 
            settings: {} as any
        } as ProcessorNodeData);
    };

    const handleSettingsChange = (key: string, value: any) => {
        // FIX: Cast to ProcessorNodeData to resolve discriminated union type mismatch
        onUpdate({ ...data, settings: { ...data.settings, [key]: value } } as ProcessorNodeData);
    };

    // Get available fields from upstream extractor nodes
    const getAvailableFields = (startNodeId: string): string[] => {
        const fields = new Set<string>();
        const visited = new Set<string>();
        const queue = [startNodeId];
        const extractorTypes = ['html-data-extractor', 'csv-extractor', 'json-extractor', 'xml-extractor', 'mysql-extractor'];

        while (queue.length > 0) {
            const currentId = queue.shift()!;
            if (visited.has(currentId)) continue;
            visited.add(currentId);

            const currentNode = nodes.find(n => n.id === currentId);
            if (!currentNode) continue;

            if (extractorTypes.includes(currentNode.type || '')) {
                if (currentNode.type === 'html-data-extractor') {
                    const d = currentNode.data as HTMLDataExtractorNodeData;
                    d.customRules.forEach(r => fields.add(r.name));
                    (d.presets || []).forEach(presetKey => {
                        PRESETS[presetKey as string]?.html?.rules.forEach(r => fields.add(r.name));
                    });
                } else if (currentNode.type === 'csv-extractor') {
                    const d = currentNode.data as CSVExtractorNodeData;
                    d.mappings.forEach(m => fields.add(m.fieldName));
                    (d.presets || []).forEach(presetKey => {
                        PRESETS[presetKey as string]?.csv?.mappings.forEach(m => fields.add(m.fieldName));
                    });
                } else if (currentNode.type === 'json-extractor') {
                    const d = currentNode.data as JSONExtractorNodeData;
                    d.mappings.forEach(m => fields.add(m.fieldName));
                    (d.presets || []).forEach(presetKey => {
                        PRESETS[presetKey as string]?.json?.mappings.forEach(m => fields.add(m.fieldName));
                    });
                } else if (currentNode.type === 'xml-extractor') {
                    const d = currentNode.data as XMLExtractorNodeData;
                    d.mappings.forEach(m => fields.add(m.fieldName));
                    (d.presets || []).forEach(presetKey => {
                        PRESETS[presetKey as string]?.xml?.mappings.forEach(m => fields.add(m.fieldName));
                    });
                } else if (currentNode.type === 'mysql-extractor') {
                    const d = currentNode.data as MySQLExtractorNodeData;
                    d.mappings.forEach(m => fields.add(m.fieldName));
                    (d.presets || []).forEach(presetKey => {
                        PRESETS[presetKey as string]?.mysql?.mappings.forEach(m => fields.add(m.fieldName));
                    });
                }
                continue;
            }

            // Find incoming edges to traverse upstream
            const incomingEdges = edges.filter((e: any) => e.target === currentId);
            incomingEdges.forEach((e: any) => queue.push(e.source));
        }
        return Array.from(fields);
    };

    const availableFields = useMemo(() => getAvailableFields(node.id), [nodes, edges, node.id]);

    const updateMapping = (field: string, value: string) => {
        const currentSettings = data.settings as any;
        const newMapping = { ...(currentSettings.fieldMappings || {}), [field]: value };
        handleSettingsChange('fieldMappings', newMapping);
    };

    const renderMappingSection = (destLabel: string) => {
        const settings = data.settings as any;
        const isAutoMap = settings.autoMapFields || false;

        return (
            <div className="pt-2 border-t mt-2">
                <label className="flex items-center gap-2 mb-2">
                    <input
                        type="checkbox"
                        checked={isAutoMap}
                        onChange={e => handleSettingsChange('autoMapFields', e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span className="text-sm font-semibold text-gray-700">Auto Map Fields</span>
                </label>
                
                <div className="space-y-2 bg-slate-50 p-2 rounded border border-slate-200">
                    <div className="text-xs font-bold text-gray-500 uppercase flex justify-between px-1">
                        <span>Extracted Field</span>
                        <span>{destLabel}</span>
                    </div>
                    {availableFields.length === 0 ? (
                        <div className="text-xs text-gray-400 italic text-center py-2">No fields found from upstream extractors.</div>
                    ) : (
                        availableFields.map(field => (
                            <div key={field} className="flex items-center gap-2">
                                <div className="flex-1 text-sm bg-white border border-gray-200 px-2 py-1.5 rounded text-gray-700 truncate" title={field}>
                                    {field}
                                </div>
                                <span className="text-gray-400">→</span>
                                <input 
                                    type="text" 
                                    placeholder={field} 
                                    value={isAutoMap ? field : ((settings.fieldMappings || {})[field] || '')}
                                    onChange={e => updateMapping(field, e.target.value)}
                                    disabled={isAutoMap}
                                    className={`${smallInputClasses} flex-1 ${isAutoMap ? 'bg-slate-100 cursor-not-allowed' : ''}`}
                                />
                            </div>
                        ))
                    )}
                </div>
            </div>
        );
    };

    // Check if processor supports field mapping
    const supportsFieldMapping = ['save_to_database', 'save-to-database', 'send_to_api', 'send-to-api', 'generate_csv_file', 'generate-csv-file'].includes(data.processorType);

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Processor Settings</h3>
            <div>
                <label className={commonLabelClasses}>Processor Type</label>
                <select value={data.processorType} onChange={e => handleTypeChange(e.target.value)} className={commonInputClasses}>
                    {processors.map(p => (
                        <option key={p.type} value={p.type}>
                            {p.icon} {p.label}
                        </option>
                    ))}
                </select>
            </div>

            <CollapsibleSection title="Configuration" defaultOpen>
                <div className="space-y-3">
                    {processors.find(p => p.type === data.processorType)?.configFields?.map(field => {
                        const settings = data.settings as any;
                        const value = settings[field.name] ?? field.default ?? '';
                        
                        return (
                            <div key={field.name}>
                                <label htmlFor={field.name} className={commonLabelClasses}>
                                    {field.label}
                                    {field.required && <span className="text-red-500 ml-1">*</span>}
                                </label>
                                
                                {field.type === 'select' && (
                                    <select 
                                        id={field.name}
                                        value={value}
                                        onChange={e => handleSettingsChange(field.name, e.target.value)}
                                        className={commonInputClasses}
                                    >
                                        {field.options && (
                                            typeof field.options === 'object' && !Array.isArray(field.options) ? (
                                                // Object format: { value: label }
                                                Object.entries(field.options).map(([val, label]) => (
                                                    <option key={val} value={val}>{label}</option>
                                                ))
                                            ) : (
                                                // Array format: ['value1', 'value2']
                                                (field.options as string[]).map(opt => (
                                                    <option key={opt} value={opt}>{opt}</option>
                                                ))
                                            )
                                        )}
                                    </select>
                                )}
                                
                                {field.type === 'textarea' && (
                                    <textarea
                                        id={field.name}
                                        value={value}
                                        onChange={e => handleSettingsChange(field.name, e.target.value)}
                                        placeholder={field.placeholder}
                                        className={`${commonInputClasses} h-24`}
                                    />
                                )}
                                
                                {field.type === 'checkbox' && (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id={field.name}
                                            checked={!!value}
                                            onChange={e => handleSettingsChange(field.name, e.target.checked)}
                                            className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <label htmlFor={field.name} className="text-sm text-gray-700">{field.placeholder || field.label}</label>
                                    </div>
                                )}
                                
                                {['text', 'number', 'url', 'password'].includes(field.type) && (
                                    <input
                                        type={field.type}
                                        id={field.name}
                                        value={value}
                                        onChange={e => handleSettingsChange(field.name, field.type === 'number' ? Number(e.target.value) : e.target.value)}
                                        placeholder={field.placeholder}
                                        className={commonInputClasses}
                                    />
                                )}
                                
                                {field.type === 'json-preview' && (
                                    <div className="mt-2">
                                        <div className="bg-gray-50 border border-gray-200 rounded-md overflow-hidden">
                                            <div className="bg-gray-100 px-3 py-2 border-b border-gray-200">
                                                <span className="text-xs font-medium text-gray-700">JSON Preview (Read-only)</span>
                                            </div>
                                            <pre className="p-4 text-xs overflow-x-auto font-mono text-gray-800" style={{ maxHeight: '400px', overflowY: 'auto', margin: 0 }}>
                                                {field.value || '{}'}
                                            </pre>
                                        </div>
                                    </div>
                                )}
                                
                                {field.description && (
                                    <p className="text-xs text-gray-500 mt-1">{field.description}</p>
                                )}
                            </div>
                        );
                    })}
                    
                    {!processors.find(p => p.type === data.processorType)?.configFields && (
                        <p className="text-sm text-gray-500 italic">No configuration fields available for this processor.</p>
                    )}
                </div>
            </CollapsibleSection>

            {supportsFieldMapping && renderMappingSection(
                data.processorType === 'save_to_database' || data.processorType === 'save-to-database' ? 'DB Column' :
                data.processorType === 'send_to_api' || data.processorType === 'send-to-api' ? 'API Field' :
                'Target Field'
            )}
        </div>
    );
};

const WorkerNodeSettings: React.FC<{ node: Node<WorkerNodeData>; onUpdate: (data: WorkerNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    const addRule = () => {
        const newRule: WorkerRule = { id: `${Date.now()}`, type: 'dom-value', selector: '', condition: 'exists', value: '' };
        onUpdate({ ...data, detectionRules: [...data.detectionRules, newRule] });
    };

    const updateRule = (id: string, ruleUpdate: Partial<WorkerRule>) => {
        const newRules = data.detectionRules.map(r => r.id === id ? { ...r, ...ruleUpdate } as WorkerRule : r);
        onUpdate({ ...data, detectionRules: newRules });
    };

    const removeRule = (id: string) => {
        onUpdate({ ...data, detectionRules: data.detectionRules.filter(r => r.id !== id) });
    };

    return (
        <div className="space-y-4">
             <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Worker Settings</h3>
             <div className="flex justify-between items-center">
                 <label className={commonLabelClasses}>Priority</label>
                 <input type="number" value={data.priority} onChange={e => onUpdate({ ...data, priority: parseInt(e.target.value) })} className={`${commonInputClasses} w-24`} />
             </div>

             <div className="flex justify-between items-center">
                 <div className="flex items-center gap-2">
                     <input
                         type="checkbox"
                         id="isArchive"
                         checked={data.isArchive || false}
                         onChange={e => onUpdate({ ...data, isArchive: e.target.checked })}
                         className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                     />
                     <label htmlFor="isArchive" className={commonLabelClasses + " mb-0 cursor-pointer"}>
                         Is Archive Page
                     </label>
                 </div>
                 <span className="text-xs text-gray-500">
                     Category pages, listing pages that contain multiple items
                 </span>
             </div>

             {/* Content Selectors for Archive Page - only show when Is Archive Page is checked */}
             {data.isArchive && (
                 <div className="border border-slate-200 rounded-lg p-4 bg-slate-50">
                     <h4 className="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                         <CursorArrowRaysIcon className="w-4 h-4" />
                         Content Selectors for Update Detection
                     </h4>
                     <p className="text-xs text-gray-600 mb-3">
                         Define the wrapper area containing all products to check for updates
                     </p>
                     
                     <div className="space-y-4">
                         <div>
                             <label className={commonLabelClasses}>
                                 Products Wrapper Selector
                             </label>
                             <input
                                 type="text"
                                 value={data.archiveProductsWrapper || ''}
                                 onChange={e => onUpdate({ ...data, archiveProductsWrapper: e.target.value })}
                                 placeholder=".products-container, .product-list, .category-products"
                                 className={commonInputClasses}
                             />
                             <span className="text-xs text-gray-500">
                                 CSS selector for the wrapper containing all products
                             </span>
                         </div>
                         
                         <div>
                             <label className={commonLabelClasses}>
                                 URL Contains String (Optional)
                             </label>
                             <input
                                 type="text"
                                 value={data.archiveUrlContains || ''}
                                 onChange={e => onUpdate({ ...data, archiveUrlContains: e.target.value })}
                                 placeholder="product, category, item"
                                 className={commonInputClasses}
                             />
                             <span className="text-xs text-gray-500">
                                 Only check URLs containing this string (leave empty to check all URLs)
                             </span>
                         </div>
                     </div>
                 </div>
             )}

             <CollapsibleSection title="Detection Rules" defaultOpen>
                <div className="space-y-4">
                     <div className="flex items-center gap-2 mb-2 p-2 bg-slate-50 rounded border border-slate-200">
                        <span className="text-sm text-gray-700 font-medium">Match:</span>
                        <select value={data.detectionLogic} onChange={e => onUpdate({ ...data, detectionLogic: e.target.value as 'and' | 'or' })} className={`${smallInputClasses} w-24 border-gray-300`}>
                            <option value="and">ALL</option>
                            <option value="or">ANY</option>
                        </select>
                        <span className="text-sm text-gray-700">of the following rules:</span>
                    </div>

                    {data.detectionRules.map((rule, index) => (
                        <div key={rule.id} className="p-4 border border-slate-200 bg-white rounded-lg shadow-sm space-y-3 relative group transition-all hover:border-blue-300">
                            
                            <div className="flex justify-between items-center border-b border-slate-100 pb-2 mb-2">
                                <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Rule {index + 1}</span>
                                <button 
                                    onClick={() => removeRule(rule.id)} 
                                    className="text-gray-400 hover:text-red-500 p-1.5 hover:bg-red-50 rounded transition-colors"
                                    title="Remove Rule"
                                >
                                    <TrashIcon />
                                </button>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 mb-1">Rule Type</label>
                                    <select value={rule.type} onChange={e => updateRule(rule.id, { type: e.target.value as WorkerRuleType })} className={commonInputClasses}>
                                        <option value="url-format">URL Format</option>
                                        <option value="html-contains">HTML Contains</option>
                                        <option value="dom-value">DOM Element Value</option>
                                        <option value="tag-attribute">Tag Attribute</option>
                                        <option value="data-source-type">Data Source Type</option>
                                    </select>
                                </div>

                                {rule.type === 'url-format' && (
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Regex Pattern</label>
                                        <input type="text" placeholder="e.g., /products/.*" value={(rule as URLFormatRule).pattern} onChange={e => updateRule(rule.id, { pattern: e.target.value })} className={commonInputClasses} />
                                    </div>
                                )}
                                {rule.type === 'html-contains' && (
                                     <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Text Content</label>
                                        <input type="text" placeholder="Text to match" value={(rule as HTMLContainsRule).text} onChange={e => updateRule(rule.id, { text: e.target.value })} className={commonInputClasses} />
                                    </div>
                                )}
                                {rule.type === 'dom-value' && (
                                    <>
                                         <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-1">CSS Selector</label>
                                            <input type="text" placeholder="e.g., .price" value={(rule as DOMValueRule).selector} onChange={e => updateRule(rule.id, { selector: e.target.value })} className={commonInputClasses} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                 <label className="block text-xs font-medium text-gray-500 mb-1">Condition</label>
                                                <select value={(rule as DOMValueRule).condition} onChange={e => updateRule(rule.id, { condition: e.target.value as RuleCondition })} className={commonInputClasses}>
                                                    <option value="exists">Exists</option>
                                                    <option value="not-exists">Not Exists</option>
                                                    <option value="contains">Contains</option>
                                                    <option value="not-contains">Not Contains</option>
                                                    <option value="matches-regex">Matches Regex</option>
                                                </select>
                                            </div>
                                            {['contains', 'not-contains', 'matches-regex'].includes((rule as DOMValueRule).condition) && (
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-500 mb-1">Value</label>
                                                    <input type="text" placeholder="Value to check" value={(rule as DOMValueRule).value} onChange={e => updateRule(rule.id, { value: e.target.value })} className={commonInputClasses} />
                                                </div>
                                            )}
                                        </div>
                                    </>
                                )}
                                {rule.type === 'tag-attribute' && (
                                    <>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-1">CSS Selector</label>
                                            <input type="text" placeholder="e.g., a.link" value={(rule as TagAttributeRule).selector} onChange={e => updateRule(rule.id, { selector: e.target.value })} className={commonInputClasses} />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-1">Attribute</label>
                                            <input type="text" placeholder="e.g., href" value={(rule as TagAttributeRule).attribute} onChange={e => updateRule(rule.id, { attribute: e.target.value })} className={commonInputClasses} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                 <label className="block text-xs font-medium text-gray-500 mb-1">Condition</label>
                                                <select value={(rule as TagAttributeRule).condition} onChange={e => updateRule(rule.id, { condition: e.target.value as RuleCondition })} className={commonInputClasses}>
                                                    <option value="exists">Exists</option>
                                                    <option value="not-exists">Not Exists</option>
                                                    <option value="contains">Contains</option>
                                                    <option value="not-contains">Not Contains</option>
                                                    <option value="matches-regex">Matches Regex</option>
                                                </select>
                                            </div>
                                            {['contains', 'not-contains', 'matches-regex'].includes((rule as TagAttributeRule).condition) && (
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-500 mb-1">Value</label>
                                                    <input type="text" placeholder="Value to check" value={(rule as TagAttributeRule).value} onChange={e => updateRule(rule.id, { value: e.target.value })} className={commonInputClasses} />
                                                </div>
                                            )}
                                        </div>
                                    </>
                                )}
                                {rule.type === 'data-source-type' && (
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Source Type</label>
                                        <select value={(rule as DataSourceTypeRule).sourceType || 'url'} onChange={e => updateRule(rule.id, { sourceType: e.target.value } as any)} className={commonInputClasses}>
                                            <option value="url">URL</option>
                                            <option value="api">API</option>
                                            <option value="xml">XML</option>
                                            <option value="csv">CSV</option>
                                            <option value="json">JSON</option>
                                            <option value="mysql">MySQL</option>
                                        </select>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    <button onClick={addRule} className={`${commonButtonClasses} bg-purple-600 hover:bg-purple-700 mt-4 py-3 shadow-sm`}>+ Add Detection Rule</button>
                </div>
             </CollapsibleSection>
        </div>
    )
};

const ShapeNodeSettings: React.FC<{ node: Node<ShapeNodeData>; onUpdate: (data: ShapeNodeData) => void }> = ({ node, onUpdate }) => {
    const { data } = node;

    return (
        <div className="space-y-4">
             <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Shape Settings</h3>
             <div>
                <label className={commonLabelClasses}>Label</label>
                <input type="text" value={data.label} onChange={e => onUpdate({ ...data, label: e.target.value })} className={commonInputClasses} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className={commonLabelClasses}>Background Color</label>
                    <input type="color" value={data.backgroundColor} onChange={e => onUpdate({ ...data, backgroundColor: e.target.value })} className="w-full h-10 p-1 rounded-md cursor-pointer border border-gray-300" />
                </div>
                 <div>
                    <label className={commonLabelClasses}>Border Color</label>
                    <input type="color" value={data.borderColor} onChange={e => onUpdate({ ...data, borderColor: e.target.value })} className="w-full h-10 p-1 rounded-md cursor-pointer border border-gray-300" />
                </div>
                 <div>
                    <label className={commonLabelClasses}>Text Color</label>
                    <input type="color" value={data.textColor} onChange={e => onUpdate({ ...data, textColor: e.target.value })} className="w-full h-10 p-1 rounded-md cursor-pointer border border-gray-300" />
                </div>
            </div>
        </div>
    );
};


// --- Main Settings Panel Component ---
const SettingsPanel: React.FC<SettingsPanelProps> = (props) => {
    const { node, onUpdateNode, onDeleteNode, onClose, projectSettings, onUpdateProjectSettings, onExport, onSave, onImport, isOpen, nodes, edges } = props;

    const renderNodeSettings = () => {
        if (!node) return null;

        const handleUpdate = (data: NodeData) => onUpdateNode(node.id, data);

        switch (node.type) {
            case 'start':
                return <StartNodeSettings node={node as Node<StartNodeData>} onUpdate={handleUpdate as (data: StartNodeData) => void} />;
            case 'click':
                return <ClickNodeSettings node={node as Node<ClickNodeData>} onUpdate={handleUpdate as (data: ClickNodeData) => void} />;
            case 'loop':
                return <LoopNodeSettings node={node as Node<LoopNodeData>} onUpdate={handleUpdate as (data: LoopNodeData) => void} />;
            case 'worker':
                return <WorkerNodeSettings node={node as Node<WorkerNodeData>} onUpdate={handleUpdate as (data: WorkerNodeData) => void} />;
            case 'html-data-extractor':
                return <HTMLDataExtractorSettings node={node as Node<HTMLDataExtractorNodeData>} onUpdate={handleUpdate as (data: HTMLDataExtractorNodeData) => void} props={props} />;
            case 'csv-extractor':
                return <CSVExtractorSettings node={node as Node<CSVExtractorNodeData>} onUpdate={handleUpdate as (data: CSVExtractorNodeData) => void} />;
            case 'json-extractor':
                return <PathBasedExtractorSettings node={node as Node<JSONExtractorNodeData>} onUpdate={handleUpdate as (data: JSONExtractorNodeData) => void} title="JSON Data Extractor" pathPlaceholder="JSON Path (e.g., $.items[0].name)" presetKey="json" />;
            case 'xml-extractor':
                return <PathBasedExtractorSettings node={node as Node<XMLExtractorNodeData>} onUpdate={handleUpdate as (data: XMLExtractorNodeData) => void} title="XML Data Extractor" pathPlaceholder="XPath (e.g., /root/item/name)" presetKey="xml" />;
            case 'mysql-extractor':
                return <MySQLExtractorSettings node={node as Node<MySQLExtractorNodeData>} onUpdate={handleUpdate as (data: MySQLExtractorNodeData) => void} />;
            case 'processor':
                return <ProcessorNodeSettings node={node as Node<ProcessorNodeData>} onUpdate={handleUpdate as (data: ProcessorNodeData) => void} nodes={nodes} edges={edges} />;
            case 'shape':
                return <ShapeNodeSettings node={node as Node<ShapeNodeData>} onUpdate={handleUpdate as (data: ShapeNodeData) => void} />;
            case 'repository':
                return <div className="text-gray-500 italic text-center p-4">This node holds the data. No specific settings available.</div>;
            case 'reception':
                return <div className="text-gray-500 italic text-center p-4">Configuration for Reception is handled via logic rules. (Coming Soon)</div>;
            case 'completion':
                return <div className="text-gray-500 italic text-center p-4">End of workflow. Reporting settings are global.</div>;
            default:
                return <div className="text-gray-500 italic text-center p-4">No settings available for this node type.</div>;
        }
    };

    const renderProjectSettings = () => {
        const { httpClients, phase1Actions } = useRegistry();

        const getSelectedPhase1Actions = () => {
            const defaultSelected = phase1Actions.find(a => a.id === 'data_update_checker') ? ['data_update_checker'] : [];
            return projectSettings.phase1Actions ?? defaultSelected;
        };

        const handleProjectActionToggle = (actionId: string) => {
            const current = getSelectedPhase1Actions();
            const next = current.includes(actionId)
                ? current.filter(id => id !== actionId)
                : [...current, actionId];
            onUpdateProjectSettings({ phase1Actions: next });
        };
        
        return (
        <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-800 border-b pb-2">Project Settings</h3>
            
            <div className="flex items-center justify-between mb-4 bg-slate-50 p-3 rounded-lg border border-slate-200">
                <div className="flex flex-col">
                    <span className="text-sm font-bold text-gray-700">Enable Project</span>
                    <span className="text-xs text-gray-500">{projectSettings.enabled ? 'Project is active' : 'Project is disabled'}</span>
                </div>
                <button
                    type="button"
                    onClick={() => onUpdateProjectSettings({ enabled: !projectSettings.enabled })}
                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${projectSettings.enabled ? 'bg-green-500' : 'bg-gray-300'}`}
                    role="switch"
                    aria-checked={projectSettings.enabled}
                >
                    <span
                        aria-hidden="true"
                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${projectSettings.enabled ? 'translate-x-5' : 'translate-x-0'}`}
                    />
                </button>
            </div>

            <div>
                <label className={commonLabelClasses}>Project Name</label>
                <input type="text" value={projectSettings.name} onChange={e => onUpdateProjectSettings({ name: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>Description</label>
                <textarea value={projectSettings.description} onChange={e => onUpdateProjectSettings({ description: e.target.value })} className={`${commonInputClasses} h-24`} />
            </div>
            <div className="space-y-4">
                <div>
                    <label className={commonLabelClasses}>Schedule (khoảng cách giữa mỗi lần chạy)</label>
                    <select
                        value={projectSettings.crawlDelay ?? 300000}
                        onChange={e => onUpdateProjectSettings({ crawlDelay: parseInt(e.target.value) })}
                        className={commonInputClasses}
                    >
                        {[
                            { value: 60000, label: 'Mỗi 1 phút' },
                            { value: 300000, label: 'Mỗi 5 phút' }, // default
                            { value: 600000, label: 'Mỗi 10 phút' },
                            { value: 900000, label: 'Mỗi 15 phút' },
                            { value: 1800000, label: 'Mỗi 30 phút' },
                            { value: 3600000, label: 'Mỗi 1 giờ' },
                            { value: 7200000, label: 'Mỗi 2 giờ' },
                            { value: 10800000, label: 'Mỗi 3 giờ' },
                            { value: 14400000, label: 'Mỗi 4 giờ' },
                            { value: 21600000, label: 'Mỗi 6 giờ' },
                            { value: 28800000, label: 'Mỗi 8 giờ' },
                            { value: 43200000, label: 'Mỗi 12 giờ' },
                            { value: 86400000, label: 'Mỗi 1 ngày' },
                            { value: 172800000, label: 'Mỗi 2 ngày' },
                            { value: 259200000, label: 'Mỗi 3 ngày' },
                            { value: 432000000, label: 'Mỗi 5 ngày' },
                            { value: 604800000, label: 'Mỗi 7 ngày' },
                            { value: 864000000, label: 'Mỗi 10 ngày' },
                            { value: 1296000000, label: 'Mỗi 15 ngày' },
                        ].map(opt => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className={commonLabelClasses}>Số items tối đa mỗi lần cron xử lý</label>
                    <input
                        type="number"
                        value={projectSettings.concurrency ?? 50}
                        onChange={e => onUpdateProjectSettings({ concurrency: parseInt(e.target.value) || 50 })}
                        className={commonInputClasses}
                        min={1}
                    />
                </div>
            </div>
            {phase1Actions.length > 0 && (
                <div className="space-y-2">
                    <label className={commonLabelClasses}>Project Actions: Extra Phase</label>
                    <p className="text-xs text-gray-500">
                        Chọn các actions chạy ở Bonus/Phase 1 (ví dụ: DataUpdateCheckerAction).
                    </p>
                    <div className="space-y-2">
                        {phase1Actions.map(action => {
                            const selected = (projectSettings.phase1Actions ?? (phase1Actions.find(a => a.id === 'data_update_checker') ? ['data_update_checker'] : [])).includes(action.id);
                            const isDataUpdateChecker = action.id === 'data_update_checker';
                            return (
                                <label
                                    key={action.id}
                                    className={`flex items-start gap-3 p-3 rounded-lg border transition-colors cursor-pointer ${
                                        selected ? 'bg-blue-50 border-blue-200' : 'bg-white border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected}
                                        onChange={() => handleProjectActionToggle(action.id)}
                                        className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div className="flex-1">
                                        <div className="font-medium text-gray-900">{action.label}</div>
                                        {action.description && (
                                            <div className="text-sm text-gray-600 mt-1">{action.description}</div>
                                        )}
                                        <div className="text-xs text-gray-500 mt-1">Priority: {action.priority}</div>
                                        {isDataUpdateChecker && selected && (
                                            <div className="mt-3 pt-3 border-t border-slate-200">
                                                <label className="text-sm font-medium text-gray-700 block mb-1">
                                                    Data Update Checker Schedule
                                                </label>
                                                <select 
                                                    value={projectSettings.dataUpdateCheckerSchedule || ''} 
                                                    onChange={e => onUpdateProjectSettings({ dataUpdateCheckerSchedule: e.target.value || undefined })} 
                                                    className={smallInputClasses}
                                                >
                                                    <option value="">Sử dụng schedule của project</option>
                                                    <option value="every_5_minutes">Mỗi 5 phút</option>
                                                    <option value="every_15_minutes">Mỗi 15 phút</option>
                                                    <option value="every_30_minutes">Mỗi 30 phút</option>
                                                    <option value="hourly">Mỗi 1 giờ</option>
                                                    <option value="twicedaily">Mỗi 12 giờ</option>
                                                    <option value="daily">Mỗi ngày</option>
                                                    <option value="weekly">Mỗi tuần</option>
                                                    <option value="15days">Mỗi 15 ngày</option>
                                                    <option value="monthly">Mỗi tháng</option>
                                                </select>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Nếu không chọn, Data Update Checker sẽ sử dụng schedule của project
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </label>
                            );
                        })}
                    </div>
                </div>
            )}
            <div>
                <label className={commonLabelClasses}>User Agent</label>
                <input type="text" value={projectSettings.userAgent} onChange={e => onUpdateProjectSettings({ userAgent: e.target.value })} className={commonInputClasses} />
            </div>
            <div>
                <label className={commonLabelClasses}>HTTP Client</label>
                <select 
                    value={projectSettings.httpClient || ''} 
                    onChange={e => onUpdateProjectSettings({ httpClient: e.target.value || undefined })} 
                    className={commonInputClasses}
                >
                    <option value="">Default (Auto-select)</option>
                    {httpClients.map(client => (
                        <option key={client.name} value={client.name}>
                            {client.icon} {client.label}
                        </option>
                    ))}
                </select>
                {projectSettings.httpClient && (
                    <p className="text-xs text-gray-500 mt-1">
                        {httpClients.find(c => c.name === projectSettings.httpClient)?.description || ''}
                    </p>
                )}
            </div>

            <div className="pt-6 border-t mt-6">
                <h4 className="font-semibold text-gray-700 mb-3">Actions</h4>
                 <div className="flex gap-2">
                    <button onClick={onExport} className={`${commonButtonClasses} bg-indigo-600 hover:bg-indigo-700 flex items-center justify-center gap-2`}>
                        <ArrowDownTrayIcon /> Export JSON
                    </button>
                     <label className={`${commonButtonClasses} bg-slate-600 hover:bg-slate-700 flex items-center justify-center gap-2 cursor-pointer`}>
                        <ArrowUpTrayIcon /> Import JSON
                        <input type="file" onChange={onImport} className="hidden" accept=".json" />
                    </label>
                </div>
                <button
                    onClick={onSave}
                    className="w-full mt-3 p-3 rounded-md font-bold text-white bg-green-600 hover:bg-green-700 transition-colors flex items-center justify-center gap-2 shadow-md"
                >
                    <CloudIcon /> Save Project
                </button>
            </div>
        </div>
        );
    };

    return (
        <aside className={`fixed top-0 right-0 h-full w-80 bg-white p-6 border-l border-gray-200 shadow-xl z-40 overflow-y-auto transform transition-transform duration-300 ease-in-out ${isOpen ? 'translate-x-0' : 'translate-x-full'}`}>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-bold text-gray-800">{node ? 'Node Settings' : 'Project Config'}</h2>
                <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800">
                    <XMarkIcon />
                </button>
            </div>
            
            {node ? (
                <>
                    {renderNodeSettings()}
                    {node.deletable !== false && (
                         <div className="mt-8 pt-6 border-t">
                            <button 
                                onClick={() => onDeleteNode(node.id)}
                                className="w-full p-2 bg-red-100 text-red-700 rounded-md font-semibold hover:bg-red-200 transition-colors flex items-center justify-center gap-2"
                            >
                                <TrashIcon /> Delete Node
                            </button>
                        </div>
                    )}
                </>
            ) : renderProjectSettings()}
        </aside>
    );
};

export default SettingsPanel;
