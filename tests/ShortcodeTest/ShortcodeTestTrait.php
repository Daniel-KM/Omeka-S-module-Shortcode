<?php declare(strict_types=1);

namespace ShortcodeTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;

trait ShortcodeTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created resource IDs for cleanup.
     */
    protected $createdResources = [];

    /**
     * @var array List of created site IDs for cleanup.
     */
    protected $createdSites = [];

    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
        $this->refreshVisibilityFilters();
    }

    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
        $this->refreshVisibilityFilters();
    }

    /**
     * Invalidate Doctrine's DQL query cache after an auth change.
     *
     * Doctrine's SQLFilter caches the generated SQL based on filter parameters.
     * ResourceVisibilityFilter uses the service locator directly (not
     * setParameter()), so the DQL cache is not automatically invalidated when
     * the identity changes. Setting a dummy parameter forces a new cache key.
     */
    protected function refreshVisibilityFilters(): void
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $filters = $em->getFilters();
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $identity = $auth->getIdentity();
        $tag = $identity ? (string) $identity->getId() : 'anon';
        foreach (['resource_visibility', 'value_visibility'] as $name) {
            if ($filters->isEnabled($name)) {
                $filters->getFilter($name)
                    ->setParameter('_identity_tag', $tag);
            }
        }
    }

    protected function createSite(string $slug, string $title)
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:title' => $title,
            'o:theme' => 'default',
            'o:is_public' => true,
        ]);
        $site = $response->getContent();
        $this->createdSites[] = $site->id();
        return $site;
    }

    protected function createItem(string $title)
    {
        $response = $this->api()->create('items', [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
        ]);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];
        return $item;
    }

    protected function createItemSet(string $title)
    {
        $response = $this->api()->create('item_sets', [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
        ]);
        $itemSet = $response->getContent();
        $this->createdResources[] = ['type' => 'item_sets', 'id' => $itemSet->id()];
        return $itemSet;
    }

    protected function cleanupResources(): void
    {
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
            }
        }
        $this->createdResources = [];
        foreach ($this->createdSites as $siteId) {
            try {
                $this->api()->delete('sites', $siteId);
            } catch (\Exception $e) {
            }
        }
        $this->createdSites = [];
    }
}
