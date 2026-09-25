<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use ReflectionProperty;
use Throwable;

use function is_object;

/**
 * Reads the number Mago gives the frozen codebase of one analysis.
 *
 * Every request the host sends carries it, and it moves whenever the host
 * analyzes again, which is what tells one analysis from the next inside a
 * watch or editor session. The SDK keeps it on the metadata cache behind the
 * `Codebase` and publishes no accessor, so it is read by reflection, and a
 * caller has to cope with null. Counting scans in the worker instead is
 * wrong: a replacement worker replays only the last scan, and an incremental
 * analysis may run none at all.
 *
 * @internal
 */
final class AnalysisGeneration
{
    private static ?ReflectionProperty $cache = null;

    private static ?ReflectionProperty $generation = null;

    /**
     * Set once the SDK turns out to keep the number somewhere else, so the
     * reflection is not attempted per call.
     */
    private static bool $unavailable = false;

    private function __construct() {}

    /**
     * The generation of this codebase, or null when it cannot be read.
     */
    public static function of(Codebase $codebase): ?int
    {
        if (self::$unavailable) {
            return null;
        }

        try {
            self::$cache ??= new ReflectionProperty(Codebase::class, 'cache');
            /** @var mixed $metadata */
            $metadata = self::$cache->getValue($codebase);
            if (!is_object($metadata)) {
                self::$unavailable = true;

                return null;
            }

            self::$generation ??= new ReflectionProperty($metadata::class, 'generation');
            $generation = Shape::int(self::$generation->getValue($metadata));
            self::$unavailable = $generation === null;

            return $generation;
        } catch (Throwable) {
            self::$unavailable = true;

            return null;
        }
    }
}
