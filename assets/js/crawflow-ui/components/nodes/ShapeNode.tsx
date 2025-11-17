import React, { memo } from 'react';
import { NodeResizer } from 'reactflow';
import type { CustomNodeProps, ShapeNodeData } from '../../types';

const ShapeNode: React.FC<CustomNodeProps<ShapeNodeData>> = ({ data, selected }) => {
  const { shapeType, label, backgroundColor, borderColor, textColor } = data;

  const selectionClass = selected ? 'ring-2 ring-blue-500 ring-offset-2' : '';

  // Special rendering for 'package' type to create a folder-like appearance
  if (shapeType === 'package') {
    return (
      <>
        <NodeResizer minWidth={150} minHeight={100} isVisible={selected} />
        <div className={`w-full h-full flex flex-col ${selectionClass}`}>
            {/* Tab */}
            <div 
                className="h-8 px-4 flex items-center self-start rounded-t-lg"
                style={{ 
                    backgroundColor: backgroundColor,
                    color: textColor,
                    borderTop: `2px solid ${borderColor}`,
                    borderLeft: `2px solid ${borderColor}`,
                    borderRight: `2px solid ${borderColor}`,
                 }}
            >
                <span className="font-semibold text-center break-words">{label}</span>
            </div>
            {/* Body */}
            <div 
                className="w-full flex-grow rounded-b-lg rounded-tr-lg"
                style={{
                    backgroundColor: backgroundColor,
                    borderLeft: `2px solid ${borderColor}`,
                    borderRight: `2px solid ${borderColor}`,
                    borderBottom: `2px solid ${borderColor}`,
                }}
            >
                {/* This is a container, so it's visually empty */}
            </div>
        </div>
      </>
    );
  }

  // Special rendering for 'frame' to position label like a legend
  if (shapeType === 'frame') {
    return (
      <>
        <NodeResizer minWidth={100} minHeight={50} isVisible={selected} />
        <div className={`w-full h-full relative ${selectionClass}`}>
          {/* The visible border */}
          <div
            className="w-full h-full rounded-lg"
            style={{
              borderColor,
              borderWidth: '4px',
              borderStyle: 'dashed',
            }}
          />
          {/* The label */}
          <div 
            className="absolute -top-3 left-4 px-1 bg-slate-100" // Match canvas background
          >
            <span 
              className="font-semibold text-left break-words"
              style={{ color: textColor }}
            >
              {label}
            </span>
          </div>
        </div>
      </>
    );
  }


  // Fallback for other shapes (rectangle, circle, ellipse)
  const shapeClasses: Record<string, string> = {
    rectangle: 'rounded-lg',
    circle: 'rounded-full',
    ellipse: 'rounded-full',
  };

  return (
    <>
      <NodeResizer minWidth={100} minHeight={50} isVisible={selected} />
      <div
        className={`w-full h-full flex items-center justify-center p-4 transition-all duration-200 ${shapeClasses[shapeType] || ''} ${selectionClass}`}
        style={{
          backgroundColor,
          borderColor,
          color: textColor,
          borderWidth: '2px',
          borderStyle: 'solid',
        }}
      >
        <span className="font-semibold text-center break-words">{label}</span>
      </div>
    </>
  );
};

export default memo(ShapeNode);
