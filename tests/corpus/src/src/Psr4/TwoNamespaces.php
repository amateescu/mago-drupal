<?php

declare(strict_types=1);

namespace Drupal\corpus\Replaced {
  /**
   * Stands in for a function of the code under test.
   */
  function corpus_psr4_replaced(): int {
    return 1;
  }
}

namespace Drupal\corpus\Elsewhere {
  /**
   * Sits in a file with two namespaces, which isn't checked.
   */
  final class TwoNamespaces {}
}
