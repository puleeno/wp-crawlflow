import React, { useRef, useState } from 'react';
import finder from '@medv/finder';

export default function MappingFieldStep({ data, setData, nextStep, prevStep }) {
  const [sampleUrl, setSampleUrl] = useState('');
  const [html, setHtml] = useState('');
  const [selectedField, setSelectedField] = useState('title');
  const [mapping, setMapping] = useState(data.mapping || {});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const iframeRef = useRef();

  // Fetch HTML từ backend REST API
  const fetchHtml = async () => {
    setLoading(true);
    setError('');
    setHtml('');
    try {
      const res = await fetch(`/wp-json/crawlflow/v1/fetch-html?url=${encodeURIComponent(sampleUrl)}`);
      if (!res.ok) throw new Error('Không thể tải HTML: ' + res.statusText);
      const text = await res.text();
      setHtml(text);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  // Khi user click vào element trong iframe
  const handleIframeLoad = () => {
    const iframe = iframeRef.current;
    if (!iframe) return;
    const doc = iframe.contentDocument || iframe.contentWindow.document;
    // Xóa sự kiện cũ nếu có
    doc.body.onclick = null;
    doc.body.onclick = event => {
      event.preventDefault();
      event.stopPropagation();
      const selector = finder(event.target);
      setMapping(m => {
        const newMap = { ...m, [selectedField]: selector };
        setData(d => ({ ...d, mapping: newMap }));
        return newMap;
      });
      alert(`Đã chọn selector cho ${selectedField}: ${selector}`);
    };
  };

  return (
    <div>
      <h2>Mapping Field</h2>
      <div style={{ marginBottom: 12 }}>
        <input
          type="text"
          placeholder="Nhập URL mẫu"
          value={sampleUrl}
          onChange={e => setSampleUrl(e.target.value)}
          style={{ width: 320 }}
        />
        <button onClick={fetchHtml} disabled={loading || !sampleUrl} style={{ marginLeft: 8 }}>
          {loading ? 'Đang tải...' : 'Tải HTML'}
        </button>
      </div>
      {error && <div style={{ color: 'red', marginBottom: 8 }}>{error}</div>}
      <div style={{ margin: '16px 0' }}>
        <label>Chọn field cần mapping: </label>
        <select value={selectedField} onChange={e => setSelectedField(e.target.value)}>
          <option value="title">Title</option>
          <option value="content">Content</option>
          <option value="image">Image</option>
          {/* Thêm các field khác nếu cần */}
        </select>
        <span style={{ marginLeft: 16 }}>
          Selector: <b>{mapping[selectedField] || '(chưa chọn)'}</b>
        </span>
      </div>
      {html && (
        <div style={{ border: '1px solid #ccc', marginBottom: 16 }}>
          <div style={{ background: '#f7f7f7', padding: 4, fontSize: 13, color: '#666' }}>
            Click vào phần tử trong trang mẫu để lấy selector cho field <b>{selectedField}</b>
          </div>
          <iframe
            ref={iframeRef}
            srcDoc={html}
            title="Sample DOM"
            style={{ width: '100%', height: 400, border: 'none' }}
            onLoad={handleIframeLoad}
          />
        </div>
      )}
      <div style={{ marginTop: 24 }}>
        <button onClick={prevStep}>Quay lại</button>
        <button onClick={nextStep} style={{ marginLeft: 8 }}>Tiếp tục</button>
      </div>
    </div>
  );
}