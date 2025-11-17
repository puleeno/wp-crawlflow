import React, { memo, useMemo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, DataExtractorNodeData } from '../../types';
import { TableCellsIcon } from '../icons';
import { PRESETS } from '../../presets';

const DataExtractorNode: React.FC<CustomNodeProps<DataExtractorNodeData>> = ({ data, selected }) => {
  const { presetNames, totalPresetRules } = useMemo(() => {
    const selectedPresets = data.presets || [];
    if (selectedPresets.length === 0) {
      return { presetNames: ['None'], totalPresetRules: 0 };
    }
    const names = selectedPresets.map(p => PRESETS[p]?.name || 'Unknown');
    const totalRules = selectedPresets.reduce((sum, p) => sum + (PRESETS[p]?.rules.length || 0), 0);
    return { presetNames: names, totalPresetRules: totalRules };
  }, [data.presets]);

  const customRuleCount = data.customRules.length;

  return (
    <BaseNode title="Data Extractor" icon={<TableCellsIcon />} selected={selected} bgColorClass="bg-teal-100">
      <div className="space-y-3">
        <div className="p-2 bg-white/50 rounded-md">
            <span className="text-sm font-medium text-gray-500 block text-center">Presets</span>
            <p className="text-center text-sm font-semibold text-gray-800 mt-1 truncate" title={presetNames.join(', ')}>
                {presetNames.join(', ')}
            </p>
             {totalPresetRules > 0 && (
                <p className="text-center text-xs text-gray-500 mt-1">
                    ({totalPresetRules} {totalPresetRules === 1 ? 'rule' : 'rules'})
                </p>
             )}
        </div>
        <div className="p-2 bg-white/50 rounded-md">
            <span className="text-sm font-medium text-gray-500 block text-center">Custom Rules</span>
            <p className="text-center text-sm font-semibold text-gray-800 mt-1">
                {customRuleCount} {customRuleCount === 1 ? 'Rule' : 'Rules'} Added
            </p>
        </div>
      </div>
    </BaseNode>
  );
};

export default memo(DataExtractorNode);