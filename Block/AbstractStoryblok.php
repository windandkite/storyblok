<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Model\Story as StoryblokStory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\ViewModel\Asset;

abstract class AbstractStoryblok extends Template
{
    protected const TEMPLATE_DIR = '';
    protected const TEMPLATE_SUFFIX = '';

    /**
     * @param StoryRepository $storyRepository
     * @param Asset $assetViewModel
     * @param Config $scopeConfig
     * @param Template\Context $context
     * @param StoryRequestService $storyRequestService
     * @param array $data
     */
    public function __construct(
        protected readonly StoryRepository $storyRepository,
        protected readonly Asset $assetViewModel,
        protected readonly Config $scopeConfig,
        protected readonly StoryRequestService $storyRequestService,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the technical Storyblok component identifier name.
     *
     * @return string|null
     */
    public abstract function getComponent(): ?string;

    /**
     * Get the designated directory name where templates for this block type live.
     *
     * @return string
     */
    public function getTemplateDir(): string
    {
        return $this->getData('template_dir') ?? static::TEMPLATE_DIR;
    }

    /**
     * Get the suffix string applied to computed template paths.
     *
     * @return string
     */
    public function getTemplateSuffix(): string
    {
        return $this->getData('template_suffix') ?? static::TEMPLATE_SUFFIX;
    }

    /**
     * Build the automatic component template path dynamically.
     *
     * Refactored to drop explicit module scope prefix mapping, allowing
     * the path to fall through native view directory search lists seamlessly.
     *
     * @return string|null
     */
    public function getStoryblokTemplate(): ?string
    {
        if ($this->_template) {
            return $this->_template;
        }

        $component = $this->getComponent();

        if (!$component) {
            return null;
        }

        $component = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $component));
        $component = str_replace('-', '_', $component);

        return sprintf(
            '%s/%s%s.phtml',
            $this->getTemplateDir(),
            $component,
            $this->getTemplateSuffix()
        );
    }

    /**
     * Intercept template rendering file determinations.
     *
     * Resolves the template file via Magento design fallbacks, utilizing
     * the calculated local component path if no explicit string path is specified.
     *
     * @param string|null $template
     *
     * @return string|bool
     */
    public function getTemplateFile(
        $template = null,
    ): bool|string {
        $template ??= $this->getStoryblokTemplate();

        $result = parent::getTemplateFile($template);

        if (!$result) {
            return parent::getTemplateFile();
        }

        return $result;
    }

    /**
     * Return default fallback templates cleanly without explicit namespace lock-ins.
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->_template ?? sprintf(
            '%s/fallback%s.phtml',
            $this->getTemplateDir(),
            $this->getTemplateSuffix()
        );
    }

    /**
     * Generate a direct storefront link URL pointing to a designated Storyblok story slug.
     *
     * @param StoryblokStory|null $story
     * @param array $params
     * @param array $retainParams
     *
     * @return string
     */
    public function getStoryUrl(
        ?StoryblokStory $story = null,
        array $params = [],
        array $retainParams = [],
    ): string {
        $story ??= $this->getStory();

        if (!$story) {
            return '';
        }

        if ($retainParams) {
            $retainedParams = [];

            foreach ($retainParams as $param) {
                $retainedParams[$param] = $this->getRequest()->getParam($param, null);
            }

            $retainedParams = array_filter($retainedParams);

            $params['_query'] = array_merge($retainedParams, $params['_query'] ?? []);
        }

        return $this->_urlBuilder->getDirectUrl(
            $story->getFullSlug(),
            $params
        );
    }

    /**
     * Fetch or dynamically resolve the active Story model payload assigned to the block structure.
     *
     * @return StoryblokStory|null
     */
    public function getStory(): ?StoryblokStory
    {
        if (!$this->getData('story')) {
            $storyRequest = $this->storyRequestService->getStoryRequest($this->getData());

            try {
                if ($slug = $this->getSlug()) {
                    $this->setData('story', $this->storyRepository->getBySlug($slug, $storyRequest));
                } else if ($this->getRequest()->getParam('story')) {
                    $this->setData('story', $this->getRequest()->getParam('story'));
                }
            } catch (\Exception) {
                $this->setData('story', null);
            }
        }

        return $this->getData('story');
    }

    /**
     * Retrieve the view-model instance utilized for asset structural modifications.
     *
     * @return Asset
     */
    public function getAssetViewModel(): Asset
    {
        return $this->assetViewModel;
    }
}
