/**
 * Unit Tests for useRegistry Hook
 */

import { renderHook, waitFor } from '@testing-library/react';
import { useRegistry } from './useRegistry';

describe('useRegistry Hook - Unit Tests', () => {
  beforeEach(() => {
    // Setup mock registry data
    window.crawlflowRegistry = {
      dataSources: [
        {
          type: 'url',
          label: 'From URL',
          description: 'Fetch data from URLs',
          icon: '🌐',
          configFields: [
            { name: 'url', type: 'url', label: 'URL', required: true }
          ]
        },
        {
          type: 'api',
          label: 'From API',
          description: 'Fetch from APIs',
          icon: '🔌',
          configFields: []
        }
      ],
      processors: [
        {
          type: 'save_to_wordpress',
          label: 'Save to WordPress',
          description: 'Save as posts',
          icon: '💾',
          configFields: {
            fields: [
              { name: 'postType', type: 'select', label: 'Post Type', required: true, options: [] }
            ],
            supportsFieldMapping: true,
            fieldMappingConfig: { availableSourceFields: [], targetFields: [] }
          }
        }
      ],
      parsers: [
        {
          type: 'html',
          label: 'HTML Parser',
          description: 'Parse HTML',
          icon: '📄'
        }
      ],
      httpClients: [
        {
          name: 'default',
          label: 'Default Client',
          description: 'WordPress HTTP',
          icon: '🔧'
        }
      ],
      nonce: 'test-nonce',
      ajaxUrl: '/wp-admin/admin-ajax.php'
    };
  });

  afterEach(() => {
    delete window.crawlflowRegistry;
  });

  // ============================================================================
  // BASIC LOADING TESTS
  // ============================================================================

  describe('Basic Loading', () => {
    test('should load data sources from window.crawlflowRegistry', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.dataSources).toHaveLength(2);
      expect(result.current.dataSources[0].type).toBe('url');
      expect(result.current.dataSources[1].type).toBe('api');
    });

    test('should load processors from window.crawlflowRegistry', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.processors).toHaveLength(1);
      expect(result.current.processors[0].type).toBe('save_to_wordpress');
    });

    test('should load parsers from window.crawlflowRegistry', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.parsers).toHaveLength(1);
      expect(result.current.parsers[0].type).toBe('html');
    });

    test('should load HTTP clients from window.crawlflowRegistry', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.httpClients).toHaveLength(1);
      expect(result.current.httpClients[0].name).toBe('default');
    });

    test('should set loading to false after data loaded', async () => {
      const { result } = renderHook(() => useRegistry());

      expect(result.current.loading).toBe(true);

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });
    });
  });

  // ============================================================================
  // MISSING DATA TESTS
  // ============================================================================

  describe('Missing Data Handling', () => {
    test('should handle missing window.crawlflowRegistry', async () => {
      delete window.crawlflowRegistry;

      const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(consoleWarnSpy).toHaveBeenCalledWith('CrawlFlow registry data not found');
      expect(result.current.dataSources).toEqual([]);
      expect(result.current.processors).toEqual([]);

      consoleWarnSpy.mockRestore();
    });

    test('should handle missing dataSources in registry', async () => {
      window.crawlflowRegistry = {
        processors: [],
        parsers: [],
        httpClients: [],
        nonce: '',
        ajaxUrl: ''
      };

      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.dataSources).toEqual([]);
    });

    test('should handle partial registry data', async () => {
      window.crawlflowRegistry = {
        dataSources: [{ type: 'url', label: 'URL', description: '', icon: '', configFields: [] }],
        // Missing processors, parsers, httpClients
        nonce: '',
        ajaxUrl: ''
      } as any;

      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      expect(result.current.dataSources).toHaveLength(1);
      expect(result.current.processors).toEqual([]);
      expect(result.current.parsers).toEqual([]);
      expect(result.current.httpClients).toEqual([]);
    });
  });

  // ============================================================================
  // DATA STRUCTURE TESTS
  // ============================================================================

  describe('Data Structure Validation', () => {
    test('should return data sources with all required fields', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      const dataSource = result.current.dataSources[0];
      expect(dataSource).toHaveProperty('type');
      expect(dataSource).toHaveProperty('label');
      expect(dataSource).toHaveProperty('description');
      expect(dataSource).toHaveProperty('icon');
      expect(dataSource).toHaveProperty('configFields');
    });

    test('should return processors with configFields structure', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      const processor = result.current.processors[0];
      expect(processor.configFields).toHaveProperty('fields');
      expect(processor.configFields).toHaveProperty('supportsFieldMapping');
      expect(processor.configFields).toHaveProperty('fieldMappingConfig');
    });

    test('should handle configFields with proper typing', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      const dataSourceConfig = result.current.dataSources[0].configFields[0];
      expect(dataSourceConfig).toHaveProperty('name');
      expect(dataSourceConfig).toHaveProperty('type');
      expect(dataSourceConfig).toHaveProperty('label');
      expect(dataSourceConfig).toHaveProperty('required');
    });
  });

  // ============================================================================
  // RE-RENDER TESTS
  // ============================================================================

  describe('Re-render Behavior', () => {
    test('should not reload data on re-render', async () => {
      const { result, rerender } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      const firstDataSources = result.current.dataSources;

      rerender();

      expect(result.current.dataSources).toBe(firstDataSources); // Same reference
    });

    test('should maintain stable references after loading', async () => {
      const { result } = renderHook(() => useRegistry());

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });

      const dataSources1 = result.current.dataSources;
      const processors1 = result.current.processors;

      // Wait a bit and check references haven't changed
      await new Promise(resolve => setTimeout(resolve, 100));

      expect(result.current.dataSources).toBe(dataSources1);
      expect(result.current.processors).toBe(processors1);
    });
  });
});

