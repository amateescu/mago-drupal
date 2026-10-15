<?php

namespace Drupal\example;

use Drupal\Core\Ii;

/**
 * Names imported classes in several tags.
 *
 * Mentions Ii in prose, which keeps its import.
 *
 * @see \Drupal\Core\Aa
 * @see \Drupal\Core\Bb::create()
 * @method static \Drupal\Core\Cc create(array<string, \Drupal\Core\Dd> $values, ?\Drupal\Core\Ee ...$rest)
 * @property-read \Drupal\Core\Ff $ff
 * @mixin \Drupal\Core\Gg
 * @phpstan-type Shape array{a: \Drupal\Core\Hh}
 * @see \Drupal\Core\Ii
 */
class Tags {

}
