
// FIX: The content for this file was missing. This is a complete implementation of the main App component.
// FIX: Import `useState`, `useCallback`, `useMemo`, and `ChangeEvent` from React to fix missing name errors.
import React, { useState, useCallback, useMemo, ChangeEvent, useEffect, MouseEvent, useRef } from 'react';
import ReactFlow, {
  ReactFlowProvider,
  addEdge,
  useNodesState,
  useEdgesState,
  Controls,
  Background,
  MiniMap,
  Node,
  Edge,
  Connection,
  NodeTypes,
  OnSelectionChangeParams,
  XYPosition,
  NodeDragHandler,
  NodeChange,
  ReactFlowInstance,
  applyNodeChanges,
  NodeDimensionChange,
} from 'reactflow';

import Sidebar from './components/Sidebar';
import SettingsPanel from './components/SettingsPanel';
import InspectorPanel from './components/InspectorPanel';
import ContextMenu from './components/ContextMenu';
import StartNode from './components/nodes/StartNode';
import ClickNode from './components/nodes/ClickNode';
import WorkerNode from './components/nodes/WorkerNode';
import LoopNode from './components/nodes/LoopNode';
import RepositoryNode from './components/nodes/RepositoryNode';
import FilterNode from './components/nodes/FilterNode';
import HTMLDataExtractorNode, { CSVExtractorNode, JSONExtractorNode, XMLExtractorNode, MySQLExtractorNode } from './components/nodes/DataMappingNode';
import ProcessorNode from './components/nodes/ProcessorNode';
import CompletionNode from './components/nodes/CompletionNode';
import ShapeNode from './components/nodes/ShapeNode';
import { Bars3Icon, Cog6ToothIcon } from './components/icons';


import { NodeData, ProjectSettings, HTMLDataExtractorNodeData, ShapeNodeData, ShapeType } from './types';

const REPOSITORY_NODE_ID = 'repository-node';
const COMPLETION_NODE_ID = 'completion-node';

// Layout Constants for automatic positioning (Vertical Layout)
const LEVEL_Y_POSITIONS = {
  start: 50,
  repository: 300,
  worker: 550,
};
const NODE_H_SPACING = 350; // Spacing between nodes on the same horizontal level
const NODE_V_SPACING = 250;   // Spacing between generic parent-child nodes
const INITIAL_X_OFFSET = 50;


const initialNodes: Node[] = [];

let id = 1;
const getId = () => `${id++}`;

const EXTRACTOR_NODE_TYPES = ['html-data-extractor', 'csv-extractor', 'json-extractor', 'xml-extractor', 'mysql-extractor'];

interface InspectorConfig {
    htmlContent: string;
    pickingState: {
        nodeId: string;
        ruleId: string;
    } | null;
}

interface MenuConfig {
    top: number;
    left: number;
}

type MouseMode = 'select' | 'pan';

const defaultShapeData: Record<ShapeType, Omit<ShapeNodeData, 'width' | 'height'>> = {
  rectangle: {
    shapeType: 'rectangle',
    label: 'My Group',
    backgroundColor: '#f3f4f6', // gray-100
    borderColor: '#9ca3af', // gray-400
    textColor: '#1f2937', // gray-800
  },
  circle: {
    shapeType: 'circle',
    label: 'Note',
    backgroundColor: '#fefce8', // yellow-50
    borderColor: '#facc15', // yellow-400
    textColor: '#422006', // yellow-900
  },
  ellipse: {
    shapeType: 'ellipse',
    label: 'Sub-process',
    backgroundColor: '#f0fdf4', // green-50
    borderColor: '#4ade80', // green-400
    textColor: '#14532d', // green-900
  },
  frame: {
    shapeType: 'frame',
    label: 'Process A',
    backgroundColor: 'transparent',
    borderColor: '#6b7280', // gray-500
    textColor: '#374151', // gray-700
  },
  package: {
    shapeType: 'package',
    label: 'My Package',
    backgroundColor: '#f9fafb', // gray-50
    borderColor: '#9ca3af', // gray-400
    textColor: '#1f2937', // gray-800
  },
};

const defaultShapeSizes: Record<ShapeType, { width: number; height: number }> = {
  rectangle: { width: 500, height: 400 },
  circle: { width: 300, height: 300 },
  ellipse: { width: 400, height: 200 },
  frame: { width: 900, height: 700 },
  package: { width: 500, height: 400 },
};


