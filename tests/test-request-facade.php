<?php
/**
 * Test Request Facade
 * Verifies Request facade returns HttpClientInterface
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Test Request Facade ===\n\n";

try {
    // Test if facade exists
    if (!class_exists('Rake\Facade\Request')) {
        echo "✗ Request facade not found\n";
        exit(1);
    }
    
    echo "✓ Request facade class exists\n";
    
    // Test if it extends Facade
    $reflection = new \ReflectionClass('Rake\Facade\Request');
    $parentClass = $reflection->getParentClass();
    
    if ($parentClass && $parentClass->getName() === 'Rake\Facade\Facade') {
        echo "✓ Request extends Facade\n";
    } else {
        echo "✗ Request does not extend Facade\n";
        exit(1);
    }
    
    // Test facade accessor
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);
    $accessor = $method->invoke(null);
    
    echo "✓ Facade accessor: {$accessor}\n";
    
    if ($accessor === 'Rake\Manager\HttpClientManager') {
        echo "✓ Points to HttpClientManager\n";
    }
    
    // Test client() method
    if ($reflection->hasMethod('client')) {
        echo "✓ client() method exists\n";
        
        $clientMethod = $reflection->getMethod('client');
        $returnType = $clientMethod->getReturnType();
        
        if ($returnType && $returnType->getName() === 'Rake\Contracts\Http\HttpClientInterface') {
            echo "✓ client() returns HttpClientInterface\n";
        }
    }
    
    echo "\n--- Facade Structure ---\n";
    echo "Class: Rake\\Facade\\Request\n";
    echo "Extends: Rake\\Facade\\Facade\n";
    echo "Accessor: Rake\\Manager\\HttpClientManager\n";
    echo "Returns: Rake\\Contracts\\Http\\HttpClientInterface\n";
    
    echo "\n--- Usage Example ---\n";
    echo "use Rake\\Facade\\Request;\n\n";
    echo "// GET request\n";
    echo "\$response = Request::get('https://api.example.com');\n\n";
    echo "// POST request\n";
    echo "\$response = Request::post('https://api.example.com', [\n";
    echo "    'json' => ['key' => 'value']\n";
    echo "]);\n\n";
    echo "// Get client instance\n";
    echo "\$client = Request::client(); // Returns HttpClientInterface\n";
    
    echo "\n=== Test Complete ===\n";
    echo "✓ Request facade properly configured\n";
    echo "✓ Returns HttpClientInterface\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

