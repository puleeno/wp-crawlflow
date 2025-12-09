import { useState, useEffect } from 'react';

/**
 * Registry data from PHP (localized)
 */
declare global {
  interface Window {
    crawlflowRegistry?: {
      dataSources: DataSource[];
      processors: Processor[];
      parsers: Parser[];
      httpClients: HttpClient[];
      completionActions: CompletionAction[];
      phase1Actions: Phase1Action[];
      phase1ExtraActions: Phase1Action[];
      nonce: string;
      ajaxUrl: string;
    };
  }
}

export interface DataSource {
  type: string;
  label: string;
  description: string;
  icon: string;
  configFields?: ConfigField[];
}

export interface Processor {
  type: string;
  label: string;
  description: string;
  icon: string;
  configFields?: ConfigField[];
}

export interface Parser {
  type: string;
  label: string;
  description: string;
  icon: string;
}

export interface HttpClient {
  name: string;
  label: string;
  description: string;
  icon: string;
}

export interface CompletionAction {
  type: string;
  label: string;
  description: string;
  icon: string;
  category: string;
  enabled: boolean;
  configFields: ConfigField[];
}

export interface Phase1Action {
  id: string;
  label: string;
  description: string;
  priority: number;
}

export interface ConfigField {
  name: string;
  type: 'text' | 'number' | 'select' | 'file' | 'textarea' | 'checkbox' | 'url' | 'password';
  label: string;
  description?: string;
  required?: boolean;
  default?: any;
  options?: Array<{ value: string; label: string }> | Record<string, string>;
  placeholder?: string;
}

/**
 * Hook to access registry data from PHP
 */
export function useRegistry() {
  const [dataSources, setDataSources] = useState<DataSource[]>([]);
  const [processors, setProcessors] = useState<Processor[]>([]);
  const [parsers, setParsers] = useState<Parser[]>([]);
  const [httpClients, setHttpClients] = useState<HttpClient[]>([]);
  const [completionActions, setCompletionActions] = useState<CompletionAction[]>([]);
  const [phase1Actions, setPhase1Actions] = useState<Phase1Action[]>([]);
   const [phase1ExtraActions, setPhase1ExtraActions] = useState<Phase1Action[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Load from localized data
    if (window.crawlflowRegistry) {
      setDataSources(window.crawlflowRegistry.dataSources || []);
      setProcessors(window.crawlflowRegistry.processors || []);
      setParsers(window.crawlflowRegistry.parsers || []);
      setHttpClients(window.crawlflowRegistry.httpClients || []);
      setCompletionActions(window.crawlflowRegistry.completionActions || []);
      setPhase1Actions(window.crawlflowRegistry.phase1Actions || []);
       setPhase1ExtraActions(window.crawlflowRegistry.phase1ExtraActions || []);
      setLoading(false);
    } else {
      console.warn('CrawlFlow registry data not found');
      setLoading(false);
    }
  }, []);

  return {
    dataSources,
    processors,
    parsers,
    httpClients,
    completionActions,
    phase1Actions,
    phase1ExtraActions,
    loading,
    nonce: window.crawlflowRegistry?.nonce || '',
    ajaxUrl: window.crawlflowRegistry?.ajaxUrl || '/wp-admin/admin-ajax.php',
  };
}

/**
 * Get data source by type
 */
export function useDataSource(type: string): DataSource | undefined {
  const { dataSources } = useRegistry();
  return dataSources.find(ds => ds.type === type);
}

/**
 * Get processor by type
 */
export function useProcessor(type: string): Processor | undefined {
  const { processors } = useRegistry();
  return processors.find(p => p.type === type);
}

/**
 * Get parser by type
 */
export function useParser(type: string): Parser | undefined {
  const { parsers } = useRegistry();
  return parsers.find(p => p.type === type);
}

/**
 * Get HTTP client by name
 */
export function useHttpClient(name: string): HttpClient | undefined {
  const { httpClients } = useRegistry();
  return httpClients.find(c => c.name === name);
}


