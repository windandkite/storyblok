<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * The Storyblok API failed (outage, timeout, rate limit, bad token), as opposed to the story not existing.
 * Not caught by the router, so visitors and crawlers get a server error rather than a 404 saying the page is gone.
 */
class StoryblokUnavailableException extends LocalizedException
{
}
