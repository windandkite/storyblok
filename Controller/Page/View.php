<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Controller\Page;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Controller for the 'storyblok/page/view' URL route.
 */
class View implements HttpGetActionInterface
{
    public const BLOCK_NAME = 'storyblok.content';
    public const DATA_KEY_STORY = 'story';

    public function __construct(
        private PageFactory $pageFactory,
        private RequestInterface $request,
    ) {}

    /**
     * Execute controller action.
     *
     * @throws NotFoundException
     */
    public function execute(): Page|ResultInterface|ResponseInterface
    {
        $story = $this->request->getParam('story');

        if (!$story || !$story->getId()) {
            throw new NotFoundException(__('Story not found.'));
        }

        $page = $this->pageFactory->create();
        $slug = trim($story->getFullSlug(), '/');
        $slugParts = explode('/', $slug);
        $slugPath = '';

        foreach ($slugParts as $part) {
            $processedPart = strtolower(str_replace('-', '_', $part));
            $slugPath .= ($slugPath ? '_' : '') . $processedPart;
            $page->addPageLayoutHandles(['slug' => $slugPath], null , false);
        }

        $page->addPageLayoutHandles(['id' => $story->getId()]);

        $page->getConfig()->getTitle()->set($story['name']);


        $slugPath = '';
        foreach ($slugParts as $part) {
            $processedPart = strtolower(str_replace('-', '_', $part));
            $slugPath .= ($slugPath ? '_' : '') . $processedPart;
            $page->getConfig()->addBodyClass('storyblok-' . $slugPath);
        }

        return $page;
    }
}
