<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * The deprecation wording that a message must follow.
 *
 * @internal
 */
enum DeprecationStandard
{
    /**
     * Applies when a `@deprecated` docblock goes with the message. It fixes
     * the removal wording and the versions.
     */
    case Strict;

    case Relaxed;

    /**
     * Returns the pattern that a message of this standard must match.
     */
    public function layout(): string
    {
        return match ($this) {
            self::Strict => '/(.+) is deprecated in (\S+) (and is removed from) (?U)(.+)\. (.*)\. See (\S+)$/',
            self::Relaxed => '/(.+) is deprecated in (\S+) (?U)(.+) (\S+)\. (.*)See (\S+)$/',
        };
    }

    /**
     * Returns the documented shape. The issue message quotes it.
     */
    public function format(): string
    {
        return match ($this) {
            self::Strict
                => '%thing% is deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%. See %cr-link%',
            self::Relaxed
                => '%thing% is deprecated in %deprecation-version% any free text %removal-version%. %extra-info%. See %cr-link%',
        };
    }

    /**
     * Returns the name of this standard in issue messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Strict => 'strict',
            self::Relaxed => 'relaxed',
        };
    }
}
