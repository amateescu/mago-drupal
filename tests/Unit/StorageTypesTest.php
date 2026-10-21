<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Entity\StorageTypes;
use Mago\Sdk\Span;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;
use function strpos;

final class StorageTypesTest extends TestCase
{
    /**
     * Each case is the class and whether it names a storage where the
     * injection check reports it.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function classes(): iterable
    {
        yield 'constructor parameter' => [
            'class A { public function __construct(NodeStorageInterface $storage) {} }',
            true,
        ];
        yield 'comment before the constructor name' => [
            'class A { public function /* Builds. */ __construct(NodeStorageInterface $storage) {} }',
            true,
        ];
        yield 'parenthesis in a default string' => [
            "class A { public function __construct(string \$open = '(', ?NodeStorage \$storage = NULL) {} }",
            true,
        ];
        yield 'property' => ['class A { protected NodeStorageInterface $storage; }', true];
        yield 'var tag' => ["class A {\n  /** @var \\Drupal\\node\\NodeStorage */\n  protected \$storage;\n}", true];
        yield 'method parameter' => ['class A { public function postSave(NodeStorageInterface $storage) {} }', false];
        yield 'constructor in a comment' => [
            "class A {\n  // public function __construct(NodeStorageInterface \$storage) {}\n  public function __construct() {}\n}",
            false,
        ];
        yield 'storage in a string' => [
            "class A { public function __construct(string \$name = 'NodeStorage \$x') {} }",
            false,
        ];
    }

    #[DataProvider('classes')]
    public function testReadsWhereAClassNamesAStorage(string $class, bool $declared): void
    {
        $contents = "<?php\n\nnamespace Drupal\\example;\n\n" . $class . "\n";
        $start = (int) strpos($contents, needle: 'class A');
        $span = new Span($start, $start + strlen($class));

        self::assertSame($declared, StorageTypes::declaredLike($contents, $span));
    }
}
