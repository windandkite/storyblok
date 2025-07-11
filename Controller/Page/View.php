<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Controller\Page;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use WindAndKite\Storyblok\Api\Data\StoryInterface;

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
        private UrlInterface $urlBuilder,
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

        $this->addPageHandles($page, $story);
        $this->addPageMeta($page, $story);
        $this->addBodyClasses($page, $story);
        $this->setBreadcrumbs($page, $story);

        return $page;
    }

    public function AddPageHandles(
        Page $page,
        StoryInterface $story,
    ): void {
        $slug = trim($story->getFullSlug(), '/');
        $slugParts = explode('/', $slug);
        $slugPath = '';

        foreach ($slugParts as $part) {
            $processedPart = strtolower(str_replace('-', '_', $part));
            $slugPath .= ($slugPath ? '_' : '') . $processedPart;
            $page->addPageLayoutHandles(['slug' => $slugPath], null, false);
        }

        $fullSlugProcessed = strtolower(str_replace(['-', '/'], '_', $slug));
        $page->addPageLayoutHandles(['slug' => $fullSlugProcessed . '_index'], null, false);

        $page->addPageLayoutHandles(['id' => $story->getId()]);
        $page->addPageLayoutHandles(['type' => $story->getContent()['component']]);

        if ($story->getIsStartPage()) {
            $page->addHandle('storyblok_page_view_startpage');
        }
    }

    public function addPageMeta(
        Page $page,
        StoryInterface $story,
    ): void {
        $page->getConfig()->getTitle()->set($story->getMetaTitle());
        $metaDescription = $story->getMetaDescription();

        if ($metaDescription) {
            $page->getConfig()->setDescription($metaDescription);
        }
    }

    public function addBodyClasses(
        Page $page,
        StoryInterface $story,
    ): void {
        $slug = trim($story->getFullSlug(), '/');
        $fullSlugProcessed = strtolower(str_replace(['_', '/'], '-', $slug));

        $page->getConfig()->addBodyClass('storyblok-' . $fullSlugProcessed);
    }

    public function setBreadcrumbs(
        Page $page,
        StoryInterface $story,
    ): void {
        $slug = rtrim($story->getFullSlug(), '/');
        $paths = explode('/', $slug);

        $breadcrumbsBlock = $page->getLayout()->getBlock('breadcrumbs');

        if (!$breadcrumbsBlock) {
            return;
        }

        $breadcrumbsBlock->addCrumb(
            'home',
            [
                'label' => __('Home'),
                'title' => __('Home'),
                'link'  => $this->urlBuilder->getUrl('')
            ]
        );

        $cumulativePath = '';

        foreach ($paths as $path) {
            $cumulativePath .= ($cumulativePath ? '/' : '') . $path;
            $url = $path == end($paths) ? null : $this->urlBuilder->getUrl($cumulativePath);
            $label = ucwords(str_replace('-', ' ', $path));
            $breadcrumbsBlock->addCrumb(
                $path,
                [
                    'label' => __($label),
                    'title' => __($label),
                    'link'  => $url
                ]
            );
        }
    }
}
