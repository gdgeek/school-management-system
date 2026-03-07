<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for shouldUsePsr15Stack() function
 * 
 * Note: This test extracts and tests the function logic in isolation
 * to avoid side effects from loading index.php
 */
class ShouldUsePsr15StackTest extends TestCase
{
    /**
     * Extracted implementation of shouldUsePsr15Stack for testing
     */
    private function shouldUsePsr15Stack(string $path): bool
    {
        // Migration configuration: list of paths/prefixes that use PSR-15 stack
        // Initially empty - will be populated as endpoints are migrated
        $migratedPaths = [];
        
        // Check if path matches any migrated endpoint
        foreach ($migratedPaths as $migratedPath) {
            // Support exact match
            if ($path === $migratedPath) {
                return true;
            }
            
            // Support prefix match (e.g., '/api/schools' matches '/api/schools/123')
            if (str_starts_with($path, $migratedPath . '/')) {
                return true;
            }
        }
        
        // Default: use legacy switch-case routing
        return false;
    }
    
    public function testReturnsFalseForAllPathsInitially(): void
    {
        // Initially, all paths should use legacy routing (empty migratedPaths array)
        $this->assertFalse($this->shouldUsePsr15Stack('/api/auth/login'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/schools'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/schools/123'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/classes'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/groups'));
        $this->assertFalse($this->shouldUsePsr15Stack('/health'));
        $this->assertFalse($this->shouldUsePsr15Stack('/'));
    }
    
    public function testHandlesEmptyPath(): void
    {
        $this->assertFalse($this->shouldUsePsr15Stack(''));
    }
    
    public function testHandlesRootPath(): void
    {
        $this->assertFalse($this->shouldUsePsr15Stack('/'));
    }
    
    public function testHandlesVariousApiPaths(): void
    {
        // All should return false initially
        $this->assertFalse($this->shouldUsePsr15Stack('/api/students'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/teachers'));
        $this->assertFalse($this->shouldUsePsr15Stack('/api/users/search'));
    }
    
    public function testHandlesPathsWithTrailingSlash(): void
    {
        $this->assertFalse($this->shouldUsePsr15Stack('/api/schools/'));
    }
    
    public function testHandlesNestedPaths(): void
    {
        $this->assertFalse($this->shouldUsePsr15Stack('/api/schools/123/details'));
    }
}
