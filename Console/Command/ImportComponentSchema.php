<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;
use WindAndKite\Storyblok\Service\ComponentSchemaImporter;
use WindAndKite\Storyblok\Service\StoryblokCliContext;

/**
 * bin/magento storyblok:schema:import [--store] [--path] [--space] [--component=<name>]... [--dry-run] [--yes]
 *                                    [--copy-vendor | --skip-vendor]
 *
 * Brings a space's component schemas (from `storyblok components pull`) into the store's templates, for
 * projects whose schemas were built in the Storyblok UI:
 * - components with no template get a starter template in the theme (`<theme>/WindAndKite_Storyblok/templates/block/`);
 * - untagged templates in the theme or app/code get the @storyblok docblock and IDE type hint (markup is
 *   untouched); templates that already have a schema are left alone unless --overwrite, because the
 *   space can't express shared whitelists or renamed_from;
 * - templates in vendor/ are never edited: they can be copied into the theme first, then updated.
 *
 * Every change is listed and confirmed first (--yes to apply without asking, --dry-run to only list).
 * Afterwards `storyblok:schema:generate` then `storyblok:schema:validate` should report no field differences.
 */
class ImportComponentSchema extends AbstractSchemaCommand
{
    private const OPTION_COMPONENT = 'component';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_YES = 'yes';
    private const OPTION_COPY_VENDOR = 'copy-vendor';
    private const OPTION_SKIP_VENDOR = 'skip-vendor';
    private const OPTION_OVERWRITE = 'overwrite';

