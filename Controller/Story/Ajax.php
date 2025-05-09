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

class Ajax extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private serializerInterface $serializer,
        private JsonFactory $resultJsonFactory,
        private StoryFactory $storyFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json|ResultInterface|ResponseInterface
    {
        $postContent = $this->serializer->unserialize($this->getRequest()->getContent());
        $storyData = $postContent['story'] ?? null;
        $storyData['is_draft'] = true;

        $layout = $this->_view->getLayout();
        $result = $this->resultJsonFactory->create();

        $story = $this->storyFactory->create();
        $story->setData($storyData);

        $this->addLayoutHandles($story, $layout);

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
