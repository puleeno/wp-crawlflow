import React, { memo } from 'react';
import { Handle, Position } from 'reactflow';
import type { CustomNodeProps, CompletionNodeData } from '../../types';
import { FlagIcon } from '../icons';

const CompletionNode: React.FC<CustomNodeProps<CompletionNodeData>> = ({ data, selected }) => {
  const selectionClass = selected ? 'ring-2 ring-blue-500 shadow-2xl' : 'border-green-200 shadow-lg';

  return (
    <div className={`bg-green-100 rounded-lg border w-80 transition-all duration-200 ${selectionClass}`}>
      <div className="flex items-center p-3 border-b border-green-200 bg-white/70 backdrop-blur-sm rounded-t-lg text-green-800">
        <div className="mr-3"><FlagIcon /></div>
        <h2 className="text-lg font-bold">Completion Actions</h2>
      </div>
      <div className="p-4">
        <div className="flex justify-between items-center text-sm py-2 px-3 rounded bg-white border border-slate-200">
            <span className="font-medium text-gray-700">Reporting</span>
            <span className="font-bold px-2 py-0.5 rounded-full text-xs text-green-700 bg-green-100">
                ENABLED
            </span>
        </div>
      </div>
       <p className="text-xs text-center text-gray-500 p-2 italic">
          The workflow finishes here.
        </p>
      <Handle type="target" position={Position.Top} className="w-3 h-3 !bg-teal-500" />
    </div>
  );
};

export default memo(CompletionNode);