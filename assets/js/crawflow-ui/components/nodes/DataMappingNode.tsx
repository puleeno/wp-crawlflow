
import React, { memo, useMemo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, HTMLDataExtractorNodeData, CSVExtractorNodeData, JSONExtractorNodeData, XMLExtractorNodeData, MySQLExtractorNodeData } from '../../types';
import { TableCellsIcon, DocumentTextIcon, DatabaseIcon } from '../icons';
import { PRESETS } from '../../presets';

const HTMLDataExtractorNode: React.FC<CustomNodeProps<HTMLDataExtractorNodeData>> = ({ data, selected }) => {
  const { presetNames, totalPresetRules } = useMemo(() => {
    const selectedPresets = data.presets || [];
    if (selectedPresets.length === 0) {
      return { presetNames: ['None'], totalPresetRules: 0 };
    }
    const names = selectedPresets.map(p => PRESETS[p as string]?.name || 'Unknown');
    const totalRules = selectedPresets.reduce((sum, p) => sum + (PRESETS[p as string]?.html?.rules.length || 0), 0);
    return { presetNames: names, totalPresetRules: totalRules };
  }, [data.presets]);

  const customRuleCount = data.customRules.length - totalPresetRules;

  return (
    <BaseNode title="HTML Data Extractor" icon={<TableCellsIcon />} selected={selected} bgColorClass="bg-teal-100">
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

const PresetSummary: React.FC<{ presets: string[] }> = ({ presets }) => {
    const presetNames = useMemo(() => {
        const selectedPresets = presets || [];
        if (selectedPresets.length === 0) return 'None';
        return selectedPresets.map(p => PRESETS[p]?.name || 'Unknown').join(', ');
    }, [presets]);

    return (
         <div className="p-2 bg-white/50 rounded-md">
            <span className="text-sm font-medium text-gray-500 block text-center">Presets</span>
            <p className="text-center text-sm font-semibold text-gray-800 mt-1 truncate" title={presetNames}>
                {presetNames}
            </p>
        </div>
    )
}

export const CSVExtractorNode: React.FC<CustomNodeProps<CSVExtractorNodeData>> = ({ data, selected }) => {
  const mappingCount = data.mappings?.length || 0;
  return (
    <BaseNode title="CSV Data Extractor" icon={<DocumentTextIcon />} selected={selected} bgColorClass="bg-teal-100">
      <div className="space-y-3">
        <PresetSummary presets={data.presets} />
        <div className="p-2 bg-white/50 rounded-md text-center">
            <span className="text-sm font-medium text-gray-500 block">Total Mappings</span>
            <p className="text-sm font-semibold text-gray-800 mt-1">
            {mappingCount} {mappingCount === 1 ? 'Field' : 'Fields'}
            </p>
        </div>
      </div>
    </BaseNode>
  );
};

export const JSONExtractorNode: React.FC<CustomNodeProps<JSONExtractorNodeData>> = ({ data, selected }) => {
  const mappingCount = data.mappings?.length || 0;
  return (
    <BaseNode title="JSON Data Extractor" icon={<DocumentTextIcon />} selected={selected} bgColorClass="bg-teal-100">
        <div className="space-y-3">
            <PresetSummary presets={data.presets} />
            <div className="p-2 bg-white/50 rounded-md text-center">
                <span className="text-sm font-medium text-gray-500 block">Total Mappings</span>
                <p className="text-sm font-semibold text-gray-800 mt-1">
                {mappingCount} {mappingCount === 1 ? 'Field' : 'Fields'}
                </p>
            </div>
        </div>
    </BaseNode>
  );
};

export const XMLExtractorNode: React.FC<CustomNodeProps<XMLExtractorNodeData>> = ({ data, selected }) => {
  const mappingCount = data.mappings?.length || 0;
  return (
    <BaseNode title="XML Data Extractor" icon={<DocumentTextIcon />} selected={selected} bgColorClass="bg-teal-100">
        <div className="space-y-3">
            <PresetSummary presets={data.presets} />
            <div className="p-2 bg-white/50 rounded-md text-center">
                <span className="text-sm font-medium text-gray-500 block">Total Mappings</span>
                <p className="text-sm font-semibold text-gray-800 mt-1">
                {mappingCount} {mappingCount === 1 ? 'Field' : 'Fields'}
                </p>
            </div>
        </div>
    </BaseNode>
  );
};

export const MySQLExtractorNode: React.FC<CustomNodeProps<MySQLExtractorNodeData>> = ({ data, selected }) => {
  const mappingCount = data.mappings?.length || 0;
  return (
    <BaseNode title="MySQL Data Extractor" icon={<DatabaseIcon />} selected={selected} bgColorClass="bg-teal-100">
        <div className="space-y-3">
            <PresetSummary presets={data.presets} />
            <div className="p-2 bg-white/50 rounded-md text-center">
                <span className="text-sm font-medium text-gray-500 block">Total Mappings</span>
                <p className="text-sm font-semibold text-gray-800 mt-1">
                {mappingCount} {mappingCount === 1 ? 'Field' : 'Fields'}
                </p>
            </div>
        </div>
    </BaseNode>
  );
};

export default memo(HTMLDataExtractorNode);
