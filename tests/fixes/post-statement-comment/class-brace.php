<?php

class BraceProperty { // Holds the count.

  /**
   * The count.
   */
  public int $count = 0;

}

class BraceBlock { /* Holds the total. */
  public int $total = 0;
}

interface BraceMethodInterface { // Counts things.

  /**
   * Counts the things.
   */
  public function count(): int;

}

trait BraceEmptyTrait { /* Nothing yet. */ }

trait BraceEmptyLineTrait { // Nothing yet.

}

enum BraceEnum: string { # The states.

  case On = 'on';

}

class BraceRun { // The first line,
  // and the second one.

  public int $count = 0;

}

class BraceCode { /* The count. */ public int $count = 0;
}

class BraceLineBound { // @codeCoverageIgnore

  public int $count = 0;

}

function brace_anonymous_class(): object {
  return new class { // Holds nothing.

    /**
     * The value.
     */
    public int $value = 0;

  };
}
