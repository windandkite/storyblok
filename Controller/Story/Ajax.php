<?php

declare(strict_types=1);

namespace WindandKite\Storyblok\Controller\Story;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\LayoutInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Block\Story;
use WindAndKite\Storyblok\Model\StoryFactory;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

class Ajax extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private serializerInterface $serializer,
        private JsonFactory $resultJsonFactory,
        private StoryFactory $storyFactory,
        private StoryblokSessionManager $storyblokSessionManager,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json|ResultInterface|ResponseInterface
    {
        // Renders caller-supplied content (including CMS directives), so only for the Visual Editor. Dev mode isn't
        // enough: it may be on for every visitor, and live updates only come from the editor.
        if (!$this->storyblokSessionManager->isValidEditorSession()) {
            return $this->resultJsonFactory->create()->setHttpResponseCode(403)->setData([]);
        }

        $postContent = $this->serializer->unserialize($this->getRequest()->getContent());
        $storyData = $postContent['story'] ?? null;
        $storyData['is_draft'] = true;
        $storyData['force_bridge'] = true;

        $layout = $this->_view->getLayout();
        $result = $this->resultJsonFactory->create();

        $story = $this->storyFactory->create();
        $story->setData($storyData);

        $this->addLayoutHandles($story, $layout);
        // Generate the layout so bloks can use its blocks (e.g. the product list item renderer, the newsletter form).
        $this->_view->loadLayout(null, true, true, false);

        $block = $layout->createBlock(Story::class)->setStory($story);

        return $result->setData([$story['content']['_uid'] => $block->toHtml()]);
    }

    public function addLayoutHandles(
        StoryInterface $story,
        LayoutInterface $layout
    ): void {
        $layout->getUpdate()->addHandle('default');
        $layout->getUpdate()->addHandle('storyblok_index_ajax');
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(
        RequestInterface $request
    ): ?bool {
        return true;
    }
}
