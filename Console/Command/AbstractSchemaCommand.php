<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;
use WindAndKite\Storyblok\Service\StoryblokCliContext;

/**
 * Shared options for the schema commands: --store (resolves the store's theme and prepares a
 * ComponentSchemaGenerator that sees the same template fallback as the storefront), and --path / --space
 * (locate the Storyblok CLI's files, see StoryblokCliContext).
 */
abstract class AbstractSchemaCommand extends Command
{
    protected const OPTION_STORE = 'store';
    protected const OPTION_PATH = 'path';
    protected const OPTION_SPACE = 'space';

    /**
     * @param State $state
     * @param ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ThemeProviderInterface $themeProvider
     * @param StoryblokCliContext $cliContext
     */
    public function __construct(
        private readonly State $state,
        private readonly ObjectManagerInterface $objectManager,
        private readonly ConfigLoaderInterface $configLoader,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ThemeProviderInterface $themeProvider,
        protected readonly StoryblokCliContext $cliContext,
    ) {
        parent::__construct();
    }

    /**
     * @return $this
     */
    protected function addCliOptions(): static
    {
        return $this
            ->addOption(
                self::OPTION_PATH,
                null,
                InputOption::VALUE_REQUIRED,
                'Storyblok CLI base directory (the CLI\'s --path), relative to the Magento root or absolute.',
                StoryblokCliContext::DEFAULT_PATH
            )
            ->addOption(
                self::OPTION_SPACE,
                null,
                InputOption::VALUE_REQUIRED,
                'Space ID to compare against. Defaults to ' . StoryblokCliContext::ENV_SPACE_ID
                . ', then the space in storyblok.config.ts.'
            );
    }

    /**
     * @return $this
     */
    protected function addStoreOption(): static
    {
        return $this->addOption(
            self::OPTION_STORE,
            's',
            InputOption::VALUE_REQUIRED,
            'Store view ID or code whose theme is used. Defaults to the default store view.'
        );
    }

    /**
     * @param InputInterface $input
     *
     * @return StoreInterface
     * @throws LocalizedException
     */
    protected function getStore(InputInterface $input): StoreInterface
    {
        $store = $input->getOption(self::OPTION_STORE);

        try {
            return $store !== null ? $this->storeManager->getStore($store) : $this->storeManager->getDefaultStoreView();
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Store "%1" not found.', $store));
        }
    }

    /**
     * @param StoreInterface $store
     *
     * @return ThemeInterface
     * @throws LocalizedException
     */
    protected function getTheme(StoreInterface $store): ThemeInterface
    {
        $themeId = $this->scopeConfig->getValue(DesignInterface::XML_PATH_THEME_ID, ScopeInterface::SCOPE_STORE, $store->getId());
        $theme = $themeId ? $this->themeProvider->getThemeById((int)$themeId) : null;

        if (!$theme || !$theme->getId()) {
            throw new LocalizedException(__('No theme configured for store "%1".', $store->getCode()));
        }

        return $theme;
    }

    /**
     * Switch to the frontend area first: the template fallback, including the compatibility-module
     * plugin, is configured there.
     *
     * @return ComponentSchemaGenerator
     */
    protected function createGenerator(): ComponentSchemaGenerator
    {
        try {
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException) {
            // Area already set by another command in this process.
        }

        $this->objectManager->configure($this->configLoader->load(Area::AREA_FRONTEND));

        return $this->objectManager->create(ComponentSchemaGenerator::class);
    }
}
