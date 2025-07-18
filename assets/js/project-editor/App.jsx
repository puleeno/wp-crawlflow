import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import Stepper from './components/Stepper';
import ProjectInfoStep from './steps/ProjectInfoStep';
import FeedParserStep from './steps/FeedParserStep';
import DynamicReceptionStep from './steps/DynamicReceptionStep';
import MappingFieldStep from './steps/MappingFieldStep';
import ProcessorChainStep from './steps/ProcessorChainStep';
import ReviewStep from './steps/ReviewStep';

const steps = [
  { label: 'Thông tin dự án', component: ProjectInfoStep },
  { label: 'Feed & Parser', component: FeedParserStep },
  { label: 'Dynamic Reception', component: DynamicReceptionStep },
  { label: 'Mapping Field', component: MappingFieldStep },
  { label: 'Processor Chain', component: ProcessorChainStep },
  { label: 'Tổng quan & Lưu', component: ReviewStep },
];

function App() {
  const [activeStep, setActiveStep] = useState(0);
  const [projectData, setProjectData] = useState({});

  const StepComponent = steps[activeStep].component;

  return (
    <div>
      <Stepper steps={steps} activeStep={activeStep} setActiveStep={setActiveStep} />
      <StepComponent
        data={projectData}
        setData={setProjectData}
        nextStep={() => setActiveStep(s => s + 1)}
        prevStep={() => setActiveStep(s => s - 1)}
      />
    </div>
  );
}

// Mount vào #crawlflow-project-editor-root nếu tồn tại
const rootEl = document.getElementById('crawlflow-project-editor-root');
if (rootEl) {
  createRoot(rootEl).render(<App />);
}

export default App;