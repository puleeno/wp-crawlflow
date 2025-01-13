import React, { useState, useCallback } from 'react';
import { BrowserRouter as Router, Routes, Route, Link } from 'react-router-dom';
import { ReactFlow, addEdge, MiniMap, Controls, Background, useNodesState, useEdgesState } from '@xyflow/react';
import '@xyflow/react/dist/style.css';

// Component cho màn hình quản lý Feed
const FeedManager = ({ feeds, onAddFeed }) => {
  return (
    <div>
      <h1>Feed Manager</h1>
      <button onClick={onAddFeed}>Add Feed</button>
      <ul>
        {feeds.map((feed, index) => (
          <li key={index}>
            <Link to={`/compose/${index}`}>{feed.label}</Link>
          </li>
        ))}
      </ul>
    </div>
  );
};

// Component cho màn hình Compose
const ComposeFeed = ({ feed, onBack }) => {
  const initialNodes = [
    { id: '1', type: 'input', data: { label: 'Feed' }, position: { x: 250, y: 5 } },
    { id: '2', data: { label: 'Tooth' }, position: { x: 100, y: 100 } },
    { id: '3', data: { label: 'DataRule' }, position: { x: 400, y: 100 } },
    { id: '4', data: { label: 'Processor' }, position: { x: 250, y: 200 } },
  ];

  const initialEdges = [
    { id: 'e1-2', source: '1', target: '2', animated: true },
    { id: 'e2-3', source: '2', target: '3', animated: true },
    { id: 'e3-4', source: '3', target: '4', animated: true },
  ];

  const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge(params, eds)),
    []
  );

  return (
    <div style={{ height: '100vh' }}>
      <Link to="/">Back to Feed Manager</Link>
      <h2>Editing Feed: {feed.label}</h2>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        fitView
      >
        <MiniMap />
        <Controls />
        <Background />
      </ReactFlow>
    </div>
  );
};

function App() {
  const [feeds, setFeeds] = useState([{ label: 'Feed 1' }, { label: 'Feed 2' }]);

  const handleAddFeed = () => {
    const newFeed = { label: `Feed ${feeds.length + 1}` };
    setFeeds([...feeds, newFeed]);
  };

  return (
    <Router>
      <Routes>
        <Route path="/" element={<FeedManager feeds={feeds} onAddFeed={handleAddFeed} />} />
        <Route
          path="/compose/:feedId"
          element={
            <ComposeFeed
              feed={feeds[parseInt(window.location.pathname.split('/').pop())]}
            />
          }
        />
      </Routes>
    </Router>
  );
}

export default App;
