<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use amateescu\MagoDrupal\Internal\EntityTypeAttribute;
use amateescu\MagoDrupal\Internal\EntityTypeDefinition;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

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

    /**
     * An editor hands Mago text it has not saved. A renamed id has to
     * resolve under its new name only, and a file whose text dropped its
     * annotation declares nothing, whatever the file says on disk.
     */
    public function testReadsAnalyzedFilesFromTheTextMagoAnalyzes(): void
    {
        $directory = sys_get_temp_dir() . '/mago-drupal-annotated-' . uniqid();
        mkdir($directory);
        try {
            file_put_contents($directory . '/Renamed.php', data: self::block('Renamed', 'old_id'));
            file_put_contents($directory . '/Dropped.php', data: self::block('Dropped', 'dropped_id'));
            file_put_contents($directory . '/Saved.php', data: self::block('Saved', 'saved_id'));
            file_put_contents($directory . '/Included.php', data: self::block('Included', 'included_id'));
            $real = (string) realpath($directory);
            $disk = AnnotatedDeclarations::byFile([
                $directory . '/Renamed.php',
                $directory . '/Dropped.php',
                $directory . '/Saved.php',
                $directory . '/Included.php',
            ]);

            $current = AnnotatedDeclarations::current($disk, [
                $real . '/Renamed.php' => self::block('Renamed', 'new_id'),
                $real . '/Dropped.php' => null,
                $real . '/Saved.php' => self::block('Saved', 'saved_id'),
                $real . '/Unsaved.php' => self::block('Unsaved', 'unsaved_id'),
            ]);
        } finally {
            DiskCacheTest::remove($directory);
        }

        self::assertSame(
            [
                self::BLOCK => [
                    'new_id' => 'Drupal\x\Plugin\Block\Renamed',
                    'unsaved_id' => 'Drupal\x\Plugin\Block\Unsaved',
                    'saved_id' => 'Drupal\x\Plugin\Block\Saved',
                    'included_id' => 'Drupal\x\Plugin\Block\Included',
                ],
            ],
            $current->plugins,
        );
    }

    private static function block(string $class, string $id): string
    {
        return "<?php\n\nnamespace Drupal\\x\\Plugin\\Block;\n\n/**\n * @Block(\n *   id = \"{$id}\",\n * )\n */\nclass {$class} {}\n";
    }

    public function testNothingToFoldIsAnEmptySet(): void
    {
        $empty = AnnotatedDeclarations::mergeAll([]);

        self::assertSame([], $empty->entityTypes);
        self::assertSame([], $empty->plugins);
        self::assertSame([], $empty->contextKeyed);
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
