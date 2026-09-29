<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Design\Fallback\Rule\RuleInterface;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\View\Design\ThemeInterface;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;

class ComponentSchemaGeneratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sb-schema-' . uniqid();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testFallbackOrderOverridesAndWhitelists(): void
    {
        // Highest priority first, as the template fallback returns them.
        $theme = $this->template('theme', 'section', ['fields' => ['body' => ['type' => 'array', 'allowed' => ['@content']], 'extra' => ['type' => 'boolean']]]);
        $this->template('theme', 'text', null); // Visual override without a tag: falls through.
        $compat = $this->template('compat', 'text', ['whitelists' => ['content'], 'fields' => ['copy' => ['type' => 'richtext']]]);
        $this->template('compat', 'teaser', ['whitelists' => ['content'], 'fields' => ['post' => ['type' => 'story', 'folder' => 'blog/']]]);
        $core = $this->template('core', 'section', ['fields' => ['body' => ['type' => 'array']]]); // Replaced by the theme.
        $this->template('core', 'fallback', null);
        $this->template('core', 'untagged', null);

        $generator = $this->generator([dirname($theme, 2), dirname($compat, 2), dirname($core, 2)]);
        $components = $generator->collect($this->createStub(ThemeInterface::class));

        $this->assertSame(['section', 'teaser', 'text'], array_keys($components));
        $this->assertSame($theme, $components['section']['file']);
        $this->assertSame($compat, $components['text']['file']);
        $this->assertCount(1, $generator->getWarnings());
        $this->assertStringContainsString('"untagged"', $generator->getWarnings()[0]);

        $byName = array_column($generator->build($components)['components'], null, 'name');

        $this->assertSame(['teaser', 'text'], $byName['section']['schema']['body']['component_whitelist']);
        $this->assertArrayHasKey('extra', $byName['section']['schema']);
        $this->assertSame(
            ['type' => 'option', 'source' => 'internal_stories', 'folder_slug' => 'blog/', 'display_name' => 'Post', 'pos' => 0],
            $byName['teaser']['schema']['post']
        );
    }

    public function testCliFormatLinksGroupsByStableUuid(): void
    {
        $schema = [
            'components' => [
                ['name' => 'section', 'component_group_name' => 'Layout', 'schema' => []],
                ['name' => 'text', 'schema' => []],
            ],
            'component_groups' => [['name' => 'Layout', 'parent_id' => null], ['name' => 'Media', 'parent_id' => null]],
        ];
        $generator = $this->generator([]);

        $items = $generator->formatForCli($schema);

        $this->assertSame('Layout', $items[0]['name']);
        $this->assertArrayNotHasKey('schema', $items[0]);
        $this->assertMatchesRegularExpression('/^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/', $items[0]['uuid']);
        $this->assertNotSame($items[0]['id'], $items[1]['id'], 'groups need unique ids or the CLI merges them');
        $this->assertNotSame($items[0]['uuid'], $items[1]['uuid']);
        $this->assertSame($items[0]['uuid'], $items[2]['component_group_uuid']);
        $this->assertArrayNotHasKey('component_group_name', $items[2]);
        $this->assertArrayNotHasKey('component_group_uuid', $items[3]);
        $this->assertSame($items, $generator->formatForCli($schema), 'stable across runs');
    }

    public function testParseErrorsNameTheFile(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('/path/bad.phtml');

        $this->generator([])->parse("<?php\n/**\n * @storyblok { \"fields\": \n */", '/path/bad.phtml');
    }

    public function testOtherTagsMayFollowTheSchema(): void
    {
        $source = "<?php\n/**\n * Section.\n *\n * @storyblok {\n *   \"fields\": {\"body\": {\"type\": \"array\"}}\n * }\n *\n * @var \\stdClass \$block\n */";

        $this->assertSame(['fields' => ['body' => ['type' => 'array']]], $this->generator([])->parse($source, 'x.phtml'));
    }

    public function testOnlyTheFirstDocblockCounts(): void
    {
        $source = "<?php\n/** @var \\stdClass \$block */\n/**\n * @storyblok {\"fields\": {}}\n */";

        $this->assertNull($this->generator([])->parse($source, 'x.phtml'));
    }

    public function testUnknownAllowedComponentFails(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown component "missing"');

        $this->generator([])->build(['a' => ['file' => 'a.phtml', 'definition' => ['fields' => ['b' => ['type' => 'array', 'allowed' => ['missing']]]]]]);
    }

    public function testNameMustRenderThisTemplate(): void
    {
        $this->template('theme', 'form_input', ['name' => 'form-input', 'fields' => []]);
        $this->assertArrayHasKey('form-input', $this->generator([$this->root . '/theme'])->collect($this->createStub(ThemeInterface::class)));

        $this->template('other', 'foo', ['name' => 'bar', 'fields' => []]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is rendered by block/bar.phtml');
        $this->generator([$this->root . '/other'])->collect($this->createStub(ThemeInterface::class));
    }

    public function testEnumLabels(): void
    {
        $schema = $this->generator([])->build(['size' => ['file' => 'size.phtml', 'definition' => ['fields' => [
            'size' => ['enum' => ['small', 'xl'], 'labels' => ['xl' => 'Extra Large']],
            'span' => ['type' => 'integer', 'enum' => [0, 1], 'labels' => ['0' => 'None']],
        ]]]])['components'][0]['schema'];

        $this->assertSame([['name' => 'Small', 'value' => 'small'], ['name' => 'Extra Large', 'value' => 'xl']], $schema['size']['options']);
        $this->assertSame([['name' => 'None', 'value' => '0'], ['name' => '1', 'value' => '1']], $schema['span']['options']);
        $this->assertArrayNotHasKey('labels', $schema['size']);
    }

    private function template(string $directory, string $name, ?array $definition): string
    {
        $path = $this->root . '/' . $directory . '/block/' . $name . '.phtml';
        @mkdir(dirname($path), 0777, true);
        $tag = $definition === null ? '' : "\n * @storyblok " . json_encode($definition);
        file_put_contents($path, "<?php\n/**\n * " . $name . $tag . "\n */\n?>\n<div></div>\n");

        return $path;
    }

    private function generator(array $directories): ComponentSchemaGenerator
    {
        $rule = $this->createStub(RuleInterface::class);
        $rule->method('getPatternDirs')->willReturn($directories);
        $rulePool = $this->createStub(RulePool::class);
        $rulePool->method('getRule')->willReturn($rule);

        return new ComponentSchemaGenerator($rulePool, new File());
    }
}
