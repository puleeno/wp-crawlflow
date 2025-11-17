import React, { memo, useMemo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, WorkerNodeData } from '../../types';
import { CpuChipIcon } from '../icons';

const WorkerNode: React.FC<CustomNodeProps<WorkerNodeData>> = ({ data, selected }) => {
  const ruleSummary = useMemo(() => {
    if (!data.detectionRules || data.detectionRules.length === 0) {
      return 'No detection rules';
    }

    const counts = data.detectionRules.reduce((acc, rule) => {
      const typeName = {
        'url-format': 'URL',
        'html-contains': 'HTML',
        'dom-value': 'DOM',
        'tag-attribute': 'Attribute',
        'data-source-type': 'Source Type',
      }[rule.type];
      acc[typeName] = (acc[typeName] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    return Object.entries(counts)
      .map(([name, count]) => `${count} ${name}`)
      .join(', ');
  }, [data.detectionRules]);

  const title = (
    <div className="flex justify-between items-center">
      <span>Worker</span>
      <span className="text-xs font-bold bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full">
        Priority: {data.priority}
      </span>
    </div>
  );

  return (
    <BaseNode title={title} icon={<CpuChipIcon />} selected={selected} bgColorClass="bg-purple-100">
      <div className="space-y-3">
        <div className="p-2 bg-white/50 rounded-md">
            <span className="text-sm font-medium text-gray-500 block text-center">Detection Logic</span>
            <p className="text-center text-sm font-semibold text-gray-800 mt-1">
                {ruleSummary} (<span className="font-bold uppercase text-purple-600">{data.detectionLogic}</span>)
            </p>
        </div>
         <div className="p-2 text-center">
            <p className="text-xs text-gray-500 mt-1 italic">
                This node processes data based on its detection rules.
            </p>
        </div>
      </div>
    </BaseNode>
  );
};

export default memo(WorkerNode);