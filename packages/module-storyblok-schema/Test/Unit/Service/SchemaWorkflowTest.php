<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokSchema\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Design\Fallback\RulePool;
use PHPUnit\Framework\TestCase;
use WindAndKite\StoryblokSchema\Service\ComponentMigrationBuilder;
use WindAndKite\StoryblokSchema\Service\ComponentSchemaComparer;
use WindAndKite\StoryblokSchema\Service\ComponentSchemaGenerator;
use WindAndKite\StoryblokSchema\Service\ComponentUsageCounter;
use WindAndKite\StoryblokSchema\Service\StoryblokCliContext;

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
            'span_tablet' => ['type' => 'number', 'tab' => 'Responsive', 'group' => 'Tablet'],
            'span_desktop' => ['type' => 'number', 'tab' => 'Responsive', 'group' => 'Desktop'],
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
            'span_desktop' => ['type' => 'number', 'enum' => [6, 12], 'default' => 12],
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
        $this->assertStringContainsString('copy(block, "body", "content");', $files['page.renames.js']);
        $this->assertStringContainsString('copy(block, "intro", "lead");', $files['page.renames.js']);
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
        file_put_contents($this->root . '/stories/b.json', json_encode(['uuid' => 'b', 'content' => ['_uid' => '4', 'component' => 'section', 'padding' => 'xl', 'legacy__i18n__de' => 'y', 'legacy__i18n__fr' => 'z']]));

        $issues = [
            ['kind' => '', 'component' => 'section', 'field' => 'padding', 'values' => ['xl']],
            ['kind' => '', 'component' => 'section', 'field' => 'legacy', 'values' => []],
            ['kind' => '', 'component' => 'section', 'field' => null, 'values' => []],
            ['kind' => '', 'component' => 'section', 'field' => 'sticky', 'values' => []],
            ['kind' => ComponentSchemaComparer::KIND_FIELD_SETTINGS, 'component' => 'section', 'field' => 'padding', 'values' => ['label']],
            ['kind' => ComponentSchemaComparer::KIND_WHITELIST_UNRESTRICTED, 'component' => 'page', 'field' => 'body', 'values' => ['section']],
            ['kind' => ComponentSchemaComparer::KIND_WHITELIST_UNRESTRICTED, 'component' => 'page', 'field' => 'body', 'values' => ['hero']],
        ];
        $annotated = (new ComponentUsageCounter(new File()))->annotate($this->root . '/stories', $issues);

        $this->assertSame(['stories' => 2, 'bloks' => 2], $annotated[0]['usage']);
        $this->assertSame(['stories' => 2, 'bloks' => 2], $annotated[1]['usage'], 'translations count, once per blok; empty values don\'t');
        $this->assertArrayNotHasKey('usage', $annotated[2]);
        $this->assertSame(['stories' => 1, 'bloks' => 1], $annotated[3]['usage'], 'a stored false is content');
        $this->assertSame(['stories' => 2, 'bloks' => 3], $annotated[4]['usage'], 'settings names aren\'t content values');
        $this->assertSame(['stories' => 0, 'bloks' => 0], $annotated[5]['usage'], 'every child is still allowed');
        $this->assertSame(['stories' => 1, 'bloks' => 1], $annotated[6]['usage'], 'children outside the new whitelist');
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
        // The old field fits the new one (an incompatible rename is covered in ComponentSchemaComparerTest).
        $pulled = [['name' => 'page', 'schema' => [
            'body' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['page']],
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

    public function testVerbatimFieldsAreComparedInFullAndKeptInTheSafeSchema(): void
    {
        $final = $this->build(['hero' => ['fields' => [
            'colour' => ['storyblok' => ['type' => 'custom', 'field_type' => 'new-picker', 'options' => [['name' => 'a', 'value' => '1']]]],
        ]]]);
        $spaceField = ['type' => 'custom', 'field_type' => 'old-picker', 'options' => [['value' => '1', 'name' => 'a']], 'id' => 'x', 'pos' => 3];
        $space = [['name' => 'hero', 'schema' => ['colour' => $spaceField]]];

        $issues = (new ComponentSchemaComparer())->compare($final, $space);
        // Options match in any key order; another plugin is a type change, not a setting.
        $this->assertSame([ComponentSchemaComparer::KIND_TYPE_CHANGED], array_column($issues, 'kind'));

        $space[0]['schema']['colour']['field_type'] = 'new-picker';
        $this->assertSame([], (new ComponentSchemaComparer())->compare($final, $space), 'id and pos aren\'t settings');

        $safe = $this->generator()->buildSafe($final, [['name' => 'hero', 'schema' => ['colour' => $spaceField]]]);
        $this->assertSame('old-picker', $safe['components'][0]['schema']['colour']['field_type'], 'pushed at the final push only');
    }

    public function testSafeSchemaNeverRestrictsEditors(): void
    {
        $final = $this->build([
            'hero' => ['root' => true, 'fields' => [
                'img' => ['type' => 'asset'],
                'kids' => ['type' => 'array', 'allowed' => ['hero']],
                'title' => ['type' => 'string', 'max_length' => '10', 'regex' => '^[a-z]*$'],
                'sub' => ['type' => 'string', 'required' => true],
            ]],
        ]);
        $space = [['name' => 'hero', 'is_root' => false, 'is_nestable' => true, 'schema' => [
            'img' => ['type' => 'asset', 'filetypes' => []],
            'kids' => ['type' => 'bloks', 'restrict_components' => true, 'restrict_type' => 'groups', 'component_group_whitelist' => ['g'], 'component_whitelist' => []],
            'title' => ['type' => 'text', 'translatable' => true, 'max_length' => '100'],
        ]]];

        $safe = $this->generator()->buildSafe($final, $space)['components'][0];

        $this->assertFalse($safe['is_root']);
        $this->assertTrue($safe['is_nestable'], 'where the blok can be used stays');
        $this->assertArrayNotHasKey('filetypes', $safe['schema']['img'], 'any file type stays any');
        $this->assertSame('groups', $safe['schema']['kids']['restrict_type']);
        $this->assertSame(['g'], $safe['schema']['kids']['component_group_whitelist']);
        $this->assertSame([], $safe['schema']['kids']['component_whitelist']);
        $this->assertTrue($safe['schema']['title']['translatable']);
        $this->assertSame('100', $safe['schema']['title']['max_length'], 'other Storyblok settings wait for the final push');
        $this->assertArrayNotHasKey('regex', $safe['schema']['title'], 'including ones the space doesn\'t have');
        $this->assertArrayNotHasKey('required', $safe['schema']['sub'], 'new fields aren\'t required yet');
    }

    public function testNumericNamesAndIdeNames(): void
    {
        $schema = $this->build(['404' => ['fields' => ['1' => ['type' => 'string']]]]);

        $this->assertSame('404', $schema['components'][0]['name']);
        $this->assertStringContainsString('class _404Blok', $this->generator()->buildIdeHelper(['404' => ['file' => '404.phtml', 'definition' => ['fields' => []]]]));
        $this->assertSame('_3_Column_GridBlok', ComponentSchemaGenerator::ideClassName('3_column_grid'));
        $this->assertSame('Form_InputBlok', ComponentSchemaGenerator::ideClassName('form-input'));
        $this->assertNotSame(
            strtolower(ComponentSchemaGenerator::ideClassName('foo_bar')),
            strtolower(ComponentSchemaGenerator::ideClassName('foobar')),
            'class names are case-insensitive'
        );
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

    public function testEveryFieldTypeAndStoryblokSettings(): void
    {
        $schema = $this->build(['card' => ['fields' => [
            'title' => ['type' => 'string', 'max_length' => '60', 'regex' => '^[a-z]*$'],
            'sizes' => ['type' => 'string', 'enum' => ['s', 'l'], 'multiple' => true, 'max_options' => '1'],
            'colour' => ['type' => 'string', 'source' => 'internal', 'datasource_slug' => 'colours'],
            'related' => ['type' => 'story', 'multiple' => true],
            'gallery' => ['type' => 'asset', 'multiple' => true],
            'copy' => ['type' => 'richtext', 'allowed' => ['card']],
            'link' => ['type' => 'multilink', 'show_anchor' => true],
            'kids' => ['type' => 'array', 'maximum' => '3'],
        ]]])['components'][0]['schema'];

        $this->assertSame(['type' => 'text', 'display_name' => 'Title', 'max_length' => '60', 'regex' => '^[a-z]*$', 'pos' => 0], $schema['title'], 'other keys are Storyblok settings');
        $this->assertSame(['options', '1'], [$schema['sizes']['type'], $schema['sizes']['max_options']]);
        $this->assertSame(['option', 'internal', 'colours'], [$schema['colour']['type'], $schema['colour']['source'], $schema['colour']['datasource_slug']]);
        $this->assertSame(['options', 'internal_stories'], [$schema['related']['type'], $schema['related']['source']]);
        $this->assertSame('multiasset', $schema['gallery']['type']);
        $this->assertSame([true, ['card']], [$schema['copy']['restrict_components'], $schema['copy']['component_whitelist']]);
        $this->assertSame(['type' => 'multilink', 'display_name' => 'Link', 'show_anchor' => true, 'pos' => 6], $schema['link'], 'link types off unless set');

        // A template's setting is compared with the space; one only the space has is kept by a push.
        $space = [['name' => 'card', 'schema' => [
            'title' => ['type' => 'text', 'max_length' => '80', 'regex' => '^[a-z]*$', 'tooltip' => true],
        ]]];
        $final = $this->build(['card' => ['fields' => ['title' => ['type' => 'string', 'max_length' => '60', 'regex' => '^[a-z]*$']]]]);
        $issues = (new ComponentSchemaComparer())->compare($this->generator()->withSpaceSettings($final, $space), $space);
        $this->assertSame([['max length']], array_column($issues, 'values'));

        // Bloks inside rich text count for whitelist changes.
        mkdir($this->root . '/stories');
        $doc = ['type' => 'doc', 'content' => [['type' => 'blok', 'attrs' => ['body' => [['_uid' => '2', 'component' => 'banner']]]]]];
        file_put_contents($this->root . '/stories/a.json', json_encode(['uuid' => 'a', 'content' => ['_uid' => '1', 'component' => 'card', 'copy' => $doc]]));
        $issues = (new ComponentUsageCounter(new File()))->annotate($this->root . '/stories', [
            ['kind' => ComponentSchemaComparer::KIND_WHITELIST_REMOVED, 'component' => 'card', 'field' => 'copy', 'values' => ['banner']],
            ['kind' => ComponentSchemaComparer::KIND_WHITELIST_UNRESTRICTED, 'component' => 'card', 'field' => 'copy', 'values' => ['banner']],
        ]);
        $this->assertSame([1, 0], [$issues[0]['usage']['bloks'], $issues[1]['usage']['bloks']]);
    }

    public function testZeroFieldNamesMultiOptionUsageAndPackagesOutsideTheRoot(): void
    {
        $final = $this->build(['page' => ['fields' => ['1' => ['type' => 'string', 'renamed_from' => '0']]]]);
        $this->assertSame([['component' => 'page', 'from' => '0', 'to' => '1']], $final['renames']);

        mkdir($this->root . '/stories');
        file_put_contents($this->root . '/stories/a.json', json_encode(['uuid' => 'a', 'content' => ['_uid' => '1', 'component' => 'card', 'colours' => ['red', 'blue']]]));
        $issues = (new ComponentUsageCounter(new File()))->annotate($this->root . '/stories', [
            ['kind' => ComponentSchemaComparer::KIND_OPTION_VALUES_REMOVED, 'component' => 'card', 'field' => 'colours', 'values' => ['blue']],
            ['kind' => ComponentSchemaComparer::KIND_OPTION_VALUES_REMOVED, 'component' => 'card', 'field' => 'colours', 'values' => ['green']],
        ]);
        $this->assertSame(['stories' => 1, 'bloks' => 1], $issues[0]['usage'], 'multi-option selections are lists of values');
        $this->assertSame(['stories' => 0, 'bloks' => 0], $issues[1]['usage']);

        // A Composer path repository's checkout, outside the Magento root.
        $outside = sys_get_temp_dir() . '/sb-outside-' . uniqid();
        mkdir($outside);
        touch($outside . '/a.phtml');
        mkdir($this->root . '/app');
        touch($this->root . '/app/b.phtml');

        $this->assertTrue($this->context()->isVendorPath($outside . '/a.phtml'));
        $this->assertFalse($this->context()->isVendorPath($this->root . '/app/b.phtml'));
        unlink($outside . '/a.phtml');
        rmdir($outside);
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
