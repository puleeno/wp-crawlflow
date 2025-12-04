/**
 * Custom Dialog Component for CrawlFlow
 * 
 * Thay thế window.alert() và window.confirm() để tránh bị browser block
 * và cung cấp UI đẹp hơn, customizable hơn.
 */

import React, { useState, useCallback, createContext, useContext, ReactNode } from 'react';

// ============================================================================
// TYPES
// ============================================================================

export type DialogType = 'info' | 'warning' | 'error' | 'confirm';

export interface DialogConfig {
  type: DialogType;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  onConfirm?: () => void;
  onCancel?: () => void;
}

interface DialogContextValue {
  showDialog: (config: DialogConfig) => Promise<boolean>;
  showAlert: (message: string, type?: DialogType, title?: string) => Promise<void>;
  showConfirm: (message: string, title?: string) => Promise<boolean>;
}

// ============================================================================
// CONTEXT
// ============================================================================

const DialogContext = createContext<DialogContextValue | null>(null);

// ============================================================================
// HOOK
// ============================================================================

export function useDialog(): DialogContextValue {
  const context = useContext(DialogContext);
  if (!context) {
    throw new Error('useDialog must be used within DialogProvider');
  }
  return context;
}

// ============================================================================
// DIALOG COMPONENT
// ============================================================================

interface DialogProps {
  isOpen: boolean;
  config: DialogConfig | null;
  onClose: (confirmed: boolean) => void;
}

const Dialog: React.FC<DialogProps> = ({ isOpen, config, onClose }) => {
  if (!isOpen || !config) return null;

  const getIconAndColor = () => {
    switch (config.type) {
      case 'info':
        return { icon: 'ℹ️', color: 'blue', bgColor: 'bg-blue-50', borderColor: 'border-blue-200' };
      case 'warning':
        return { icon: '⚠️', color: 'yellow', bgColor: 'bg-yellow-50', borderColor: 'border-yellow-200' };
      case 'error':
        return { icon: '❌', color: 'red', bgColor: 'bg-red-50', borderColor: 'border-red-200' };
      case 'confirm':
        return { icon: '❓', color: 'blue', bgColor: 'bg-blue-50', borderColor: 'border-blue-200' };
      default:
        return { icon: 'ℹ️', color: 'gray', bgColor: 'bg-gray-50', borderColor: 'border-gray-200' };
    }
  };

  const { icon, color, bgColor, borderColor } = getIconAndColor();
  const isConfirmDialog = config.type === 'confirm';

  const handleConfirm = () => {
    if (config.onConfirm) config.onConfirm();
    onClose(true);
  };

  const handleCancel = () => {
    if (config.onCancel) config.onCancel();
    onClose(false);
  };

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      handleCancel();
    }
  };

  return (
    <div 
      className="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50"
      onClick={handleBackdropClick}
    >
      <div 
        className="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 animate-fadeIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className={`flex items-center gap-3 p-4 border-b ${borderColor}`}>
          <span className="text-2xl">{icon}</span>
          <h2 className="text-xl font-bold text-gray-800 flex-1">
            {config.title}
          </h2>
        </div>

        {/* Body */}
        <div className={`p-6 ${bgColor}`}>
          <div className="text-gray-700 whitespace-pre-wrap">
            {config.message}
          </div>
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-3 p-4 bg-gray-50 rounded-b-lg">
          {isConfirmDialog && (
            <button
              onClick={handleCancel}
              className="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition-colors"
            >
              {config.cancelText || 'Cancel'}
            </button>
          )}
          <button
            onClick={handleConfirm}
            className={`px-4 py-2 text-white rounded-md transition-colors ${
              color === 'blue' ? 'bg-blue-600 hover:bg-blue-700' :
              color === 'yellow' ? 'bg-yellow-600 hover:bg-yellow-700' :
              color === 'red' ? 'bg-red-600 hover:bg-red-700' :
              'bg-gray-600 hover:bg-gray-700'
            }`}
          >
            {config.confirmText || 'OK'}
          </button>
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// PROVIDER
// ============================================================================

interface DialogProviderProps {
  children: ReactNode;
}

export const DialogProvider: React.FC<DialogProviderProps> = ({ children }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [config, setConfig] = useState<DialogConfig | null>(null);
  const [resolvePromise, setResolvePromise] = useState<((value: boolean) => void) | null>(null);

  const showDialog = useCallback((dialogConfig: DialogConfig): Promise<boolean> => {
    return new Promise((resolve) => {
      setConfig(dialogConfig);
      setIsOpen(true);
      setResolvePromise(() => resolve);
    });
  }, []);

  const showAlert = useCallback(
    (message: string, type: DialogType = 'info', title?: string): Promise<void> => {
      return showDialog({
        type,
        title: title || (
          type === 'error' ? 'Error' :
          type === 'warning' ? 'Warning' :
          'Information'
        ),
        message,
        confirmText: 'OK'
      }).then(() => undefined);
    },
    [showDialog]
  );

  const showConfirm = useCallback(
    (message: string, title: string = 'Confirm'): Promise<boolean> => {
      return showDialog({
        type: 'confirm',
        title,
        message,
        confirmText: 'OK',
        cancelText: 'Cancel'
      });
    },
    [showDialog]
  );

  const handleClose = useCallback((confirmed: boolean) => {
    setIsOpen(false);
    if (resolvePromise) {
      resolvePromise(confirmed);
      setResolvePromise(null);
    }
    // Clear config after animation
    setTimeout(() => setConfig(null), 300);
  }, [resolvePromise]);

  const contextValue: DialogContextValue = {
    showDialog,
    showAlert,
    showConfirm
  };

  return (
    <DialogContext.Provider value={contextValue}>
      {children}
      <Dialog isOpen={isOpen} config={config} onClose={handleClose} />
    </DialogContext.Provider>
  );
};

// ============================================================================
// CSS ANIMATION (add to your global styles or Tailwind config)
// ============================================================================

// @keyframes fadeIn {
//   from { opacity: 0; transform: scale(0.95); }
//   to { opacity: 1; transform: scale(1); }
// }
// .animate-fadeIn {
//   animation: fadeIn 0.2s ease-out;
// }

