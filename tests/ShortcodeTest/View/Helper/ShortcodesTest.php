<?php declare(strict_types=1);

namespace ShortcodeTest\View\Helper;

use Omeka\Test\AbstractHttpControllerTestCase;
use ShortcodeTest\ShortcodeTestTrait;

class ShortcodesTest extends AbstractHttpControllerTestCase
{
    use ShortcodeTestTrait;

    /**
     * @var \Shortcode\View\Helper\Shortcodes
     */
    protected $shortcodes;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $this->shortcodes = $viewHelperManager->get('shortcodes');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testNoShortcodeReturnsUnchanged(): void
    {
        $content = '<p>Simple text without shortcodes.</p>';
        $result = ($this->shortcodes)($content);
        $this->assertEquals($content, $result);
    }

    public function testUnknownShortcodeReturnsUnchanged(): void
    {
        $content = '[unknown_shortcode id=1]';
        $result = ($this->shortcodes)($content);
        $this->assertEquals($content, $result);
    }

    public function testNoopShortcodeReturnsEmpty(): void
    {
        $content = 'before [noop] after';
        $result = ($this->shortcodes)($content);
        $this->assertEquals('before  after', $result);
    }

    public function testItemShortcodeWithInvalidId(): void
    {
        $content = '[item id=999999]';
        $result = ($this->shortcodes)($content);
        $this->assertEquals('', $result);
    }

    public function testCountShortcodeItems(): void
    {
        $item = $this->createItem('Count Test Item');

        $content = '[count resource=item]';
        $result = ($this->shortcodes)($content);
        $this->assertGreaterThanOrEqual(1, (int) $result);
    }

    public function testCountShortcodeWithSpan(): void
    {
        $content = '[count resource=item span=total-items]';
        $result = ($this->shortcodes)($content);
        $this->assertStringContainsString('<span class="total-items">', $result);
        $this->assertStringContainsString('</span>', $result);
    }

    public function testCountShortcodeInvalidResource(): void
    {
        $content = '[count resource=invalid]';
        $result = ($this->shortcodes)($content);
        $this->assertEquals('0', $result);
    }

    public function testCountShortcodeItemSets(): void
    {
        $this->createItemSet('Count Item Set');
        $content = '[count resource=item_set]';
        $result = ($this->shortcodes)($content);
        $this->assertGreaterThanOrEqual(1, (int) $result);
    }

    public function testItemMetaTitle(): void
    {
        $item = $this->createItem('Meta Title Item');
        $content = '[item id=' . $item->id() . ' meta=title]';
        $result = ($this->shortcodes)($content);
        $this->assertStringContainsString('Meta Title Item', $result);
    }

    public function testItemMetaCreated(): void
    {
        $item = $this->createItem('Created Item');
        $content = '[item id=' . $item->id() . ' meta=created]';
        $result = ($this->shortcodes)($content);
        $this->assertNotEmpty($result);
    }

    public function testItemMetaWithSpan(): void
    {
        $item = $this->createItem('Span Meta Item');
        $content = '[item id=' . $item->id() . ' meta=title span=item-title]';
        $result = ($this->shortcodes)($content);
        $this->assertStringContainsString('<span class="item-title">', $result);
        $this->assertStringContainsString('Span Meta Item', $result);
    }

    public function testShortcodeWithoutIdReturnsEmpty(): void
    {
        $content = '[item]';
        $result = ($this->shortcodes)($content);
        $this->assertEquals('', $result);
    }

    public function testMultipleShortcodesInContent(): void
    {
        $item1 = $this->createItem('Multi 1');
        $item2 = $this->createItem('Multi 2');
        $content = 'A [item id=' . $item1->id() . ' meta=title] B [item id=' . $item2->id() . ' meta=title] C';
        $result = ($this->shortcodes)($content);
        $this->assertStringContainsString('A ', $result);
        $this->assertStringContainsString(' B ', $result);
        $this->assertStringContainsString(' C', $result);
        $this->assertStringContainsString('Multi 1', $result);
        $this->assertStringContainsString('Multi 2', $result);
    }

    public function testShortcodeEscapesHtmlInMeta(): void
    {
        $item = $this->createItem('Item <script>alert(1)</script>');
        $content = '[item id=' . $item->id() . ' meta=title]';
        $result = ($this->shortcodes)($content);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testImageShortcodeWithNoMedia(): void
    {
        $item = $this->createItem('No Media Item');
        $content = '[image id=' . $item->id() . ']';
        $result = ($this->shortcodes)($content);
        $this->assertEquals('', $result);
    }

    public function testItemsShortcodeReturnsList(): void
    {
        $this->createItem('List Item 1');
        $this->createItem('List Item 2');
        $content = '[items num=2]';
        $result = ($this->shortcodes)($content);
        $this->assertNotEmpty($result);
    }

    public function testItemMetaAdded(): void
    {
        $item = $this->createItem('Added Compat Item');
        $content = '[item id=' . $item->id() . ' added]';
        $result = ($this->shortcodes)($content);
        $this->assertNotEmpty($result);
    }

    public function testItemMetaModified(): void
    {
        $item = $this->createItem('Modified Item');
        $content = '[item id=' . $item->id() . ' modified]';
        $result = ($this->shortcodes)($content);
        $this->assertNotEmpty($result);
    }

    public function testContentWithNoBrackets(): void
    {
        $content = 'No brackets here';
        $result = ($this->shortcodes)($content);
        $this->assertEquals($content, $result);
    }

    public function testCountWithQueryFilter(): void
    {
        $this->createItem('Filtered Item');
        $content = '[count resource=item query="sort_by=created"]';
        $result = ($this->shortcodes)($content);
        $this->assertGreaterThanOrEqual(1, (int) $result);
    }

    public function testItemSetMetaTitle(): void
    {
        $itemSet = $this->createItemSet('Test Collection Meta');
        $content = '[collection id=' . $itemSet->id() . ' meta=title]';
        $result = ($this->shortcodes)($content);
        $this->assertStringContainsString('Test Collection Meta', $result);
    }
}
