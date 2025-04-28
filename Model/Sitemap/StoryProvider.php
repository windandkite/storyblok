<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model\Sitemap;

use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Storyblok\Api\Domain\Value\Filter\Filters\IsFilter;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Model\Story;
use WindAndKite\Storyblok\Scope\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;

class StoryProvider implements ItemProviderInterface
{
    private const PAGE_SIZE = 100;

    /**
     * @param Config $config
     * @param StoryRepositoryInterface $storyRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SitemapItemInterfaceFactory $sitemapItemFactory
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private Config $config,
        private StoryRepositoryInterface $storyRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private SitemapItemInterfaceFactory $sitemapItemFactory,
        private SortOrderBuilder $sortOrderBuilder,
    ) {}

    /**
     * @inheritDoc
     */
    public function getItems(
        $storeId
    ): array {
        $items = [];
        $additionalFilters = [];
        $currentPage = 1;
        $processedItems = 0;

        if (
            !$this->config->isModuleEnabled(scopeCode: $storeId)
            || !$this->config->isSitemapEnabled(scopeCode: $storeId)
            || !$this->config->isPageRoutingEnabled(scopeCode: $storeId)
        ) {
            return [];
        }

        if ($this->config->isRestrictFolderEnabled(scopeCode: $storeId)) {
            if ($folderPath = $this->config->getFolderPath(scopeCode: $storeId)) {
                $additionalFilters['starts_with'] =  $folderPath . '/*';
                $additionalFilters['is_startpage'] = 'false';
            }
        }

        if ($excludedFolders = $this->config->getSitemapExcludeFolders(scopeCode: $storeId)) {
//            $additionalFilters['excluding_slugs'] = array_map(fn($folder) => $folder . '/*', $excludedFolders);
            $additionalFilters['excluding_slugs'] =  implode('/*,', $excludedFolders) . '/*';
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('published_at')
            ->setDirection('DESC')
            ->create();

        $this->searchCriteriaBuilder
            ->addSortOrder($sortOrder)
            ->setPageSize(self::PAGE_SIZE)
            ->setCurrentPage($currentPage);

        $stories = $this->storyRepository->getList($this->searchCriteriaBuilder->create(), $additionalFilters);
        $priority = $this->config->getSitemapPriority(scopeCode: $storeId);
        $changefreq = $this->config->getSitemapChangefreq(scopeCode: $storeId);

        $totalItems = $stories->getTotalCount();

        while ($processedItems < $totalItems) {
            /** @var Story $story */
            foreach ($stories->getItems() as $story) {
                $url = $story->getFullSlug();
                $updatedAt = new \DateTime($story->getUpdatedAt());

                $items[] = $this->sitemapItemFactory->create(
                    [
                        'id' => $story->getId(),
                        'url' => $url,
                        'updated_at' => $updatedAt,
                        'priority' => $priority,
                        'changeFrequency' => $changefreq,
                    ]
                );

                $processedItems++;
            }

            if ($processedItems >= $totalItems) {
                break;
            }

            $currentPage++;
            $searchCriteria = $stories->getSearchCriteria();
            $searchCriteria->setCurrentPage($currentPage);
            $stories = $this->storyRepository->getList($searchCriteria, $additionalFilters);
        }

        return $items;
    }
}
