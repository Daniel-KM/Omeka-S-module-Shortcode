<?php declare(strict_types=1);

namespace ShortcodeTest\Shortcode;

use Omeka\Test\AbstractHttpControllerTestCase;
use Shortcode\Shortcode\AbstractShortcode;
use ShortcodeTest\ShortcodeTestTrait;

class AbstractShortcodeTest extends AbstractHttpControllerTestCase
{
    use ShortcodeTestTrait;

    /**
     * @var AbstractShortcode
     */
    protected $shortcode;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->shortcode = new class extends AbstractShortcode {
            public function render(array $args = []): string
            {
                return '';
            }
            public function publicBool(string $value): bool
            {
                return $this->bool($value);
            }
            public function publicBoolean(string $value): int
            {
                return $this->boolean($value);
            }
            public function publicListIds(string $value): array
            {
                return $this->listIds($value);
            }
            public function publicSingleOrListIds(string $value)
            {
                return $this->singleOrListIds($value);
            }
            public function publicListTermsOrIds(string $value): array
            {
                return $this->listTermsOrIds($value);
            }
            public function publicSingleOrListTermsOrIds(string $value)
            {
                return $this->singleOrListTermsOrIds($value);
            }
            public function publicWrapSpan(string $html, ?string $class = null): string
            {
                return $this->wrapSpan($html, $class);
            }
            public function publicGetViewTemplate(array $args): ?string
            {
                return $this->getViewTemplate($args);
            }
        };
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $renderer = $viewHelperManager->getRenderer();
        $this->shortcode->setView($renderer);
    }

    /**
     * @dataProvider boolProvider
     */
    public function testBool(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->shortcode->publicBool($input));
    }

    public function boolProvider(): array
    {
        return [
            ['1', true],
            ['true', true],
            ['yes', true],
            ['0', false],
            ['false', false],
        ];
    }

    /**
     * @dataProvider booleanProvider
     */
    public function testBoolean(string $input, int $expected): void
    {
        $this->assertSame($expected, $this->shortcode->publicBoolean($input));
    }

    public function booleanProvider(): array
    {
        return [
            ['1', 1],
            ['true', 1],
            ['0', 0],
            ['false', 0],
        ];
    }

    /**
     * @dataProvider listIdsProvider
     */
    public function testListIds(string $input, array $expected): void
    {
        $this->assertSame($expected, $this->shortcode->publicListIds($input));
    }

    public function listIdsProvider(): array
    {
        return [
            ['5', [5]],
            ['1,2,3', [1, 2, 3]],
            ['3-6', [3, 4, 5, 6]],
            ['6-3', [3, 4, 5, 6]],
        ];
    }

    /**
     * @dataProvider singleOrListIdsProvider
     */
    public function testSingleOrListIds(string $input, $expected): void
    {
        $this->assertSame($expected, $this->shortcode->publicSingleOrListIds($input));
    }

    public function singleOrListIdsProvider(): array
    {
        return [
            ['5', 5],
            ['1,2,3', [1, 2, 3]],
        ];
    }

    /**
     * @dataProvider listTermsOrIdsProvider
     */
    public function testListTermsOrIds(string $input, array $expected): void
    {
        $this->assertSame($expected, $this->shortcode->publicListTermsOrIds($input));
    }

    public function listTermsOrIdsProvider(): array
    {
        return [
            ['dcterms:title', ['dcterms:title']],
            ['dcterms:title, dcterms:date', ['dcterms:title', 'dcterms:date']],
        ];
    }

    public function testSingleOrListTermsOrIdsSingle(): void
    {
        $this->assertSame('dcterms:title', $this->shortcode->publicSingleOrListTermsOrIds('dcterms:title'));
    }

    public function testSingleOrListTermsOrIdsList(): void
    {
        $result = $this->shortcode->publicSingleOrListTermsOrIds('dcterms:title, dcterms:date');
        $this->assertSame(['dcterms:title', 'dcterms:date'], $result);
    }

    public function testWrapSpanWithoutClass(): void
    {
        $result = $this->shortcode->publicWrapSpan('content');
        $this->assertEquals('<span>content</span>', $result);
    }

    public function testWrapSpanWithClass(): void
    {
        $result = $this->shortcode->publicWrapSpan('content', 'my-class');
        $this->assertEquals('<span class="my-class">content</span>', $result);
    }

    public function testWrapSpanWithEmptyClass(): void
    {
        $result = $this->shortcode->publicWrapSpan('content', '');
        $this->assertEquals('<span>content</span>', $result);
    }

    public function testWrapSpanEscapesClassAttribute(): void
    {
        $result = $this->shortcode->publicWrapSpan('content', '"><script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testGetViewTemplateWithDotReturnsNull(): void
    {
        $result = $this->shortcode->publicGetViewTemplate(['view' => 'path/to.file']);
        $this->assertNull($result);
    }

    public function testGetViewTemplateWithNonExistentReturnsNull(): void
    {
        $result = $this->shortcode->publicGetViewTemplate(['view' => 'nonexistent-template-xyz']);
        $this->assertNull($result);
    }

    public function testGetViewTemplateWithoutViewReturnsNull(): void
    {
        $result = $this->shortcode->publicGetViewTemplate([]);
        $this->assertNull($result);
    }

    public function testResourceNamesMapping(): void
    {
        $reflection = new \ReflectionProperty(AbstractShortcode::class, 'resourceNames');
        $reflection->setAccessible(true);
        $names = $reflection->getValue($this->shortcode);

        $this->assertSame('items', $names['item']);
        $this->assertSame('items', $names['items']);
        $this->assertSame('item_sets', $names['collection']);
        $this->assertSame('item_sets', $names['collections']);
        $this->assertSame('site_pages', $names['page']);
        $this->assertSame('digital_objects', $names['digital_object']);
        $this->assertSame('digital_objects', $names['digital_objects']);
    }
}
