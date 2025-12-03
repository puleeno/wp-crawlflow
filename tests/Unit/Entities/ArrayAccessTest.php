<?php

namespace CrawlFlow\Tests\Unit\Entities;

use PHPUnit\Framework\TestCase;
use Rake\Entities\ParsedData\ExtractedDataItem;
use Rake\Entities\ParsedData\NullDataItem;

/**
 * Test ArrayAccess Implementation
 * 
 * @group entities
 * @group array-access
 * @group unit
 */
class ArrayAccessTest extends TestCase
{
    public function test_extracted_data_item_implements_array_access()
    {
        $item = new ExtractedDataItem();
        
        $this->assertInstanceOf(\ArrayAccess::class, $item);
    }

    public function test_can_get_via_array_access()
    {
        $item = new ExtractedDataItem(['title' => 'Test']);
        
        $this->assertEquals('Test', $item['title']);
    }

    public function test_can_set_via_array_access()
    {
        $item = new ExtractedDataItem();
        
        $item['title'] = 'New Title';
        
        $this->assertEquals('New Title', $item['title']);
        $this->assertEquals('New Title', $item->get('title'));
    }

    public function test_can_check_isset_via_array_access()
    {
        $item = new ExtractedDataItem(['title' => 'Test']);
        
        $this->assertTrue(isset($item['title']));
        $this->assertFalse(isset($item['nonexistent']));
    }

    public function test_can_unset_via_array_access()
    {
        $item = new ExtractedDataItem(['title' => 'Test']);
        
        unset($item['title']);
        
        $this->assertFalse(isset($item['title']));
        $this->assertFalse($item->has('title'));
    }

    public function test_null_data_item_array_access_get_returns_null()
    {
        $item = new NullDataItem();
        
        $this->assertNull($item['anything']);
    }

    public function test_null_data_item_array_access_isset_returns_false()
    {
        $item = new NullDataItem();
        
        $this->assertFalse(isset($item['anything']));
    }

    public function test_null_data_item_array_access_set_is_noop()
    {
        $item = new NullDataItem();
        
        // Should not throw
        $item['key'] = 'value';
        
        // Value should not be stored
        $this->assertNull($item['key']);
    }

    public function test_null_data_item_array_access_unset_is_noop()
    {
        $item = new NullDataItem();
        
        // Should not throw
        unset($item['key']);
        
        $this->assertTrue(true);
    }

    public function test_mixed_access_style_works()
    {
        $item = new ExtractedDataItem();
        
        // Set via method
        $item->set('via_method', 'method');
        
        // Get via array
        $this->assertEquals('method', $item['via_method']);
        
        // Set via array
        $item['via_array'] = 'array';
        
        // Get via method
        $this->assertEquals('array', $item->get('via_array'));
    }

    public function test_array_access_in_loop()
    {
        $item = new ExtractedDataItem([
            'title' => 'Test',
            'content' => 'Content',
        ]);
        
        $keys = [];
        
        // Can iterate like array
        foreach ($item->getData() as $key => $value) {
            $keys[] = $key;
            
            // Access via array syntax in loop
            $this->assertEquals($value, $item[$key]);
        }
        
        $this->assertCount(2, $keys);
    }
}

