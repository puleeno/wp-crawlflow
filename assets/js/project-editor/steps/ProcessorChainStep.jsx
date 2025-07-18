import React from 'react';

export default function ProcessorChainStep({ data, setData, nextStep, prevStep }) {
  return (
    <div>
      <h2>Processor Chain</h2>
      {/* Placeholder: Gán processor chain cho từng feed item */}
      <button onClick={prevStep}>Quay lại</button>
      <button onClick={nextStep}>Tiếp tục</button>
    </div>
  );
}