const App: React.FC = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNode, setSelectedNode] = useState<Node | null>(null);
  const [projectSettings, setProjectSettings] = useState<ProjectSettings>({
    name: 'My Crawler Project',
    description: 'A new web crawler configuration.',
    enabled: true,
    crawlDelay: 1000,
    userAgent: 'Crawler/1.0',
    concurrency: 5,
  });
  
  // State for UI panels
  const [isSidebarOpen, setSidebarOpen] = useState(false);
  // Initialize settings panel as open on desktop (>768px), closed on mobile
  const [isSettingsOpen, setSettingsOpen] = useState(typeof window !== 'undefined' && window.innerWidth >= 768);

  // State for the Inspector Panel
  const [inspectorConfig, setInspectorConfig] = useState<InspectorConfig | null>(null);
  const [highlightedSelector, setHighlightedSelector] = useState<string | null>(null);

  // State for Context Menu
  const [menu, setMenu] = useState<MenuConfig | null>(null);

  // State for mouse mode
  const [mouseMode, setMouseMode] = useState<MouseMode>('select');

  // Ref for React Flow instance and wrapper
  const [rfInstance, setRfInstance] = useState<ReactFlowInstance | null>(null);
  const reactFlowWrapper = useRef<HTMLDivElement>(null);

  // Load saved project data from WordPress on mount
  useEffect(() => {
    // Wait a bit to ensure window.crawlflowConfig is available
    const loadConfig = () => {
      const config = (window as any).crawlflowConfig;
      if (config && config.projectConfig) {
        const { projectSettings, nodes, edges } = config.projectConfig;
        
        // Load project settings if available
        if (projectSettings) {
          setProjectSettings((prev) => ({
            ...prev,
            ...projectSettings,
          }));
        }
        
        // Load nodes if available
        if (nodes && Array.isArray(nodes) && nodes.length > 0) {
          setNodes(nodes);
        }
        
        // Load edges if available
        if (edges && Array.isArray(edges)) {
          setEdges(edges);
        }
      } else if (config && !config.projectConfig && config.projectId) {
        // If project exists but no config, it's a new project - keep defaults
        console.log('CrawlFlow: New project, using default configuration');
      }
    };
    
    // Try to load immediately
    loadConfig();
    
    // Also try after a short delay in case script loads later
    const timeout = setTimeout(loadConfig, 100);
    
    return () => clearTimeout(timeout);
  }, []); // Only run once on mount

  // Effect to clean up the entire workflow when no start nodes exist
  useEffect(() => {
    const hasStartNode = nodes.some(n => n.type === 'start');
    const hasRepoNode = nodes.some(n => n.id === REPOSITORY_NODE_ID);

    if (!hasStartNode && hasRepoNode) {
      // If the last data source is removed, the repository and all subsequent
      // nodes are no longer valid. Clear the entire canvas.
      setNodes([]);
      setEdges([]);
      setSelectedNode(null);
    }
  }, [nodes, setNodes, setEdges]);
  
  
  // Effect to recenter repository node
  useEffect(() => {
    const startNodes = nodes.filter(n => n.type === 'start');
    const repoNode = nodes.find(n => n.id === REPOSITORY_NODE_ID);

    if (startNodes.length > 0 && repoNode) {
        const newAvgX = startNodes.reduce((sum, node) => sum + node.position.x, 0) / startNodes.length;
        if (repoNode.position.x !== newAvgX) {
            setNodes(nds => 
                nds.map(n => 
                    n.id === REPOSITORY_NODE_ID 
                    ? { ...n, position: { ...n.position, x: newAvgX } } 
                    : n
                )
            );
        }
    }
  }, [nodes, setNodes]);

  // Effect to manage the Completion node and its connections
  useEffect(() => {
    const processorNodes = nodes.filter(n => n.type === 'processor');
    const completionNode = nodes.find(n => n.id === COMPLETION_NODE_ID);

    // Case 1: No processors exist. Remove completion node if it exists.
    if (processorNodes.length === 0) {
        if (completionNode) {
            setNodes(nds => nds.filter(n => n.id !== COMPLETION_NODE_ID));
            setEdges(eds => eds.filter(e => e.target !== COMPLETION_NODE_ID));
        }
        return;
    }

    // Case 2: Processors exist, but completion node doesn't. Add it.
    if (processorNodes.length > 0 && !completionNode) {
        const avgX = processorNodes.reduce((sum, n) => sum + n.position.x, 0) / processorNodes.length;
        const maxY = Math.max(...processorNodes.map(n => n.position.y));
        const newCompletionNode: Node = {
            id: COMPLETION_NODE_ID,
            type: 'completion',
            position: { x: avgX, y: maxY + NODE_V_SPACING + 50 },
            data: {},
            deletable: false,
            draggable: false,
        };
        setNodes(nds => [...nds, newCompletionNode]);
        return; // Edges will be handled in the next render cycle
    }
    
    // Case 3: Both processors and completion node exist. Manage positions and connections.
    if (processorNodes.length > 0 && completionNode) {
        // Identify "last" processors (those not connected to another processor)
        const processorsThatAreSourcesForOtherProcessors = new Set<string>();
        for (const edge of edges) {
            const sourceNode = nodes.find(n => n.id === edge.source);
            const targetNode = nodes.find(n => n.id === edge.target);
            if (sourceNode?.type === 'processor' && targetNode?.type === 'processor') {
                processorsThatAreSourcesForOtherProcessors.add(sourceNode.id);
            }
        }

        const lastProcessorIds = processorNodes
            .filter(p => !processorsThatAreSourcesForOtherProcessors.has(p.id))
            .map(p => p.id);
        
        const lastProcessorNodes = nodes.filter(n => lastProcessorIds.includes(n.id));

        // Update position of completion node based on the "last" processors for a cleaner layout
        if (lastProcessorNodes.length > 0) {
            const avgX = lastProcessorNodes.reduce((sum, n) => sum + n.position.x, 0) / lastProcessorNodes.length;
            const maxY = Math.max(...lastProcessorNodes.map(n => n.position.y));
            const newY = maxY + NODE_V_SPACING + 50;

            if (completionNode.position.x !== avgX || completionNode.position.y !== newY) {
                setNodes(nds => nds.map(n => 
                    n.id === COMPLETION_NODE_ID 
                    ? { ...n, position: { x: avgX, y: newY } }
                    : n
                ));
            }
        }

        // Synchronize edges to the completion node
        const currentCompletionEdges = edges.filter(e => e.target === COMPLETION_NODE_ID);
        const edgesToCreate = lastProcessorIds.filter(id => !currentCompletionEdges.some(e => e.source === id));
        const edgesToRemove = currentCompletionEdges.filter(e => !lastProcessorIds.includes(e.source as string));

        if (edgesToCreate.length > 0 || edgesToRemove.length > 0) {
            setEdges(eds => {
                const filteredEdges = eds.filter(e => !edgesToRemove.some(er => er.id === e.id));
                const newEdges = edgesToCreate.map(sourceId => ({
                    id: `e-${sourceId}-${COMPLETION_NODE_ID}`,
                    source: sourceId,
                    target: COMPLETION_NODE_ID,
                    type: 'smoothstep',
                }));
                return [...filteredEdges, ...newEdges];
            });
        }
    }
  }, [nodes, edges, setNodes, setEdges]);
  
  // Effect for keyboard shortcuts to switch mouse mode
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      // Ignore key events if an input, textarea, or select is focused
      const activeEl = document.activeElement;
      if (activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName)) {
        return;
      }
      
      if (event.key.toLowerCase() === 'h') {
        event.preventDefault();
        setMouseMode('pan');
      } else if (event.key.toLowerCase() === 'v') {
        event.preventDefault();
        setMouseMode('select');
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  const onConnect = useCallback((params: Edge | Connection) => {
    const sourceNode = nodes.find(n => n.id === params.source);
    const targetNode = nodes.find(n => n.id === params.target);

    if (sourceNode?.type === 'repository' && targetNode?.type !== 'worker') {
      console.warn("Connection prevented: Raw Items Repository can only connect to a Worker node.");
      return;
    }

    setEdges((eds) => addEdge(params, eds));
  }, [nodes, setEdges]);

  const onSelectionChange = useCallback(({ nodes: selectedNodes }: OnSelectionChangeParams) => {
    const newSelectedNode = selectedNodes.length === 1 ? selectedNodes[0] : null;
    setSelectedNode(newSelectedNode);
    // Automatically open settings when a node is selected
    if (newSelectedNode) {
        setSettingsOpen(true);
    }
  }, []);

  const onNodesChangeHandler = useCallback((changes: NodeChange[]) => {
    setNodes((nds) => {
      const changedNodes = applyNodeChanges(changes, nds);

      // After applying the changes from React Flow, we map over the nodes
      // to sync dimensions to our custom `data` object for persistence.
      return changedNodes.map((node) => {
        // Find if there was a dimension change for this specific node.
        const dimensionChange = changes.find(
          (change): change is NodeDimensionChange =>
            change.type === 'dimensions' && change.id === node.id
        );

        // The key to fixing the "ResizeObserver loop" error is to only sync
        // our data object *after* the resize is complete. The `resizing` flag
        // is true during the drag and false on the final event.
        if (dimensionChange && !dimensionChange.resizing && node.type === 'shape') {
          const data = node.data as ShapeNodeData;
          
          // `node.width` and `node.height` are the final dimensions after the resize.
          const { width, height } = node;

          // Only create a new node object if the dimensions in our data store are actually different.
          if (width && height && (data.width !== width || data.height !== height)) {
            return {
              ...node,
              data: {
                ...node.data,
                width,
                height,
              },
            };
          }
        }

        return node;
      });
    });
  }, [setNodes]);


  const addNode = (type: string, data: NodeData, sourceNode: Node | null = null) => {
    // Start Node Logic
    if (type === 'start') {
      const startNodes = nodes.filter(n => n.type === 'start');
      const startNodePosition: XYPosition = {
        x: INITIAL_X_OFFSET + (startNodes.length * NODE_H_SPACING),
        y: LEVEL_Y_POSITIONS.start,
      };

      const newStartNode: Node = {
        id: getId(),
        type: 'start',
        position: startNodePosition,
        data,
      };
      
      const repoNodeExists = nodes.some(n => n.id === REPOSITORY_NODE_ID);

      const currentStartXSum = startNodes.reduce((sum, node) => sum + node.position.x, 0);
      const newAvgX = (currentStartXSum + startNodePosition.x) / (startNodes.length + 1);
      
      const startToRepoEdge: Edge = {
        id: `e-${newStartNode.id}-${REPOSITORY_NODE_ID}`,
        source: newStartNode.id,
        target: REPOSITORY_NODE_ID,
        animated: true,
      };

      if (!repoNodeExists) {
        const newRepoNode: Node = { 
          id: REPOSITORY_NODE_ID, 
          type: 'repository', 
          position: { x: newAvgX, y: LEVEL_Y_POSITIONS.repository }, 
          data: {},
          deletable: false,
          draggable: false,
        };
        
        setNodes((nds) => nds.concat(newStartNode, newRepoNode));
        setEdges((eds) => eds.concat([startToRepoEdge]));
      } else {
        setNodes((nds) => 
            nds.map(n => 
                n.id === REPOSITORY_NODE_ID 
                ? { ...n, position: { x: newAvgX, y: LEVEL_Y_POSITIONS.repository } } 
                : n
            ).concat(newStartNode)
        );
        setEdges((eds) => addEdge(startToRepoEdge, eds));
      }

      return;
    }
    
    // Worker Node Logic
    if (type === 'worker') {
      const workerNodesCount = nodes.filter(n => n.type === 'worker').length;
      const newNodeId = getId();
      const newNode: Node = {
        id: newNodeId,
        type: 'worker',
        position: {
          x: INITIAL_X_OFFSET + (workerNodesCount * NODE_H_SPACING),
          y: LEVEL_Y_POSITIONS.worker,
        },
        data,
      };
      setNodes((nds) => nds.concat(newNode));
      if (sourceNode) {
        const newEdge: Edge = {
          id: `e-${sourceNode.id}-${newNodeId}`,
          source: sourceNode.id,
          target: newNodeId,
          animated: true,
        };
        setEdges((eds) => addEdge(newEdge, eds));
      }
      return;
    }

    // Worker Input Nodes (All Extractor Types)
    if (EXTRACTOR_NODE_TYPES.includes(type) && sourceNode?.type === 'worker') {
        const hasExtractorInput = edges.some(edge => {
            if (edge.target !== sourceNode.id) return false;
            const sourceNodeFromEdge = nodes.find(n => n.id === edge.source);
            return sourceNodeFromEdge && EXTRACTOR_NODE_TYPES.includes(sourceNodeFromEdge.type as string);
        });

        if (hasExtractorInput) {
            alert("This Worker node can only have one Data Extractor input.");
            return;
        }

        const worker = sourceNode;
        const workerInputNodes = edges.filter(e => e.target === worker.id && nodes.find(n => n.id === e.source && EXTRACTOR_NODE_TYPES.includes(n.type as string))).length;
        const newNodeId = getId();
        
        const position : XYPosition = {
             x: worker.position.x - (NODE_H_SPACING / 4) + (workerInputNodes * (NODE_H_SPACING / 2)),
             y: worker.position.y - NODE_V_SPACING,
        };
        
        const newNode: Node = {
            id: newNodeId,
            type,
            position,
            data,
        };

        const newEdge: Edge = {
            id: `e-${newNodeId}-${worker.id}`,
            source: newNodeId,
            target: worker.id,
        };
        setNodes((nds) => nds.concat(newNode));
        setEdges((eds) => addEdge(newEdge, eds));
        return;
    }

    // Standard logic for other action nodes (Click, Loop, Processor)
    let finalSourceNode = sourceNode;

    if (type === 'processor' && sourceNode?.type === 'worker') {
      // If adding a processor from a worker, find the end of the existing chain
      let lastProcessorInChainId: string | null = null;
      
      const firstProcessorEdge = edges.find(e => 
          e.source === sourceNode.id && nodes.find(n => n.id === e.target)?.type === 'processor'
      );
      
      if (firstProcessorEdge) {
          lastProcessorInChainId = firstProcessorEdge.target;
          let isLast = false;
          while (!isLast) {
              const nextEdge = edges.find(e => 
                  e.source === lastProcessorInChainId && nodes.find(n => n.id === e.target)?.type === 'processor'
              );
              if (nextEdge) {
                  lastProcessorInChainId = nextEdge.target;
              } else {
                  isLast = true;
              }
          }
      }
      
      if (lastProcessorInChainId) {
          finalSourceNode = nodes.find(n => n.id === lastProcessorInChainId) || sourceNode;
      }
    }

    const newNodeId = getId();
    let position: XYPosition;
    if (finalSourceNode) {
      if(type === 'processor') {
          // Stack processors vertically for a clear chain
          position = {
              x: finalSourceNode.position.x,
              y: finalSourceNode.position.y + NODE_V_SPACING
          };
      } else {
          const childEdgesCount = edges.filter(e => e.source === finalSourceNode!.id).length;
          position = { 
            x: finalSourceNode.position.x + (childEdgesCount * (NODE_H_SPACING / 2)),
            y: finalSourceNode.position.y + NODE_V_SPACING
          };
      }
    } else {
      position = { // Fallback position
        x: Math.random() * 250 + 50,
        y: Math.random() * 150 + 900,
      };
    }

    const newNode: Node = {
      id: newNodeId,
      type,
      position,
      data,
    };

    setNodes((nds) => nds.concat(newNode));

    if (finalSourceNode) {
      const newEdge: Edge = {
        id: `e-${finalSourceNode.id}-${newNodeId}`,
        source: finalSourceNode.id,
        target: newNodeId,
        animated: true,
      };
      setEdges((eds) => addEdge(newEdge, eds));
    }
  };
  
  const updateNodeData = (nodeId: string, data: NodeData) => {
    setNodes((nds) =>
      nds.map((node) => {
        if (node.id === nodeId) {
          return { ...node, data };
        }
        return node;
      })
    );

    if (selectedNode?.id === nodeId) {
      setSelectedNode((prev) => (prev ? { ...prev, data } : null));
    }
  };
  
  const deleteNode = useCallback((nodeId: string) => {
    setNodes((nds) => nds.filter((node) => node.id !== nodeId));
    setEdges((eds) => eds.filter((edge) => edge.source !== nodeId && edge.target !== nodeId));
    setSelectedNode(null);
  }, [setNodes, setEdges]);

  const updateProjectSettings = useCallback((update: Partial<ProjectSettings>) => {
    setProjectSettings(prev => ({ ...prev, ...update }));
  }, []);

  const exportConfiguration = useCallback(() => {
    const config = {
      projectSettings,
      nodes,
      edges,
    };
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(config, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", `${projectSettings.name.replace(/\s+/g, '_').toLowerCase()}.json`);
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
  }, [projectSettings, nodes, edges]);

  const importConfiguration = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const config = JSON.parse(e.target?.result as string);
          if (config.projectSettings && config.nodes && config.edges) {
            setProjectSettings(config.projectSettings);
            setNodes(config.nodes);
            setEdges(config.edges);
            setSelectedNode(null);
          } else {
            alert('Invalid configuration file.');
          }
        } catch (error) {
          alert('Error reading configuration file.');
        }
      };
      reader.readAsText(file);
    }
    if(event.target) {
        event.target.value = '';
    }
  }, [setNodes, setEdges]);

  const saveProject = useCallback(async () => {
    if (!projectSettings.name?.trim()) {
      alert('Project name is required');
      return;
    }

    // Get WordPress AJAX URL and nonce from localized script
    const config = (window as any).crawlflowConfig || {};
    const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = config.nonce || '';
    const projectId = config.projectId || 0;

    // Prepare JSON payload
    const payload: {
      action: string;
      nonce: string;
      project_name: string;
      project_description: string;
      status: string;
      project_data: {
        projectSettings: any;
        nodes: any[];
        edges: any[];
      };
      project_id?: number;
    } = {
      action: 'crawlflow_save_project',
      nonce: nonce,
      project_name: projectSettings.name,
      project_description: projectSettings.description || '',
      status: 'draft',
      project_data: {
        projectSettings,
        nodes,
        edges,
      },
    };

    if (projectId) {
      payload.project_id = projectId;
    }

    try {
      console.log('CrawlFlow: Saving project...', { projectId, projectName: projectSettings.name });
      
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log('CrawlFlow: Save response:', result);

      if (result.success) {
        alert('Project saved successfully!');
        // If new project was created, update the project ID
        if (result.data?.project_id && !projectId) {
          // Update URL or reload page with new project ID
          const adminUrl = '/wp-admin/';
          const newUrl = adminUrl + `admin.php?page=crawlflow-projects&sub=compose&editor=flow&project_id=${result.data.project_id}`;
          window.history.replaceState({}, '', newUrl);
          // Update window config with new project ID
          if ((window as any).crawlflowConfig) {
            (window as any).crawlflowConfig.projectId = result.data.project_id;
          }
        }
      } else {
        const errorMsg = result.data?.message || result.data || 'Unknown error';
        console.error('CrawlFlow: Save failed:', errorMsg);
        alert('Error saving project: ' + errorMsg);
      }
    } catch (error: any) {
      console.error('CrawlFlow: Save error:', error);
      alert('Error saving project: ' + (error.message || 'Network error. Please check console for details.'));
    }
  }, [projectSettings, nodes, edges]);


  const onNodeDragStop: NodeDragHandler = useCallback((event, node) => {
    const parentNode = nodes.find(n => 
        node.position.x >= n.position.x &&
        node.position.y >= n.position.y &&
        node.position.x <= n.position.x + (n.width ?? 0) &&
        node.position.y <= n.position.y + (n.height ?? 0) &&
        n.id !== node.id &&
        n.type === 'loop'
    );

    if (parentNode) {
        setNodes(nds => nds.map(n => {
            if (n.id === node.id) {
                return {...n, parentNode: parentNode.id, extent: 'parent'};
            }
            return n;
        }))
    } else {
         setNodes(nds => nds.map(n => {
            if (n.id === node.id) {
                // remove parentNode and extent properties
                const { parentNode, extent, ...rest } = n;
                return rest;
            }
            return n;
        }))
    }
  }, [nodes, setNodes]);


  const nodeTypes: NodeTypes = useMemo(() => ({
    start: (props) => <StartNode {...props} />,
    click: (props) => <ClickNode {...props} />,
    worker: (props) => <WorkerNode {...props} />,
    loop: (props) => <LoopNode {...props} />,
    repository: (props) => <RepositoryNode {...props} />,
    reception: (props) => <FilterNode {...props} />,
    'html-data-extractor': (props) => <HTMLDataExtractorNode {...props} />,
    'csv-extractor': (props) => <CSVExtractorNode {...props} />,
    'json-extractor': (props) => <JSONExtractorNode {...props} />,
    'xml-extractor': (props) => <XMLExtractorNode {...props} />,
    'mysql-extractor': (props) => <MySQLExtractorNode {...props} />,
    processor: (props) => <ProcessorNode {...props} />,
    completion: (props) => <CompletionNode {...props} />,
    shape: (props) => <ShapeNode {...props} />,
  }), []);

  // FIX: Wrapped handleCloseSettings in useCallback for referential stability.
  const handleCloseSettings = useCallback(() => {
    setSettingsOpen(false);
    setSelectedNode(null);
  }, []);

  const handleClosePanels = () => {
    setSidebarOpen(false);
    setSettingsOpen(false);
  }

  // Inspector Panel Handlers
  const showInspector = useCallback((htmlContent: string) => {
      setInspectorConfig(prev => ({ ...(prev ?? { pickingState: null }), htmlContent }));
  }, []);

  const hideInspector = useCallback(() => {
      setInspectorConfig(null);
      setHighlightedSelector(null); // Also clear highlight when inspector closes
  }, []);

  const handleStartPicking = useCallback((nodeId: string, ruleId: string) => {
      setHighlightedSelector(null); // Clear any highlights when starting to pick
      setInspectorConfig(prev => {
          if (!prev) return prev;
          return { ...prev, pickingState: { nodeId, ruleId } };
      });
  }, []);

  const handleStopPicking = useCallback(() => {
      setInspectorConfig(prev => {
          if (!prev) return prev;
          return { ...prev, pickingState: null };
      });
  }, []);

  const handleSelectorPicked = useCallback((selector: string) => {
      if (!inspectorConfig?.pickingState) return;

      const { nodeId, ruleId } = inspectorConfig.pickingState;
      const targetNode = nodes.find(n => n.id === nodeId);

      if (targetNode && targetNode.type === 'html-data-extractor') {
          const nodeData = targetNode.data as HTMLDataExtractorNodeData;
          const updatedRules = nodeData.customRules.map(rule =>
              rule.id === ruleId ? { ...rule, selector } : rule
          );
          updateNodeData(nodeId, { ...nodeData, customRules: updatedRules });
      }

      handleStopPicking();
  }, [inspectorConfig, nodes, updateNodeData, handleStopPicking]);

  // Context Menu Handlers
  const onNodeContextMenu = useCallback((event: MouseEvent, node: Node) => {
    event.preventDefault();
    event.stopPropagation(); // Prevent the event from bubbling up to the pane

    const isNodeSelected = nodes.find(n => n.id === node.id)?.selected;

    // If the right-clicked node is not already part of the selection,
    // clear the previous selection and select only the clicked node.
    if (!isNodeSelected) {
      setNodes(nds => nds.map(n => ({
        ...n,
        selected: n.id === node.id,
      })));
    }
    
    // Show the context menu. Actions will operate on all selected nodes.
    setMenu({
      top: event.clientY,
      left: event.clientX,
    });
  }, [nodes, setNodes]);

  const onPaneContextMenu = useCallback((event: MouseEvent) => {
      event.preventDefault();
      setMenu(null);
  }, []);

  const onPaneClick = useCallback(() => {
      setMenu(null);
  }, []);
  
  const onMoveStart = useCallback(() => {
      setMenu(null);
  }, []);
  
  const handleDeleteSelectedNodes = useCallback(() => {
      const deletableNodeIds = nodes.filter(n => n.selected && n.deletable !== false).map(n => n.id);
      
      if (deletableNodeIds.length > 0) {
          setEdges(eds => eds.filter(e => !deletableNodeIds.includes(e.source) && !deletableNodeIds.includes(e.target)));
          setNodes(nds => nds.filter(n => !deletableNodeIds.includes(n.id)));
      }
      setMenu(null);
  }, [nodes, setNodes, setEdges]);
  
  const handleDuplicateSelectedNodes = useCallback(() => {
      const nodesToDuplicate = nodes.filter(n => n.selected && n.deletable !== false);
      if (nodesToDuplicate.length === 0) {
          setMenu(null);
          return;
      }

      const newNodes: Node[] = [];
      const oldIdToNewIdMap = new Map<string, string>();

      nodesToDuplicate.forEach(node => {
          const newNodeId = getId();
          oldIdToNewIdMap.set(node.id, newNodeId);
          newNodes.push({
              ...node,
              id: newNodeId,
              position: { x: node.position.x + 20, y: node.position.y + 20 },
              selected: true,
          });
      });

      const newEdges: Edge[] = [];
      const duplicatedIds = new Set(nodesToDuplicate.map(n => n.id));

      edges.forEach(edge => {
          if (duplicatedIds.has(edge.source) && duplicatedIds.has(edge.target)) {
              const newSourceId = oldIdToNewIdMap.get(edge.source)!;
              const newTargetId = oldIdToNewIdMap.get(edge.target)!;
              newEdges.push({
                  ...edge,
                  id: `e-${newSourceId}-${newTargetId}-${getId()}`,
                  source: newSourceId,
                  target: newTargetId,
              });
          }
      });
      
      setNodes(nds => [
          ...nds.map(n => ({ ...n, selected: false })),
          ...newNodes
      ]);
      setEdges(eds => eds.concat(newEdges));
      setMenu(null);
  }, [nodes, edges, setNodes, setEdges]);
  
  const addShapeNode = useCallback((shapeType: ShapeType) => {
    if (!rfInstance || !reactFlowWrapper.current) return;

    // --- Helper function for collision detection ---
    const isOverlapping = (rect1: {x: number, y: number, width: number, height: number}, rect2: {x: number, y: number, width: number, height: number}) => {
        // Add a small buffer to avoid placing nodes directly touching each other
        const buffer = 20; 
        return (
            rect1.x < rect2.x + rect2.width + buffer &&
            rect1.x + rect1.width + buffer > rect2.x &&
            rect1.y < rect2.y + rect2.height + buffer &&
            rect1.y + rect1.height + buffer > rect2.y
        );
    };
    
    // --- Find a free position on the canvas ---
    const findFreePosition = (initialPos: XYPosition, nodeWidth: number, nodeHeight: number) => {
        let testPosition = { 
            x: initialPos.x - nodeWidth / 2, 
            y: initialPos.y - nodeHeight / 2 
        };
        
        const shiftAmount = 40;
        let attempt = 0;
        const maxAttempts = 50; // Safety break

        while (attempt < maxAttempts) {
            const newNodeRect = { ...testPosition, width: nodeWidth, height: nodeHeight };
            let overlapping = false;

            for (const node of nodes) {
                 const existingNodeRect = {
                    x: node.position.x,
                    y: node.position.y,
                    width: node.width || 150, // Fallback width
                    height: node.height || 50, // Fallback height
                };

                if (isOverlapping(newNodeRect, existingNodeRect)) {
                    overlapping = true;
                    break;
                }
            }

            if (!overlapping) {
                return testPosition; // Found a free spot
            }
            
            // If overlapping, shift position down and slightly right for the next check
            testPosition.y += shiftAmount;
            testPosition.x += shiftAmount / 2;
            attempt++;
        }

        // Fallback to the initial position if no free spot is found after max attempts
        return { x: initialPos.x - nodeWidth / 2, y: initialPos.y - nodeHeight / 2 };
    };

    // --- Original logic to get initial position and node data ---
    const initialCenterPosition = rfInstance.screenToFlowPosition({
        x: reactFlowWrapper.current.clientWidth / 2,
        y: reactFlowWrapper.current.clientHeight / 2,
    });

    const { width, height } = defaultShapeSizes[shapeType];
    const data: ShapeNodeData = { ...defaultShapeData[shapeType], width, height };
    
    // --- Use the new function to get the final position ---
    const finalPosition = findFreePosition(initialCenterPosition, width, height);

    const newNode: Node<ShapeNodeData> = {
        id: getId(),
        type: 'shape',
        position: finalPosition,
        data,
        width,
        height,
        zIndex: -1,
    };
    setNodes((nds) => nds.concat(newNode));
    handleCloseSettings();
}, [rfInstance, nodes, setNodes, handleCloseSettings]);

  return (
    <div className="flex flex-col h-screen font-sans bg-slate-100 overflow-hidden">
        <div className="flex flex-1 h-full overflow-hidden">
            <ReactFlowProvider>
                {/* Backdrop for mobile overlays */}
                <div 
                    className={`fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden transition-opacity ${(isSidebarOpen || isSettingsOpen) ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                    onClick={handleClosePanels}
                />

                <Sidebar 
                    onAddNode={addNode} 
                    selectedNode={selectedNode}
                    isOpen={isSidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                    nodes={nodes}
                    edges={edges}
                    mouseMode={mouseMode}
                    onSetMouseMode={setMouseMode}
                    onAddShapeNode={addShapeNode}
                />
                <main className="flex-1 h-full relative" ref={reactFlowWrapper}>
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChangeHandler}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    nodeTypes={nodeTypes}
                    onSelectionChange={onSelectionChange}
                    onNodeDragStop={onNodeDragStop}
                    onInit={setRfInstance}
                    fitView
                    className="bg-slate-100"
                    selectionOnDrag={mouseMode === 'select'}
                    panOnDrag={mouseMode === 'pan'}
                    onNodeContextMenu={onNodeContextMenu}
                    onPaneContextMenu={onPaneContextMenu}
                    onPaneClick={onPaneClick}
                    onMoveStart={onMoveStart}
                >
                    <Controls />
                    <MiniMap nodeStrokeWidth={3} zoomable pannable />
                    <Background gap={16} size={1} />
                </ReactFlow>
                 {menu && (
                    <ContextMenu
                        top={menu.top}
                        left={menu.left}
                        onClose={() => setMenu(null)}
                        onDelete={handleDeleteSelectedNodes}
                        onDuplicate={handleDuplicateSelectedNodes}
                    />
                )}
                {/* Toggle Buttons */}
                <div className="absolute top-4 left-4 z-10 md:hidden">
                    <button onClick={() => setSidebarOpen(true)} className="p-2 bg-white rounded-full shadow-lg text-gray-700 hover:bg-gray-100">
                        <Bars3Icon />
                    </button>
                </div>
                <div className="absolute top-4 right-4 z-10">
                    {!isSettingsOpen && (
                        <button onClick={() => setSettingsOpen(true)} className="p-2 bg-white rounded-full shadow-lg text-gray-700 hover:bg-gray-100">
                            <Cog6ToothIcon />
                        </button>
                    )}
                </div>
                </main>
                <SettingsPanel
                  key={selectedNode?.id ?? 'project-settings'}
                  node={selectedNode}
                  onUpdateNode={updateNodeData}
                  onDeleteNode={deleteNode}
                  onClose={handleCloseSettings}
                  projectSettings={projectSettings}
                  onUpdateProjectSettings={updateProjectSettings}
                  onExport={exportConfiguration}
                  onSave={saveProject}
                  onImport={importConfiguration}
                  isOpen={isSettingsOpen}
                  onShowInspector={showInspector}
                  onHideInspector={hideInspector}
                  onStartPicking={handleStartPicking}
                  onStopPicking={handleStopPicking}
                  pickingRuleId={inspectorConfig?.pickingState?.ruleId ?? null}
                  onInspectSelector={setHighlightedSelector}
                  highlightedSelector={highlightedSelector}
                  nodes={nodes}
                  edges={edges}
                />
            </ReactFlowProvider>
        </div>
        {inspectorConfig && (
            <InspectorPanel
                htmlContent={inspectorConfig.htmlContent}
                isPicking={!!inspectorConfig.pickingState}
                onClose={hideInspector}
                onSelectorPicked={handleSelectorPicked}
                highlightedSelector={highlightedSelector}
            />
        )}
    </div>
  );
};

export default App;
