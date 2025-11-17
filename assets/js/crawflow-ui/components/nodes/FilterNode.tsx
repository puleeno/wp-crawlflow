import React, { memo } from 'react';
import BaseNode from './BaseNode';
// FIX: Corrected the type from 'ReceptionRuleNodeData' to 'ReceptionNodeData' to match the definition in '../../types'.
import type { CustomNodeProps, ReceptionNodeData } from '../../types';
import { FunnelIcon } from '../icons';

const FilterNode: React.FC<CustomNodeProps<ReceptionNodeData>> = ({ data, selected }) => {
  const ruleCount = data.rules.length;

  return (
    <BaseNode title="Filter & Validate" icon={<FunnelIcon />} selected={selected}>
      <div className="p-2 text-center bg-gray-50 rounded-md">
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
