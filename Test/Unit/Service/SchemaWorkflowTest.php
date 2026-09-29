<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Design\Fallback\RulePool;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Service\ComponentMigrationBuilder;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;
use WindAndKite\Storyblok\Service\ComponentUsageCounter;
use WindAndKite\Storyblok\Service\StoryblokCliContext;

/**
 * Tabs/groups, renames, safe schema, IDE helper, migrations, space resolution and usage counts.
 */
class SchemaWorkflowTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sb-workflow-' . uniqid();
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testTabsAndGroupsUseStoryblokFormat(): void
    {
        $schema = $this->build(['column' => ['folder' => 'Layout', 'fields' => [
            'body' => ['type' => 'array'],
            'span_tablet' => ['type' => 'integer', 'tab' => 'Responsive', 'group' => 'Tablet'],
            'span_desktop' => ['type' => 'integer', 'tab' => 'Responsive', 'group' => 'Desktop'],
            'advanced' => ['type' => 'string', 'group' => 'Advanced'],
            'debug' => ['type' => 'boolean', 'group' => 'Advanced'],
        ]]])['components'][0];

        $fields = $schema['schema'];
        $tabKey = array_values(array_filter(array_keys($fields), static fn ($key) => str_starts_with($key, 'tab-')))[0];

        $this->assertSame('Layout', $schema['component_group_name']);
        $this->assertMatchesRegularExpression('/^tab-[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/', $tabKey);
        $this->assertSame(['type' => 'tab', 'display_name' => 'Responsive', 'keys' => ['Tablet', 'span_tablet', 'Desktop', 'span_desktop'], 'pos' => 1], $fields[$tabKey]);
        $this->assertSame(['type' => 'section', 'keys' => ['span_tablet'], 'pos' => 2], $fields['Tablet']);
        $this->assertSame(['type' => 'section', 'keys' => ['advanced', 'debug'], 'pos' => 6], $fields['Advanced'], 'a group outside any tab keeps all its fields');
        $this->assertSame([0, 3, 5, 7], [$fields['body']['pos'], $fields['span_tablet']['pos'], $fields['span_desktop']['pos'], $fields['advanced']['pos']]);
        $this->assertArrayNotHasKey('tab', $fields['span_tablet']);
        $this->assertSame($tabKey, array_keys($this->build(['column' => ['fields' => ['x' => ['type' => 'string', 'tab' => 'Responsive']]]])['components'][0]['schema'])[0], 'stable tab key per component and tab');
    }

    public function testGroupClashesFail(): void
    {
        foreach ([
            ['title' => ['type' => 'string'], 'subtitle' => ['type' => 'string', 'group' => 'title']],
            ['a' => ['type' => 'string', 'tab' => 'One', 'group' => 'Mobile'], 'b' => ['type' => 'string', 'tab' => 'Two', 'group' => 'Mobile']],
        ] as $fields) {
            try {
                $this->build(['x' => ['fields' => $fields]]);
                $this->fail('Expected a clash error');
            } catch (LocalizedException $e) {
                $this->assertStringContainsString('x.phtml', $e->getMessage());
            }
        }

        $this->expectExceptionMessage('Use "folder"');
        $this->build(['x' => ['group' => 'Layout', 'fields' => []]]);
    }

    public function testRenamesAndSafeSchema(): void
    {
        $final = $this->build(['page' => ['fields' => [
            'content' => ['type' => 'array', 'renamed_from' => 'body'],
            'size' => ['type' => 'string', 'enum' => ['small', 'large']],
            'image' => ['type' => 'asset'],
            'story' => ['type' => 'string', 'enum' => ['a', 'b']],
            'photo' => ['type' => 'asset', 'required' => true],
            'related' => ['type' => 'story', 'folder' => 'blog/'],
        ]]]);

        $this->assertSame([['component' => 'page', 'from' => 'body', 'to' => 'content']], $final['renames']);
        $this->assertArrayNotHasKey('renamed_from', $final['components'][0]['schema']['content']);

        $space = [['name' => 'page', 'schema' => [
            'body' => ['type' => 'bloks', 'pos' => 0, 'id' => 'abc'],
            'size' => ['type' => 'option', 'options' => [['name' => 'Small', 'value' => 'small'], ['name' => 'XL', 'value' => 'xl']]],
            'image' => ['type' => 'text', 'pos' => 2],
            'legacy' => ['type' => 'text'],
            'tab-old' => ['type' => 'tab', 'keys' => ['legacy']],
            'story' => ['type' => 'option', 'source' => 'internal_stories'],
            'photo' => ['type' => 'asset', 'filetypes' => ['images', 'videos']],
            'related' => ['type' => 'option', 'source' => 'internal_stories'],
        ]]];

        $safe = $this->generator()->buildSafe($final, $space)['components'][0]['schema'];

        $this->assertSame('Deprecated: renamed to "content".', $safe['body']['description']);
        $this->assertArrayNotHasKey('id', $safe['body']);
        $this->assertArrayHasKey('legacy', $safe, 'fields only in the space are kept');
        $this->assertArrayNotHasKey('tab-old', $safe, 'the space layout is replaced');
        $this->assertSame(['small', 'large', 'xl'], array_column($safe['size']['options'], 'value'), 'option values merged');
        $this->assertSame('text', $safe['image']['type'], 'type changes keep the space type');
        $this->assertSame('internal_stories', $safe['story']['source'], 'option source changes keep the space field');
        $this->assertArrayNotHasKey('options', $safe['story']);
        $this->assertArrayNotHasKey('required', $safe['photo'], 'required only once the final schema is pushed');
        $this->assertSame(['images', 'videos'], $safe['photo']['filetypes'], 'file types merged');
        $this->assertArrayNotHasKey('folder_slug', $safe['related'], 'the picker stays unrestricted');
        $this->assertSame('array', $this->build(['page' => ['fields' => ['content' => ['type' => 'array']]]])['components'][0]['schema']['content']['type'] === 'bloks' ? 'array' : 'x');
    }

    public function testIdeHelper(): void
    {
        $source = $this->generator()->buildIdeHelper(['section' => ['file' => '/t/section.phtml', 'definition' => ['fields' => [
            'body' => ['type' => 'array'],
            'span_desktop' => ['type' => 'integer', 'enum' => [6, 12], 'default' => 12],
            'above_the_fold' => ['type' => 'boolean', 'description' => 'LCP */ image'],
        ]]]]);

        $this->assertStringContainsString('namespace WindAndKite\\Storyblok\\Ide\\Block;', $source);
        $this->assertStringContainsString('class SectionBlok extends \\WindAndKite\\Storyblok\\Block\\Block', $source);
        $this->assertStringContainsString('@method array getBody() Child bloks', $source);
        $this->assertStringContainsString('@method string getBodyHtml()', $source);
        $this->assertStringContainsString('@method string|null getSpanDesktop() Numeric string. "6" | "12". default 12', $source);
        $this->assertStringContainsString('@method bool|null getAboveTheFold() LCP * / image', $source, 'a "*/" in a description cannot close the docblock');

        // Throws \ParseError if the generated source isn't valid PHP.
        token_get_all($source, TOKEN_PARSE);
    }

    public function testMigrationsAreIdempotentCopies(): void
    {
        $files = (new ComponentMigrationBuilder())->build([
            ['component' => 'page', 'from' => 'body', 'to' => 'content'],
            ['component' => 'page', 'from' => 'intro', 'to' => 'lead'],
        ]);

        $this->assertSame(['page.renames.js'], array_keys($files));
        $this->assertStringContainsString(ComponentMigrationBuilder::MARKER, $files['page.renames.js']);
        $this->assertStringContainsString('if (isEmpty(block["content"]) && !isEmpty(block["body"])) {', $files['page.renames.js']);
        $this->assertStringContainsString('block["lead"] = block["intro"];', $files['page.renames.js']);
        $this->assertStringNotContainsString('delete', $files['page.renames.js'], 'copy, never move');
    }

    public function testSpaceResolution(): void
    {
        $context = $this->context();
        putenv(StoryblokCliContext::ENV_SPACE_ID);

        $this->assertSame(['id' => '42', 'source' => '--space'], $context->resolveSpace('42'));
        $this->assertSame(['id' => null, 'source' => null], $context->resolveSpace(null));

        file_put_contents($this->root . '/storyblok.config.ts', "export default defineConfig({\n    space: \"285467077178149\",\n});\n");
        $this->assertSame(['id' => '285467077178149', 'source' => 'storyblok.config.ts'], $context->resolveSpace(null));

        putenv(StoryblokCliContext::ENV_SPACE_ID . '=777');
        $this->assertSame(['id' => '777', 'source' => StoryblokCliContext::ENV_SPACE_ID], $context->resolveSpace(null));
        putenv(StoryblokCliContext::ENV_SPACE_ID);

        $this->assertSame($this->root . '/.storyblok', $context->getBasePath(null));
        $this->assertSame('storyblok', $context->getCliPrefix($this->root . '/.storyblok'));
        $this->assertSame('storyblok --path var/sb', $context->getCliPrefix($this->root . '/var/sb'));
    }

    public function testUsageCounts(): void
    {
        mkdir($this->root . '/stories');
        file_put_contents($this->root . '/stories/a.json', json_encode(['uuid' => 'a', 'content' => ['_uid' => '1', 'component' => 'page', 'body' => [
            ['_uid' => '2', 'component' => 'section', 'padding' => 'xl', 'legacy' => 'x'],
            ['_uid' => '3', 'component' => 'section', 'padding' => 'small', 'legacy' => '', 'sticky' => false],
        ]]]));
        file_put_contents($this->root . '/stories/b.json', json_encode(['uuid' => 'b', 'content' => ['_uid' => '4', 'component' => 'section', 'padding' => 'xl']]));

        $issues = [
            ['component' => 'section', 'field' => 'padding', 'values' => ['xl']],
            ['component' => 'section', 'field' => 'legacy', 'values' => []],
            ['component' => 'section', 'field' => null, 'values' => []],
            ['component' => 'section', 'field' => 'sticky', 'values' => []],
        ];
        $annotated = (new ComponentUsageCounter(new File()))->annotate($this->root . '/stories', $issues);

        $this->assertSame(['stories' => 2, 'bloks' => 2], $annotated[0]['usage']);
        $this->assertSame(['stories' => 1, 'bloks' => 1], $annotated[1]['usage'], 'empty values are not usage');
        $this->assertArrayNotHasKey('usage', $annotated[2]);
        $this->assertSame(['stories' => 1, 'bloks' => 1], $annotated[3]['usage'], 'a stored false is content');
        $this->assertNull((new ComponentUsageCounter(new File()))->annotate($this->root . '/missing', $issues));

        // Zero usage is only reported when stories were actually read.
        mkdir($this->root . '/empty');
        file_put_contents($this->root . '/empty/folder.json', json_encode(['uuid' => 'f', 'is_folder' => true, 'content' => null]));
        $this->assertNull((new ComponentUsageCounter(new File()))->annotate($this->root . '/empty', $issues), 'no stories');

        file_put_contents($this->root . '/stories/broken.json', '{"uuid": "c", "content": ');
        $this->assertNull((new ComponentUsageCounter(new File()))->annotate($this->root . '/stories', $issues), 'invalid story file');
    }

    public function testPulledComponentsMustBeValidJson(): void
    {
        $context = $this->context();
        mkdir($this->root . '/pull');
        file_put_contents($this->root . '/pull/groups.json', json_encode([['name' => 'Layout', 'uuid' => 'g']]));
        file_put_contents($this->root . '/pull/components.json', json_encode([['name' => 'page', 'schema' => []]]));

        $this->assertNull($context->readPulledComponents($this->root . '/missing'));
        $this->assertCount(2, $context->readPulledComponents($this->root . '/pull'));

        file_put_contents($this->root . '/pull/v3.json', json_encode(['components' => [['name' => 'x', 'schema' => []]]]));

        try {
            $context->readPulledComponents($this->root . '/pull');
            $this->fail('A CLI v3 pull is rejected');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('Storyblok CLI v3', $e->getMessage());
        }

        unlink($this->root . '/pull/v3.json');
        file_put_contents($this->root . '/pull/section.json', '{"name": "section", ');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is not valid JSON');
        $context->readPulledComponents($this->root . '/pull');
    }

    public function testComparerKindsAndDestructiveChanges(): void
    {
        $expected = ['components' => [['name' => 'page', 'schema' => [
            'content' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['page']],
            'Mobile' => ['type' => 'section', 'keys' => ['content']],
        ]]], 'renames' => [['component' => 'page', 'from' => 'body', 'to' => 'content']]];
        $pulled = [['name' => 'page', 'schema' => [
            'body' => ['type' => 'bloks'],
            'content' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['page']],
            'legacy' => ['type' => 'text'],
        ]]];

        $comparer = new ComponentSchemaComparer();
        $issues = $comparer->compare($expected, $pulled);
        $kinds = [];

        foreach ($issues as $issue) {
            $kinds[(string)$issue['field']] = $issue['kind'];
        }

        $this->assertSame(ComponentSchemaComparer::KIND_FIELD_RENAMED, $kinds['body']);
        $this->assertSame(ComponentSchemaComparer::KIND_FIELD_REMOVED, $kinds['legacy']);
        $this->assertContains(ComponentSchemaComparer::KIND_LAYOUT, array_column($issues, 'kind'));
        $this->assertSame(['legacy'], array_column(array_filter($issues, [$comparer, 'isDestructive']), 'field'), 'renames and layout are not destructive');

        // Old field not in the space (e.g. already removed): the new field still needs pushing.
        unset($pulled[0]['schema']['body'], $pulled[0]['schema']['content']);
        $this->assertContains(
            [ComponentSchemaComparer::KIND_FIELD_NOT_PUSHED, 'content'],
            array_map(static fn ($issue) => [$issue['kind'], $issue['field']], $comparer->compare($expected, $pulled))
        );
    }

    public function testSpaceSettingsTheFormatCantExpressAreKept(): void
    {
        $final = $this->build(['hero' => ['fields' => [
            'title' => ['type' => 'string'],
            'link' => ['type' => 'multilink'],
            'raw' => ['storyblok' => ['type' => 'text']],
            'body' => ['type' => 'richtext'],
            'lang' => ['type' => 'string', 'translatable' => false],
        ]]]);
        $space = [['name' => 'hero', 'preview_field' => 'title', 'schema' => [
            'title' => ['type' => 'text', 'id' => 'x', 'translatable' => true, 'max_length' => '60', 'required' => true],
            'link' => ['type' => 'multilink', 'email_link_type' => false],
            'raw' => ['type' => 'text', 'regex' => '^a'],
            'body' => ['type' => 'textarea', 'rtl' => true],
            'lang' => ['type' => 'text', 'translatable' => true],
        ]]];

        $merged = $this->generator()->withSpaceSettings($final, $space)['components'][0];
        $schema = $merged['schema'];

        $this->assertSame('title', $merged['preview_field']);
        $this->assertTrue($schema['title']['translatable'], 'not declared: kept from the space');
        $this->assertSame('60', $schema['title']['max_length']);
        $this->assertArrayNotHasKey('required', $schema['title'], 'owned by the template');
        $this->assertArrayNotHasKey('id', $schema['title']);
        $this->assertFalse($schema['link']['email_link_type'], 'multilink options the format can\'t set are kept');
        $this->assertArrayNotHasKey('regex', $schema['raw'], 'verbatim fields are used exactly as written');
        $this->assertArrayNotHasKey('rtl', $schema['body'], 'only merged when the type matches');
        $this->assertFalse($schema['lang']['translatable'], 'declared: the template wins');
        $this->assertSame([], (new ComponentSchemaComparer())->compare(
            $this->generator()->withSpaceSettings($this->build(['hero' => ['fields' => ['title' => ['type' => 'string', 'required' => true]]]]), $space),
            [['name' => 'hero', 'preview_field' => 'title', 'schema' => ['title' => $space[0]['schema']['title']]]]
        ), 'kept settings aren\'t reported');
    }

    public function testSafeSchemaNeverRestrictsEditors(): void
    {
        $final = $this->build([
            'hero' => ['root' => true, 'fields' => [
                'img' => ['type' => 'asset'],
                'kids' => ['type' => 'array', 'allowed' => ['hero']],
                'title' => ['type' => 'string'],
                'sub' => ['type' => 'string', 'required' => true],
            ]],
        ]);
        $space = [['name' => 'hero', 'is_root' => false, 'is_nestable' => true, 'schema' => [
            'img' => ['type' => 'asset', 'filetypes' => []],
            'kids' => ['type' => 'bloks', 'restrict_components' => true, 'restrict_type' => 'groups', 'component_group_whitelist' => ['g'], 'component_whitelist' => []],
            'title' => ['type' => 'text', 'translatable' => true],
        ]]];

        $safe = $this->generator()->buildSafe($final, $space)['components'][0];

        $this->assertFalse($safe['is_root']);
        $this->assertTrue($safe['is_nestable'], 'where the blok can be used stays');
        $this->assertArrayNotHasKey('filetypes', $safe['schema']['img'], 'any file type stays any');
        $this->assertSame('groups', $safe['schema']['kids']['restrict_type']);
        $this->assertSame(['g'], $safe['schema']['kids']['component_group_whitelist']);
        $this->assertSame([], $safe['schema']['kids']['component_whitelist']);
        $this->assertTrue($safe['schema']['title']['translatable']);
        $this->assertArrayNotHasKey('required', $safe['schema']['sub'], 'new fields aren\'t required yet');
    }

    public function testNumericNamesAndIdeNames(): void
    {
        $schema = $this->build(['404' => ['fields' => ['1' => ['type' => 'string']]]]);

        $this->assertSame('404', $schema['components'][0]['name']);
        $this->assertSame('_3ColumnGridBlok', ComponentSchemaGenerator::ideClassName('3_column_grid'));
        $this->assertSame('FormInputBlok', ComponentSchemaGenerator::ideClassName('form-input'));
        $this->assertSame([], (new ComponentSchemaComparer())->compare($schema, [['name' => '404', 'schema' => ['1' => ['type' => 'text', 'display_name' => '1']]]]));

        $helper = $this->generator()->buildIdeHelper(['hero' => ['file' => 'hero.phtml', 'definition' => ['fields' => [
            'image_2x' => ['type' => 'asset'],
            'fooBar' => ['type' => 'string'],
        ]]]]);
        token_get_all($helper, TOKEN_PARSE);
        $this->assertStringContainsString('getImage2x()', $helper);
        $this->assertStringNotContainsString('getFooBar()', $helper, 'Magento would read "foo_bar"');
        $this->assertStringContainsString("getData('fooBar')", $helper);
    }

    private function build(array $definitions): array
    {
        $components = [];

        foreach ($definitions as $name => $definition) {
            $components[$name] = ['file' => '/theme/block/' . $name . '.phtml', 'definition' => $definition];
        }

        return $this->generator()->build($components);
    }

    private function generator(): ComponentSchemaGenerator
    {
        return new ComponentSchemaGenerator($this->createStub(RulePool::class), new File());
    }

    private function context(): StoryblokCliContext
    {
        $directory = $this->createStub(ReadInterface::class);
        $directory->method('getAbsolutePath')->willReturn($this->root . '/');
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($directory);

        return new StoryblokCliContext($filesystem, new File());
    }

    public function testChangedComponentsListsOnlyRealDifferences(): void
    {
        $expected = ['components' => [
            ['name' => 'same', 'display_name' => 'Same', 'component_group_name' => 'Layout', 'schema' => [
                'title' => ['type' => 'text', 'display_name' => 'Title', 'pos' => 0],
                'show' => ['type' => 'boolean', 'display_name' => 'Show', 'default_value' => false, 'pos' => 1],
            ]],
            ['name' => 'relabelled', 'display_name' => 'Relabelled', 'schema' => [
                'title' => ['type' => 'text', 'display_name' => 'Heading', 'description' => 'New help', 'pos' => 0],
                'size' => ['type' => 'option', 'options' => [['name' => 'Big', 'value' => 'l']], 'display_name' => 'Size', 'pos' => 1],
            ]],
            ['name' => 'reordered', 'display_name' => 'Reordered', 'schema' => [
                'b' => ['type' => 'text', 'display_name' => 'B', 'pos' => 0],
                'a' => ['type' => 'text', 'display_name' => 'A', 'pos' => 1],
            ]],
            ['name' => 'brand_new', 'display_name' => 'Brand new', 'schema' => []],
        ]];
        $pulled = [
            ['name' => 'Layout', 'uuid' => 'folder-1'],
            // Storyblok extras (ids, unset values as empty strings / false) must not count as changes.
            ['name' => 'same', 'display_name' => 'Same', 'component_group_uuid' => 'folder-1', 'description' => '', 'schema' => [
                'title' => ['type' => 'text', 'display_name' => 'Title', 'pos' => 0, 'id' => 'x', 'required' => false, 'description' => ''],
                'show' => ['type' => 'boolean', 'display_name' => 'Show', 'default_value' => false, 'pos' => 1, 'id' => 'y'],
            ]],
            ['name' => 'relabelled', 'display_name' => 'Relabelled', 'schema' => [
                'title' => ['type' => 'text', 'display_name' => 'Title', 'pos' => 0],
                'size' => ['type' => 'option', 'options' => [['name' => 'Large', 'value' => 'l']], 'display_name' => 'Size', 'pos' => 1],
            ]],
            ['name' => 'reordered', 'display_name' => 'Reordered', 'schema' => [
                'a' => ['type' => 'text', 'display_name' => 'A', 'pos' => 0],
                'b' => ['type' => 'text', 'display_name' => 'B', 'pos' => 1],
            ]],
            ['name' => 'space_only', 'schema' => []],
        ];

        $comparer = new ComponentSchemaComparer();
        $changed = $comparer->changedComponents($comparer->compare($expected, $pulled));

        $this->assertSame(['brand_new', 'relabelled', 'reordered'], array_keys($changed), 'unchanged and space-only components are left out');
        $this->assertSame(['new component'], $changed['brand_new']);
        $this->assertSame(['title: label, description changed', 'size: option labels changed'], $changed['relabelled']);
        $this->assertSame(['field order changed'], $changed['reordered']);
    }
}
