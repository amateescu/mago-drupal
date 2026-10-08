<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\NodeAnalysisContext;

use function count;
use function preg_match;
use function str_contains;
use function strcasecmp;
use function substr;

use const T_ATTRIBUTE;

/**
 * Reads procedural functions off a file's text, the way Drupal's hook
 * collector sees them.
 *
 * @internal
 */
final class ProceduralFunctions
{
    /**
     * The function's own name, the first one after `function`.
     */
    private const NAME = '/\bfunction\s+&?\s*([A-Za-z_]\w*)/';

    /**
     * How much of a function's start to read for its name, attributes
     * included.
     */
    private const HEAD = 512;

    /**
     * The attribute that stops Drupal's scan of the file for procedural
     * hooks, at its function.
     */
    private const SCAN_STOP = 'ProceduralHookScanStop';

    private function __construct() {}

    /**
     * The name of the function declared at the offset, or null.
     */
    public static function nameAt(string $contents, int $start): ?string
    {
        $matches = [];

        return preg_match(self::NAME, substr($contents, $start, self::HEAD), $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Whether the function ending at the offset is the one marked
     * `#[ProceduralHookScanStop]` or comes after it, where Drupal stops
     * collecting procedural hooks. The marker sits before the end of either.
     */
    public static function afterScanStop(string $contents, int $end): bool
    {
        if (!str_contains($contents, self::SCAN_STOP)) {
            return false;
        }

        $stop = LastFile::get(self::class, $contents, static fn(): ?int => self::scanStop($contents));

        return $stop !== null && $stop < $end;
    }

    /**
     * Where the first attribute naming the marker starts, read off the
     * tokens as Drupal's parser does, so a mention in a comment or a string
     * does not count.
     */
    private static function scanStop(string $contents): ?int
    {
        $tokens = PhpTokens::of($contents);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!$tokens[$i]->is(T_ATTRIBUTE)) {
                continue;
            }

            $close = PhpTokens::closer($tokens, $i, '[', ']');
            if (PhpTokens::mentions($tokens, $i, $close, self::SCAN_STOP)) {
                return $tokens[$i]->pos;
            }

            $i = $close;
        }

        return null;
    }

    /**
     * The metadata of the function the node declares under the short name.
     * A bare attribute on the function is a resolved name too, so the name
     * and the position pick the function out.
     */
    public static function declared(NodeAnalysisContext $context, string $short): ?FunctionLikeMetadata
    {
        $start = $context->node->span->start;
        foreach (DeclaredClass::candidates($context->source, $context->node) as $candidate) {
            if (strcasecmp(ClassNames::short($candidate), $short) !== 0) {
                continue;
            }

            $function = $context->codebase->getFunction($candidate);
            if ($function !== null && $function->location->span->start === $start) {
                return $function;
            }
        }

        return null;
    }
}
