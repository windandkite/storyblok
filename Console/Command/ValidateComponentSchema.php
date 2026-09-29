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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;
use WindAndKite\Storyblok\Service\ComponentUsageCounter;
use WindAndKite\Storyblok\Service\StoryblokCliContext;

/**
 * bin/magento storyblok:schema:validate [--store] [--path] [--space] [--strict]
 *
 * Compares a space's schema with the schema the store's templates define, and lists missing templates and
 * mismatched fields. The space's schema is read from `<path>/components/<space>/`, written by
 * `storyblok components pull`; the space comes from --space, STORYBLOK_SPACE_ID or storyblok.config.ts.
 * With `storyblok stories pull` files in `<path>/stories/<space>/`, field issues show how much content
 * they affect. Exits non-zero on errors, or on any issue with --strict.
 */
class ValidateComponentSchema extends AbstractSchemaCommand
{
    private const OPTION_STRICT = 'strict';

    /**
     * @param ComponentSchemaComparer $comparer
     * @param ComponentUsageCounter $usageCounter
     * @param File $file
     * @param State $state
     * @param ObjectManagerInterface $objectManager
     * @param ConfigLoaderInterface $configLoader
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ThemeProviderInterface $themeProvider
     * @param StoryblokCliContext $cliContext
     */
    public function __construct(
        private readonly ComponentSchemaComparer $comparer,
        private readonly ComponentUsageCounter $usageCounter,
        private readonly File $file,
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
        $this->setName('storyblok:schema:validate')
            ->setDescription('Compare a pulled Storyblok space schema with the schema defined by a store\'s blok templates')
            ->addStoreOption()
            ->addCliOptions()
            ->addOption(self::OPTION_STRICT, null, InputOption::VALUE_NONE, 'Fail on warnings as well as errors.');
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
            $output->writeln('<error>No space to validate against: pass --space, set ' . StoryblokCliContext::ENV_SPACE_ID . ' or add `space` to storyblok.config.ts.</error>');

            return Command::FAILURE;
        }

        $pulledDirectory = $basePath . '/components/' . $space['id'];

        try {
            $pulled = $this->readPulled($pulledDirectory, $this->cliContext->getCliPrefix($basePath), $space['id']);
            $store = $this->getStore($input);
            $theme = $this->getTheme($store);
            $generator = $this->createGenerator();
            // What a push would write: the templates' schema plus the space settings it keeps.
            $expected = $generator->withSpaceSettings($generator->build($generator->collect($theme)), $this->comparer->spaceComponents($pulled));
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $issues = $this->comparer->compare($expected, $pulled, $generator->getUntagged());
        $issues = $this->usageCounter->annotate($basePath . '/stories/' . $space['id'], $issues) ?? $issues;
        $errors = count(array_filter($issues, static fn ($issue) => $issue['level'] === ComponentSchemaComparer::LEVEL_ERROR));
        $warnings = count($issues) - $errors;

        $output->writeln(sprintf(
            'Store "%s" (theme %s) against space %s (from %s)',
            $store->getCode(),
            $theme->getFullPath(),
            $space['id'],
            $space['source']
        ));

        if ($issues) {
            usort($issues, static fn ($a, $b) => [$a['level'], $a['component'], (string)$a['field']] <=> [$b['level'], $b['component'], (string)$b['field']]);

            $table = new Table($output);
            $table->setHeaders(['Level', 'Component', 'Field', 'Issue']);
            $table->setColumnMaxWidth(3, 90);

            foreach ($issues as $issue) {
                $level = $issue['level'] === ComponentSchemaComparer::LEVEL_ERROR ? '<error>error</error>' : '<comment>warning</comment>';
                $usage = isset($issue['usage']) && $issue['usage']['bloks']
                    ? sprintf(' Used in %d blok(s) across %d story(ies).', $issue['usage']['bloks'], $issue['usage']['stories'])
                    : '';
                $table->addRow([$level, $issue['component'], (string)$issue['field'], $issue['message'] . $usage]);
            }

            $table->render();
        }

        $output->writeln(sprintf(
            '%s: %d error(s), %d warning(s).',
            $issues ? 'Schema mismatches found' : 'Space and templates match',
            $errors,
            $warnings
        ));

        return $errors || ($warnings && $input->getOption(self::OPTION_STRICT)) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param string $directory
     * @param string $cli
     * @param string $spaceId
     *
     * @return array
     * @throws LocalizedException
     */
    private function readPulled(string $directory, string $cli, string $spaceId): array
    {
        return $this->cliContext->readPulledComponents($directory)
            ?? throw new LocalizedException(__('Space %1 hasn\'t been pulled: run `%2 components pull --space %1` first.', $spaceId, $cli));
    }
}
