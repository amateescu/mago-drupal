<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function explode;
use function file_get_contents;
use function function_exists;
use function getmypid;
use function is_file;
use function posix_getppid;
use function strrpos;
use function substr;

/**
 * Names the Mago process the workers of one run belong to.
 *
 * Every worker is a child of the same host process, so its id groups the
 * workers of a run. The id alone is not enough to tell one run from a later
 * one: the operating system hands the number out again, and a cached index
 * from the earlier run would then look current. The start time settles it.
 *
 * @internal
 */
final class HostProcess
{
    /**
     * Marks a system that does not publish process start times.
     */
    private const UNKNOWN = 'x';

    private function __construct() {}

    /**
     * The host of this worker: its process id and start time.
     */
    public static function identity(): string
    {
        $pid = function_exists('posix_getppid') ? posix_getppid() : (int) getmypid();

        return self::identify($pid);
    }

    /**
     * The identity of one process, `<pid>-<start time>`.
     */
    public static function identify(int $pid): string
    {
        return (string) $pid . '-' . self::startedAt($pid);
    }

    /**
     * Clock ticks since boot at which the process started, read from
     * `/proc/<pid>/stat`. The command name field holds arbitrary text between
     * brackets, so the fields are counted from the last bracket: the first
     * one after it is the state, and the start time is the twentieth.
     */
    private static function startedAt(int $pid): string
    {
        $file = '/proc/' . (string) $pid . '/stat';
        $stat = is_file($file) ? file_get_contents($file) : false;
        $end = $stat === false ? false : strrpos($stat, needle: ')');
        if ($stat === false || $end === false) {
            return self::UNKNOWN;
        }

        $fields = explode(separator: ' ', string: substr($stat, offset: $end + 2));

        return $fields[19] ?? self::UNKNOWN;
    }
}
