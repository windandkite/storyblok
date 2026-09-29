<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
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
use WindAndKite\Storyblok\Service\ComponentMigrationBuilder;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;
use WindAndKite\Storyblok\Service\ComponentUsageCounter;
use WindAndKite\Storyblok\Service\StoryblokCliContext;

/**
 * bin/magento storyblok:schema:generate [--store] [--path] [--space] [--force] [--no-ide-helper] [--changed-only]
 *
 * Writes files for Storyblok CLI v4 (`storyblok components push`).
 *
 * Generates, from the @storyblok docblocks of the blok templates the store's theme renders:
 * - the final schema, `<path>/components/<store_code>/`: exactly what the templates define, pushed at deploy;
 * - the safe schema, `<path>/components/<store_code>-safe/`, when it differs: additive only, safe to push any
 *   time (e.g. during development) because nothing the space has is removed or changed;
 * - copy migrations for renamed fields, `<path>/migrations/<store_code>/<component>.renames.js`;
 * - an IDE helper with typed @method stubs per blok, `<path>/ide-helper.php`;
 * - with --changed-only, `<path>/components/<store_code>-changes/`: only the components that differ from the
 *   space, for reviewing and pushing just those. The full final schema is still written as normal.
 *
 * With a pulled space (`storyblok components pull`, space from --space / STORYBLOK_SPACE_ID /
 * storyblok.config.ts), changes that would orphan stored content are listed first, with usage counts
 * from `storyblok stories pull` when available, and need confirmation (or --force).
 */
class GenerateComponentSchema extends AbstractSchemaCommand
{
    private const OPTION_FORCE = 'force';
    private const OPTION_NO_IDE_HELPER = 'no-ide-helper';
    private const OPTION_CHANGED_ONLY = 'changed-only';
    private const IDE_HELPER_FILE = 'ide-helper.php';

