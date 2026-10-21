<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Docblock\FileDocblock;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

use function pack;
use function strlen;
use function strpos;

/**
 * FileDocblock reads the target list of a snapshot once and keeps it, so an
 * editor session that lints the same path again after an edit has to get
 * the new snapshot's declarations.
 */
final class FileDocblockTest extends TestCase
{
    /**
     * The parent, first child and next sibling of a node that doesn't have any.
     */
    private const NO_LINKS = [4_294_967_295, 4_294_967_295, 4_294_967_295];

    private const PATH = 'modules/corpus/src/Thing.php';

    public function testASecondSnapshotOfThePathIsReadAgain(): void
    {
        $namespaced = '<?php namespace Drupal\corpus; class Thing {}';
        $plain = '<?php class Thing {}';

        self::assertTrue(FileDocblock::isNamespacedClassFile(self::namespacedClass($namespaced)));
        self::assertFalse(FileDocblock::isNamespacedClassFile(self::plainClass($plain)));
        self::assertTrue(FileDocblock::isNamespacedClassFile(self::namespacedClass($namespaced)));
    }

    /**
     * A snapshot holds only what its targets need, so two snapshots of the
     * same text can list different nodes.
     */
    public function testTwoSnapshotsOfTheSameTextKeepTheirOwnTargets(): void
    {
        $contents = '<?php namespace Drupal\corpus; class Thing {}';

        self::assertTrue(FileDocblock::isNamespacedClassFile(self::namespacedClass($contents)));
        self::assertFalse(FileDocblock::isNamespacedClassFile(self::plainClass($contents)));
    }

    /**
     * A snapshot whose targets are the namespace and the class.
     */
    private static function namespacedClass(string $contents): SourceFile
    {
        $records =
            self::record($contents, kind: 0, keyword: 'namespace') . self::record($contents, kind: 1, keyword: 'class');

        return self::sourceFile($contents, [0, 1], new NodeStore([NodeKind::Namespace, NodeKind::Class_], $records, 2));
    }

    /**
     * A snapshot whose only target is the class.
     */
    private static function plainClass(string $contents): SourceFile
    {
        $records = self::record($contents, kind: 0, keyword: 'class');

        return self::sourceFile($contents, [0], new NodeStore([NodeKind::Class_], $records, 1));
    }

    /**
     * The record of a node that spans from its keyword to the end of the file.
     */
    private static function record(string $contents, int $kind, string $keyword): string
    {
        return pack('CNNNNN', $kind, (int) strpos($contents, $keyword), strlen($contents), ...self::NO_LINKS);
    }

    /**
     * @param list<int<0, 4294967295>> $targets
     */
    private static function sourceFile(string $contents, array $targets, NodeStore $nodes): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 3),
            self::PATH,
            $contents,
            $targets,
            $nodes,
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }
}
