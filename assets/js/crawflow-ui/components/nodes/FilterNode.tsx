import React, { memo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, ReceptionNodeData } from '../../types';
import { FunnelIcon } from '../icons';

const FilterNode: React.FC<CustomNodeProps<ReceptionNodeData>> = ({ data, selected }) => {
  const ruleCount = data.rules.length;

  return (
    <BaseNode title="Reception" icon={<FunnelIcon />} selected={selected} bgColorClass="bg-cyan-100">
      <div className="p-2 text-center bg-white/50 rounded-md">
        <p className="text-sm font-semibold text-gray-800">
          {ruleCount} {ruleCount === 1 ? 'Rule' : 'Rules'} Applied
        </p>
        <p className="text-xs text-gray-500 mt-1">
          Logic: <span className="font-bold uppercase text-cyan-600">{data.logic}</span>
        </p>
        <p className="text-xs text-gray-500 mt-2 italic">
          Only items passing these rules will continue.
        </p>
      </div>
    </BaseNode>
  );
};

export default memo(FilterNode);
