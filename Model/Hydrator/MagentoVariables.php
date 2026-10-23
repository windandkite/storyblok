<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model\Hydrator;

use Magento\Framework\App\Response\Http as HttpResponse;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\ViewModel\Variables;

/**
 * Gives the magento_variables reference blok its view model, so any theme's template can use it.
 *
 * Only in a validated Visual Editor session: an unpublished story can still be requested as a draft, and the list
 * includes custom variable values that aren't otherwise public. Without a session the template renders nothing.
 */
class MagentoVariables implements BlockHydratorInterface
{
    /**
     * @param Variables $variables
     * @param StoryblokSessionManager $sessionManager
     * @param HttpResponse $response
     */
    public function __construct(
        private readonly Variables $variables,
        private readonly StoryblokSessionManager $sessionManager,
        private readonly HttpResponse $response,
    ) {}

    /**
     * Add the variables view model to the block for editors.
     *
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        if (!$this->sessionManager->isValidEditorSession()) {
            return $blockInstance;
        }

        // Keep the listing out of the full page cache and Varnish. Blocks render after Magento sets its public
        // cache headers, so this wins.
        $this->response->setNoCacheHeaders();

        return $blockInstance->setData('variables_view_model', $this->variables);
    }
}
