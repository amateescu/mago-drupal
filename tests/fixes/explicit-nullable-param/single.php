<?php

namespace Drupal\example;

use Drupal\Core\Entity\EntityInterface;

/**
 * Takes parameters of one type with a null default. PHP rejects the
 * promoted parameter as written, and accepts it once its type is nullable.
 */
class SingleType
{
    public function __construct(
        protected EntityInterface $entity = NULL,
        private readonly ?string $label = NULL,
    ) {}

    public function load(int $id = null, self $parent = NULL, \Countable $items = \NULL, array &$cache = NULL, false $flag = NULL): void
    {
        $callback = function (string $name = NULL, int $count = 1, ?int $limit = NULL, $any = NULL, mixed $value = NULL) {
            return $name;
        };
        $arrow = fn(callable $map = NULL) => $map;
    }
}
