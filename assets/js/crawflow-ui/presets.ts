
import { ExtractionRule, PresetType, SaveToDbSettings, SendToApiSettings, GenerateCsvSettings, SendEmailSettings, ProcessorNodeData } from './types';

export const PRESETS: Record<PresetType, { name: string; rules: ExtractionRule[] }> = {
  'woocommerce-product': {
    name: 'WooCommerce Product',
    rules: [
      { id: 'preset-wc-1', name: 'product_name', extractFrom: 'html-element', selector: 'h1.product_title, .product_title, h1', extract: 'text' },
      { id: 'preset-wc-2', name: 'price', extractFrom: 'html-element', selector: '.price .woocommerce-Price-amount', extract: 'text' },
      { id: 'preset-wc-3', name: 'short_description', extractFrom: 'html-element', selector: '.woocommerce-product-details__short-description, p:nth-child(4)', extract: 'text' },
      { id: 'preset-wc-4', name: 'image_url', extractFrom: 'html-element', selector: '.woocommerce-product-gallery__image a', extract: 'attribute', attribute: 'href' },
      { id: 'preset-wc-5', name: 'sku', extractFrom: 'html-element', selector: '.sku', extract: 'text' },
    ],
  },
  'blog-post': {
    name: 'Blog Post',
    rules: [
      { id: 'preset-bp-1', name: 'title', extractFrom: 'html-element', selector: 'h1.post-title, .entry-title, .heading-element, .f4, .title, h1', extract: 'text' },
      { id: 'preset-bp-2', name: 'author', extractFrom: 'html-element', selector: '.author-name, .post-author', extract: 'text' },
      { id: 'preset-bp-3', name: 'publish_date', extractFrom: 'html-element', selector: '.post-date, .entry-date', extract: 'text' },
      { id: 'preset-bp-4', name: 'content', extractFrom: 'html-element', selector: '.post-content, .entry-content', extract: 'text' },
      { id: 'preset-bp-5', name: 'featured_image', extractFrom: 'html-element', selector: '.post-image img, .wp-post-image', extract: 'attribute', attribute: 'src' },
    ],
  },
  'seo-metadata': {
    name: 'SEO Metadata',
    rules: [
      { id: 'preset-seo-1', name: 'meta_title', extractFrom: 'html-element', selector: 'title', extract: 'text' },
      { id: 'preset-seo-2', name: 'meta_description', extractFrom: 'html-element', selector: 'meta[name="description"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-seo-3', name: 'meta_keywords', extractFrom: 'html-element', selector: 'meta[name="keywords"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-seo-4', name: 'canonical_url', extractFrom: 'html-element', selector: 'link[rel="canonical"]', extract: 'attribute', attribute: 'href' },
      { id: 'preset-seo-5', name: 'og_image', extractFrom: 'html-element', selector: 'meta[property="og:image"]', extract: 'attribute', attribute: 'content' },
    ],
  },
  'open-graph': {
    name: 'Open Graph Tags',
    rules: [
      { id: 'preset-og-1', name: 'og_title', extractFrom: 'html-element', selector: 'meta[property="og:title"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-og-2', name: 'og_description', extractFrom: 'html-element', selector: 'meta[property="og:description"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-og-3', name: 'og_type', extractFrom: 'html-element', selector: 'meta[property="og:type"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-og-4', name: 'og_url', extractFrom: 'html-element', selector: 'meta[property="og:url"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-og-5', name: 'og_image', extractFrom: 'html-element', selector: 'meta[property="og:image"]', extract: 'attribute', attribute: 'content' },
      { id: 'preset-og-6', name: 'og_site_name', extractFrom: 'html-element', selector: 'meta[property="og:site_name"]', extract: 'attribute', attribute: 'content' },
    ],
  },
};

export const PROCESSORS: {
  id: ProcessorNodeData['processorType'];
  name: string;
  defaultSettings: ProcessorNodeData['settings'];
}[] = [
  {
    id: 'save-to-database',
    name: 'Save to Database',
    defaultSettings: {
      connectionType: 'mysql',
      host: 'localhost',
      port: '3306',
      user: 'root',
      password: '',
      database: 'scraped_data',
      tableName: 'results',
      conflictStrategy: 'upsert',
    } as SaveToDbSettings,
  },
  {
    id: 'send-to-api',
    name: 'Send to API',
    defaultSettings: {
      endpointUrl: 'https://api.example.com/data',
      method: 'POST',
      authType: 'none',
      authDetails: {},
      headers: [{ id: '1', key: 'Content-Type', value: 'application/json' }],
    } as SendToApiSettings,
  },
  {
    id: 'generate-csv-file',
    name: 'Generate CSV File',
    defaultSettings: {
      fileName: 'crawl_results_{{date}}.csv',
      delimiter: ',',
      includeHeader: true,
    } as GenerateCsvSettings,
  },
  {
    id: 'send-email-notification',
    name: 'Send Email Notification',
    defaultSettings: {
      recipients: 'admin@example.com',
      subject: 'Crawl Finished: New Data Found for {{url}}',
      body: 'Data was successfully extracted.\n\nTitle: {{title}}\nPrice: {{price}}',
    } as SendEmailSettings,
  },
];