    /**
     * @param File $file
     * @param ComponentSchemaComparer $comparer
     * @param ComponentUsageCounter $usageCounter
     * @param ComponentMigrationBuilder $migrationBuilder
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
        private readonly ComponentSchemaComparer $comparer,
        private readonly ComponentUsageCounter $usageCounter,
        private readonly ComponentMigrationBuilder $migrationBuilder,
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
        $this->setName('storyblok:schema:generate')
            ->setDescription('Generate the Storyblok component schema, migrations and IDE helper from a store\'s blok templates')
            ->addStoreOption()
            ->addCliOptions()
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Write the final schema even if it would orphan stored content, without asking.')
            ->addOption(self::OPTION_NO_IDE_HELPER, null, InputOption::VALUE_NONE, 'Don\'t write the IDE helper.')
            ->addOption(self::OPTION_CHANGED_ONLY, null, InputOption::VALUE_NONE, 'Also write <store_code>-changes/: only the components that differ from the pulled space.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $store = $this->getStore($input);
            $theme = $this->getTheme($store);
            $generator = $this->createGenerator();
            $components = $generator->collect($theme);
            $schema = $generator->build($components);
            $basePath = $this->cliContext->getBasePath($input->getOption(self::OPTION_PATH));
            $space = $this->cliContext->resolveSpace($input->getOption(self::OPTION_SPACE));
            $spaceComponents = $space['id'] ? $this->cliContext->readPulledComponents($basePath . '/components/' . $space['id']) : null;

            if ($spaceComponents !== null) {
                // Keep space settings the templates can't express, so the push doesn't drop them.
                $schema = $generator->withSpaceSettings($schema, $this->comparer->spaceComponents($spaceComponents));
            }

            $finalFile = $generator->formatForCli($schema);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        foreach ($generator->getWarnings() as $warning) {
            $output->writeln('<comment>' . $warning . '</comment>');
        }

        $storeCode = $store->getCode();

        if ($space['id'] && $spaceComponents === null) {
            $output->writeln(sprintf(
                '<comment>Space %s (from %s) hasn\'t been pulled: run `%s components pull --space %s` to check changes against it.</comment>',
                $space['id'],
                $space['source'],
                $this->cliContext->getCliPrefix($basePath),
                $space['id']
            ));
        } elseif (!$space['id']) {
            $output->writeln('<comment>No space to compare against (--space, ' . StoryblokCliContext::ENV_SPACE_ID . ' or storyblok.config.ts): changes are not checked and no safe schema is written.</comment>');
        }

        $issues = $spaceComponents !== null ? $this->comparer->compare($schema, $spaceComponents, $generator->getUntagged()) : [];

        if ($spaceComponents !== null && !$this->confirmChanges($input, $output, $issues, $basePath . '/stories/' . $space['id'], $space['id'])) {
            return Command::FAILURE;
        }

        $finalPath = $basePath . '/components/' . $storeCode . '/components.json';
        $safePath = $basePath . '/components/' . $storeCode . '-safe/components.json';

        $this->writeJson($finalPath, $finalFile);
        $output->writeln(sprintf(
            '<info>Wrote %d components for store "%s" (theme %s) to %s</info>',
            count($schema['components']),
            $storeCode,
            $theme->getFullPath(),
            $this->cliContext->toDisplayPath($finalPath)
        ));

        $safeWritten = false;

        if ($spaceComponents !== null) {
            $safeFile = $generator->formatForCli($generator->buildSafe($schema, $spaceComponents));

            if ($safeFile !== $finalFile) {
                $this->writeJson($safePath, $safeFile);
                $safeWritten = true;
                $output->writeln('<info>Wrote the safe (additive) schema to ' . $this->cliContext->toDisplayPath($safePath) . '</info>');
            } else {
                $this->removeStale($safePath);
            }
        } else {
            // Nothing to be additive against: an earlier safe schema would be stale.
            $this->removeStale($safePath);
        }

        $changesWritten = $input->getOption(self::OPTION_CHANGED_ONLY)
            && $this->writeChanges($output, $spaceComponents, $issues, $finalFile, $basePath, $storeCode);

        $migrations = $this->migrationBuilder->build($schema['renames']);
        $this->writeMigrations($basePath . '/migrations/' . $storeCode, $migrations);

        foreach (array_keys($migrations) as $migration) {
            $output->writeln('<info>Wrote migration ' . $this->cliContext->toDisplayPath($basePath . '/migrations/' . $storeCode . '/' . $migration) . '</info>');
        }

        if (!$input->getOption(self::OPTION_NO_IDE_HELPER)) {
            $this->file->createDirectory($basePath);
            $this->file->filePutContents($basePath . '/' . self::IDE_HELPER_FILE, $generator->buildIdeHelper($components));
            $output->writeln('<info>Wrote the IDE helper to ' . $this->cliContext->toDisplayPath($basePath . '/' . self::IDE_HELPER_FILE) . '</info>');
        }

        $this->printNextSteps($output, $basePath, $storeCode, $safeWritten, (bool)$migrations, $changesWritten, $space);

        return Command::SUCCESS;
    }

    /**
     * List changes that would orphan stored content, and ask before writing when content is affected.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $issues From ComponentSchemaComparer::compare().
     * @param string $storiesDirectory
     * @param string $spaceId
     *
     * @return bool Whether to continue.
     */
    private function confirmChanges(
        InputInterface $input,
        OutputInterface $output,
        array $issues,
        string $storiesDirectory,
        string $spaceId,
    ): bool {
        $annotated = $this->usageCounter->annotate($storiesDirectory, $issues);
        $issues = $annotated ?? $issues;

        $renames = array_filter($issues, static fn ($issue) => $issue['kind'] === ComponentSchemaComparer::KIND_FIELD_RENAMED);
        $destructive = array_values(array_filter($issues, fn ($issue) => $this->comparer->isDestructive($issue)));

        if ($renames) {
            $output->writeln('<info>Renamed fields (copied by the generated migration):</info>');

            foreach ($renames as $issue) {
                $output->writeln(sprintf('  %s.%s: %s%s', $issue['component'], $issue['field'], $issue['message'], $this->formatUsage($issue)));
            }
        }

        if (!$destructive) {
            return true;
        }

        $output->writeln('');
        $output->writeln('<comment>These changes would orphan content already in space ' . $spaceId . ' once the final schema is pushed.</comment>');
        $output->writeln('<comment>Storyblok keeps the data (editors see "out of schema" and can restore it), but templates and editors stop using it.</comment>');

        foreach ($destructive as $issue) {
            $output->writeln(sprintf(
                '  - %s%s: %s%s',
                $issue['component'],
                $issue['field'] !== null ? '.' . $issue['field'] : '',
                $issue['message'],
                $this->formatUsage($issue)
            ));
        }

        if ($annotated === null) {
            $output->writeln('<comment>Content usage unknown: run `storyblok stories pull` to count affected stories.</comment>');
        }

        // Nothing stored uses the affected fields or values: report, but don't ask.
        $affectsContent = $annotated === null || array_filter(
            $destructive,
            static fn ($issue) => ($issue['usage']['bloks'] ?? 1) > 0
        );

        if (!$affectsContent || $input->getOption(self::OPTION_FORCE)) {
            return true;
        }

        if (!$input->isInteractive()) {
            $output->writeln('<error>Stored content is affected: re-run with --force to write the final schema anyway. Nothing was written.</error>');

            return false;
        }

        $question = new ConfirmationQuestion('Write the final schema anyway? [y/N] ', false);

        if ($this->getHelper('question')->ask($input, $output, $question)) {
            return true;
        }

        $output->writeln('Nothing was written.');

        return false;
    }

    /**
     * @param array $issue
     *
     * @return string
     */
    private function formatUsage(array $issue): string
    {
        if (!isset($issue['usage'])) {
            return '';
        }

        return $issue['usage']['bloks']
            ? sprintf(' (used in %d blok(s) across %d story(ies))', $issue['usage']['bloks'], $issue['usage']['stories'])
            : ' (no stored content uses it)';
    }

