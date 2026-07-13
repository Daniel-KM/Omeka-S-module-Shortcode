<?php declare(strict_types=1);

namespace ShortcodeTest\Shortcode;

use Omeka\Test\AbstractHttpControllerTestCase;
use ShortcodeTest\ShortcodeTestTrait;

/**
 * Tests that shortcodes respect resource and value visibility.
 *
 * The shortcode view helper is recreated after each auth change to ensure the
 * API calls inside use the current identity context.
 */
class VisibilityTest extends AbstractHttpControllerTestCase
{
    use ShortcodeTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function shortcodes(): \Shortcode\View\Helper\Shortcodes
    {
        return $this->getServiceLocator()
            ->get('ViewHelperManager')
            ->get('shortcodes');
    }

    /**
     * A private resource should not be accessible via shortcode for anonymous
     * users.
     */
    public function testPrivateResourceNotAccessibleAnonymous(): void
    {
        $item = $this->createItem('Secret Item');
        $itemId = $item->id();
        $this->api()->update('items', $itemId, ['o:is_public' => false], [], ['isPartial' => true]);

        $this->logout();

        $result = ($this->shortcodes())('[item id=' . $itemId . ' meta=title]');
        $this->assertEmpty(
            $result,
            'Private resource should not be rendered for anonymous users'
        );
    }

    /**
     * A private resource should not be counted via shortcode for anonymous
     * users.
     */
    public function testPrivateResourceNotCountedAnonymous(): void
    {
        $this->createItem('Public Count Item');
        $privateItem = $this->createItem('Private Count Item');
        $this->api()->update('items', $privateItem->id(), ['o:is_public' => false], [], ['isPartial' => true]);

        $adminCount = (int) ($this->shortcodes())('[count resource=item]');

        $this->logout();

        $anonCount = (int) ($this->shortcodes())('[count resource=item]');

        $this->assertLessThan(
            $adminCount,
            $anonCount,
            'Anonymous count should be less than admin count when private items exist'
        );
    }

    /**
     * A private resource should still be accessible by admin.
     */
    public function testPrivateResourceAccessibleAdmin(): void
    {
        $item = $this->createItem('Admin Secret Item');
        $this->api()->update('items', $item->id(), ['o:is_public' => false], [], ['isPartial' => true]);

        $result = ($this->shortcodes())('[item id=' . $item->id() . ' meta=title]');
        $this->assertStringContainsString(
            'Admin Secret Item',
            $result,
            'Private resource should be visible to admin'
        );
    }

    /**
     * A public resource with a private value should not expose that value to
     * anonymous users via meta shortcode.
     *
     * Omeka S uses a Doctrine SQL filter (ValueVisibilityFilter) that adds
     * "is_public = 1" to value queries for non-admin users. Private literal
     * values are therefore filtered at the SQL level.
     */
    public function testPublicResourceWithPrivateValue(): void
    {
        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => 'Public Title',
                    'is_public' => true,
                ],
            ],
            'dcterms:description' => [
                [
                    'type' => 'literal',
                    'property_id' => 4,
                    '@value' => 'Secret Description',
                    'is_public' => false,
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        // Admin can see private values.
        $adminDesc = ($this->shortcodes())('[item id=' . $item->id() . ' meta=dcterms:description]');
        $this->assertStringContainsString(
            'Secret Description',
            $adminDesc,
            'Admin should see private values'
        );

        $this->logout();

        // Clear entity cache so the item is re-loaded with value visibility
        // filter applied (private values excluded at SQL level).
        $this->getEntityManager()->clear();

        // Title (denormalized in entity) is always accessible.
        $titleResult = ($this->shortcodes())('[item id=' . $item->id() . ' meta=title]');
        $this->assertStringContainsString(
            'Public Title',
            $titleResult,
            'Public title should be visible to anonymous users'
        );

        // Private description should not be accessible to anonymous users.
        $descResult = ($this->shortcodes())('[item id=' . $item->id() . ' meta=dcterms:description]');
        $this->assertEmpty(
            $descResult,
            'Private value should not be visible to anonymous users'
        );
    }

    /**
     * A public resource with a resource-type value pointing to a private
     * resource should not expose that linked resource to anonymous users.
     */
    public function testPublicResourceWithPrivateLinkedResource(): void
    {
        $privateLinked = $this->createItem('Linked Private Item');
        $this->api()->update('items', $privateLinked->id(), ['o:is_public' => false], [], ['isPartial' => true]);

        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => 'Public Linker',
                    'is_public' => true,
                ],
            ],
            'dcterms:relation' => [
                [
                    'type' => 'resource:item',
                    'property_id' => 6,
                    'value_resource_id' => $privateLinked->id(),
                    'is_public' => true,
                ],
            ],
        ]);
        $linker = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $linker->id()];

        $this->logout();

        // Re-read the resource as anonymous to get filtered values.
        $anonLinker = $this->api()->read('items', $linker->id())->getContent();
        $values = $anonLinker->values();
        if (isset($values['dcterms:relation'])) {
            $this->assertEmpty(
                $values['dcterms:relation']['values'],
                'Resource-type value pointing to private resource should be'
                . ' hidden for anonymous users'
            );
        } else {
            // Property not present at all — also correct.
            $this->assertArrayNotHasKey(
                'dcterms:relation',
                $values,
                'Private linked resource should not appear in values'
            );
        }
    }
}
