// FIX: The content for this file was missing. This is the implementation for the StartNode component.
import React, { memo } from 'react';
import { Handle, Position } from 'reactflow';
import type { CustomNodeProps, StartNodeData, DataSourceType, MySQLConnection } from '../../types';
import { GlobeAltIcon, CloudIcon, DocumentTextIcon, DatabaseIcon, ArrowUpTrayIcon } from '../icons';

const isFileBasedSource = (sourceType: DataSourceType) => ['xml', 'csv', 'json'].includes(sourceType);

const sourceInfo: Record<DataSourceType, { icon: React.ReactNode; label: string; color: string }> = {
  url: { icon: <GlobeAltIcon />, label: 'Start URL', color: 'green' },
  api: { icon: <CloudIcon />, label: 'Start API', color: 'blue' },
  xml: { icon: <DocumentTextIcon />, label: 'Start XML', color: 'orange' },
  csv: { icon: <DocumentTextIcon />, label: 'Start CSV', color: 'purple' },
  json: { icon: <DocumentTextIcon />, label: 'Start JSON', color: 'yellow' },
  mysql: { icon: <DatabaseIcon />, label: 'Start MySQL', color: 'red' },
};

const StartNode: React.FC<CustomNodeProps<StartNodeData>> = ({ data, selected }) => {
  const selectionClass = selected ? 'ring-2 ring-blue-500 shadow-2xl' : 'shadow-lg';
  const { icon, label, color } = sourceInfo[data.sourceType] || sourceInfo.url;

  const headerTextColors: Record<string, string> = {
    green: 'text-green-900',
    blue: 'text-blue-900',
    orange: 'text-orange-900',
    purple: 'text-purple-900',
    yellow: 'text-yellow-900',
    red: 'text-red-900',
  }

  const bgColors: Record<string, string> = {
    green: 'bg-green-100',
    blue: 'bg-blue-100',
    orange: 'bg-orange-100',
    purple: 'bg-purple-100',
    yellow: 'bg-yellow-100',
    red: 'bg-red-100',
  }
  
  const borderColors: Record<string, string> = {
    green: 'border-green-200',
    blue: 'border-blue-200',
    orange: 'border-orange-200',
    purple: 'border-purple-200',
    yellow: 'border-yellow-200',
    red: 'border-red-200',
  }
  
  const renderContent = () => {
    const commonClasses = "text-md text-gray-800 font-mono bg-white/70 p-2 rounded break-all max-h-28 overflow-y-auto";

    if (isFileBasedSource(data.sourceType)) {
        switch(data.inputMethod) {
            case 'upload':
                return (
                    <div className="flex items-center gap-2 p-2 text-gray-700">
                        <ArrowUpTrayIcon /> 
                        <span className="font-sans font-semibold">{data.fileName || 'No file selected'}</span>
                    </div>
                );
            case 'cloudUrl':
                return <p className={commonClasses}>{String(data.sourceValue) || 'Cloud URL not set'}</p>;
            case 'paste':
            default:
                 const valueStr = String(data.sourceValue);
                return <p className={commonClasses}>{valueStr ? `${valueStr.substring(0, 80)}...` : 'Pasted content is empty'}</p>
        }
    }
    
    if (data.sourceType === 'mysql') {
        const conn = data.sourceValue as MySQLConnection;
        if (typeof conn === 'object' && conn !== null) {
            const { user, host, database } = conn;
            if (!user && !host && !database) {
                return <p className={commonClasses}>Not configured</p>;
            }
            return (
                <p className={commonClasses}>
                    {`${user || 'user'}@${host || 'host'}/${database || 'db'}`}
                </p>
            );
        }
        return <p className={commonClasses}>{String(data.sourceValue) || 'Not configured'}</p>;
    }


    return (
        <p className={commonClasses}>
        {String(data.sourceValue) || 'Not set'}
        </p>
    );
  }

  return (
    <div className={`rounded-lg border w-80 transition-all duration-200 ${selectionClass} ${bgColors[color]} ${borderColors[color]}`}>
      <div className={`flex items-center p-3 border-b ${borderColors[color]} rounded-t-lg bg-white/70 backdrop-blur-sm ${headerTextColors[color]}`}>
        <div className="mr-3">{icon}</div>
        <h2 className="text-lg font-bold">{label}</h2>
      </div>
      <div className="p-4 space-y-2">
        {renderContent()}
      </div>
      <Handle type="source" position={Position.Bottom} className="w-3 h-3 !bg-teal-500" />
    </div>
  );
};

export default memo(StartNode);