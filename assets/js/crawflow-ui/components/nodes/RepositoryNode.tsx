import React, { memo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, RepositoryNodeData } from '../../types';
import { ArchiveBoxIcon } from '../icons';

const RepositoryNode: React.FC<CustomNodeProps<RepositoryNodeData>> = ({ data, selected }) => {
  return (
    <BaseNode title="Raw Items Repository" icon={<ArchiveBoxIcon />} selected={selected} bgColorClass="bg-slate-100">
      <div className="p-2 text-center bg-white/50 rounded-md">
        <p className="text-sm text-gray-600">
          This node holds the raw data fetched from the source.
        </p>
        <p className="text-xs text-gray-500 mt-2">
          Connect your next actions (like Loop or Extract) from here.
        </p>
      </div>
    </BaseNode>
  );
};

export default memo(RepositoryNode);