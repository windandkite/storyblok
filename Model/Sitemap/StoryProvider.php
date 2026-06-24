<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model\Sitemap;

use DateTime;
use Exception;
use Magento\Framework\Api\CriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\StoriesSearchCriteriaBuilder;
use WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Model\Story;
use WindAndKite\Storyblok\Scope\Config;

class StoryProvider implements ItemProviderInterface
{
    private const PAGE_SIZE = 100;

    /**
     * @param Config $config
     * @param StoryRepositoryInterface $storyRepository
     * @param StoriesSearchCriteriaBuilder $searchCriteriaBuilder
     * @param SitemapItemInterfaceFactory $sitemapItemFactory
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoryRepositoryInterface $storyRepository,
        private readonly StoriesSearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SitemapItemInterfaceFactory $sitemapItemFactory,
        private readonly SortOrderBuilder $sortOrderBuilder,
    ) {}

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function getItems(
        $storeId
    ): array {
        if (!$this->isSitemapGenerationAllowed((int)$storeId)) {
            return [];
        }

        $this->buildSearchCriteria((int)$storeId);

        $priority = $this->config->getSitemapPriority(scopeCode: $storeId);
        $changefreq = $this->config->getSitemapChangefreq(scopeCode: $storeId);

        $items = [];
        $currentPage = 1;

        do {
            $this->searchCriteriaBuilder->setCurrentPage($currentPage);
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $searchResults = $this->storyRepository->getList($searchCriteria);
            $stories = $searchResults->getItems();

            if (empty($stories)) {
                break;
            }

            /** @var Story $story */
            foreach ($stories as $story) {
                $items[] = $this->sitemapItemFactory->create([
                    'id' => $story->getId(),
                    'url' => $story->getFullSlug(),
                    'updated_at' => new DateTime($story->getUpdatedAt()),
                    'priority' => $priority,
                    'changeFrequency' => $changefreq,
                ]);
            }

            $currentPage++;
        } while (count($items) < $searchResults->getTotalCount());

        return $items;
    }

    /**
     * Check if sitemap generation is allowed for the store.
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function isSitemapGenerationAllowed(
        int $storeId,
    ): bool {
        return $this->config->isModuleEnabled(scopeCode: $storeId)
            && $this->config->isSitemapEnabled(scopeCode: $storeId)
            && $this->config->isPageRoutingEnabled(scopeCode: $storeId);
    }

    /**
     * Prepare initial filters and sorting on the search criteria builder.
     *
     * @param int $storeId
     *
     * @return void
     */
    private function buildSearchCriteria(
        int $storeId,
    ): void {
        if (
            $this->config->isRestrictFolderEnabled(scopeCode: $storeId)
            && $folderPath = $this->config->getFolderPath(scopeCode: $storeId)
        ) {
            $this->searchCriteriaBuilder
                ->addFilter(StoriesSearchCriteriaInterface::STARTS_WITH, rtrim($folderPath, '/') . '/')
                ->addFilter(StoriesSearchCriteriaInterface::IS_STARTPAGE, false);
        }

        if ($excludedFolders = $this->config->getSitemapExcludeFolders(scopeCode: $storeId)) {
            $excludedSlugs = array_map(
                static fn(string $folder): string => rtrim($folder, '/') . '/*',
                $excludedFolders
            );

            $this->searchCriteriaBuilder->addFilter('slug', $excludedSlugs, 'nin');
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField(StoryInterface::KEY_FIRST_PUBLISHED_AT)
            ->setDirection(CriteriaInterface::SORT_ORDER_DESC)
            ->create();

        $this->searchCriteriaBuilder->addSortOrder($sortOrder)->setPageSize(self::PAGE_SIZE);
    }
}
