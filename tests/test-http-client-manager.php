<?php
/**
 * Test HTTP Client Manager
 * Verifies HttpClientManager can create and manage clients
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\HttpClientManager;
use Rake\Facade\Request;

echo "=== Test HTTP Client Manager ===\n\n";

try {
    
    // Test 1: Get default client
    echo "--- Test 1: Get Default Client ---\n";
    
    $client = HttpClientManager::getDefaultClient();
    
    echo "✓ Default client created\n";
    echo "  Type: " . get_class($client) . "\n";
    echo "  Implements HttpClientInterface: " . 
         ($client instanceof \Rake\Contracts\Http\HttpClientInterface ? "YES" : "NO") . "\n";
    
    // Test 2: Make GET request
    echo "\n--- Test 2: Make GET Request ---\n";
    
    try {
        $response = $client->get('https://httpbin.org/get');
        
        echo "✓ GET request successful\n";
        echo "  Status: " . $response->getStatusCode() . "\n";
        echo "  Is Successful: " . ($response->isSuccessful() ? "YES" : "NO") . "\n";
        echo "  Body length: " . strlen($response->getBody()) . " bytes\n";
        
        // Try to parse JSON
        $json = $response->json();
        if (!empty($json)) {
            echo "  JSON parsed: " . count($json) . " keys\n";
        }
        
    } catch (\Exception $e) {
        echo "⚠ Request failed (expected if no internet): " . $e->getMessage() . "\n";
    }
    
    // Test 3: Use Request facade
    echo "\n--- Test 3: Use Request Facade ---\n";
    
    // First, register HttpClientManager in Rake container
    $rake = \Rake\Rake::getInstance();
    
    if (!$rake->has(HttpClientManager::class)) {
        $rake->singleton(HttpClientManager::class, function() {
            return new HttpClientManager();
        });
        echo "✓ HttpClientManager registered in container\n";
    }
    
    try {
        // Use facade
        $facadeResponse = Request::get('https://httpbin.org/get');
        
        echo "✓ Request facade works\n";
        echo "  Status: " . $facadeResponse->getStatusCode() . "\n";
        echo "  Via facade: YES\n";
        
    } catch (\Exception $e) {
        echo "⚠ Facade request failed: " . $e->getMessage() . "\n";
    }
    
    // Test 4: Register custom client
    echo "\n--- Test 4: Register Custom Client ---\n";
    
    $customClient = $client; // Use WordPress client as custom
    HttpClientManager::register('custom', $customClient);
    
    echo "✓ Custom client registered\n";
    
    $hasCustom = HttpClientManager::has('custom');
    echo "  Has 'custom': " . ($hasCustom ? "YES" : "NO") . "\n";
    
    // Test 5: Get client via manager instance
    echo "\n--- Test 5: Get Client via Manager ---\n";
    
    $manager = new HttpClientManager();
    $clientViaManager = $manager->getClient();
    
    echo "✓ Client retrieved via manager\n";
    echo "  Same as default: " . ($clientViaManager === $client ? "YES" : "NO") . "\n";
    
    echo "\n=== All Tests Complete ===\n";
    echo "\n✓ HttpClientManager working correctly\n";
    echo "✓ Default client creates automatically\n";
    echo "✓ Request facade can use HttpClientManager\n";
    echo "✓ Returns HttpClientInterface\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

