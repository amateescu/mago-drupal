<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function count;
use function preg_match;
use function trim;

/**
 * The first `@return` tag of a docblock, read the way
 * Drupal.Commenting.FunctionComment reads it.
 *
 * @internal
 */
final class ReturnTag
{
    /**
     * A `@return` line that holds one type and one variable and nothing else.
     * A type such as `callable(int $a): int` does not match.
     */
    private const TYPE_AND_VARIABLE = '/^[ \t]*\S+[ \t]+\$\S+[ \t]*$/';

    /**
     * @param int $count How many `@return` tags the docblock has.
     * @param ?string $type The type, or null when the tag is bare or has
     *   none.
     * @param string $rest The text after the type.
     */
    private function __construct(
        public readonly DocblockTag $tag,
        public readonly int $count,
        public readonly ?string $type,
        public readonly string $rest,
    ) {}

    /**
     * Reads the first of the `@return` tags, or returns null when there is
     * none.
     *
     * @param list<DocblockTag> $returns
     */
    public static function read(array $returns): ?self
    {
        $tag = $returns[0] ?? null;
        if ($tag === null) {
            return null;
        }

        $return = new self($tag, count($returns), type: null, rest: '');
        if ($return->isBare()) {
            return $return;
        }

        [$type, $rest] = Docblocks::splitType($tag->content());

        return new self($tag, $return->count, $type, $rest);
    }

    /**
     * Whether the docblock's only `@return` doesn't have anything on its own
     * line. Coder reads the type from that line only, and doesn't check
     * anything else of such a tag.
     */
    public function isBare(): bool
    {
        return $this->count === 1 && trim($this->tag->lines[0]->text) === '';
    }

    /**
     * Whether the tag's own line holds a type and a variable name and
     * nothing else. Only that line can hold the name. A description below
     * it may start with one, as in `$this.`.
     */
    public function namesVariable(): bool
    {
        return preg_match(self::TYPE_AND_VARIABLE, $this->tag->lines[0]->text) === 1;
    }
}
