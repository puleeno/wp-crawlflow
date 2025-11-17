

// FIX: The content for this file was missing. This is a complete implementation of the main App component.
// FIX: Import `useState`, `useCallback`, `useMemo`, and `ChangeEvent` from React to fix missing name errors.
import React, { useState, useCallback, useMemo, ChangeEvent, useEffect } from 'react';
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
} from 'reactflow';

import Sidebar from './components/Sidebar';
import SettingsPanel from './components/SettingsPanel';
import InspectorPanel from './components/InspectorPanel';
import StartNode from './components/nodes/StartNode';
import ClickNode from './components/nodes/ClickNode';
import WorkerNode from './components/nodes/WorkerNode';
import LoopNode from './components/nodes/LoopNode';
import RepositoryNode from './components/nodes/RepositoryNode';
import ReceptionNode from './components/nodes/ReceptionRuleNode';
import DataExtractorNode from './components/nodes/DataMappingNode';
import ProcessorNode from './components/nodes/ProcessorNode';
import CompletionNode from './components/nodes/CompletionNode';
import { Bars3Icon, Cog6ToothIcon } from './components/icons';


import { NodeData, ProjectSettings, DataExtractorNodeData, ExtractionRule } from './types';

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

interface InspectorConfig {
    htmlContent: string;
    pickingState: {
        nodeId: string;
        ruleId: string;
    } | null;
}

