
import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// Mount vào WordPress container - đợi DOM ready và CSS load để tránh FOUC
function waitForCSS(callback: () => void, maxWait = 3000) {
  const startTime = Date.now();
  
  function checkCSS() {
    // Check if React Flow CSS is loaded
    const reactFlowStyles = Array.from(document.styleSheets).some(sheet => {
      try {
        return sheet.href && sheet.href.includes('reactflow');
      } catch (e) {
        return false;
      }
    });
    
    // Check if link element exists and is loaded
    const linkElement = document.querySelector('link[href*="reactflow@11.11.4"]') as HTMLLinkElement;
    const linkLoaded = linkElement && (linkElement.sheet || (linkElement as any).styleSheet);
    
    if (reactFlowStyles || linkLoaded) {
      callback();
      return;
    }
    
    // Timeout fallback
    if (Date.now() - startTime > maxWait) {
      console.warn('CrawlFlow: CSS load timeout, proceeding anyway');
      callback();
      return;
    }
    
    // Check again
    requestAnimationFrame(checkCSS);
  }
  
  checkCSS();
}

function initReactApp() {
  const rootElement = document.getElementById('crawlflow-react-flow-root') || document.getElementById('root');
  if (!rootElement) {
    console.warn("CrawlFlow: Root element not found. Expected #crawlflow-react-flow-root or #root");
    return;
  }

  // Wait for CSS to load before rendering
  waitForCSS(() => {
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
  });
}

// Đợi DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initReactApp);
} else {
  // DOM đã sẵn sàng
  initReactApp();
}
