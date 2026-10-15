<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\EntityKeyGuards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function max;
use function strlen;
use function strpos;
use function strrpos;

final class EntityKeyGuardsTest extends TestCase
{
    /**
     * Code whose last getter call a check guards.
     *
     * @return iterable<string, array{string}>
     */
    public static function guarded(): iterable
    {
        yield 'in the block' => ["if (\$type->hasKey('owner')) {\n  return \$type->getKey('owner');\n}"];
        yield 'later in the condition' => [
            "if (\$type->hasKey('owner') && \$a && \$name == \$type->getKey('owner')) {\n  return 1;\n}",
        ];
        yield 'after another && part' => ["if (\$a && \$type->hasKey('owner')) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'in a nested if' => [
            "if (\$type->hasKey('owner')) {\n  if (isset(\$f[\$type->getKey('owner')])) {\n    return 1;\n  }\n}",
        ];
        yield 'in an elseif' => ["if (\$a) {\n}\nelseif (\$type->hasKey('owner')) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'without braces' => ["if (\$type->hasKey('owner')) return \$type->getKey('owner');"];
        yield 'other quotes and spacing' => ["if (\$type -> hasKey(\"owner\")) {\n  f(\$type->getKey( 'owner' ));\n}"];
        yield 'on a property' => [
            "if (\$this->entityType->hasKey('owner')) {\n  f(\$this->entityType->getKey('owner'));\n}",
        ];
        yield 'with a variable key' => ["if (\$type->hasKey(\$key)) {\n  f(\$type->getKey(\$key));\n}"];
        yield 'after a throw' => [
            "if (!\$type->hasKey('owner')) {\n  throw new \\LogicException('No; owner.');\n}\nf(\$type->getKey('owner'));",
        ];
        yield 'after a return in a nested block' => [
            "if (!\$type->hasKey('owner')) {\n  return;\n}\nif (\$a) {\n  f(\$type->getKey('owner'));\n}",
        ];
        yield 'a link template in the block' => [
            "if (\$type->hasLinkTemplate('canonical')) {\n  return \$type->getLinkTemplate('canonical');\n}",
        ];
        yield 'a link template after a throw' => [
            "if (!\$type->hasLinkTemplate(\$key)) {\n  throw new \\LogicException();\n}\nf(\$type->getLinkTemplate(\$key));",
        ];
        yield 'later in an assigned && chain' => [
            "\$pin = \$a && \$type->hasLinkTemplate('restore') && f(\$type->getLinkTemplate('restore'), '{');",
        ];
        yield 'later in a returned and chain' => ["return \$type->hasKey('owner') and f(\$type->getKey('owner'));"];
        yield 'in an array later in an && chain' => [
            "\$a = \$type->hasKey('owner') && f([1, \$type->getKey('owner')]);",
        ];
    }

    /**
     * Code whose last getter call isn't guarded by a check.
     *
     * @return iterable<string, array{string}>
     */
    public static function unguarded(): iterable
    {
        yield 'another key' => ["if (\$type->hasKey('published')) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'another receiver' => ["if (\$other->hasKey('owner')) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'a receiver that starts the same' => [
            "if (\$types->hasKey('owner')) {\n  f(\$type->getKey('owner'));\n}",
        ];
        yield 'the else branch' => ["if (\$type->hasKey('owner')) {\n}\nelse {\n  f(\$type->getKey('owner'));\n}"];
        yield 'after the if' => ["if (\$type->hasKey('owner')) {\n  \$a = 1;\n}\nf(\$type->getKey('owner'));"];
        yield 'an || condition' => ["if (\$type->hasKey('owner') || \$a) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'a negated check' => ["if (!\$type->hasKey('owner')) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'a check held in a call' => ["if (f(\$type->hasKey('owner'))) {\n  f(\$type->getKey('owner'));\n}"];
        yield 'a check assigned' => ["\$has = \$type->hasKey('owner');\nf(\$type->getKey('owner'));"];
        yield 'a ternary' => ["return \$type->hasKey('owner') ? \$type->getKey('owner') : 'uid';"];
        yield 'a guard with no exit' => ["if (!\$type->hasKey('owner')) {\n  \$a = 1;\n}\nf(\$type->getKey('owner'));"];
        yield 'a guard with more in its condition' => [
            "if (!\$type->hasKey('owner') && \$a) {\n  return;\n}\nf(\$type->getKey('owner'));",
        ];
        yield 'an exit in a block closed before the call' => [
            "if (\$a) {\n  if (!\$type->hasKey('owner')) {\n    return;\n  }\n}\nf(\$type->getKey('owner'));",
        ];
        yield 'a reassigned receiver' => [
            "if (\$type->hasKey('owner')) {\n  \$type = \$other;\n  f(\$type->getKey('owner'));\n}",
        ];
        yield 'a changed key' => ["if (\$type->hasKey(\$key)) {\n  \$key .= '_id';\n  f(\$type->getKey(\$key));\n}"];
        yield 'a receiver bound by a foreach' => [
            "if (\$type->hasKey('owner')) {\n  foreach (\$types as \$type) {\n    f(\$type->getKey('owner'));\n  }\n}",
        ];
        yield 'a check in a comment' => ["// if (\$type->hasKey('owner')) {\nf(\$type->getKey('owner'));"];
        yield 'a check outside a closure' => [
            "if (\$type->hasKey('owner')) {\n  \$f = function () use (\$type) {\n    return \$type->getKey('owner');\n  };\n}",
        ];
        yield 'the check of the other getter' => [
            "if (\$type->hasKey('canonical')) {\n  f(\$type->getLinkTemplate('canonical'));\n}",
        ];
        yield 'another link template' => [
            "if (\$type->hasLinkTemplate('canonical')) {\n  f(\$type->getLinkTemplate('edit-form'));\n}",
        ];
        yield 'an || chain' => ["\$a = \$type->hasKey('owner') || f(\$type->getKey('owner'));"];
        yield 'an || later in the chain' => ["\$a = \$type->hasKey('owner') && \$b || f(\$type->getKey('owner'));"];
        yield 'a ternary after the chain' => ["\$a = \$type->hasKey('owner') && \$b ? f(\$type->getKey('owner')) : 1;"];
        yield 'a negated check in a chain' => ["\$a = !\$type->hasKey('owner') && f(\$type->getKey('owner'));"];
        yield 'a chain held in a call' => ["\$a = f(\$type->hasKey('owner')) && f(\$type->getKey('owner'));"];
        yield 'a comparison before the check' => [
            "\$a = \$b == \$type->hasKey('owner') && f(\$type->getKey('owner'));",
        ];
        yield 'a bitwise and after the check' => ["\$a = \$type->hasKey('owner') & f(\$type->getKey('owner'));"];
        yield 'another array element' => ["\$a = [\$b && \$type->hasKey('owner'), f(\$type->getKey('owner'))];"];
        yield 'a chain in an earlier statement' => [
            "\$a = \$type->hasKey('owner') && \$b;\nf(\$type->getKey('owner'));",
        ];
    }

    #[DataProvider('guarded')]
    public function testGuarded(string $body): void
    {
        self::assertTrue(self::check($body));
    }

    #[DataProvider('unguarded')]
    public function testUnguarded(string $body): void
    {
        self::assertFalse(self::check($body));
    }

    public function testOnlyReadsAGetKeyCall(): void
    {
        $contents = "<?php\nfunction f(\$type) {\n  if (\$type->hasKey('owner')) {\n    return \$type->getKey('owner') . 'x';\n  }\n}\n";
        $start = (int) strrpos($contents, needle: '$type->getKey');
        self::assertFalse(EntityKeyGuards::guarded(
            $contents,
            $start,
            $start + strlen("\$type->getKey('owner') . 'x'"),
        ));
        self::assertTrue(EntityKeyGuards::guarded($contents, $start, $start + strlen("\$type->getKey('owner')")));
    }

    /**
     * Whether the last `getKey()` or `getLinkTemplate()` call of the body,
     * in a function, is guarded.
     */
    private static function check(string $body): bool
    {
        $contents = "<?php\n\nfunction example(\$type, \$other, \$types, \$key, \$a, \$b) {\n{$body}\n}\n";
        $start = max(
            (int) strrpos($contents, needle: '->getKey('),
            (int) strrpos($contents, needle: '->getLinkTemplate('),
        );
        $start = (int) strrpos($contents, needle: '$', offset: $start - strlen($contents));
        $close = (int) strpos($contents, needle: ')', offset: $start);

        return EntityKeyGuards::guarded($contents, $start, $close + 1);
    }
}
