import React from 'react';
import type { Node, Edge } from 'reactflow';
import type { NodeData, ClickNodeData, LoopNodeData, StartNodeData, DataSourceType, WorkerNodeData, DataExtractorNodeData, ProcessorNodeData, CompletionNodeData } from '../types';
import {
  CursorArrowRaysIcon,
  DocumentMagnifyingGlassIcon,
  ArrowPathIcon,
  GlobeAltIcon,
  CloudIcon,
  DocumentTextIcon,
  DatabaseIcon,
  CpuChipIcon,
  TableCellsIcon,
  Cog6ToothIcon,
  XMarkIcon,
  FlagIcon,
} from './icons';
import { PROCESSORS } from '../presets';

interface SidebarProps {
  onAddNode: (type: string, data: NodeData, sourceNode?: Node | null) => void;
  selectedNode: Node | null;
  isOpen: boolean;
  onClose: () => void;
  nodes: Node[];
  edges: Edge[];
}

const dataSources: {
  type: DataSourceType;
  label: string;
  icon: React.ReactNode;
  defaultData: StartNodeData;
  colorClasses: string;
}[] = [
  {
    type: 'url',
    label: 'From URL',
    icon: <GlobeAltIcon />,
    defaultData: {
      sourceType: 'url',
      sourceValue: 'https://example.com',
      urlSettings: {
        scope: 'entire-website',
        excludeExtensions: ['pdf', 'jpg', 'png', 'zip', 'mp4', 'svg'],
        excludePatterns: [],
        whitelistPatterns: [],
        domainPolicy: 'all',
        domainWhitelist: [],
      }
    },
    colorClasses: 'bg-green-500 hover:bg-green-600'
  },
  {
    type: 'api',
    label: 'From API',
    icon: <CloudIcon />,
    defaultData: {
      sourceType: 'api',
      sourceValue: 'https://api.example.com/data',
      apiSettings: {
        authType: 'none',
        authDetails: {},
        paginationType: 'none',
        paginationDetails: {},
      }
    },
    colorClasses: 'bg-blue-500 hover:bg-blue-600'
  },
  {
    type: 'mysql',
    label: 'From MySQL',
    icon: <DatabaseIcon />,
    defaultData: {
      sourceType: 'mysql',
      sourceValue: { host: 'localhost', port: '3306', user: 'root', password: '', database: 'mydatabase' },
    },
    colorClasses: 'bg-red-500 hover:bg-red-600'
  },
  {
    type: 'csv',
    label: 'From CSV',
    icon: <DocumentTextIcon />,
    defaultData: { sourceType: 'csv', sourceValue: '', inputMethod: 'paste' },
    colorClasses: 'bg-purple-500 hover:bg-purple-600'
  },
  {
    type: 'xml',
    label: 'From XML',
    icon: <DocumentTextIcon />,
    defaultData: {
      sourceType: 'xml',
      sourceValue: '',
      inputMethod: 'paste',
      xmlSettings: {
        scanUrls: true,
        domainPolicy: 'all',
        domainWhitelist: [],
      }
    },
    colorClasses: 'bg-orange-500 hover:bg-orange-600'
  },
  {
    type: 'json',
    label: 'From JSON',
    icon: <DocumentTextIcon />,
    defaultData: {
      sourceType: 'json',
      sourceValue: '',
      inputMethod: 'paste',
      jsonSettings: {
        dataHandling: 'raw',
        urlSource: 'all-values',
        urlKey: '',
        domainPolicy: 'all',
        domainWhitelist: [],
      }
    },
    colorClasses: 'bg-yellow-500 hover:bg-yellow-600 text-gray-800'
  },
];


