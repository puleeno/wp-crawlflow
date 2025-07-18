import React from 'react';

export default function DynamicReceptionStep({ data, setData, nextStep, prevStep }) {
  return (
    <div>
      <h2>Dynamic Reception (Compose Logic)</h2>
      {/* Placeholder: Logic builder cho phân loại feed item */}
      <button onClick={prevStep}>Quay lại</button>
      <button onClick={nextStep}>Tiếp tục</button>
    </div>
  );
}