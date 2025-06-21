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

    public function __construct(
        protected StoryRepository $storyRepository,
        private Asset $assetViewModel,
        protected Config $scopeConfig,
        Template\Context $context,
        protected StoryRequestService $storyRequestService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public abstract function getComponent(): ?string;

    public function getTemplateDir(): string
    {
        return $this->getData('template_dir') ?? static::TEMPLATE_DIR;
    }

    public function getTemplateSuffix(): string
    {
        return $this->getData('template_suffix') ?? static::TEMPLATE_SUFFIX;
    }

    public function getStoryblokTemplate(): ?string
    {
        if ($this->getTemplate()) {
            return $this->getTemplate();
        }

        $component = $this->getComponent();

        if (!$component) {
            return null;
        }

        $component = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $component));
        $component = str_replace('-', '_', $component);

        return sprintf(
            'WindAndKite_Storyblok::%s/%s%s.phtml',
            $this->getTemplateDir(),
            $component,
            $this->getTemplateSuffix()
        );
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

    public function getTemplate()
    {
        return $this->_template ?? sprintf(
            'WindAndKite_Storyblok::%s/fallback%s.phtml',
            $this->getTemplateDir(),
            $this->getTemplateSuffix()
        );
    }

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

    public function getStory(): ?StoryblokStory
    {
        if (!$this->getData('story')) {
            $storyRequest = $this->storyRequestService->getStoryRequest($this->getData());

            try {
                if ($slug = $this->getSlug()) {
                    $this->setData('story', $this->storyRepository->getBySlug($slug, $storyRequest));
                } elseif ($this->getRequest()->getParam('story')) {
                    $this->setData('story', $this->getRequest()->getParam('story'));
                }
            } catch (\Exception $e) {
                $this->setData('story', null);
            }
        }

        return $this->getData('story');
    }

    public function getAssetViewModel(): Asset
    {
        return $this->assetViewModel;
    }
}
