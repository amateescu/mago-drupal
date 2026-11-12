<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Extends an exception under a name with no suffix.
 */
// @mago-expect lint:drupal/exception-suffix
class ExceptionSuffixMissing extends \RuntimeException {}

/**
 * Extends an exception under a name with the suffix.
 */
class ExceptionSuffixException extends \RuntimeException {}

/**
 * Extends the class above, whose name doesn't say that it is an exception.
 */
class ExceptionSuffixChild extends ExceptionSuffixMissing {}

/**
 * Extends a class that isn't an exception.
 */
class ExceptionSuffixPlain extends \stdClass {}
