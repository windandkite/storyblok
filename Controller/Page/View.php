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
use WindAndKite\Storyblok\ViewModel\PageRenderer;

/**
 * Controller for the 'storyblok/page/view' URL route.
 */
class View implements HttpGetActionInterface
{
    public const BLOCK_NAME = 'storyblok.content';
    public const DATA_KEY_STORY = 'story';
    public const DATA_KEY_PAGE_RENDERER = 'page_renderer';

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
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
        $page->getConfig()->getTitle()->set($story['name']);
        $page->getLayout()
            ->getBlock(self::BLOCK_NAME)
            ->setData(self::DATA_KEY_STORY, $story);

        return $page;
    }
}
