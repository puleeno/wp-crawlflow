import React from 'react';

export default function ProjectInfoStep({ data, setData, nextStep }) {
  return (
    <div>
      <h2>Thông tin dự án</h2>
      <input
        type="text"
        placeholder="Tên dự án"
        value={data.name || ''}
        onChange={e => setData(d => ({ ...d, name: e.target.value }))}
      />
      <br />
      <textarea
        placeholder="Mô tả"
        value={data.description || ''}
        onChange={e => setData(d => ({ ...d, description: e.target.value }))}
      />
      <br />
      <button onClick={nextStep}>Tiếp tục</button>
    </div>
  );
}