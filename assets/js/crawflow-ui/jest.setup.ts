import '@testing-library/jest-dom';

// Mock window.crawlflowRegistry for tests
global.window.crawlflowRegistry = {
  dataSources: [
    {
      type: 'url',
      label: 'From URL',
      description: 'Fetch data from URLs',
      icon: '🌐',
      configFields: []
    },
    {
      type: 'api',
      label: 'From API',
      description: 'Fetch data from REST APIs',
      icon: '🔌',
      configFields: []
    }
  ],
  processors: [
    {
      type: 'save_to_wordpress',
      label: 'Save to WordPress',
      description: 'Save as WordPress posts',
      icon: '💾',
      configFields: {
        fields: [],
        supportsFieldMapping: true,
        fieldMappingConfig: {
          availableSourceFields: [],
          targetFields: []
        }
      }
    }
  ],
  parsers: [],
  httpClients: [
    {
      name: 'default',
      label: 'Default (Auto-select)',
      description: 'WordPress HTTP Client',
      icon: '🔧'
    }
  ],
  nonce: 'test-nonce',
  ajaxUrl: '/wp-admin/admin-ajax.php'
};

