import React from 'react';

export default function ReviewStep({ data, prevStep }) {
  return (
    <div>
      <h2>Tổng quan & Lưu</h2>
      <pre>{JSON.stringify(data, null, 2)}</pre>
      <button onClick={prevStep}>Quay lại</button>
      <button>Lưu dự án</button>
    </div>
  );
}