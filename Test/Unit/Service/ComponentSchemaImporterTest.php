<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Design\Fallback\RulePool;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;
use WindAndKite\Storyblok\Service\ComponentSchemaGenerator;
use WindAndKite\Storyblok\Service\ComponentSchemaImporter;

class ComponentSchemaImporterTest extends TestCase
{
    /**
     * Shape of a component pulled with `storyblok components pull` after adding a tab and groups in the UI.
     */
    private const COLUMN = [
        'name' => 'column',
        'display_name' => 'Column',
        'component_group_uuid' => 'folder-uuid',
        'is_root' => false,
        'schema' => [
            'Tablet' => ['type' => 'section', 'pos' => 0, 'keys' => ['span_tablet'], 'id' => 'a'],
            'Desktop' => ['type' => 'section', 'pos' => 1, 'keys' => ['span_desktop'], 'id' => 'b'],
            'span_desktop' => ['type' => 'option', 'options' => [['name' => '6', 'value' => '6'], ['name' => '12', 'value' => '12']], 'pos' => 2, 'display_name' => 'Span desktop', 'default_value' => '12', 'id' => 'c'],
            'span_tablet' => ['type' => 'option', 'options' => [['name' => '4', 'value' => '4'], ['name' => '8', 'value' => '8']], 'pos' => 3, 'display_name' => 'Span tablet', 'default_value' => '8', 'id' => 'd'],
            'body' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['column'], 'pos' => 5, 'display_name' => 'Body', 'description' => 'Child bloks', 'id' => 'e'],
            'colour' => ['type' => 'custom', 'field_type' => 'native-color-picker', 'options' => [], 'pos' => 6, 'id' => 'f'],
            'tab-9f07b3b2-2348-46c4-ae3e-7c164647f8bb' => ['display_name' => 'Responsive', 'keys' => ['span_tablet', 'span_desktop', 'Tablet', 'Desktop'], 'pos' => 7, 'type' => 'tab', 'id' => 'g'],
        ],
    ];

    public function testConvertsPulledComponent(): void
    {
        $definition = (new ComponentSchemaImporter())->toDefinition(self::COLUMN, ['folder-uuid' => 'Layout']);

        $this->assertSame('Layout', $definition['folder']);
        $this->assertArrayNotHasKey('name', $definition, 'no override when the name matches the template file');
        $this->assertSame(['span_desktop', 'span_tablet', 'body', 'colour'], array_keys($definition['fields']), 'ordered by pos, layout entries removed');
        $this->assertSame(['type' => 'integer', 'enum' => [6, 12], 'default' => 12, 'tab' => 'Responsive', 'group' => 'Desktop'], $definition['fields']['span_desktop']);
        $this->assertSame(['type' => 'array', 'allowed' => ['column'], 'description' => 'Child bloks'], $definition['fields']['body']);
        $this->assertSame(['storyblok' => ['type' => 'custom', 'field_type' => 'native-color-picker', 'options' => []]], $definition['fields']['colour'], 'unsupported types kept verbatim');
        $this->assertSame('form-input', (new ComponentSchemaImporter())->toDefinition(['name' => 'form-input', 'schema' => []])['name']);
    }

    public function testRoundTripThroughTheGenerator(): void
    {
        $importer = new ComponentSchemaImporter();
        $definition = $importer->toDefinition(self::COLUMN, ['folder-uuid' => 'Layout']);

        // Through the docblock and back, as `import` then `generate` would.
        $source = $importer->applyToTemplate("<?php\n/** @var \\WindAndKite\\Storyblok\\Block\\Block \$block */\n?>\n<div></div>\n", $importer->formatTag($definition), 'column');
        $generator = new ComponentSchemaGenerator($this->createStub(RulePool::class), new File());
        $parsed = $generator->parse($source, 'column.phtml');
        $schema = $generator->build(['column' => ['file' => 'column.phtml', 'definition' => $parsed]]);

        $issues = (new ComponentSchemaComparer())->compare($schema, [self::COLUMN, ['name' => 'Layout', 'uuid' => 'folder-uuid']]);

        $this->assertSame([], $issues, 'no field or layout differences after a round trip');
    }

    public function testAppliesTagToTemplates(): void
    {
        $importer = new ComponentSchemaImporter();
        $tag = $importer->formatTag(['fields' => ['title' => ['type' => 'string']]]);

        // Header docblock without a tag: appended, markup untouched.
        $header = "<?php\n/**\n * Hero.\n */\n\ndeclare(strict_types=1);\n\n/** @var \\WindAndKite\\Storyblok\\Block\\Block \$block */\n?>\n<section>x</section>\n";
        $updated = $importer->applyToTemplate($header, $tag, 'hero');
        $this->assertStringContainsString(" * Hero.\n *\n * @storyblok {", $updated);
        $this->assertStringContainsString('@var \\WindAndKite\\Storyblok\\Block\\Block|\\WindAndKite\\Storyblok\\Ide\\Block\\HeroBlok $block', $updated);
        $this->assertStringEndsWith("?>\n<section>x</section>\n", $updated);

        // Only a variable docblock: a new header docblock goes first, so it's the one that gets parsed.
        $varOnly = "<?php\n/** @var \\WindAndKite\\Storyblok\\Block\\Block \$block */\n?>\n<div></div>\n";
        $this->assertStringStartsWith("<?php\n/**\n * @storyblok {", $importer->applyToTemplate($varOnly, $tag, 'hero'));

        // Existing tag: replaced, following tags kept, and re-applying changes nothing.
        $tagged = "<?php\n/**\n * Hero.\n *\n * @storyblok {\"fields\": {\"old\": {\"type\": \"string\"}}}\n *\n * @see Something\n */\n?>\n<div></div>\n";
        $once = $importer->applyToTemplate($tagged, $tag, 'hero');
        $this->assertStringNotContainsString('"old"', $once);
        $this->assertStringContainsString('@see Something', $once);
        $this->assertSame($once, $importer->applyToTemplate($once, $tag, 'hero'), 'idempotent');
    }

    public function testTagIsAddedWhateverFollowsTheOpeningTag(): void
    {
        $importer = new ComponentSchemaImporter();
        $tag = $importer->formatTag(['fields' => ['title' => ['type' => 'string']]]);
        $generator = new ComponentSchemaGenerator($this->createStub(RulePool::class), new File());

        foreach ([
            'CRLF' => "<?php\r\ndeclare(strict_types=1);\r\n?>\r\n<div></div>\r\n",
            'same line' => "<?php declare(strict_types=1); ?>\n<div></div>\n",
            'markup first' => "<div><?= 'x' ?></div>\n",
        ] as $case => $source) {
            $updated = $importer->applyToTemplate($source, $tag, 'hero');

            token_get_all($updated, TOKEN_PARSE);
            $this->assertSame(['fields' => ['title' => ['type' => 'string']]], $generator->parse($updated, 'hero.phtml'), $case);
        }
    }

    public function testCustomOptionLabelsRoundTrip(): void
    {
        $importer = new ComponentSchemaImporter();
        $component = ['name' => 'box', 'schema' => [
            'size' => ['type' => 'option', 'options' => [['name' => 'Small', 'value' => 'small'], ['name' => 'Extra Large', 'value' => 'xl']]],
            'level' => ['type' => 'option', 'options' => [['name' => 'Off', 'value' => '0'], ['name' => 'On', 'value' => '1']]],
        ]];

        $tag = $importer->formatTag($importer->toDefinition($component));
        $this->assertStringContainsString('"labels": {"xl": "Extra Large"}', $tag, 'only labels that differ from the derived ones');
        $this->assertStringContainsString('"labels": {"0": "Off", "1": "On"}', $tag, 'an object even when keys are 0, 1');

        $generator = new ComponentSchemaGenerator($this->createStub(RulePool::class), new File());
        $definition = $generator->parse("<?php\n/**\n" . $tag . "\n */\n", 'box.phtml');
        $schema = $generator->build(['box' => ['file' => 'box.phtml', 'definition' => $definition]])['components'][0]['schema'];

        $this->assertSame([], (new ComponentSchemaComparer())->compare(['components' => [['name' => 'box', 'schema' => $schema]]], [$component]));
    }

    public function testNewTemplateIsValidPhp(): void
    {
        $importer = new ComponentSchemaImporter();
        $definition = $importer->toDefinition(self::COLUMN, []);
        $source = $importer->newTemplate('column', $definition, $importer->formatTag($definition));

        token_get_all($source, TOKEN_PARSE);
        $this->assertStringContainsString('$block->getBodyHtml()', $source);
        $this->assertStringContainsString('@var \\WindAndKite\\Storyblok\\Block\\Block|\\WindAndKite\\Storyblok\\Ide\\Block\\ColumnBlok $block', $source);
        $this->assertNotNull((new ComponentSchemaGenerator($this->createStub(RulePool::class), new File()))->parse($source, 'column.phtml'));
    }
}
