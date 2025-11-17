import React, { memo } from 'react';
import { Handle, Position } from 'reactflow';
import type { CustomNodeProps, LoopNodeData } from '../../types';
import { ArrowPathIcon } from '../icons';
import BaseNode from './BaseNode';

const LoopNode: React.FC<CustomNodeProps<LoopNodeData>> = ({ data, selected }) => {
  return (
    <div style={{ width: 400, height: 300 }}>
        <BaseNode title="Loop / For Each" icon={<ArrowPathIcon />} selected={selected} bgColorClass="bg-orange-100">
            <div className="bg-white/50 p-4 rounded-lg">
                <span className="text-sm font-medium text-gray-500 block">For each element matching selector:</span>
                <p className="text-md font-mono bg-white/70 p-2 mt-1 rounded break-all">
                {data.iteratorSelector || 'Not set'}
                </p>
                <p className="text-xs text-center text-gray-400 mt-4 italic">Drag and drop action nodes inside this loop</p>
            </div>
        </BaseNode>
    </div>
  );
};

export default memo(LoopNode);