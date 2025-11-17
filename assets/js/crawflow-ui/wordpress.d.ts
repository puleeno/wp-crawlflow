// WordPress Integration Types
declare global {
  interface Window {
    crawlflowConfig?: {
      projectId: number | null;
      project: any;
      projectConfig: {
        projectSettings?: any;
        nodes?: any[];
        edges?: any[];
      } | null;
      ajaxUrl: string;
      nonce: string;
      pluginUrl: string;
    };
  }
}

export {};

