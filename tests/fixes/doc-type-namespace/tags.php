<?php

namespace Drupal\example;

use Drupal\Core\Aa;
use Drupal\Core\Bb;
use Drupal\Core\Cc;
use Drupal\Core\Dd;
use Drupal\Core\Ee;
use Drupal\Core\Ff;
use Drupal\Core\Gg;
use Drupal\Core\Hh;
use Drupal\Core\Ii;

/**
 * Names imported classes in several tags.
 *
 * Mentions Ii in prose, which keeps its import.
 *
 * @see Aa
 * @see Bb::create()
 * @method static Cc create(array<string, Dd> $values, ?Ee ...$rest)
 * @property-read Ff $ff
 * @mixin Gg
 * @phpstan-type Shape array{a: Hh}
 * @see Ii
 */
class Tags {

}
