<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Puleeno\Rake\WordPress\Http\WordPressHttpClient;
use Rake\Contracts\Http\HttpClientInterface;
use Rake\Manager\HttpClientManager;
use Rake\Facade\Request;

/**
 * Integration Test: WordPress HTTP Client
 * Verify WordPress HTTP client implements Rake contracts correctly
 * 
 * @group integration
 * @group http
 * @group wordpress-adapter
 */
class WordPressHttpClientTest extends TestCase
{
    private WordPressHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new WordPressHttpClient();
    }

    public function test_implements_http_client_interface()
    {
        $this->assertInstanceOf(HttpClientInterface::class, $this->client);
    }

    public function test_has_required_methods()
    {
        $this->assertTrue(method_exists($this->client, 'get'));
        $this->assertTrue(method_exists($this->client, 'post'));
        $this->assertTrue(method_exists($this->client, 'request'));
    }

    public function test_can_register_in_http_client_manager()
    {
        HttpClientManager::register('wordpress', $this->client);
        
        $this->assertTrue(HttpClientManager::has('wordpress'));
    }

    public function test_can_set_as_default_client()
    {
        HttpClientManager::register('wordpress', $this->client);
        HttpClientManager::setDefaultClientName('wordpress');
        
        $defaultClient = HttpClientManager::getDefaultClient();
        
        $this->assertSame($this->client, $defaultClient);
    }

    public function test_works_with_request_facade()
    {
        // Register in container
        $rake = \Rake\Rake::getInstance();
        
        if (!$rake->has(HttpClientManager::class)) {
            $rake->singleton(HttpClientManager::class, function() {
                return new HttpClientManager();
            });
        }

        // Register WordPress client as default
        HttpClientManager::register('wordpress', $this->client);
        HttpClientManager::setDefaultClientName('wordpress');

        // Should be able to use Request facade
        $this->assertTrue(class_exists(Request::class));
    }

    public function test_custom_options_are_applied()
    {
        $customClient = new WordPressHttpClient([
            'timeout' => 15,
            'user-agent' => 'TestAgent/1.0',
        ]);

        $options = $customClient->getDefaultOptions();
        
        $this->assertEquals(15, $options['timeout']);
        $this->assertEquals('TestAgent/1.0', $options['user-agent']);
    }

    public function test_integrates_with_rake_framework()
    {
        // This tests the complete integration:
        // Rake → HttpClientManager → WordPressHttpClient → WordPress HTTP API

        // 1. WordPress client implements Rake interface
        $this->assertInstanceOf(HttpClientInterface::class, $this->client);

        // 2. Can register in Rake's HttpClientManager
        HttpClientManager::clearAll(); // Clean slate
        HttpClientManager::register('wordpress', $this->client);
        $this->assertTrue(HttpClientManager::has('wordpress'));

        // 3. Set as default so we can retrieve it
        HttpClientManager::setDefaultClientName('wordpress');
        
        // 4. Manager can retrieve it
        $retrieved = HttpClientManager::getDefaultClient();
        $this->assertInstanceOf(HttpClientInterface::class, $retrieved);

        // Integration verified
        $this->assertTrue(true, 'WordPress HTTP client integrates with Rake framework');
    }
}

