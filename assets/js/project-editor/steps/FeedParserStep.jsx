import React from 'react';

export default function FeedParserStep({ data, setData, nextStep, prevStep }) {
  return (
    <div>
      <h2>Feed & Parser</h2>
      {/* Placeholder: Thêm feed và chọn parser ở đây */}
      <button onClick={prevStep}>Quay lại</button>
      <button onClick={nextStep}>Tiếp tục</button>
    </div>
  );
}