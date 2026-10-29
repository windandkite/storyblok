<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Plugin;

use Magento\Cms\Controller\Index\Index;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\App\RequestInterface;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Controller\Router;

class Home {
    public function __construct(
        protected Router $router,
        protected RequestInterface $request,
        protected Config $scopeConfig
    ) {}

    public function afterExecute(
        Index $subject,
        $result
    ) {
        if (!$this->scopeConfig->isHomeEnabled() || !$this->scopeConfig->getHomeSlug()) {
            return $result;
        }

        if ($result instanceof  Forward) {
            $request = $this->request->setPathInfo($this->scopeConfig->getHomeSlug());
            $storyResult = $this->router->match($request);

            if ($storyResult) {
                return $storyResult;
            }
        }

        return $result;
    }
}
