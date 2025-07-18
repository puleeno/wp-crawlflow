import React from 'react';

export default function MappingFieldStep({ data, setData, nextStep, prevStep }) {
  return (
    <div>
      <h2>Mapping Field</h2>
      {/* Placeholder: Mapping field, DOM inspect ở đây */}
      <button onClick={prevStep}>Quay lại</button>
      <button onClick={nextStep}>Tiếp tục</button>
    </div>
  );
}