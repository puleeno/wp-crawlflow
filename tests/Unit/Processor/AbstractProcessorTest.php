<?php

namespace CrawlFlow\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Rake\Processor\AbstractProcessor;
use Rake\Contracts\Processor\ProcessorInterface;

/**
 * Test AbstractProcessor
 * 
 * @group processor
 * @group unit
 */
class AbstractProcessorTest extends TestCase
{
    public function test_implements_processor_interface()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class);
        
        $this->assertInstanceOf(ProcessorInterface::class, $processor);
    }

    public function test_accepts_config_in_constructor()
    {
        $config = [
            'option1' => 'value1',
            'option2' => 'value2',
        ];
        
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class, [$config]);
        
        // Use reflection to access protected config
        $reflection = new \ReflectionClass($processor);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        
        $this->assertEquals($config, $property->getValue($processor));
    }

    public function test_get_config_returns_value()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class, [
            ['key1' => 'value1']
        ]);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getConfig');
        $method->setAccessible(true);
        
        $value = $method->invoke($processor, 'key1');
        
        $this->assertEquals('value1', $value);
    }

    public function test_get_config_returns_default_if_not_found()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('getConfig');
        $method->setAccessible(true);
        
        $value = $method->invoke($processor, 'nonexistent', 'default');
        
        $this->assertEquals('default', $value);
    }

    public function test_can_create_item()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('createItem');
        $method->setAccessible(true);
        
        $data = ['title' => 'Test', 'content' => 'Content'];
        $metadata = ['source' => 'test'];
        
        $item = $method->invoke($processor, $data, $metadata);
        
        $this->assertInstanceOf(\Rake\Entities\ParsedData\ExtractedDataItem::class, $item);
        $this->assertEquals('Test', $item->get('title'));
        $this->assertEquals('test', $item->getMeta('source'));
    }

    public function test_validate_required_fields_passes()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('validateRequiredFields');
        $method->setAccessible(true);
        
        $item = new \Rake\Entities\ParsedData\ExtractedDataItem(['field1' => 'value1', 'field2' => 'value2']);
        
        // Should not throw
        $method->invoke($processor, $item, ['field1', 'field2']);
        
        $this->assertTrue(true);
    }

    public function test_validate_required_fields_throws_on_missing()
    {
        $processor = $this->getMockForAbstractClass(AbstractProcessor::class);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('validateRequiredFields');
        $method->setAccessible(true);
        
        $item = new \Rake\Entities\ParsedData\ExtractedDataItem(['field1' => 'value1']);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'field2' is missing");
        
        $method->invoke($processor, $item, ['field1', 'field2']);
    }

    public function test_wordpress_post_processor_extends_abstract()
    {
        $processor = new \CrawlFlow\Processors\WordPressPostProcessor();
        
        $this->assertInstanceOf(AbstractProcessor::class, $processor);
        $this->assertInstanceOf(ProcessorInterface::class, $processor);
    }
}

