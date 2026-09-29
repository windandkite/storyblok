<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Locates the Storyblok CLI's working files the same way the CLI does: a base path (default
 * `.storyblok` in the Magento root) containing `components/<space>/`, `stories/<space>/` and
 * `migrations/<space>/`.
 *
 * The space ID comes from, in order: the --space option, the STORYBLOK_SPACE_ID environment variable,
 * or a literal `space: "<id>"` in storyblok.config.(ts|js|mjs|cjs) in the Magento root.
 */
class StoryblokCliContext
{
    public const DEFAULT_PATH = '.storyblok';
    public const ENV_SPACE_ID = 'STORYBLOK_SPACE_ID';

    private const CONFIG_FILES = ['storyblok.config.ts', 'storyblok.config.js', 'storyblok.config.mjs', 'storyblok.config.cjs'];

    /**
     * @param Filesystem $filesystem
     * @param File $file
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly File $file,
    ) {}

    /**
     * Absolute base path; relative values are resolved from the Magento root.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function getBasePath(?string $path): string
    {
        $path = rtrim($path ?: self::DEFAULT_PATH, '/');

        return str_starts_with($path, '/') ? $path : $this->getRoot() . $path;
    }

    /**
     * @param string|null $space --space option value.
     *
     * @return array{id: string|null, source: string|null}
     */
    public function resolveSpace(?string $space): array
    {
        if ($space !== null && $space !== '') {
            return ['id' => $space, 'source' => '--space'];
        }

        $env = getenv(self::ENV_SPACE_ID);

        if (is_string($env) && $env !== '') {
            return ['id' => $env, 'source' => self::ENV_SPACE_ID];
        }

        foreach (self::CONFIG_FILES as $configFile) {
            $path = $this->getRoot() . $configFile;

            // Only a literal value can be read here; process.env references are resolved by Node, not PHP.
            if ($this->file->isFile($path)
                && preg_match('/\bspace\s*:\s*[\'"]?(\d+)[\'"]?/', $this->file->fileGetContents($path), $matches)
            ) {
                return ['id' => $matches[1], 'source' => $configFile];
            }
        }

        return ['id' => null, 'source' => null];
    }

    /**
     * Items (components and folders) from a `storyblok components pull` folder.
     *
     * @param string $directory
     *
     * @return array|null Null when the folder doesn't exist.
     * @throws LocalizedException When a file isn't valid JSON or there are no components: a partial or failed
     *         pull must never pass for a complete one.
     */
    public function readPulledComponents(string $directory): ?array
    {
        if (!$this->file->isDirectory($directory)) {
            return null;
        }

        $items = [];

        foreach ($this->file->readDirectory($directory) as $path) {
            if (!str_ends_with($path, '.json')) {
                continue;
            }

            try {
                $data = json_decode($this->file->fileGetContents($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new LocalizedException(__('%1 is not valid JSON (%2): pull the space again.', $path, $e->getMessage()));
            }

            if (is_array($data) && isset($data['components'])) {
                throw new LocalizedException(__('%1 was written by Storyblok CLI v3: pull the space with CLI v4 (`storyblok components pull`).', $path));
            }

            if (is_array($data)) {
                // One list per file, or one component per file (`--separate-files`).
                $items = [...$items, ...(array_is_list($data) ? $data : [$data])];
            }
        }

        if (!array_filter($items, static fn ($item) => is_array($item) && isset($item['name'], $item['schema']))) {
            throw new LocalizedException(__('No components found in %1: pull the space again.', $directory));
        }

        return $items;
    }

    /**
     * Whether a file is inside the Magento root's vendor/ directory, following symlinks either way (a
     * Composer path repository links vendor/ to a directory elsewhere).
     *
     * @param string $path
     *
     * @return bool
     */
    public function isVendorPath(string $path): bool
    {
        // ponytail: assumes Composer's default vendor-dir; read composer.json "config.vendor-dir" if a project changes it.
        $vendor = $this->getRoot() . 'vendor/';
        $realVendor = realpath($vendor);
        $realPath = realpath($path);

        return str_starts_with($path, $vendor)
            || ($realVendor !== false && $realPath !== false && str_starts_with($realPath, $realVendor . '/'));
    }

    /**
     * Path relative to the Magento root when inside it, for printing CLI commands.
     *
     * @param string $path
     *
     * @return string
     */
    public function toDisplayPath(string $path): string
    {
        return str_starts_with($path, $this->getRoot()) ? substr($path, strlen($this->getRoot())) : $path;
    }

    /**
     * The `storyblok` command prefix: --path is a global option and must come before the subcommand.
     *
     * @param string $basePath
     *
     * @return string
     */
    public function getCliPrefix(string $basePath): string
    {
        $display = $this->toDisplayPath($basePath);

        return $display === self::DEFAULT_PATH ? 'storyblok' : 'storyblok --path ' . $display;
    }

    /**
     * @return string Magento root with a trailing slash.
     */
    private function getRoot(): string
    {
        return rtrim($this->filesystem->getDirectoryRead(DirectoryList::ROOT)->getAbsolutePath(), '/') . '/';
    }
}
