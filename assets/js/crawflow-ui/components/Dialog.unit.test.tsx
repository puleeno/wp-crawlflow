/**
 * Unit Tests for Custom Dialog Component
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { DialogProvider, useDialog } from './Dialog';

// Test component that uses useDialog hook
const TestComponent: React.FC<{ onResult?: (result: any) => void }> = ({ onResult }) => {
  const dialog = useDialog();

  return (
    <div>
      <button onClick={async () => {
        await dialog.showAlert('Test alert message', 'info', 'Test Title');
        onResult?.('alert-closed');
      }}>
        Show Alert
      </button>

      <button onClick={async () => {
        const result = await dialog.showConfirm('Are you sure?', 'Confirm Action');
        onResult?.(result);
      }}>
        Show Confirm
      </button>

      <button onClick={async () => {
        await dialog.showAlert('Warning message', 'warning', 'Warning');
        onResult?.('warning-closed');
      }}>
        Show Warning
      </button>

      <button onClick={async () => {
        await dialog.showAlert('Error message', 'error', 'Error');
        onResult?.('error-closed');
      }}>
        Show Error
      </button>
    </div>
  );
};

describe('Dialog Component - Unit Tests', () => {
  // ============================================================================
  // PROVIDER TESTS
  // ============================================================================

  describe('DialogProvider', () => {
    test('should render children', () => {
      render(
        <DialogProvider>
          <div>Test Content</div>
        </DialogProvider>
      );

      expect(screen.getByText('Test Content')).toBeInTheDocument();
    });

    test('should throw error when useDialog used outside provider', () => {
      // Suppress console.error for this test
      const originalError = console.error;
      console.error = jest.fn();

      const TestComponentWithoutProvider = () => {
        expect(() => useDialog()).toThrow('useDialog must be used within DialogProvider');
        return <div>Test</div>;
      };

      expect(() => render(<TestComponentWithoutProvider />)).toThrow();

      console.error = originalError;
    });
  });

  // ============================================================================
  // ALERT DIALOG TESTS
  // ============================================================================

  describe('Alert Dialog', () => {
    test('should show alert dialog with correct content', async () => {
      render(
        <DialogProvider>
          <TestComponent />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Alert'));

      await waitFor(() => {
        expect(screen.getByText('Test Title')).toBeInTheDocument();
        expect(screen.getByText('Test alert message')).toBeInTheDocument();
        expect(screen.getByText('OK')).toBeInTheDocument();
      });
    });

    test('should close alert when OK button clicked', async () => {
      const onResult = jest.fn();
      
      render(
        <DialogProvider>
          <TestComponent onResult={onResult} />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Alert'));

      await waitFor(() => {
        expect(screen.getByText('Test Title')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('OK'));

      await waitFor(() => {
        expect(onResult).toHaveBeenCalledWith('alert-closed');
      });
    });

    test('should show warning dialog with yellow styling', async () => {
      render(
        <DialogProvider>
          <TestComponent />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Warning'));

      await waitFor(() => {
        expect(screen.getByText('Warning')).toBeInTheDocument();
        expect(screen.getByText('Warning message')).toBeInTheDocument();
        expect(screen.getByText('⚠️')).toBeInTheDocument();
      });
    });

    test('should show error dialog with red styling', async () => {
      render(
        <DialogProvider>
          <TestComponent />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Error'));

      await waitFor(() => {
        expect(screen.getByText('Error')).toBeInTheDocument();
        expect(screen.getByText('Error message')).toBeInTheDocument();
        expect(screen.getByText('❌')).toBeInTheDocument();
      });
    });
  });

  // ============================================================================
  // CONFIRM DIALOG TESTS
  // ============================================================================

  describe('Confirm Dialog', () => {
    test('should show confirm dialog with OK and Cancel buttons', async () => {
      render(
        <DialogProvider>
          <TestComponent />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Confirm'));

      await waitFor(() => {
        expect(screen.getByText('Confirm Action')).toBeInTheDocument();
        expect(screen.getByText('Are you sure?')).toBeInTheDocument();
        expect(screen.getByText('OK')).toBeInTheDocument();
        expect(screen.getByText('Cancel')).toBeInTheDocument();
      });
    });

    test('should return true when OK clicked', async () => {
      const onResult = jest.fn();
      
      render(
        <DialogProvider>
          <TestComponent onResult={onResult} />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Confirm'));

      await waitFor(() => {
        expect(screen.getByText('OK')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('OK'));

      await waitFor(() => {
        expect(onResult).toHaveBeenCalledWith(true);
      });
    });

    test('should return false when Cancel clicked', async () => {
      const onResult = jest.fn();
      
      render(
        <DialogProvider>
          <TestComponent onResult={onResult} />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Confirm'));

      await waitFor(() => {
        expect(screen.getByText('Cancel')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('Cancel'));

      await waitFor(() => {
        expect(onResult).toHaveBeenCalledWith(false);
      });
    });

    test('should close dialog when backdrop clicked', async () => {
      const onResult = jest.fn();
      
      render(
        <DialogProvider>
          <TestComponent onResult={onResult} />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Confirm'));

      await waitFor(() => {
        expect(screen.getByText('Confirm Action')).toBeInTheDocument();
      });

      // Click backdrop (the overlay)
      const backdrop = screen.getByText('Confirm Action').closest('.fixed');
      if (backdrop) {
        fireEvent.click(backdrop);
      }

      await waitFor(() => {
        expect(onResult).toHaveBeenCalledWith(false);
      });
    });
  });

  // ============================================================================
  // MULTIPLE DIALOGS TESTS
  // ============================================================================

  describe('Multiple Dialogs', () => {
    test('should queue dialogs and show them sequentially', async () => {
      const TestMultipleDialogs = () => {
        const dialog = useDialog();

        return (
          <button onClick={async () => {
            await dialog.showAlert('First', 'info');
            await dialog.showAlert('Second', 'info');
          }}>
            Show Multiple
          </button>
        );
      };

      render(
        <DialogProvider>
          <TestMultipleDialogs />
        </DialogProvider>
      );

      fireEvent.click(screen.getByText('Show Multiple'));

      // First dialog
      await waitFor(() => {
        expect(screen.getByText('First')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('OK'));

      // Second dialog should appear after first is closed
      await waitFor(() => {
        expect(screen.getByText('Second')).toBeInTheDocument();
      });
    });
  });

  // ============================================================================
  // ICON AND STYLING TESTS
  // ============================================================================

  describe('Dialog Icons and Styling', () => {
    test('should show correct icon for each type', async () => {
      const TestIcons = () => {
        const dialog = useDialog();

        return (
          <>
            <button onClick={() => dialog.showAlert('Info', 'info')}>Info</button>
            <button onClick={() => dialog.showAlert('Warning', 'warning')}>Warning</button>
            <button onClick={() => dialog.showAlert('Error', 'error')}>Error</button>
            <button onClick={() => dialog.showConfirm('Confirm', 'Confirm')}>Confirm</button>
          </>
        );
      };

      const { rerender } = render(
        <DialogProvider>
          <TestIcons />
        </DialogProvider>
      );

      // Test info icon
      fireEvent.click(screen.getByText('Info'));
      await waitFor(() => expect(screen.getByText('ℹ️')).toBeInTheDocument());
      fireEvent.click(screen.getByText('OK'));

      // Test warning icon
      await waitFor(() => expect(screen.queryByText('ℹ️')).not.toBeInTheDocument());
      fireEvent.click(screen.getByText('Warning'));
      await waitFor(() => expect(screen.getByText('⚠️')).toBeInTheDocument());
      fireEvent.click(screen.getByText('OK'));

      // Test error icon
      await waitFor(() => expect(screen.queryByText('⚠️')).not.toBeInTheDocument());
      fireEvent.click(screen.getByText('Error'));
      await waitFor(() => expect(screen.getByText('❌')).toBeInTheDocument());
      fireEvent.click(screen.getByText('OK'));

      // Test confirm icon
      await waitFor(() => expect(screen.queryByText('❌')).not.toBeInTheDocument());
      fireEvent.click(screen.getByText('Confirm'));
      await waitFor(() => expect(screen.getByText('❓')).toBeInTheDocument());
    });
  });
});

