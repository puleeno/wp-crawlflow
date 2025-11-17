

import React from 'react';
import { Handle, Position } from 'reactflow';
import type { PropsWithChildren, ReactNode } from 'react';

interface BaseNodeProps {
  title: ReactNode;
  icon: ReactNode;
  children: ReactNode;
  selected: boolean;
  bgColorClass?: string;
}

const BaseNode: React.FC<PropsWithChildren<BaseNodeProps>> = ({ title, icon, children, selected, bgColorClass = 'bg-white' }) => {
  const selectionClass = selected ? 'ring-2 ring-blue-500 shadow-2xl' : 'border-slate-300 shadow-lg';
  
  return (
    <div className={`${bgColorClass} rounded-lg border w-80 transition-all duration-200 ${selectionClass}`}>
      <div className="flex items-center p-3 border-b border-slate-200 bg-white/70 backdrop-blur-sm rounded-t-lg">
        <div className="text-blue-500 mr-3">{icon}</div>
        <h2 className="text-lg font-bold text-gray-700 w-full">{title}</h2>
      </div>
      <div className="p-4 space-y-3">
        {children}
      </div>
      <Handle type="target" position={Position.Top} className="w-3 h-3 !bg-teal-500" />
      <Handle type="source" position={Position.Bottom} className="w-3 h-3 !bg-teal-500" />
    </div>
  );
};

export default BaseNode;