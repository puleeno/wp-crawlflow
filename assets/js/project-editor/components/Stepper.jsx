import React from 'react';

export default function Stepper({ steps, activeStep, setActiveStep }) {
  return (
    <div className="crawlflow-stepper" style={{ display: 'flex', gap: 12, marginBottom: 24 }}>
      {steps.map((step, idx) => (
        <div
          key={step.label}
          style={{
            padding: '8px 16px',
            borderRadius: 4,
            background: idx === activeStep ? '#0073aa' : '#f1f1f1',
            color: idx === activeStep ? '#fff' : '#333',
            cursor: idx <= activeStep ? 'pointer' : 'default',
            fontWeight: idx === activeStep ? 'bold' : 'normal',
            border: idx === activeStep ? '2px solid #0073aa' : '1px solid #ccc'
          }}
          onClick={() => idx <= activeStep && setActiveStep(idx)}
        >
          {idx + 1}. {step.label}
        </div>
      ))}
    </div>
  );
}
