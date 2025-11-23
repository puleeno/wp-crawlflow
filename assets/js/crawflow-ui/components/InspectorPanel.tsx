
import React, { useRef, useEffect, useState } from 'react';
import { finder } from '@medv/finder';
import { XMarkIcon } from './icons';

interface InspectorPanelProps {
  htmlContent: string;
  isPicking: boolean;
  onClose: () => void;
  onSelectorPicked: (selector: string) => void;
  highlightedSelector: string | null;
}

const InspectorPanel: React.FC<InspectorPanelProps> = ({ htmlContent, isPicking, onClose, onSelectorPicked, highlightedSelector }) => {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [iframeBody, setIframeBody] = useState<HTMLBodyElement | null>(null);
  const [toast, setToast] = useState<{ visible: boolean; x: number; y: number; message: string } | null>(null);
  const highlightedElementsRef = useRef<HTMLElement[]>([]);


  // Effect to manage the iframe's loaded state.
  // When the iframe's srcDoc changes, it reloads. The 'load' event lets us know when it's ready.
  useEffect(() => {
    const iframe = iframeRef.current;
    if (!iframe) return;

    const handleLoad = () => {
      if (iframe.contentDocument) {
        // Inject a <base> tag to help the iframe resolve relative asset paths (like images/CSS) correctly.
        const base = iframe.contentDocument.createElement('base');
        base.href = document.location.href;
        iframe.contentDocument.head.appendChild(base);
        setIframeBody(iframe.contentDocument.body as HTMLBodyElement);
      }
    };

    iframe.addEventListener('load', handleLoad);
    
    // In some fast-refresh scenarios, the iframe might already be loaded.
    if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
      handleLoad();
    }

    return () => {
      iframe.removeEventListener('load', handleLoad);
    };
  }, [htmlContent]); // Rerun when htmlContent changes, forcing a reload.

  // Effect to highlight elements based on the `highlightedSelector` prop.
  useEffect(() => {
    // Cleanup previous highlights first
    highlightedElementsRef.current.forEach(el => {
      el.style.outline = '';
      el.style.outlineOffset = '';
      el.style.boxShadow = '';
    });
    highlightedElementsRef.current = [];

    if (!iframeBody || !highlightedSelector) {
      return;
    }
    
    try {
      const elements = iframeBody.querySelectorAll(highlightedSelector);
      if (elements.length > 0) {
        elements.forEach(el => {
          const htmlEl = el as HTMLElement;
          htmlEl.style.outline = '3px dashed #ea580c'; // orange-600
          htmlEl.style.outlineOffset = '2px';
          htmlEl.style.boxShadow = '0 0 10px 2px rgba(234, 88, 12, 0.6)';
          highlightedElementsRef.current.push(htmlEl);
        });
        
        // Scroll the first element into view
        (elements[0] as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    } catch (e) {
      console.error(`Invalid selector for highlighting: "${highlightedSelector}"`, e);
    }

    // Cleanup function for this effect
    return () => {
      highlightedElementsRef.current.forEach(el => {
        el.style.outline = '';
        el.style.outlineOffset = '';
        el.style.boxShadow = '';
      });
      highlightedElementsRef.current = [];
    };
  }, [iframeBody, highlightedSelector]);


  // Effect to manage all event listeners within the iframe.
  // It runs whenever the iframe body is ready or the picking state changes.
  useEffect(() => {
    if (!iframeBody) return;

    // Define options for the selector finder library to generate more robust selectors.
    const finderConfig = {
      root: iframeBody,
      className: (name: string): boolean => {
        // Blacklist of state-based classes to avoid selectors tied to temporary states.
        const stateBlacklist = ['active', 'selected', 'checked', 'disabled', 'focus', 'hover', 'visited', 'open', 'expanded'];
        if (stateBlacklist.some(state => name.toLowerCase().includes(state))) {
          return false;
        }

        // Blacklist of common JS framework classes for internal state management.
        const frameworkBlacklist = ['ng-dirty', 'ng-pristine', 'ng-touched', 'ng-valid', 'ng-invalid'];
        if (frameworkBlacklist.includes(name)) {
          return false;
        }
        
        // Filter out common generated class names from CSS-in-JS libraries.
        // This encourages using semantic or structural selectors, often including parent elements for context.
        if (/^(css|styled|sc)-[a-zA-Z0-9]{6,}$/.test(name)) {
          return false;
        }

        return true;
      },
    };

    let lastHovered: HTMLElement | null = null;

    // --- Handler for highlighting elements on mouseover ---
    const handleMouseover = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (target && target !== lastHovered) {
        if (lastHovered) {
          lastHovered.style.outline = '';
        }
        target.style.outline = '2px solid #3b82f6'; // blue-500
        lastHovered = target;
      }
    };

    // --- Handler for removing highlight ---
    const handleMouseout = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (target) {
        target.style.outline = '';
        lastHovered = null;
      }
    };

    // --- Handler for left-clicking to select an element ---
    const handleClick = (e: MouseEvent) => {
      e.preventDefault();
      e.stopPropagation();
      const target = e.target as HTMLElement;
      if (target) {
        target.style.outline = '';
        const selector = finder(target, finderConfig);
        onSelectorPicked(selector);
      }
    };

    // --- Handler for right-clicking to copy selector ---
    const handleContextMenu = (e: MouseEvent) => {
      e.preventDefault();
      const target = e.target as HTMLElement;
      if (target) {
        const selector = finder(target, finderConfig);
        navigator.clipboard.writeText(selector).then(() => {
          // Show a confirmation toast near the cursor
          setToast({
            visible: true,
            x: e.clientX,
            y: e.clientY,
            message: 'Selector Copied!'
          });
          setTimeout(() => setToast(null), 2000);
        }).catch(err => {
          console.error("Failed to copy selector: ", err);
          setToast({
            visible: true,
            x: e.clientX,
            y: e.clientY,
            message: 'Copy failed!'
          });
          setTimeout(() => setToast(null), 2000);
        });
      }
    };

    // Attach the context menu listener always
    iframeBody.addEventListener('contextmenu', handleContextMenu);

    // Attach picking-related listeners only if `isPicking` is true
    if (isPicking) {
      iframeBody.addEventListener('mouseover', handleMouseover);
      iframeBody.addEventListener('mouseout', handleMouseout);
      iframeBody.addEventListener('click', handleClick, true); // Use capture phase
    }

    // Cleanup function to remove listeners
    return () => {
      if (lastHovered) lastHovered.style.outline = '';
      iframeBody.removeEventListener('contextmenu', handleContextMenu);
      if (isPicking) {
        iframeBody.removeEventListener('mouseover', handleMouseover);
        iframeBody.removeEventListener('mouseout', handleMouseout);
        iframeBody.removeEventListener('click', handleClick, true);
      }
    };
  }, [iframeBody, isPicking, onSelectorPicked]);
  
  // Effect for toast visibility transition
  useEffect(() => {
    if (toast?.visible) {
      const timer = setTimeout(() => {
        setToast(t => (t ? { ...t, visible: false } : null));
      }, 1800); // Start fade out before removing
      return () => clearTimeout(timer);
    }
  }, [toast?.visible]);


  return (
    <div className="h-1/2 flex flex-col border-t-2 border-gray-300 bg-white shadow-lg">
      <header className="flex justify-between items-center p-2 bg-gray-100 border-b">
        <h3 className="font-bold text-gray-800">Inspector Preview</h3>
        <div className="flex items-center gap-4">
            <span className="text-sm text-gray-600 hidden md:block">Right-click to copy selector</span>
            {isPicking && (
                <div className="p-1 px-3 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold animate-pulse">
                    Picking Element...
                </div>
            )}
        </div>
        <button 
            onClick={onClose} 
            className="p-2 text-gray-600 rounded-md hover:bg-gray-200 hover:text-gray-900"
            title="Close Inspector"
        >
          <XMarkIcon />
        </button>
      </header>
      <div className="flex-1 overflow-hidden relative">
        <iframe
          ref={iframeRef}
          srcDoc={htmlContent}
          title="Inspector Preview"
          sandbox="allow-same-origin" // Security: No scripts, but allow content to be treated as same-origin
          className={`w-full h-full border-0 ${isPicking ? 'cursor-crosshair' : 'cursor-default'}`}
        />
        {toast && (
            <div 
                className={`absolute p-2 bg-black text-white text-sm rounded-md shadow-lg transition-opacity duration-200 pointer-events-none ${toast.visible ? 'opacity-75' : 'opacity-0'}`}
                style={{ left: `${toast.x + 15}px`, top: `${toast.y + 10}px`, zIndex: 100 }}
            >
                {toast.message}
            </div>
        )}
      </div>
    </div>
  );
};

export default InspectorPanel;