const App: React.FC = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNode, setSelectedNode] = useState<Node | null>(null);
  const [projectSettings, setProjectSettings] = useState<ProjectSettings>({
    name: 'My Crawler Project',
    description: 'A new web crawler configuration.',
    crawlDelay: 1000,
    userAgent: 'Crawler/1.0',
    concurrency: 5,
  });
  
  // State for mobile UI
  const [isSidebarOpen, setSidebarOpen] = useState(false);
  const [isSettingsOpen, setSettingsOpen] = useState(false);

  // State for the Inspector Panel
  const [inspectorConfig, setInspectorConfig] = useState<InspectorConfig | null>(null);
  const [highlightedSelector, setHighlightedSelector] = useState<string | null>(null);


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
  
  // Effect to clean up completion node when no processors exist
  useEffect(() => {
    const hasProcessorNode = nodes.some(n => n.type === 'processor');
    const hasCompletionNode = nodes.some(n => n.id === COMPLETION_NODE_ID);

    if (!hasProcessorNode && hasCompletionNode) {
      setNodes((nds) => nds.filter((node) => node.id !== COMPLETION_NODE_ID));
    }
  }, [nodes, setNodes]);
  
  
  // Effect to recenter repository and completion nodes
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
    
    const processorNodes = nodes.filter(n => n.type === 'processor');
    const completionNode = nodes.find(n => n.id === COMPLETION_NODE_ID);

    if (processorNodes.length > 0 && completionNode) {
        const newAvgX = processorNodes.reduce((sum, node) => sum + node.position.x, 0) / processorNodes.length;
        const newMaxY = Math.max(...processorNodes.map(n => n.position.y));
        const newY = newMaxY + NODE_V_SPACING;

        if (completionNode.position.x !== newAvgX || completionNode.position.y !== newY) {
            setNodes(nds => 
                nds.map(n => 
                    n.id === COMPLETION_NODE_ID 
                    ? { ...n, position: { x: newAvgX, y: newY } } 
                    : n
                )
            );
        }
    }
  }, [nodes, setNodes]);


  const onConnect = useCallback((params: Edge | Connection) => {
    const sourceNode = nodes.find(n => n.id === params.source);
    const targetNode = nodes.find(n => n.id === params.target);

    if (sourceNode?.type === 'repository' && targetNode?.type !== 'worker') {
      console.warn("Connection prevented: Raw Items Repository can only connect to a Worker node.");
      return;
    }

    if (targetNode?.type === 'completion') {
      console.warn("Connection prevented: Connections to the Completion node are managed automatically.");
      return;
    }

    setEdges((eds) => addEdge(params, eds));
  }, [nodes, setEdges]);

  const onSelectionChange = useCallback(({ nodes: selectedNodes }: OnSelectionChangeParams) => {
    const newSelectedNode = selectedNodes.length === 1 ? selectedNodes[0] : null;
    setSelectedNode(newSelectedNode);
    // On mobile, automatically open settings when a node is selected
    if (newSelectedNode && window.innerWidth < 768) {
        setSettingsOpen(true);
    }
  }, []);

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

    // Worker Input Nodes (DataExtractor)
    if (type === 'data-extractor' && sourceNode?.type === 'worker') {
        const hasDataExtractorInput = edges.some(edge => {
            if (edge.target !== sourceNode.id) return false;
            const sourceNodeFromEdge = nodes.find(n => n.id === edge.source);
            return sourceNodeFromEdge?.type === 'data-extractor';
        });

        if (hasDataExtractorInput) {
            alert("This Worker node can only have one Data Extractor input.");
            return;
        }

        const worker = sourceNode;
        const workerInputNodes = edges.filter(e => e.target === worker.id && nodes.find(n => n.id === e.source)?.type === 'data-extractor').length;
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

    // Processor Node Logic (auto-handles Completion node)
    if (type === 'processor') {
        const newProcessorId = getId();
        let position: XYPosition;
        if (sourceNode) {
            const childEdgesCount = edges.filter(e => e.source === sourceNode.id).length;
            position = {
                x: sourceNode.position.x + (childEdgesCount * (NODE_H_SPACING / 2)),
                y: sourceNode.position.y + NODE_V_SPACING
            };
        } else {
            position = { x: 400, y: 1000 }; // Fallback
        }

        const newProcessorNode: Node = {
            id: newProcessorId,
            type: 'processor',
            position,
            data,
        };

        const nodesToAdd: Node[] = [newProcessorNode];
        let edgesToAdd: Edge[] = [];

        if (sourceNode) {
            edgesToAdd.push({ id: `e-${sourceNode.id}-${newProcessorId}`, source: sourceNode.id, target: newProcessorId, animated: true });
        }

        const completionNodeExists = nodes.some(n => n.id === COMPLETION_NODE_ID);

        if (!completionNodeExists) {
            const otherProcessors = nodes.filter(n => n.type === 'processor');
            const allProcessors = [...otherProcessors, newProcessorNode];
            const avgX = allProcessors.reduce((sum, node) => sum + node.position.x, 0) / allProcessors.length;
            const maxY = Math.max(...allProcessors.map(n => n.position.y));

            const newCompletionNode: Node = {
                id: COMPLETION_NODE_ID,
                type: 'completion',
                position: { x: avgX, y: maxY + NODE_V_SPACING },
                data: { reportEnabled: true },
                deletable: false,
                draggable: false,
            };
            nodesToAdd.push(newCompletionNode);
            
            const processorEdges = allProcessors.map(p => ({
                id: `e-${p.id}-${COMPLETION_NODE_ID}`,
                source: p.id,
                target: COMPLETION_NODE_ID,
                animated: true,
            }));
            edgesToAdd = [...edgesToAdd, ...processorEdges];
        } else {
            edgesToAdd.push({ id: `e-${newProcessorId}-${COMPLETION_NODE_ID}`, source: newProcessorId, target: COMPLETION_NODE_ID, animated: true });
        }

        setNodes(nds => nds.concat(nodesToAdd));
        setEdges(eds => eds.concat(edgesToAdd));

        return;
    }


    // Standard logic for other action nodes (Click, Loop)
    const newNodeId = getId();
    let position: XYPosition;
    if (sourceNode) {
      const childEdgesCount = edges.filter(e => e.source === sourceNode.id).length;
      position = { 
        x: sourceNode.position.x + (childEdgesCount * (NODE_H_SPACING / 2)),
        y: sourceNode.position.y + NODE_V_SPACING
      };
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

    if (sourceNode) {
      const newEdge: Edge = {
        id: `e-${sourceNode.id}-${newNodeId}`,
        source: sourceNode.id,
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

  // WordPress integration: Save to WordPress
  const saveToWordPress = useCallback(async () => {
    const config = {
      projectSettings,
      nodes,
      edges,
    };
    
    // Check if we have WordPress config
    if (typeof window.crawlflowConfig === 'undefined') {
      console.warn('WordPress config not found, using export instead');
      exportConfiguration();
      return;
    }
    
    const { ajaxUrl, nonce, projectId } = window.crawlflowConfig;
    
    try {
      const configJson = JSON.stringify(config);
      
      // Validate JSON before sending
      try {
        JSON.parse(configJson);
      } catch (e) {
        console.error('Invalid JSON before sending:', e);
        alert('Error: Invalid configuration data. Please check your project settings.');
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'crawlflow_save_flow_config');
      formData.append('nonce', nonce);
      formData.append('project_id', projectId || '0');
      formData.append('config', configJson);
      
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        alert('Project saved successfully!');
        // Update project ID if it's a new project
        if (result.data?.project_id && !projectId) {
          window.crawlflowConfig.projectId = result.data.project_id;
          // Update URL without reload
          const url = new URL(window.location.href);
          url.searchParams.set('project_id', result.data.project_id);
          window.history.pushState({}, '', url);
        }
      } else {
        const errorMsg = result.data?.message || result.data?.debug_info?.first_chars || 'Unknown error';
        console.error('Save error:', result);
        alert('Failed to save project: ' + errorMsg);
      }
    } catch (error) {
      console.error('Error saving to WordPress:', error);
      alert('Error saving project: ' + (error instanceof Error ? error.message : 'Please try again.'));
    }
  }, [projectSettings, nodes, edges, exportConfiguration]);
  
  // Load from WordPress on mount
  useEffect(() => {
    if (typeof window.crawlflowConfig !== 'undefined' && window.crawlflowConfig.projectConfig) {
      const config = window.crawlflowConfig.projectConfig;
      if (config.projectSettings) {
        setProjectSettings(config.projectSettings);
      }
      if (config.nodes) {
        setNodes(config.nodes);
      }
      if (config.edges) {
        setEdges(config.edges);
      }
    }
  }, []);

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
    reception: (props) => <ReceptionNode {...props} />,
    'data-extractor': (props) => <DataExtractorNode {...props} />,
    processor: (props) => <ProcessorNode {...props} />,
    completion: (props) => <CompletionNode {...props} />,
  }), []);

  const handleCloseSettings = () => {
    setSettingsOpen(false);
    setSelectedNode(null);
  };

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

      if (targetNode && targetNode.type === 'data-extractor') {
          const nodeData = targetNode.data as DataExtractorNodeData;
          const updatedRules = nodeData.customRules.map(rule =>
              rule.id === ruleId ? { ...rule, selector } : rule
          );
          updateNodeData(nodeId, { ...nodeData, customRules: updatedRules });
      }

      handleStopPicking();
  }, [inspectorConfig, nodes, updateNodeData, handleStopPicking]);
  

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
                />
                <main className="flex-1 h-full relative">
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    nodeTypes={nodeTypes}
                    onSelectionChange={onSelectionChange}
                    onNodeDragStop={onNodeDragStop}
                    fitView
                    className="bg-slate-100"
                >
                    <Controls />
                    <MiniMap nodeStrokeWidth={3} zoomable pannable />
                    <Background gap={16} size={1} />
                </ReactFlow>
                {/* Mobile Toggle Buttons */}
                <div className="absolute top-4 left-4 z-10 md:hidden">
                    <button onClick={() => setSidebarOpen(true)} className="p-2 bg-white rounded-full shadow-lg text-gray-700 hover:bg-gray-100">
                        <Bars3Icon />
                    </button>
                </div>
                <div className="absolute top-4 right-4 z-10 md:hidden">
                    <button onClick={() => setSettingsOpen(true)} className="p-2 bg-white rounded-full shadow-lg text-gray-700 hover:bg-gray-100">
                        <Cog6ToothIcon />
                    </button>
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
                  onImport={importConfiguration}
                  onSaveToWordPress={saveToWordPress}
                  isOpen={isSettingsOpen}
                  onShowInspector={showInspector}
                  onHideInspector={hideInspector}
                  onStartPicking={handleStartPicking}
                  onStopPicking={handleStopPicking}
                  pickingRuleId={inspectorConfig?.pickingState?.ruleId ?? null}
                  onInspectSelector={setHighlightedSelector}
                  highlightedSelector={highlightedSelector}
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