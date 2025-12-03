<?php

namespace CrawlFlow\Tests\Unit\Entities;

use PHPUnit\Framework\TestCase;
use Rake\Entities\ParsedData\ExtractedDataItem;
use Rake\Entities\ParsedData\NullDataItem;
use Rake\Contracts\Entities\ParsedDataItemInterface;

/**
 * Test Data Items
 * 
 * @group entities
 * @group unit
 */
class DataItemTest extends TestCase
{
    public function test_extracted_data_item_implements_interface()
    {
        $item = new ExtractedDataItem();
        
        $this->assertInstanceOf(ParsedDataItemInterface::class, $item);
    }

    public function test_null_data_item_implements_interface()
    {
        $item = new NullDataItem();
        
        $this->assertInstanceOf(ParsedDataItemInterface::class, $item);
    }

    public function test_extracted_data_item_is_not_null()
    {
        $item = new ExtractedDataItem(['title' => 'Test']);
        
        $this->assertFalse($item->isNull());
    }

    public function test_null_data_item_is_null()
    {
        $item = new NullDataItem();
        
        $this->assertTrue($item->isNull());
    }

    public function test_null_data_item_returns_reason()
    {
        $item = new NullDataItem('Validation failed');
        
        $this->assertEquals('Validation failed', $item->getReason());
    }

    public function test_null_data_item_get_returns_default()
    {
        $item = new NullDataItem();
        
        $this->assertEquals('default', $item->get('any_key', 'default'));
        $this->assertNull($item->get('any_key'));
    }

    public function test_null_data_item_has_always_false()
    {
        $item = new NullDataItem();
        
        $this->assertFalse($item->has('any_key'));
    }

    public function test_extracted_data_item_stores_and_retrieves_data()
    {
        $item = new ExtractedDataItem(['title' => 'Test']);
        
        $this->assertEquals('Test', $item->get('title'));
        $this->assertTrue($item->has('title'));
    }

    public function test_extracted_data_item_can_set_data()
    {
        $item = new ExtractedDataItem();
        
        $item->set('key', 'value');
        
        $this->assertEquals('value', $item->get('key'));
    }

    public function test_extracted_data_item_can_merge_data()
    {
        $item = new ExtractedDataItem(['a' => 1]);
        
        $item->mergeData(['b' => 2, 'a' => 10]);
        
        $this->assertEquals(10, $item->get('a'));
        $this->assertEquals(2, $item->get('b'));
    }

    public function test_extracted_data_item_handles_metadata()
    {
        $item = new ExtractedDataItem([], ['source' => 'test']);
        
        $this->assertEquals('test', $item->getMeta('source'));
        
        $item->setMeta('processed', true);
        
        $this->assertTrue($item->getMeta('processed'));
    }
}

