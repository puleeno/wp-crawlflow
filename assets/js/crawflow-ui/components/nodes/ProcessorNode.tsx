import React, { memo } from 'react';
import BaseNode from './BaseNode';
import type { CustomNodeProps, ProcessorNodeData, SaveToDbSettings, SendToApiSettings, GenerateCsvSettings, SendEmailSettings } from '../../types';
import { Cog6ToothIcon } from '../icons';
import { useRegistry } from '../../hooks/useRegistry';

const ProcessorNode: React.FC<CustomNodeProps<ProcessorNodeData>> = ({ data, selected }) => {
  const { processors } = useRegistry();
  const processor = processors.find(p => p.type === data.processorType);
  const processorName = processor ? processor.label : 'Not Selected';

  const renderSummary = () => {
    switch (data.processorType) {
        case 'save-to-database': {
            const settings = data.settings;
            return `Table: ${settings.tableName || 'N/A'}`;
        }
        case 'send-to-api': {
            const settings = data.settings;
            const url = settings.endpointUrl.replace(/^https?:\/\//, '');
            const displayUrl = url.length > 25 ? `${url.substring(0, 25)}...` : url;
            return `${settings.method} to ${displayUrl}`;
        }
        case 'generate-csv-file': {
            const settings = data.settings;
            return `File: ${settings.fileName}`;
        }
        case 'send-email-notification': {
            const settings = data.settings;
            return `To: ${settings.recipients.split(',')[0]}`;
        }
        default:
            return 'No configuration summary available.';
    }
  };

  const fullSummary = () => {
     switch (data.processorType) {
        case 'save-to-database': {
            const s = data.settings;
            return `${s.user}@${s.host}/${s.database} -> ${s.tableName}`;
        }
        case 'send-to-api': {
            const s = data.settings;
            return `${s.method} to ${s.endpointUrl}`;
        }
        default:
            return renderSummary();
    }
  }


  return (
    <BaseNode title="Processor" icon={<Cog6ToothIcon />} selected={selected} bgColorClass="bg-slate-100">
      <div className="p-2 text-center bg-white/50 rounded-md">
        <p className="text-sm font-semibold text-gray-800">
          {processorName}
        </p>
         <p className="text-xs text-gray-600 mt-1 font-mono break-all" title={fullSummary()}>
          {renderSummary()}
        </p>
        <p className="text-xs text-gray-500 mt-2 italic">
          Handles the processing of mapped data.
        </p>
      </div>
    </BaseNode>
  );
};

export default memo(ProcessorNode);