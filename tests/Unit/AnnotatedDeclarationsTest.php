<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use amateescu\MagoDrupal\Internal\EntityTypeAttribute;
use amateescu\MagoDrupal\Internal\EntityTypeDefinition;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use PHPUnit\Framework\TestCase;

use function array_map;

final class AnnotatedDeclarationsTest extends TestCase
{
    private const BLOCK = 'Drupal\Core\Block\Attribute\Block';

    public function testFoldsEverySetIntoOne(): void
    {
        $all = AnnotatedDeclarations::mergeAll([
            new AnnotatedDeclarations(
                [self::entityType('alpha')],
                [self::BLOCK => ['a' => 'Drupal\one\Plugin\Block\A']],
                ['drupal\one\plugin\block\a' => true],
            ),
            new AnnotatedDeclarations(
                [self::entityType('beta')],
                [self::BLOCK => ['b' => 'Drupal\two\Plugin\Block\B']],
                ['drupal\two\plugin\block\b' => true],
            ),
        ]);

        self::assertSame(
            ['alpha', 'beta'],
            array_map(static fn(EntityTypeDefinition $type): string => $type->id, $all->entityTypes),
        );
        self::assertSame(
            [self::BLOCK => ['a' => 'Drupal\one\Plugin\Block\A', 'b' => 'Drupal\two\Plugin\Block\B']],
            $all->plugins,
        );
        self::assertSame(
            ['drupal\one\plugin\block\a' => true, 'drupal\two\plugin\block\b' => true],
            $all->contextKeyed,
        );
    }

    public function testAnIdTwoClassesClaimBecomesAmbiguous(): void
    {
        $one = new AnnotatedDeclarations([], [self::BLOCK => ['a' => 'Drupal\one\Plugin\Block\A']]);
        $other = new AnnotatedDeclarations([], [self::BLOCK => ['a' => 'Drupal\two\Plugin\Block\A']]);

        self::assertSame([self::BLOCK => ['a' => null]], AnnotatedDeclarations::mergeAll([$one, $other])->plugins);
        // The same class twice is the same declaration read twice.
        self::assertSame(
            [self::BLOCK => ['a' => 'Drupal\one\Plugin\Block\A']],
            AnnotatedDeclarations::mergeAll([$one, $one])->plugins,
        );
        // An id already ambiguous stays ambiguous.
        self::assertSame(
            [self::BLOCK => ['a' => null]],
            AnnotatedDeclarations::mergeAll([$one, $other, $one])->plugins,
        );
    }

    public function testNothingToFoldIsAnEmptySet(): void
    {
        self::assertTrue(AnnotatedDeclarations::mergeAll([])->isEmpty());
    }

    /**
     * @param non-empty-string $id
     */
    private static function entityType(string $id): EntityTypeDefinition
    {
        return new EntityTypeDefinition(
            $id,
            'Drupal\one\Entity\Thing',
            EntityTypeKind::Content,
            EntityTypeAttribute::withDefaults(EntityTypeKind::Content, []),
            true,
        );
    }
}
