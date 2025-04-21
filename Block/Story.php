<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\Story as StoryblokStory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\ViewModel\Asset;

class Story extends Template
{
    protected string $templateSuffix = '';
    protected string $templateDir = 'story';

    public function __construct(
        Template\Context $context,
        private readonly StoryRepository $storyRepository,
        private readonly Asset $assetViewModel,
        protected readonly Config $scopeConfig,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getData($key = '', $index = null)
    {
        if ($key === 'story') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getData('story')?->getData($key, $index);
    }

    public function getStory(): ?StoryblokStory
    {
        $story = $this->getData('story');

        if (!$story && $slug = $this->getData('slug')) {
            $story = $this->storyRepository->getBySlug($slug);
            $this->setData('story', $story);
        }

        return $story;
    }

    public function getContent(): StoryblokBlock
    {
        return $this->getStory()->getContent();
    }

    public function getContentHtml(): string
    {
        $blockName = 'content-'
            . $this->getContent()->getComponent()
            . '-'
            . md5(json_encode($this->getContent()->getData()));
        $block = $this->getLayout()
            ->createBlock(Block::class, $blockName)
            ->setData('block', $this->getContent())
            ->setData('asset_view_model', $this->assetViewModel);

        return $block->toHtml();
    }

    public function getCacheKeyInfo(): array
    {
        return [
            ...parent::getCacheKeyInfo(),
            'storyblok_story',
            'storyblok_cv_' . $this->getStory()->getCacheVersion(),
            'storyblok_id_' . $this->getStory()->getId(),
            'storyblok_slug_' . $this->getStory()->getSlug(),
        ];
    }

    public function getStoryTemplate(): string
    {
        $content = $this->getStory()->getContent();

        if ($componentName = $content->getComponent()) {
            $templateName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $componentName));
            $templateName = str_replace('-', '_', $templateName);

            return sprintf(
                'WindAndKite_Storyblok::%s/%s%s.phtml',
                $this->templateDir,
                $templateName,
                $this->templateSuffix
            );
        }

        return $this->_template;
    }

    public function getTemplateFile(
        $template = null
    ) {
        $template ??= $this->getStoryTemplate();

        $result = parent::getTemplateFile($template);

        if (!$result) {
            return parent::getTemplateFile();
        }

        return $result;
    }
}
