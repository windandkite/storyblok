<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use WindAndKite\Storyblok\Model\Block as StoryblokBlock;

class Story extends AbstractStoryblok
{
    protected const TEMPLATE_DIR = 'story';

    public function getData($key = '', $index = null)
    {
        if ($key === 'story') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getData('story')?->getData($key, $index);
    }

    public function getContent(): StoryblokBlock
    {
        return $this->getStory()->getContent();
    }

    public function getContentHtml(
        array $data = [],
    ): string {
        $blockName = 'content-'
            . $this->getContent()->getComponent()
            . '-'
            . md5(json_encode($this->getStory()->getData()));

        $data['block'] = $this->getContent();
        $data['story'] = $this->getStory();

        $block = $this->getLayout()
            ->createBlock(Block::class, $blockName)
            ->setData($data);

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

    public function getTemplateFile(
        $template = null
    ) {
        $template ??= $this->getStoryblokTemplate();

        $result = parent::getTemplateFile($template);

        if (!$result) {
            return parent::getTemplateFile();
        }

        return $result;
    }

    public function getComponent(): string
    {
        return $this->getContent()->getComponent();
    }
}
