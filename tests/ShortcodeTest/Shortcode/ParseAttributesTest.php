<?php declare(strict_types=1);

namespace ShortcodeTest\Shortcode;

use Omeka\Test\AbstractHttpControllerTestCase;
use ShortcodeTest\ShortcodeTestTrait;

class ParseAttributesTest extends AbstractHttpControllerTestCase
{
    use ShortcodeTestTrait;

    /**
     * @var \Shortcode\View\Helper\Shortcodes
     */
    protected $shortcodes;

    /**
     * @var \ReflectionMethod
     */
    protected $parseMethod;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $this->shortcodes = $viewHelperManager->get('shortcodes');
        $this->parseMethod = new \ReflectionMethod($this->shortcodes, 'parseShortcodeAttributes');
        $this->parseMethod->setAccessible(true);
    }

    protected function parse(string $attributes): array
    {
        return $this->parseMethod->invoke($this->shortcodes, $attributes);
    }

    public function testEmptyString(): void
    {
        $this->assertSame([], $this->parse(''));
    }

    public function testKeyValuePair(): void
    {
        $result = $this->parse('id=5');
        $this->assertSame(['id' => '5'], $result);
    }

    public function testMultipleKeyValuePairs(): void
    {
        $result = $this->parse('id=5 num=10 sort=created');
        $this->assertSame(['id' => '5', 'num' => '10', 'sort' => 'created'], $result);
    }

    public function testDoubleQuotedValue(): void
    {
        $result = $this->parse('title="My Title"');
        $this->assertSame(['title' => 'My Title'], $result);
    }

    public function testSingleQuotedValue(): void
    {
        $result = $this->parse("title='My Title'");
        $this->assertSame(['title' => 'My Title'], $result);
    }

    public function testPositionalValue(): void
    {
        $result = $this->parse('42');
        $this->assertSame([0 => '42'], $result);
    }

    public function testMixedPositionalAndKeyed(): void
    {
        $result = $this->parse('42 view=url');
        $this->assertArrayHasKey(0, $result);
        $this->assertSame('42', $result[0]);
        $this->assertSame('url', $result['view']);
    }

    public function testHtmlEntityDoubleQuote(): void
    {
        $result = $this->parse('title=&quot;My Title&quot;');
        $this->assertSame(['title' => 'My Title'], $result);
    }

    public function testHtmlEntitySingleQuote(): void
    {
        $result = $this->parse('title=&#39;My Title&#39;');
        $this->assertSame(['title' => 'My Title'], $result);
    }

    public function testKeysAreLowercased(): void
    {
        $result = $this->parse('ID=5 View=url');
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('view', $result);
    }

    public function testUnclosedHtmlTagIsRejected(): void
    {
        $result = $this->parse('title="<script"');
        $this->assertSame('', $result['title']);
    }

    public function testQueryAttribute(): void
    {
        $result = $this->parse('query="property[0][property]=dcterms:subject&property[0][type]=eq&property[0][text]=test"');
        $this->assertArrayHasKey('query', $result);
        $this->assertStringContainsString('dcterms:subject', $result['query']);
    }
}
