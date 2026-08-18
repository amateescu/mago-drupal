<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * Which deprecation wording a message has to follow.
 *
 * @internal
 */
enum DeprecationStandard
{
    /**
     * Used when a `@deprecated` docblock accompanies the message. It fixes the
     * removal wording as well as the versions.
     */
    case Strict;

    case Relaxed;

    /**
     * Returns the pattern a message of this standard has to match.
     */
    public function layout(): string
    {
        return match ($this) {
            self::Strict => '/(.+) is deprecated in (\S+) (and is removed from) (?U)(.+)\. (.*)\. See (\S+)$/',
            self::Relaxed => '/(.+) is deprecated in (\S+) (?U)(.+) (\S+)\. (.*)See (\S+)$/',
        };
    }

    /**
     * Returns the documented shape, quoted back in the issue message.
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
     * Returns the name used for this standard in issue messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Strict => 'strict',
            self::Relaxed => 'relaxed',
        };
    }
}
