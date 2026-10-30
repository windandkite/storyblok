<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Domain\Value\Resolver\LinkLevel;
use Storyblok\Api\Domain\Value\Resolver\LinkType;

class StoriesSearchCriteriaBuilder extends SearchCriteriaBuilder
{
    public function setLanguage(
        string $language
    ): StoriesSearchCriteriaBuilder {
        return $this->_set(StoriesSearchCriteriaInterface::LANGUAGE, $language);
    }

    public function setExcludeFields(
        array $excludeFields
    ): StoriesSearchCriteriaBuilder {
        return $this->_set(StoriesSearchCriteriaInterface::EXCLUDING_FIELDS, $excludeFields);
    }

    public function setResolvedRelations(
        array $relations
    ): StoriesSearchCriteriaBuilder {
        return $this->_set(StoriesSearchCriteriaInterface::RESOLVE_RELATIONS, $relations);
    }

    public function setVersion(
        Version $version
    ): StoriesSearchCriteriaBuilder {
        return $this->_set(StoriesSearchCriteriaInterface::VERSION, $version);
    }
}
