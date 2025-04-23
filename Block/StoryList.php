<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template;
use Storyblok\Api\Request\StoriesRequest;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\ViewModel\Asset;

class StoryList extends Story
{
    protected const TEMPLATE_DIR = 'story/list';

    public function __construct(
        StoryRepository $storyRepository,
        Asset $assetViewModel,
        Config $scopeConfig,
        Template\Context $context,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($storyRepository, $assetViewModel, $scopeConfig, $context, $data);
    }

    public function getStories(): ?SearchResultsInterface
    {
        if ($stories = $this->getData('stories')) {
            return $stories;
        }

        $parent = $this->getStory();

        if (!$this->scopeConfig->isStoryListsEnabled() || !$parent->getIsStartpage()) {
            return null;
        }

        if (!$this->getData('stories')) {
            $currentPage = (int)$this->getRequest()->getParam('p', 1);
            $pageSize = $this->scopeConfig->getStoryListPerPage() ?? StoriesRequest::PER_PAGE;

            $this->searchCriteriaBuilder->setCurrentPage($currentPage)->setPageSize($pageSize);
            $additionalFilters = [
                'starts_with' => rtrim($parent->getFullSlug(), '/') . '/',
                'is_startpage' => 'false',
            ];

            $this->setData(
                'stories',
                $this->storyRepository->getList($this->searchCriteriaBuilder->create(), $additionalFilters)
            );
        }

        return $this->getData('stories');
    }

    public function getStats(): Phrase
    {
        $stories = $this->getStories();
        $storyCount = count($stories->getItems());
        $pageSize = (int)$stories->getSearchCriteria()->getPageSize();
        $currentPage = (int)$stories->getSearchCriteria()->getCurrentPage();
        $totalCount = (int)$stories->getTotalCount();

        return match (true) {
            $storyCount === 1 => __('1 Item found'),
            $totalCount <= $pageSize => __('Items 1 to %1', $totalCount),
            default => __(
                'Items %1 to %2 of %3 total',
                ($currentPage - 1) * $pageSize + 1,
                min(($currentPage * $pageSize), $totalCount),
                $totalCount
            )
        };
    }

    public function toHtml()
    {
        if ($this->getStory()->getIsStartpage()) {
            return parent::toHtml();
        }

        return '';
    }
}
