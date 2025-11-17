

import React, { memo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, ClickNodeData } from '../../types';
import { CursorArrowRaysIcon } from '../icons';

const ClickNode: React.FC<CustomNodeProps<ClickNodeData>> = ({ data, selected }) => {
  return (
    <BaseNode title="Click Element" icon={<CursorArrowRaysIcon />} selected={selected} bgColorClass="bg-indigo-100">
      <div>
        <span className="text-sm font-medium text-gray-500 block">CSS Selector</span>
        <p className="text-md text-gray-800 font-mono bg-white/70 p-2 rounded break-all">
          {data.selector || 'Not set'}
        </p>
      </div>
    </BaseNode>
  );
};

export default memo(ClickNode);