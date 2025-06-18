<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\DataObject\IdentityInterface;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;

class Story extends AbstractStoryblok implements IdentityInterface
{
    protected const TEMPLATE_DIR = 'story';

    public function getData($key = '', $index = null)
    {
        if ($key === 'story') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getData('story')?->getData($key, $index);
    }

    public function getContent(): ?StoryblokBlock
    {
        return $this->getStory()?->getContent();
    }

    public function getContentHtml(
        array $data = [],
    ): string {
        $story = $this->getStory();
        $content = $this->getContent();

        if (!$story || !$content) {
            return '';
        }

        $blockName = 'content-'
            . $this->getContent()->getComponent()
            . '-'
            . md5(json_encode($story->getData()));

        $data['block'] = $content;
        $data['story'] = $story;

        $block = $this->getLayout()
            ->createBlock(Block::class, $blockName)
            ->setData($data);

        return $block->toHtml();
    }

    public function getIdentities(): array
    {
        if (!$this->getStory()) {
            return [];
        }

        return [
            'storyblok_story',
            'storyblok_cv_' . $this->getStory()->getCacheVersion(),
            'storyblok_story_id_' . $this->getStory()->getId(),
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

    public function getComponent(): ?string
    {
        return $this->getContent()?->getComponent();
    }
}
