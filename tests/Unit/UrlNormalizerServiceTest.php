<?php

namespace Tests\Unit;

use App\Services\UrlNormalizerService;
use Tests\TestCase;

class UrlNormalizerServiceTest extends TestCase
{
    private UrlNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new UrlNormalizerService();
    }

    /** @test */
    public function it_returns_null_for_empty_string()
    {
        $result = $this->normalizer->normalize('');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_whitespace_only()
    {
        $result = $this->normalizer->normalize('   ');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_malformed_url_with_no_host()
    {
        $result = $this->normalizer->normalize('://bad');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_scheme_only()
    {
        $result = $this->normalizer->normalize('http://');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_invalid_url()
    {
        $result = $this->normalizer->normalize('not a url at all');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_relative_url()
    {
        $result = $this->normalizer->normalize('/path/to/page');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_unsupported_scheme()
    {
        $result = $this->normalizer->normalize('ftp://example.com');
        $this->assertNull($result);
    }

    /** @test */
    public function it_normalizes_valid_url()
    {
        $result = $this->normalizer->normalize('https://example.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('normalized', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('query_hash', $result);
        
        $this->assertEquals('https://example.com/', $result['normalized']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals('/', $result['path']);
        $this->assertNotEmpty($result['hash']);
        $this->assertEquals(64, strlen($result['hash'])); // SHA256 is 64 chars
    }

    /** @test */
    public function it_auto_adds_https_scheme_to_domain()
    {
        $result = $this->normalizer->normalize('example.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/', $result['normalized']);
        $this->assertEquals('example.com', $result['host']);
    }

    /** @test */
    public function it_auto_adds_https_scheme_to_subdomain()
    {
        $result = $this->normalizer->normalize('www.example.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://www.example.com/', $result['normalized']);
        $this->assertEquals('www.example.com', $result['host']);
    }

    /** @test */
    public function it_removes_fragment()
    {
        $result = $this->normalizer->normalize('https://example.com/page#section');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/page', $result['normalized']);
        $this->assertStringNotContainsString('#section', $result['normalized']);
    }

    /** @test */
    public function it_removes_tracking_parameters()
    {
        $result = $this->normalizer->normalize('https://example.com?utm_source=test&utm_medium=email&id=123');
        
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('utm_source', $result['normalized']);
        $this->assertStringNotContainsString('utm_medium', $result['normalized']);
        $this->assertStringContainsString('id=123', $result['normalized']);
    }

    /** @test */
    public function it_removes_default_http_port()
    {
        $result = $this->normalizer->normalize('http://example.com:80/page');
        
        $this->assertIsArray($result);
        $this->assertEquals('http://example.com/page', $result['normalized']);
        $this->assertStringNotContainsString(':80', $result['normalized']);
    }

    /** @test */
    public function it_removes_default_https_port()
    {
        $result = $this->normalizer->normalize('https://example.com:443/page');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/page', $result['normalized']);
        $this->assertStringNotContainsString(':443', $result['normalized']);
    }

    /** @test */
    public function it_keeps_non_default_port()
    {
        $result = $this->normalizer->normalize('https://example.com:8080/page');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com:8080/page', $result['normalized']);
    }

    /** @test */
    public function it_lowercases_scheme_and_host()
    {
        $result = $this->normalizer->normalize('HTTPS://EXAMPLE.COM/Path');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/Path', $result['normalized']);
        $this->assertEquals('example.com', $result['host']);
    }

    /** @test */
    public function it_removes_trailing_slash_from_path()
    {
        $result = $this->normalizer->normalize('https://example.com/page/');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/page', $result['normalized']);
    }

    /** @test */
    public function it_keeps_root_slash()
    {
        $result = $this->normalizer->normalize('https://example.com/');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/', $result['normalized']);
        $this->assertEquals('/', $result['path']);
    }

    /** @test */
    public function it_sorts_query_parameters()
    {
        $result1 = $this->normalizer->normalize('https://example.com?b=2&a=1');
        $result2 = $this->normalizer->normalize('https://example.com?a=1&b=2');
        
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertEquals($result1['hash'], $result2['hash']);
        $this->assertEquals($result1['normalized'], $result2['normalized']);
    }

    /** @test */
    public function it_generates_consistent_hash_for_same_url()
    {
        $result1 = $this->normalizer->normalize('https://example.com/page');
        $result2 = $this->normalizer->normalize('https://example.com/page');
        
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertEquals($result1['hash'], $result2['hash']);
    }

    /** @test */
    public function it_generates_different_hash_for_different_urls()
    {
        $result1 = $this->normalizer->normalize('https://example.com/page1');
        $result2 = $this->normalizer->normalize('https://example.com/page2');
        
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertNotEquals($result1['hash'], $result2['hash']);
    }

    /** @test */
    public function it_handles_url_with_path_and_query()
    {
        $result = $this->normalizer->normalize('https://example.com/path/to/page?key=value');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/path/to/page?key=value', $result['normalized']);
        $this->assertEquals('/path/to/page', $result['path']);
        $this->assertNotNull($result['query_hash']);
    }

    /** @test */
    public function it_handles_complex_real_world_url()
    {
        $result = $this->normalizer->normalize('https://entrepreneur.com/article/12345');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://entrepreneur.com/article/12345', $result['normalized']);
        $this->assertEquals('entrepreneur.com', $result['host']);
        $this->assertEquals('/article/12345', $result['path']);
        $this->assertNotEmpty($result['hash']);
    }

    /** @test */
    public function it_handles_url_with_all_tracking_params()
    {
        $url = 'https://example.com/page?utm_source=google&utm_medium=cpc&utm_campaign=test&fbclid=123&gclid=456&id=789';
        $result = $this->normalizer->normalize($url);
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/page?id=789', $result['normalized']);
    }

    /** @test */
    public function it_resolves_relative_path_segments()
    {
        $result = $this->normalizer->normalize('https://example.com/path/./to/../page');
        
        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/path/page', $result['normalized']);
    }

    /** @test */
    public function test_make_absolute_with_absolute_url()
    {
        $result = $this->normalizer->makeAbsolute('https://example.com/page', 'https://base.com');
        $this->assertEquals('https://example.com/page', $result);
    }

    /** @test */
    public function test_make_absolute_with_absolute_path()
    {
        $result = $this->normalizer->makeAbsolute('/page', 'https://example.com/base');
        $this->assertEquals('https://example.com/page', $result);
    }

    /** @test */
    public function test_make_absolute_with_relative_path()
    {
        $result = $this->normalizer->makeAbsolute('page', 'https://example.com/base/');
        $this->assertEquals('https://example.com/base/page', $result);
    }

    /** @test */
    public function test_are_equivalent_returns_true_for_same_urls()
    {
        $result = $this->normalizer->areEquivalent(
            'https://example.com/page',
            'https://example.com/page'
        );
        $this->assertTrue($result);
    }

    /** @test */
    public function test_are_equivalent_returns_true_for_normalized_equivalents()
    {
        $result = $this->normalizer->areEquivalent(
            'https://example.com/page?utm_source=test',
            'https://example.com/page'
        );
        $this->assertTrue($result);
    }

    /** @test */
    public function test_are_equivalent_returns_false_for_different_urls()
    {
        $result = $this->normalizer->areEquivalent(
            'https://example.com/page1',
            'https://example.com/page2'
        );
        $this->assertFalse($result);
    }
}
