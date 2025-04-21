<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\View\Element\Template;
use Storyblok\Api\Request\StoriesRequest;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\ViewModel\Asset;

class StoryList extends Story
{
    protected string $templateSuffix = '_list';
    protected string $templateDir = 'list';

    public function __construct(
        Template\Context $context,
        private readonly StoryRepository $storyRepository,
        Asset $assetViewModel,
        Config $scopeConfig,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = [],
    ) {
        parent::__construct($context, $storyRepository, $assetViewModel, $scopeConfig, $data);
    }

    public function getStories(): ?SearchResultsInterface
    {
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
}
