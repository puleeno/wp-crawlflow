<?php

namespace CrawlFlow\Tests\Unit\Manager;

use PHPUnit\Framework\TestCase;
use Rake\Manager\ProcessorManager;
use Rake\Contracts\Processor\ProcessorInterface;
use CrawlFlow\Processors\WordPressPostProcessor;

/**
 * Test ProcessorManager
 * 
 * @group manager
 * @group processor
 * @group unit
 */
class ProcessorManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProcessorManager::clearAll();
    }

    public function test_can_register_processor()
    {
        ProcessorManager::register('test_processor', WordPressPostProcessor::class);
        
        $this->assertTrue(ProcessorManager::has('test_processor'));
    }

    public function test_can_register_processor_with_config()
    {
        ProcessorManager::register('test_processor', WordPressPostProcessor::class, [
            'postType' => 'page',
            'postStatus' => 'publish',
        ]);
        
        $this->assertTrue(ProcessorManager::has('test_processor'));
    }

    public function test_can_get_processor()
    {
        ProcessorManager::register('test_processor', WordPressPostProcessor::class);
        
        $manager = new ProcessorManager();
        $processor = $manager->getProcessor('test_processor');
        
        $this->assertInstanceOf(ProcessorInterface::class, $processor);
        $this->assertInstanceOf(WordPressPostProcessor::class, $processor);
    }

    public function test_can_register_alias()
    {
        ProcessorManager::register('wordpress_post', WordPressPostProcessor::class);
        ProcessorManager::alias('wp_post', 'wordpress_post');
        
        $this->assertTrue(ProcessorManager::has('wp_post'));
    }

    public function test_alias_resolves_to_original()
    {
        ProcessorManager::register('original', WordPressPostProcessor::class);
        ProcessorManager::alias('alias', 'original');
        
        $manager = new ProcessorManager();
        $processor = $manager->getProcessor('alias');
        
        $this->assertInstanceOf(WordPressPostProcessor::class, $processor);
    }

    public function test_get_all_registered_types()
    {
        ProcessorManager::register('type1', WordPressPostProcessor::class);
        ProcessorManager::register('type2', WordPressPostProcessor::class);
        
        $types = ProcessorManager::getRegisteredTypes();
        
        $this->assertCount(2, $types);
        $this->assertContains('type1', $types);
        $this->assertContains('type2', $types);
    }

    public function test_can_create_processor_chain()
    {
        ProcessorManager::register('save_to_wordpress', WordPressPostProcessor::class);
        
        $chainConfig = [
            ['type' => 'save_to_wordpress', 'settings' => ['postType' => 'post']],
        ];
        
        $manager = new ProcessorManager();
        $chain = $manager->createChain($chainConfig);
        
        $this->assertCount(1, $chain);
        $this->assertInstanceOf(ProcessorInterface::class, $chain[0]);
    }

    public function test_can_execute_processor_chain()
    {
        ProcessorManager::register('save_to_wordpress', WordPressPostProcessor::class);
        
        $manager = new ProcessorManager();
        $processor = $manager->getProcessor('save_to_wordpress');
        
        $data = [
            'title' => 'Test Post',
            'content' => 'Test content',
        ];
        
        $result = $manager->executeChain([$processor], $data);
        
        $this->assertInstanceOf(\Rake\Contracts\Entities\ParsedDataItemInterface::class, $result);
        $this->assertFalse($result->isNull());
        $this->assertTrue($result->has('post_id'));
        $this->assertTrue($result->has('processed'));
        $this->assertTrue($result->get('processed'));
    }

    public function test_chain_returns_null_data_item_on_failure()
    {
        ProcessorManager::register('save_to_wordpress', WordPressPostProcessor::class);
        
        $manager = new ProcessorManager();
        $processor = $manager->getProcessor('save_to_wordpress');
        
        // Invalid data (missing title)
        $data = [
            'content' => 'Content without title',
        ];
        
        $result = $manager->executeChain([$processor], $data);
        
        $this->assertInstanceOf(\Rake\Contracts\Entities\ParsedDataItemInterface::class, $result);
        $this->assertTrue($result->isNull());
        $this->assertInstanceOf(\Rake\Entities\ParsedData\NullDataItem::class, $result);
    }

    public function test_throws_exception_for_unregistered_processor()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Processor 'nonexistent' not registered");
        
        $manager = new ProcessorManager();
        $manager->getProcessor('nonexistent');
    }
}

