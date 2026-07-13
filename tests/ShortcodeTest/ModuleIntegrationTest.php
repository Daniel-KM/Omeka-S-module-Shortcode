<?php declare(strict_types=1);

namespace ShortcodeTest;

use Omeka\Test\AbstractHttpControllerTestCase;

class ModuleIntegrationTest extends AbstractHttpControllerTestCase
{
    use ShortcodeTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testModuleIsActive(): void
    {
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Shortcode');
        $this->assertNotNull($module, 'Shortcode module should be found');
        $this->assertEquals('active', $module->getState());
    }

    public function testShortcodeManagerIsRegistered(): void
    {
        $this->assertTrue(
            $this->getServiceLocator()->has('ShortcodeManager')
        );
    }

    public function testViewHelperIsRegistered(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $this->assertTrue($viewHelperManager->has('shortcodes'));
    }

    public function testAllShortcodesAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $invokables = $config['shortcodes']['invokables'] ?? [];

        $expected = [
            'noop', 'count', 'image', 'link',
            'annotation', 'asset', 'collection', 'digital_object',
            'item', 'item_set', 'media', 'page', 'resource', 'site',
            'annotations', 'assets', 'collections', 'digital_objects',
            'items', 'item_sets', 'medias', 'site_pages', 'sites',
            'file',
            'featured_collections', 'featured_items',
            'recent_collections', 'recent_items',
        ];

        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $invokables, "Shortcode '$name' should be registered");
        }
    }
}