    /**
     * @param OutputInterface $output
     * @param string $basePath
     * @param string $storeCode
     * @param bool $safeWritten
     * @param bool $hasMigrations
     * @param bool $changesWritten
     * @param array{id: string|null, source: string|null} $space
     *
     * @return void
     */
    private function printNextSteps(
        OutputInterface $output,
        string $basePath,
        string $storeCode,
        bool $safeWritten,
        bool $hasMigrations,
        bool $changesWritten,
        array $space,
    ): void {
        $cli = $this->cliContext->getCliPrefix($basePath);
        // The CLI reads the space from storyblok.config.* itself; any other source has to be passed on.
        $spaceOption = $space['id'] && !str_starts_with((string)$space['source'], 'storyblok.config.') ? ' --space ' . $space['id'] : '';

        $output->writeln('');
        $output->writeln('Next steps (from the Magento root):');

        if ($changesWritten) {
            $output->writeln(sprintf('  Changed components only (review it first): %s components push --from %s-changes%s', $cli, $storeCode, $spaceOption));
        }

        if ($safeWritten) {
            $output->writeln(sprintf('  During development (additive, safe any time): %s components push --from %s-safe%s', $cli, $storeCode, $spaceOption));
        }

        $output->writeln(sprintf('  At deploy, after the templates are live: %s components push --from %s%s', $cli, $storeCode, $spaceOption));

        if ($hasMigrations) {
            $output->writeln(sprintf('  Then copy renamed content: %s migrations run --from %s%s --dry-run (then without --dry-run)', $cli, $storeCode, $spaceOption));
            $output->writeln('  Renamed fields are copied, not moved: templates should read the new name with a fallback to the old one.');
        }
    }

    /**
     * Write only the components that differ from the space (plus the folders they use), and list why.
     *
     * @param OutputInterface $output
     * @param array|null $spaceComponents
     * @param array $issues
     * @param array $finalFile The final schema in the CLI format being written.
     * @param string $basePath
     * @param string $storeCode
     *
     * @return bool Whether a file was written.
     */
    private function writeChanges(
        OutputInterface $output,
        ?array $spaceComponents,
        array $issues,
        array $finalFile,
        string $basePath,
        string $storeCode,
    ): bool {
        $path = $basePath . '/components/' . $storeCode . '-changes/components.json';

        $this->removeStale($path);

        if ($spaceComponents === null) {
            $output->writeln('<comment>--changed-only needs a pulled space to compare against: no changes file was written.</comment>');

            return false;
        }

        $changed = $this->comparer->changedComponents($issues);

        if (!$changed) {
            $output->writeln('<info>No components differ from the space: no changes file was written.</info>');

            return false;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%d component(s) differ from the space:</info>', count($changed)));

        foreach ($changed as $name => $reasons) {
            $output->writeln(sprintf('  %s: %s', $name, implode('; ', $reasons)));
        }

        $this->writeJson($path, $this->subset($finalFile, $changed));
        $output->writeln('<info>Wrote the changed components to ' . $this->cliContext->toDisplayPath($path) . '</info>');

        return true;
    }

    /**
     * @param array $items
     * @param array $changed
     *
     * @return array
     */
    private function subset(array $items, array $changed): array
    {
        $components = array_values(array_filter($items, static fn ($item) => isset($item['schema'], $changed[$item['name']])));
        $folders = array_flip(array_filter(array_column($components, 'component_group_uuid')));

        return [
            ...array_values(array_filter($items, static fn ($item) => !isset($item['schema']) && isset($folders[$item['uuid'] ?? '']))),
            ...$components,
        ];
    }


    /**
     * Write the generated migrations and remove generated ones whose rename no longer exists.
     * Hand-written migrations (without the generator's marker) are never touched.
     *
     * @param string $directory
     * @param array<string, string> $migrations
     *
     * @return void
     */
    private function writeMigrations(string $directory, array $migrations): void
    {
        if ($this->file->isDirectory($directory)) {
            foreach ($this->file->readDirectory($directory) as $path) {
                if (str_ends_with($path, ComponentMigrationBuilder::FILE_SUFFIX)
                    && !isset($migrations[basename($path)])
                    && str_contains($this->file->fileGetContents($path), ComponentMigrationBuilder::MARKER)
                ) {
                    $this->file->deleteFile($path);
                }
            }
        }

        if (!$migrations) {
            return;
        }

        $this->file->createDirectory($directory);

        foreach ($migrations as $name => $source) {
            $this->file->filePutContents($directory . '/' . $name, $source);
        }
    }

    /**
     * Remove a generated file that no longer applies, and its folder once empty.
     *
     * @param string $path
     *
     * @return void
     */
    private function removeStale(string $path): void
    {
        if ($this->file->isFile($path)) {
            $this->file->deleteFile($path);
        }

        $directory = dirname($path);

        if (basename(dirname($directory)) === 'components' && $this->file->isDirectory($directory) && !$this->file->readDirectory($directory)) {
            $this->file->deleteDirectory($directory);
        }
    }

    /**
     * @param string $path
     * @param array $items A formatForCli() item list.
     *
     * @return void
     */
    private function writeJson(string $path, array $items): void
    {
        $this->file->createDirectory(dirname($path));
        $this->file->filePutContents($path, ComponentSchemaGenerator::toJson($items));
    }
}
