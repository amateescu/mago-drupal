<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\TestFiles;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\SourceLocation;
use PhpToken;

use function array_key_exists;
use function count;
use function file_get_contents;
use function is_file;
use function strtolower;
use function substr;

use const T_DOUBLE_COLON;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;

/**
 * Reports plugin managers whose constructor skips `alterInfo()` or
 * `setCacheBackend()`; runs on DefaultPluginManager descendants.
 *
 * Ports phpstan-drupal's PluginManagerSetsCacheBackendRule and
 * PluginManagerInspectionRule. The calls are read off the constructor's
 * tokens, so a call in a comment does not count. A `parent::__construct()`
 * call is followed into the parent's constructor, read off disk, so a
 * manager that leaves the wiring to its parent is not reported.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class PluginManagerCheck implements MetadataCheck
{
    public const ANCESTORS = ['Drupal\Core\Plugin\DefaultPluginManager'];

    public const ALTER_CODE = 'plugin-manager-alter-info';

    public const CACHE_CODE = 'plugin-manager-cache-backend';

    /**
     * How far a `parent::__construct()` chain is followed.
     */
    private const DEPTH = 5;

    /**
     * Only a class declaring its own constructor is checked.
     */
    public function textGate(): ?string
    {
        return '/\b__construct\b/i';
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $metadata = $class->class;
        $constructor = $class->constructor();
        $location = $constructor === null ? null : $constructor->nameLocation ?? $constructor->location;
        if (
            $location === null
            || $constructor?->location === null
            || $metadata->flags->contains(MetadataFlags::ABSTRACT)
            || TestFiles::isTest($metadata->location->file ?? '')
        ) {
            return;
        }

        $calls = self::calls($class->codebase, $metadata->name, $constructor->location);
        $name = $class->name();
        if (!array_key_exists('alterinfo', $calls)) {
            $reporter->warning(self::ALTER_CODE, Reporter::issue(
                "{$name} never calls alterInfo(), so other modules cannot alter its plugin definitions.",
                $location,
                'Call $this->alterInfo(\'mymodule_data\') in the constructor to invoke hook_mymodule_data_alter().',
            ));
        }

        if (!array_key_exists('setcachebackend', $calls)) {
            $reporter->warning(self::CACHE_CODE, Reporter::issue(
                "{$name} never sets a cache backend, so plugin discovery runs on every request.",
                $location,
                'Call $this->setCacheBackend($cache_backend, \'mymodule_plugins\') in the constructor.',
            ));
        }
    }

    /**
     * Lowercased names of the methods a constructor calls, with those of
     * the parent constructors it calls in turn.
     *
     * @param string $class The class declaring the constructor.
     * @return array<string, true>
     */
    private static function calls(Codebase $codebase, string $class, SourceLocation $constructor, int $depth = 0): array
    {
        $calls = self::methodCalls($constructor);
        $parent = $codebase->getClassLike($class)?->directParentClass;
        if (!array_key_exists('__construct', $calls) || $parent === null || $depth >= self::DEPTH) {
            return $calls;
        }

        $inherited =
            $codebase->findMethods(class: $parent, name: '__construct', fields: MethodFields::LOCATIONS)[0] ?? null;
        $declaring = $inherited?->identifier->class;
        if ($inherited?->location === null || $declaring === null) {
            return $calls;
        }

        return [...$calls, ...self::calls($codebase, $declaring, $inherited->location, $depth + 1)];
    }

    /**
     * Lowercased names called with `->`, `?->` or `::` in the method's text.
     *
     * @return array<string, true>
     */
    private static function methodCalls(SourceLocation $method): array
    {
        $file = $method->file;
        $contents = $file !== null && is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            return [];
        }

        $tokens = PhpToken::tokenize('<?php ' . substr($contents, $method->span->start, $method->span->length()));
        $calls = [];
        $previous = null;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->isIgnorable()) {
                continue;
            }

            if (
                $token->is(T_STRING)
                && $previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]) === true
                && self::nextIsParenthesis($tokens, $i + 1)
            ) {
                $calls[strtolower($token->text)] = true;
            }

            $previous = $token;
        }

        return $calls;
    }

    /**
     * Whether the next token that is not whitespace or a comment is `(`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nextIsParenthesis(array $tokens, int $from): bool
    {
        $count = count($tokens);
        for ($i = $from; $i < $count; $i++) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i]->text === '(';
            }
        }

        return false;
    }
}
