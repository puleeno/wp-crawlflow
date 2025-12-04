
import { ExtractionRule, ColumnMapping, PathMapping } from './types';

interface Preset {
  name: string;
  html?: { rules: ExtractionRule[] };
  csv?: { mappings: ColumnMapping[] };
  json?: { mappings: PathMapping[] };
  xml?: { mappings: PathMapping[] };
  mysql?: { mappings: ColumnMapping[] };
}

export const PRESETS: Record<string, Preset> = {
  'ecommerce-product': {
    name: 'E-commerce Product',
    html: {
      rules: [
        { id: 'preset-ecom-html-1', name: 'product_name', extractFrom: 'html-element', selector: 'h1.product_title, .product-name, h1', extract: 'text' },
        { id: 'preset-ecom-html-2', name: 'price', extractFrom: 'html-element', selector: '.price, .product-price, .price-tag', extract: 'text' },
        { id: 'preset-ecom-html-3', name: 'sku', extractFrom: 'html-element', selector: '.sku, .product-sku', extract: 'text' },
        { id: 'preset-ecom-html-4', name: 'description', extractFrom: 'html-element', selector: '.description, .product-description', extract: 'html' },
        { id: 'preset-ecom-html-5', name: 'image_url', extractFrom: 'html-element', selector: '.product-image img, .main-image img', extract: 'attribute', attribute: 'src' },
      ]
    },
    json: {
      mappings: [
        { id: 'preset-ecom-json-1', path: '$.name', fieldName: 'product_name' },
        { id: 'preset-ecom-json-2', path: '$.offers.price', fieldName: 'price' },
        { id: 'preset-ecom-json-3', path: '$.sku', fieldName: 'sku' },
        { id: 'preset-ecom-json-4', path: '$.description', fieldName: 'description' },
        { id: 'preset-ecom-json-5', path: '$.image', fieldName: 'image_url' },
      ]
    },
    csv: {
        mappings: [
            { id: 'preset-ecom-csv-1', source: 'name', fieldName: 'product_name' },
            { id: 'preset-ecom-csv-2', source: 'price', fieldName: 'price' },
            { id: 'preset-ecom-csv-3', source: 'sku', fieldName: 'sku' },
            { id: 'preset-ecom-csv-4', source: 'description', fieldName: 'description' },
            { id: 'preset-ecom-csv-5', source: 'image_url', fieldName: 'image_url' },
        ]
    },
    xml: {
        mappings: [
            { id: 'preset-ecom-xml-1', path: '/product/name', fieldName: 'product_name' },
            { id: 'preset-ecom-xml-2', path: '/product/price', fieldName: 'price' },
            { id: 'preset-ecom-xml-3', path: '/product/sku', fieldName: 'sku' },
            { id: 'preset-ecom-xml-4', path: '/product/description', fieldName: 'description' },
            { id: 'preset-ecom-xml-5', path: '/product/imageURL', fieldName: 'image_url' },
        ]
    },
    mysql: {
        mappings: [
            { id: 'preset-ecom-mysql-1', source: 'product_name', fieldName: 'product_name' },
            { id: 'preset-ecom-mysql-2', source: 'sale_price', fieldName: 'price' },
            { id: 'preset-ecom-mysql-3', source: 'product_sku', fieldName: 'sku' },
            { id: 'preset-ecom-mysql-4', source: 'product_desc', fieldName: 'description' },
            { id: 'preset-ecom-mysql-5', source: 'main_image', fieldName: 'image_url' },
        ]
    }
  },
  'blog-content': {
    name: 'Blog Content',
    html: {
      rules: [
        { id: 'preset-blog-html-1', name: 'title', extractFrom: 'html-element', selector: 'h1.post-title, .entry-title, h1', extract: 'text' },
        { id: 'preset-blog-html-2', name: 'author', extractFrom: 'html-element', selector: '.author-name, .post-author, .byline', extract: 'text' },
        { id: 'preset-blog-html-3', name: 'publish_date', extractFrom: 'html-element', selector: '.post-date, .entry-date, time', extract: 'text' },
        { id: 'preset-blog-html-4', name: 'content', extractFrom: 'html-element', selector: '.post-content, .entry-content, article', extract: 'html' },
        { id: 'preset-blog-html-5', name: 'featured_image', extractFrom: 'html-element', selector: '.post-image img, .wp-post-image', extract: 'attribute', attribute: 'src' },
      ]
    },
    json: {
        mappings: [
          { id: 'preset-blog-json-1', path: '$.headline', fieldName: 'title' },
          { id: 'preset-blog-json-2', path: '$.author.name', fieldName: 'author' },
          { id: 'preset-blog-json-3', path: '$.datePublished', fieldName: 'publish_date' },
          { id: 'preset-blog-json-4', path: '$.articleBody', fieldName: 'content' },
          { id: 'preset-blog-json-5', path: '$.image.url', fieldName: 'featured_image' },
        ]
    },
    csv: {
        mappings: [
            { id: 'preset-blog-csv-1', source: 'post_title', fieldName: 'title' },
            { id: 'preset-blog-csv-2', source: 'author_name', fieldName: 'author' },
            { id: 'preset-blog-csv-3', source: 'published_at', fieldName: 'publish_date' },
            { id: 'preset-blog-csv-4', source: 'body_content', fieldName: 'content' },
            { id: 'preset-blog-csv-5', source: 'image_url', fieldName: 'featured_image' },
        ]
    },
    xml: {
      mappings: [
        { id: 'preset-blog-xml-1', path: '/rss/channel/item/title', fieldName: 'title' },
        { id: 'preset-blog-xml-2', path: 'dc:creator', fieldName: 'author' },
        { id: 'preset-blog-xml-3', path: '/rss/channel/item/pubDate', fieldName: 'publish_date' },
        { id: 'preset-blog-xml-4', path: 'content:encoded', fieldName: 'content' },
        { id: 'preset-blog-xml-5', path: 'media:content/@url', fieldName: 'featured_image' },
      ]
    },
    mysql: {
        mappings: [
            { id: 'preset-blog-mysql-1', source: 'post_title', fieldName: 'title' },
            { id: 'preset-blog-mysql-2', source: 'author_id', fieldName: 'author' },
            { id: 'preset-blog-mysql-3', source: 'publish_date', fieldName: 'publish_date' },
            { id: 'preset-blog-mysql-4', source: 'post_content', fieldName: 'content' },
            { id: 'preset-blog-mysql-5', source: 'featured_image_url', fieldName: 'featured_image' },
        ]
    }
  },
  'user-profile': {
    name: 'User Profile',
    html: {
        rules: [
          { id: 'preset-user-html-1', name: 'user_id', extractFrom: 'html-element', selector: '[data-userid]', extract: 'attribute', attribute: 'data-userid' },
          { id: 'preset-user-html-2', name: 'username', extractFrom: 'html-element', selector: '.profile-username, .username', extract: 'text' },
          { id: 'preset-user-html-3', name: 'full_name', extractFrom: 'html-element', selector: '.profile-fullname, .full-name', extract: 'text' },
          { id: 'preset-user-html-4', name: 'email', extractFrom: 'html-element', selector: '.profile-email, .email a', extract: 'text' },
          { id: 'preset-user-html-5', name: 'join_date', extractFrom: 'html-element', selector: '.profile-joindate, .join-date', extract: 'text' },
        ]
    },
    json: {
        mappings: [
          { id: 'preset-user-json-1', path: '$.user.id', fieldName: 'user_id' },
          { id: 'preset-user-json-2', path: '$.user.username', fieldName: 'username' },
          { id: 'preset-user-json-3', path: '$.user.profile.fullName', fieldName: 'full_name' },
          { id: 'preset-user-json-4', path: '$.user.email', fieldName: 'email' },
          { id: 'preset-user-json-5', path: '$.user.createdAt', fieldName: 'join_date' },
        ]
    },
    csv: {
      mappings: [
          { id: 'preset-user-csv-1', source: 'user_id', fieldName: 'user_id' },
          { id: 'preset-user-csv-2', source: 'username', fieldName: 'username' },
          { id: 'preset-user-csv-3', source: 'full_name', fieldName: 'full_name' },
          { id: 'preset-user-csv-4', source: 'email_address', fieldName: 'email' },
          { id: 'preset-user-csv-5', source: 'join_date', fieldName: 'join_date' },
      ]
    },
    xml: {
        mappings: [
          { id: 'preset-user-xml-1', path: '/user/@id', fieldName: 'user_id' },
          { id: 'preset-user-xml-2', path: '/user/username', fieldName: 'username' },
          { id: 'preset-user-xml-3', path: '/user/fullName', fieldName: 'full_name' },
          { id: 'preset-user-xml-4', path: '/user/email', fieldName: 'email' },
          { id: 'preset-user-xml-5', path: '/user/joinDate', fieldName: 'join_date' },
        ]
    },
    mysql: {
      mappings: [
          { id: 'preset-user-mysql-1', source: 'id', fieldName: 'user_id' },
          { id: 'preset-user-mysql-2', source: 'username', fieldName: 'username' },
          { id: 'preset-user-mysql-3', source: 'full_name', fieldName: 'full_name' },
          { id: 'preset-user-mysql-4', source: 'email', fieldName: 'email' },
          { id: 'preset-user-mysql-5', source: 'created_at', fieldName: 'join_date' },
      ]
    }
  },
  'seo-metadata': {
    name: 'SEO Metadata',
    html: {
      rules: [
        { id: 'preset-seo-html-1', name: 'meta_title', extractFrom: 'html-element', selector: 'title', extract: 'text' },
        { id: 'preset-seo-html-2', name: 'meta_description', extractFrom: 'html-element', selector: 'meta[name="description"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-seo-html-3', name: 'meta_keywords', extractFrom: 'html-element', selector: 'meta[name="keywords"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-seo-html-4', name: 'canonical_url', extractFrom: 'html-element', selector: 'link[rel="canonical"]', extract: 'attribute', attribute: 'href' },
      ]
    },
    json: {
        mappings: [
          { id: 'preset-seo-json-1', path: '$.seo.title', fieldName: 'meta_title' },
          { id: 'preset-seo-json-2', path: '$.seo.description', fieldName: 'meta_description' },
          { id: 'preset-seo-json-3', path: '$.seo.keywords', fieldName: 'meta_keywords' },
          { id: 'preset-seo-json-4', path: '$.url.canonical', fieldName: 'canonical_url' },
        ]
    },
    csv: {
        mappings: [
            { id: 'preset-seo-csv-1', source: 'meta_title', fieldName: 'meta_title' },
            { id: 'preset-seo-csv-2', source: 'meta_description', fieldName: 'meta_description' },
            { id: 'preset-seo-csv-3', source: 'meta_keywords', fieldName: 'meta_keywords' },
            { id: 'preset-seo-csv-4', source: 'canonical_url', fieldName: 'canonical_url' },
        ]
    },
    xml: {
        mappings: [
            { id: 'preset-seo-xml-1', path: '/page/seo/title', fieldName: 'meta_title' },
            { id: 'preset-seo-xml-2', path: '/page/seo/description', fieldName: 'meta_description' },
            { id: 'preset-seo-xml-3', path: '/page/seo/keywords', fieldName: 'meta_keywords' },
            { id: 'preset-seo-xml-4', path: '/page/seo/canonical', fieldName: 'canonical_url' },
        ]
    },
    mysql: {
        mappings: [
            { id: 'preset-seo-mysql-1', source: 'meta_title', fieldName: 'meta_title' },
            { id: 'preset-seo-mysql-2', source: 'meta_desc', fieldName: 'meta_description' },
            { id: 'preset-seo-mysql-3', source: 'meta_keywords', fieldName: 'meta_keywords' },
            { id: 'preset-seo-mysql-4', source: 'canonical_link', fieldName: 'canonical_url' },
        ]
    }
  },
  'open-graph': {
    name: 'Open Graph Tags',
    html: {
      rules: [
        { id: 'preset-og-html-1', name: 'og_title', extractFrom: 'html-element', selector: 'meta[property="og:title"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-og-html-2', name: 'og_description', extractFrom: 'html-element', selector: 'meta[property="og:description"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-og-html-3', name: 'og_type', extractFrom: 'html-element', selector: 'meta[property="og:type"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-og-html-4', name: 'og_url', extractFrom: 'html-element', selector: 'meta[property="og:url"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-og-html-5', name: 'og_image', extractFrom: 'html-element', selector: 'meta[property="og:image"]', extract: 'attribute', attribute: 'content' },
        { id: 'preset-og-html-6', name: 'og_site_name', extractFrom: 'html-element', selector: 'meta[property="og:site_name"]', extract: 'attribute', attribute: 'content' },
      ]
    },
    json: {
        mappings: [
          { id: 'preset-og-json-1', path: '$.og.title', fieldName: 'og_title' },
          { id: 'preset-og-json-2', path: '$.og.description', fieldName: 'og_description' },
          { id: 'preset-og-json-3', path: '$.og.type', fieldName: 'og_type' },
          { id: 'preset-og-json-4', path: '$.og.url', fieldName: 'og_url' },
          { id: 'preset-og-json-5', path: '$.og.image.url', fieldName: 'og_image' },
          { id: 'preset-og-json-6', path: '$.og.site_name', fieldName: 'og_site_name' },
        ]
    },
    csv: {
      mappings: [
          { id: 'preset-og-csv-1', source: 'og_title', fieldName: 'og_title' },
          { id: 'preset-og-csv-2', source: 'og_description', fieldName: 'og_description' },
          { id: 'preset-og-csv-3', source: 'og_type', fieldName: 'og_type' },
          { id: 'preset-og-csv-4', source: 'og_url', fieldName: 'og_url' },
          { id: 'preset-og-csv-5', source: 'og_image', fieldName: 'og_image' },
          { id: 'preset-og-csv-6', source: 'og_site_name', fieldName: 'og_site_name' },
      ]
    },
    xml: {
        mappings: [
          { id: 'preset-og-xml-1', path: '/page/og/title', fieldName: 'og_title' },
          { id: 'preset-og-xml-2', path: '/page/og/description', fieldName: 'og_description' },
          { id: 'preset-og-xml-3', path: '/page/og/type', fieldName: 'og_type' },
          { id: 'preset-og-xml-4', path: '/page/og/url', fieldName: 'og_url' },
          { id: 'preset-og-xml-5', path: '/page/og/image', fieldName: 'og_image' },
          { id: 'preset-og-xml-6', path: '/page/og/site_name', fieldName: 'og_site_name' },
        ]
    },
    mysql: {
      mappings: [
          { id: 'preset-og-mysql-1', source: 'og_title', fieldName: 'og_title' },
          { id: 'preset-og-mysql-2', source: 'og_description', fieldName: 'og_description' },
          { id: 'preset-og-mysql-3', source: 'og_type', fieldName: 'og_type' },
          { id: 'preset-og-mysql-4', source: 'og_url', fieldName: 'og_url' },
          { id: 'preset-og-mysql-5', source: 'og_image', fieldName: 'og_image' },
          { id: 'preset-og-mysql-6', source: 'og_site_name', fieldName: 'og_site_name' },
      ]
    }
  }
};


// PROCESSORS removed - now loaded dynamically from ProcessorManager via registry
// See: src/Admin/RegistryService.php and hooks/useRegistry.ts
