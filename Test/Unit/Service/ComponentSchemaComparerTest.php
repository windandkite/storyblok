<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Service\ComponentSchemaComparer;

class ComponentSchemaComparerTest extends TestCase
{
    private const EXPECTED = ['components' => [
        ['name' => 'section', 'schema' => [
            'body' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['text', 'hero']],
            'padding' => ['type' => 'option', 'options' => [['value' => 'small'], ['value' => 'large']]],
            'title' => ['type' => 'text'],
            'anchor_id' => ['type' => 'text'],
        ]],
        ['name' => 'text', 'schema' => ['copy' => ['type' => 'richtext']]],
        ['name' => 'hero', 'schema' => []],
        ['name' => 'teaser', 'schema' => ['story' => ['type' => 'option', 'source' => 'internal_stories']]],
    ]];

    public function testMatchingSchemasHaveNoIssues(): void
    {
        $pulled = self::EXPECTED;
        // Compatible text types are not reported.
        $pulled['components'][0]['schema']['title']['type'] = 'textarea';

        $this->assertSame([], (new ComponentSchemaComparer())->compare(self::EXPECTED, $pulled));
    }

    public function testReportsEachKindOfMismatch(): void
    {
        $pulled = ['components' => [
            ['name' => 'section', 'schema' => [
                'body' => ['type' => 'bloks', 'restrict_components' => true, 'component_whitelist' => ['text', 'legacy_banner']],
                'padding' => ['type' => 'option', 'options' => [['value' => 'small'], ['value' => 'xl']]],
                'title' => ['type' => 'asset'],
                'subtitle' => ['type' => 'text'],
            ]],
            ['name' => 'text', 'schema' => ['copy' => ['type' => 'richtext']]],
            ['name' => 'hero', 'schema' => []],
            ['name' => 'teaser', 'schema' => ['story' => ['type' => 'option', 'source' => '']]],
            ['name' => 'legacy_banner', 'schema' => []],
            ['name' => 'page', 'is_root' => true, 'schema' => []],
            ['name' => 'promo', 'schema' => []],
        ]];

        $issues = (new ComponentSchemaComparer())->compare(self::EXPECTED, $pulled, ['promo' => '/theme/block/promo.phtml']);
        $summary = array_map(static fn ($issue) => $issue['level'] . ' ' . $issue['component'] . '.' . $issue['field'], $issues);

        $this->assertEqualsCanonicalizing([
            'error legacy_banner.',       // in space, no template
            'error page.',                // content type, no block template
            'warning promo.',             // template exists but untagged
            'warning section.subtitle',   // only in space
            'warning section.anchor_id',  // only in template
            'error section.title',        // type mismatch
            'error section.padding',      // space value "xl" not handled
            'warning section.padding',    // template value "large" not in space
            'error section.body',         // space allows a blok with no template
            'warning section.body',       // template allows "hero", space doesn't
            'error teaser.story',         // option source differs
        ], $summary);
    }

    public function testUnpushedComponentAndUnrestrictedSpaceField(): void
    {
        $pulled = ['components' => [
            ['name' => 'section', 'schema' => array_merge(self::EXPECTED['components'][0]['schema'], [
                'body' => ['type' => 'bloks', 'restrict_components' => false],
            ])],
            self::EXPECTED['components'][1],
            self::EXPECTED['components'][3],
        ]];

        $issues = (new ComponentSchemaComparer())->compare(self::EXPECTED, $pulled);

        $this->assertSame(
            [['warning', 'section', 'body'], ['warning', 'hero', null]],
            array_map(static fn ($issue) => [$issue['level'], $issue['component'], $issue['field']], $issues)
        );
    }

    public function testAcceptsAPlainListOfComponents(): void
    {
        $this->assertSame([], (new ComponentSchemaComparer())->compare(self::EXPECTED, self::EXPECTED['components']));
    }
}
