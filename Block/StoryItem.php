<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

class StoryItem extends Story
{
    protected string $templateSuffix = '_item';
    protected string $templateDir = 'list';
}
