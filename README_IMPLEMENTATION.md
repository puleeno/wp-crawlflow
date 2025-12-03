# CRAWLFLOW IMPLEMENTATION GUIDE

## 🎯 PROJECT OVERVIEW

WP-CrawlFlow 2.0 - A WordPress plugin for data migration and web crawling using:
- **Rake Framework** (Core)
- **Rake WordPress Adapter** (Integration)
- **Flow-Based Architecture** (React Flow UI)

## 📁 ARCHITECTURE

### Service Provider Pattern
```
ApplicationBootstrapper
├── CoreServiceProvider (Logger, Config)
├── AdminServiceProvider (Dashboard, Projects, Controller)
└── FlowServiceProvider (Flow execution, Node executors)
```

### Key Components:
1. **Bootstrapper** (`src/Bootstrapper/ApplicationBootstrapper.php`)
   - Registers all service providers
   - Boots services in correct order

2. **Service Providers** (`src/ServiceProvider/`)
   - `CoreServiceProvider` - Foundation services
   - `AdminServiceProvider` - WordPress admin
   - `FlowServiceProvider` - Flow execution

3. **Services** (`src/Admin/`, `src/Flow/`)
   - `ProjectService` - Project CRUD
   - `FlowService` - Flow execution
   - `CrawlFlowController` - AJAX handlers

## 🚀 GETTING STARTED

### Installation
```bash
cd wp-content/plugins/wp-crawlflow
composer install
cd assets/js/crawflow-ui
npm install
npm run build
```

### Running Tests
```bash
vendor/bin/phpunit --testsuite="Unit Tests"
vendor/bin/phpunit --testsuite="Integration Tests"
```

## 📝 DEVELOPMENT WORKFLOW

### Adding a New Service

1. **Create Service Class**
```php
// src/Admin/MyService.php
namespace CrawlFlow\Admin;

class MyService {
    public function doSomething() {
        // Implementation
    }
}
```

2. **Register in Service Provider**
```php
// src/ServiceProvider/AdminServiceProvider.php
protected function registerServices(): void
{
    $this->app->singleton('CrawlFlow\Admin\MyService', function ($app) {
        return new \CrawlFlow\Admin\MyService();
    });
}
```

3. **Use Service**
```php
$rake = \Rake\Rake::getInstance();
$myService = $rake->make('CrawlFlow\Admin\MyService');
$myService->doSomething();
```

### Adding a Test

1. **Create Test File**
```php
// tests/Unit/Admin/MyServiceTest.php
namespace CrawlFlow\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase {
    public function test_service_works() {
        $service = new MyService();
        $this->assertInstanceOf(MyService::class, $service);
    }
}
```

2. **Run Test**
```bash
vendor/bin/phpunit tests/Unit/Admin/MyServiceTest.php
```

## 🔧 CONFIGURATION

### WordPress Constants (Required)
```php
define('CRAWLFLOW_VERSION', '2.0.0');
define('CRAWLFLOW_PLUGIN_FILE', __FILE__);
define('CRAWLFLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRAWLFLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### Rake Configuration
```php
// Auto-configured in CoreServiceProvider
$config = $rake->make('config');
// Returns plugin configuration
```

## 📊 TESTING STRATEGY

### Unit Tests
Test individual components in isolation.

```php
public function test_project_creation() {
    $service = new ProjectService();
    $id = $service->createProject(['name' => 'Test']);
    $this->assertGreaterThan(0, $id);
}
```

### Integration Tests
Test interaction between components.

```php
public function test_complete_flow() {
    // Create project
    // Execute flow
    // Verify results
}
```

### Feature Tests (Planned)
Test complete user workflows with React UI.

## 🐛 DEBUGGING

### Enable Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Logs
```
wp-content/debug.log
wp-content/crawlflow/logs/crawlflow.log
```

### Service Resolution
```php
// Check if service is registered
$rake = \Rake\Rake::getInstance();
if ($rake->has('ServiceName')) {
    $service = $rake->make('ServiceName');
}
```

## 📖 API DOCUMENTATION

### AJAX Endpoints

#### Save Project
```javascript
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'crawlflow_save_project',
        nonce: crawlflowAdmin.nonce,
        project_name: 'My Project',
        project_data: {
            nodes: [...],
            edges: [...]
        }
    })
});
```

#### Get Flow Config
```javascript
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'crawlflow_get_flow_config',
        nonce: crawlflowAdmin.nonce,
        project_id: 123
    })
});
```

## 🔐 SECURITY

### Nonce Verification
All AJAX requests require nonce verification:
```php
if (!\wp_verify_nonce($_POST['nonce'], 'crawlflow_admin_nonce')) {
    wp_send_json_error('Security check failed');
}
```

### Input Sanitization
All user input is sanitized:
```php
$name = sanitize_text_field($_POST['project_name']);
$description = sanitize_textarea_field($_POST['description']);
```

## 📦 DEPLOYMENT

### Production Checklist
- [ ] Run all tests
- [ ] Build React UI (`npm run build`)
- [ ] Set `WP_DEBUG` to `false`
- [ ] Clear all logs
- [ ] Test on staging environment
- [ ] Backup database
- [ ] Deploy plugin
- [ ] Verify functionality

## 🆘 TROUBLESHOOTING

### Common Issues

**Issue**: Tests fail with "undefined function"
**Fix**: Check `tests/mocks/wordpress-functions.php`

**Issue**: Service not found
**Fix**: Verify service is registered in appropriate ServiceProvider

**Issue**: JSON request returns 400
**Fix**: Check `parseJsonRequest()` is called early enough

**Issue**: React UI not loading
**Fix**: Run `npm run build` in `assets/js/crawflow-ui`

## 📚 RESOURCES

- [Rake Framework Docs](vendor/ramphor/rake/docs/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [React Flow Docs](https://reactflow.dev/)
- [PHPUnit Docs](https://phpunit.de/)

## 👥 CONTRIBUTING

1. Create feature branch
2. Write tests first (TDD)
3. Implement feature
4. Run tests
5. Submit PR with test coverage

## 📄 LICENSE

GPL v3 - See LICENSE file

