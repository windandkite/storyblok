<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokHyvaComponents\Test\Unit\Observer;

use PHPUnit\Framework\TestCase;
use WindAndKite\StoryblokHyvaComponents\Observer\RegisterModuleForHyvaConfig;

class RegisterModuleForHyvaConfigTest extends TestCase
{
    public function testPathsAreRelativeToTheMagentoRoot(): void
    {
        $this->assertSame('app/code/A/B', RegisterModuleForHyvaConfig::relativePath('/var/www/html', '/var/www/html/app/code/A/B'));
        $this->assertSame('vendor/a/b', RegisterModuleForHyvaConfig::relativePath('/var/www/html/', '/var/www/html/vendor/a/b'));
        $this->assertSame(
            '../../../srv/storyblok/packages/x',
            RegisterModuleForHyvaConfig::relativePath('/var/www/html', '/srv/storyblok/packages/x'),
            'a path repository checkout outside the store'
        );
    }
}