    /**
     * @param File $file
     * @param ComponentSchemaImporter $importer
     * @param ComponentSchemaComparer $comparer
     * @param ComponentRegistrar $componentRegistrar
     * @param State $state
     * @param ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ThemeProviderInterface $themeProvider
     * @param StoryblokCliContext $cliContext
     */
    public function __construct(
        private readonly File $file,
        private readonly ComponentSchemaImporter $importer,
        private readonly ComponentSchemaComparer $comparer,
        private readonly ComponentRegistrar $componentRegistrar,
        State $state,
        ObjectManagerInterface $objectManager,
        ConfigLoaderInterface $configLoader,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ThemeProviderInterface $themeProvider,
        StoryblokCliContext $cliContext,
    ) {
        parent::__construct($state, $objectManager, $configLoader, $storeManager, $scopeConfig, $themeProvider, $cliContext);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('storyblok:schema:import')
            ->setDescription('Write a Storyblok space\'s component schemas into the store\'s blok templates (@storyblok docblocks)')
            ->addStoreOption()
            ->addCliOptions()
            ->addOption(self::OPTION_COMPONENT, 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only import these components (repeatable).')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'List the changes without writing anything.')
            ->addOption(self::OPTION_YES, 'y', InputOption::VALUE_NONE, 'Apply every change without asking.')
            ->addOption(self::OPTION_COPY_VENDOR, null, InputOption::VALUE_NONE, 'Copy templates from vendor/ into the theme and update the copies, without asking.')
            ->addOption(self::OPTION_SKIP_VENDOR, null, InputOption::VALUE_NONE, 'Skip templates in vendor/ without asking.')
            ->addOption(self::OPTION_OVERWRITE, null, InputOption::VALUE_NONE, 'Also replace existing @storyblok schemas with the space\'s (loses shared whitelists and renamed_from).');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = $this->cliContext->getBasePath($input->getOption(self::OPTION_PATH));
        $space = $this->cliContext->resolveSpace($input->getOption(self::OPTION_SPACE));

        if (!$space['id']) {
            $output->writeln('<error>No space to import from: pass --space, set ' . StoryblokCliContext::ENV_SPACE_ID . ' or add `space` to storyblok.config.ts.</error>');

            return Command::FAILURE;
        }

        try {
            $items = $this->readPulled($basePath . '/components/' . $space['id'], $this->cliContext->getCliPrefix($basePath), $space['id']);
            $store = $this->getStore($input);
            $theme = $this->getTheme($store);
            $themeDirectory = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme->getFullPath());

            if (!$themeDirectory || str_contains($themeDirectory, '/vendor/')) {
                throw new LocalizedException(__('The store\'s theme (%1) is not in app/design, so new templates can\'t be written to it.', $theme->getFullPath()));
            }

            $blockDirectories = $this->createGenerator()->getBlockDirectories($theme);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $components = $this->comparer->spaceComponents($items);
        $only = $input->getOption(self::OPTION_COMPONENT);

        if ($only) {
            $components = array_intersect_key($components, array_flip($only));
        }

        $folders = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['uuid'], $item['name']) && !isset($item['schema'])) {
                $folders[$item['uuid']] = $item['name'];
            }
        }

        $themeBlockDirectory = $themeDirectory . '/WindAndKite_Storyblok/templates/block';
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        $applyAll = (bool)$input->getOption(self::OPTION_YES);

        if (!$dryRun && !$applyAll && !$input->isInteractive()) {
            $output->writeln('<comment>Non-interactive without --yes: listing changes only.</comment>');
            $dryRun = true;
        }

        $written = 0;

        foreach ($components as $name => $component) {
            $definition = $this->importer->toDefinition($component, $folders);
            $tag = $this->importer->formatTag($definition);
            $template = ComponentSchemaGenerator::templateName($name) . '.phtml';
            $existing = $this->findTemplate($blockDirectories, $template);

            if ($existing !== null && !$input->getOption(self::OPTION_OVERWRITE)
                && str_contains($this->file->fileGetContents($existing), '@storyblok')
            ) {
                $output->writeln(sprintf('%s: already has a schema, skipped (--overwrite to replace it) (%s)', $name, $this->cliContext->toDisplayPath($existing)));
                continue;
            }

            if ($existing === null) {
                [$action, $target, $content] = ['create', $themeBlockDirectory . '/' . $template, $this->importer->newTemplate($name, $definition, $tag)];
            } elseif (str_contains($existing, '/vendor/')) {
                if (!$this->copyVendorTemplate($input, $output, $name, $existing, $themeBlockDirectory . '/' . $template, $dryRun)) {
                    continue;
                }

                [$action, $target, $content] = ['copy from vendor and update', $themeBlockDirectory . '/' . $template, $this->importer->applyToTemplate($this->file->fileGetContents($existing), $tag, $name)];
            } else {
                $current = $this->file->fileGetContents($existing);
                $content = $this->importer->applyToTemplate($current, $tag, $name);

                if ($content === $current) {
                    $output->writeln(sprintf('%s: up to date (%s)', $name, $this->cliContext->toDisplayPath($existing)));
                    continue;
                }

                [$action, $target] = ['update', $existing];
            }

            $output->writeln('');
            $output->writeln(sprintf('<info>%s</info>: %s <comment>%s</comment>', $name, $action, $this->cliContext->toDisplayPath($target)));
            $output->writeln($tag);

            if ($dryRun || !$this->confirm($input, $output, $applyAll, 'Write this change? [y/N] ')) {
                continue;
            }

            $this->file->createDirectory(dirname($target));
            $this->file->filePutContents($target, $content);
            $written++;
        }

        $output->writeln('');
        $output->writeln($dryRun
            ? 'Dry run: nothing was written.'
            : sprintf('%d template(s) written. Check them with `bin/magento storyblok:schema:generate` then `bin/magento storyblok:schema:validate`.', $written));

        return Command::SUCCESS;
    }

    /**
     * Templates in vendor/ are never edited; a copy in the theme can be.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $component
     * @param string $source
     * @param string $target
     * @param bool $dryRun
     *
     * @return bool Whether to copy it.
     */
    private function copyVendorTemplate(
        InputInterface $input,
        OutputInterface $output,
        string $component,
        string $source,
        string $target,
        bool $dryRun,
    ): bool {
        $from = $this->cliContext->toDisplayPath($source);
        $to = $this->cliContext->toDisplayPath($target);

        if ($input->getOption(self::OPTION_SKIP_VENDOR)) {
            $output->writeln(sprintf('%s: skipped, template is in vendor (%s)', $component, $from));

            return false;
        }

        if ($input->getOption(self::OPTION_COPY_VENDOR) || $dryRun) {
            return true;
        }

        if (!$input->isInteractive()) {
            $output->writeln(sprintf('<comment>%s: skipped, template is in vendor (%s). Use --copy-vendor to copy it into the theme.</comment>', $component, $from));

            return false;
        }

        $output->writeln(sprintf('%s: the template is in vendor and won\'t be edited: %s', $component, $from));
        $output->writeln(sprintf('  It can be copied to the theme (%s) and updated there. The copy stops receiving upstream template fixes.', $to));

        return $this->confirm($input, $output, false, 'Copy it into the theme? [y/N] ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param bool $yes
     * @param string $question
     *
     * @return bool
     */
    private function confirm(InputInterface $input, OutputInterface $output, bool $yes, string $question): bool
    {
        return $yes || (bool)$this->getHelper('question')->ask($input, $output, new ConfirmationQuestion($question, false));
    }

    /**
     * @param string[] $blockDirectories
     * @param string $template
     *
     * @return string|null
     */
    private function findTemplate(array $blockDirectories, string $template): ?string
    {
        foreach ($blockDirectories as $directory) {
            if ($this->file->isFile($directory . '/' . $template)) {
                return $directory . '/' . $template;
            }
        }

        return null;
    }

    /**
     * @param string $directory
     * @param string $cli
     * @param string $spaceId
     *
     * @return array All items (components and folders) from the pull.
     * @throws LocalizedException
     */
    private function readPulled(string $directory, string $cli, string $spaceId): array
    {
        if (!$this->file->isDirectory($directory)) {
            throw new LocalizedException(__('Space %1 hasn\'t been pulled: run `%2 components pull --space %1` first.', $spaceId, $cli));
        }

        $items = [];

        foreach ($this->file->readDirectory($directory) as $path) {
            if (str_ends_with($path, '.json') && is_array($data = json_decode($this->file->fileGetContents($path), true))) {
                $items = [...$items, ...($data['components'] ?? (array_is_list($data) ? $data : [$data]))];
            }
        }

        return $items;
    }
}
