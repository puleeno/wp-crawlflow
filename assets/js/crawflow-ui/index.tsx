import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// WordPress integration: Wait for DOM and mount to WordPress container
function waitForElement(callback: () => void, maxWait = 5000) {
  const startTime = Date.now();
  
  function checkElement() {
    // Check for WordPress container first, then fallback to root
    const rootElement = document.getElementById('crawlflow-react-flow-root') || document.getElementById('root');
    
    if (rootElement) {
      callback();
      return;
    }
    
    // Timeout fallback
    if (Date.now() - startTime > maxWait) {
      console.warn('CrawlFlow: Root element not found after timeout. Expected #crawlflow-react-flow-root or #root');
      return;
    }
    
    // Check again
    requestAnimationFrame(checkElement);
  }
  
  checkElement();
}

function initReactApp() {
  // WordPress container or fallback to root
  const rootElement = document.getElementById('crawlflow-react-flow-root') || document.getElementById('root');
  if (!rootElement) {
    console.warn("CrawlFlow: Root element not found. Expected #crawlflow-react-flow-root or #root");
    return;
  }

  // Suppress the benign "ResizeObserver loop" error.
  // This error is a browser warning that often crashes React apps in development but is safe to ignore.
  const resizeObserverLoopErr = /ResizeObserver loop limit exceeded|ResizeObserver loop completed with undelivered notifications/;

  const originalError = console.error;
  console.error = (...args) => {
    if (args.length > 0 && typeof args[0] === 'string' && resizeObserverLoopErr.test(args[0])) {
      return;
    }
    originalError.call(console, ...args);
  };

  window.addEventListener('error', (event) => {
    if (typeof event.message === 'string' && resizeObserverLoopErr.test(event.message)) {
      event.stopImmediatePropagation();
    }
  });

  try {
    // Mark as loaded for visibility
    rootElement.classList.add('react-flow-loaded');
    
    const root = ReactDOM.createRoot(rootElement);
    root.render(
      <React.StrictMode>
        <App />
      </React.StrictMode>
    );
  } catch (error) {
    console.error('CrawlFlow: Error initializing React app:', error);
  }
}

// Wait for DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    waitForElement(initReactApp);
  });
} else {
  // DOM already ready
  waitForElement(initReactApp);
}