const Sidebar: React.FC<SidebarProps> = ({ onAddNode, selectedNode, isOpen, onClose, nodes, edges }) => {
  
  const handleAddNode = (type: string, data: NodeData, sourceNode?: Node | null) => {
    onAddNode(type, data, sourceNode);
    onClose();
  }
  
  const addClickNode = () => {
    const data: ClickNodeData = { selector: 'a.next-page' };
    handleAddNode('click', data, selectedNode);
  };

  const addWorkerNode = () => {
    const data: WorkerNodeData = {
      detectionRules: [{ 
          id: `${Date.now()}`,
          type: 'dom-value',
          selector: 'body', 
          condition: 'exists', 
          value: '' 
      }],
      detectionLogic: 'and',
      priority: 1,
    };
    handleAddNode('worker', data, selectedNode);
  };

  const addLoopNode = () => {
    const data: LoopNodeData = { iteratorSelector: '.item-list .item' };
    handleAddNode('loop', data, selectedNode);
  };
  
  const addDataExtractorNode = () => {
    const data: DataExtractorNodeData = {
        presets: [],
        customRules: [{
            id: '1', 
            name: 'title', 
            extractFrom: 'html-element',
            selector: 'h1', 
            extract: 'text' 
        }],
        inspectorUrl: '',
        inspectorHtmlContent: '',
    };
    handleAddNode('data-extractor', data, selectedNode);
  };

  const addProcessorNode = () => {
    const defaultProcessor = PROCESSORS[0];
    // FIX: Cast the created data object to ProcessorNodeData. This is necessary because TypeScript cannot infer the correlation between the 'id' and 'defaultSettings' properties from the PROCESSORS array, leading to a type error with the discriminated union.
    const data = { 
        processorType: defaultProcessor.id,
        settings: defaultProcessor.defaultSettings
    } as ProcessorNodeData;
    handleAddNode('processor', data, selectedNode);
  }

  const addStartNode = (data: StartNodeData) => {
    handleAddNode('start', data, null);
  }
  
  const renderContent = () => {
    if (!selectedNode) {
        return (
            <>
                <div>
                    <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Data Sources</h2>
                    <p className="text-sm text-gray-600 mt-2 mb-4">Click to add a starting point for your crawl.</p>
                    <div className="flex flex-col gap-3">
                        {dataSources.map(source => (
                           <button
                            key={source.type}
                            onClick={() => addStartNode(source.defaultData)}
                            className={`flex items-center gap-3 p-3 text-white rounded-lg transition-all duration-200 shadow-md transform hover:scale-105 ${source.colorClasses}`}
                          >
                            {source.icon}
                            <span className="font-semibold">{source.label}</span>
                          </button>
                        ))}
                    </div>
                </div>
            </>
        )
    }

    if (selectedNode.type === 'repository') {
       return (
            <div>
                <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Next Action</h2>
                <p className="text-sm text-gray-600 mt-2 mb-4">Add a Worker node to process the items collected in the repository.</p>
                <div className="flex flex-col gap-3">
                    <button
                      onClick={addWorkerNode}
                      className="flex items-center gap-3 p-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-all duration-200 shadow-md transform hover:scale-105"
                    >
                      <CpuChipIcon />
                      <span className="font-semibold">Add Worker</span>
                    </button>
                </div>
            </div>
       )
    }

    if (selectedNode.type === 'worker') {
        const hasDataExtractorInput = edges.some(edge => {
            if (edge.target !== selectedNode.id) return false;
            const sourceNode = nodes.find(n => n.id === edge.source);
            return sourceNode?.type === 'data-extractor';
        });

        return (
            <>
                <div>
                    <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Worker Inputs</h2>
                    <p className="text-sm text-gray-600 mt-2 mb-4">
                        Connect a data extractor to define what data this worker should extract.
                    </p>
                     <div className="flex flex-col gap-3">
                        <button
                          onClick={addDataExtractorNode}
                          disabled={hasDataExtractorInput}
                          className="flex items-center gap-3 p-3 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition-all duration-200 shadow-md transform hover:scale-105 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                          <TableCellsIcon />
                          <span className="font-semibold">Add Data Extractor</span>
                        </button>
                        {hasDataExtractorInput && (
                            <p className="text-xs text-center text-gray-500 mt-2">
                                A Worker can only have one Data Extractor input.
                            </p>
                        )}
                    </div>
                </div>
                <div className="mt-6">
                    <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Next Action</h2>
                    <p className="text-sm text-gray-600 mt-2 mb-4">
                        After extracting data, add a Processor to handle the results.
                    </p>
                    <div className="flex flex-col gap-3">
                        <button
                          onClick={addProcessorNode}
                          className="flex items-center gap-3 p-3 bg-slate-500 text-white rounded-lg hover:bg-slate-600 transition-all duration-200 shadow-md transform hover:scale-105"
                        >
                          <Cog6ToothIcon />
                          <span className="font-semibold">Add Processor</span>
                        </button>
                    </div>
                </div>
            </>
        )
    }

    if (selectedNode.type === 'processor') {
        return (
             <div>
                <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Processor Node</h2>
                <p className="text-sm text-gray-600 mt-2 mb-4">This node processes the extracted data. It will automatically connect to the final Completion node.</p>
            </div>
        )
    }

    if (['click', 'loop'].includes(selectedNode.type as string)) {
        return (
            <div>
                <h2 className="text-xl font-bold text-gray-800 border-b pb-2">Next Action</h2>
                <p className="text-sm text-gray-600 mt-2 mb-4">Add a step to follow the selected node.</p>
                <div className="flex flex-col gap-3">
                    <button
                      onClick={addClickNode}
                      className="flex items-center gap-3 p-3 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-all duration-200 shadow-md transform hover:scale-105"
                    >
                      <CursorArrowRaysIcon />
                      <span className="font-semibold">Add Click Step</span>
                    </button>
                    <button
                      onClick={addLoopNode}
                      className="flex items-center gap-3 p-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all duration-200 shadow-md transform hover:scale-105"
                    >
                      <ArrowPathIcon />
                      <span className="font-semibold">Add Loop</span>
                    </button>
                     <button
                      onClick={addWorkerNode}
                      className="flex items-center gap-3 p-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-all duration-200 shadow-md transform hover:scale-105"
                    >
                      <CpuChipIcon />
                      <span className="font-semibold">Add Worker</span>
                    </button>
                </div>
            </div>
        )
    }

    return null; // For start nodes, etc., show nothing.
  }

  return (
    <aside className={`fixed top-0 left-0 h-full w-64 bg-white p-4 border-r border-gray-200 shadow-lg z-40 flex flex-col gap-4 transform transition-transform duration-300 ease-in-out md:relative md:w-64 md:transform-none md:z-20 ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}>
      <div className="flex justify-between items-center md:block">
        <h1 className="text-2xl font-bold text-gray-800">Crawler Builder</h1>
        <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800 md:hidden">
            <XMarkIcon />
        </button>
      </div>
      <div className="flex flex-col gap-3 mt-4 flex-1 overflow-y-auto">
        {renderContent()}
      </div>
      <div className="mt-auto pt-4 border-t text-center text-xs text-gray-500">
        React Flow Crawler v1.0
      </div>
    </aside>
  );
};

export default Sidebar;