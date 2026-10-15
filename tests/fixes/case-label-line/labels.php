<?php

function labels(int $x): int {
  switch ($x) {
    /* The first one. */ case 1:
      return 1;

    /* The second one. */   case 2:
      return 2;

    /** A docblock. */ default:
      return 0;
  }
}

function label_after_code(int $x): int {
  switch ($x) { /* The first one. */ case 1:
      return 1;
  }

  return 0;
}

function label_after_two_comments(int $x): int {
  switch ($x) {
    /* One. */ /* Two. */ case 1:
      return 1;
  }

  return 0;
}

function label_after_long_comment(int $x): int {
  switch ($x) {
    /* One line,
       two lines. */ case 1:
      return 1;
  }

  return 0;
}

function label_alternative(int $x): int {
  switch ($x):
    /* The first one. */ default:
      return 0;
  endswitch;
}

function labels_fine(int $x): int {
  switch ($x) {
    /* The first one. */
    case 1: /* After the label. */
      return 1;
  }

  return 0;
}
