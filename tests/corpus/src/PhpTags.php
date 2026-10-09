<?php

/**
 * @file
 * Exercises the rules about PHP open and close tags.
 */

declare(strict_types=1);

// @mago-format-ignore-start
/**
 * Writes a pair of tags with nothing between them.
 */
function corpus_empty_pair(): void {
  // @mago-expect lint:drupal/empty-php-tags
  ?><?php ?>text<?php
}

/**
 * Writes pairs with line breaks, spaces and several on one line.
 */
function corpus_empty_pairs(): void {
  // @mago-expect lint:drupal/empty-php-tags(4)
  ?>a<?php
?>b<?php   ?>c<?php ?><?php ?>d<?php
}

/**
 * Writes a pair in a branch of the alternative syntax.
 */
function corpus_empty_pair_alternative(bool $show): void {
  if ($show): ?>
    <?php
    // @mago-expect lint:drupal/empty-php-tags
    ?><?php ?>shown<?php
  endif;
}

/**
 * Writes an empty echo tag.
 */
function corpus_empty_echo(): void {
  // @mago-expect analysis:too-few-arguments
  // @mago-expect lint:drupal/empty-php-tags
  ?><?= ?><?php
}

/**
 * Writes tags with a comment or code between them.
 */
function corpus_pair_with_content(): void {
  ?>a<?php /* note */ ?>b<?php
  // Note.
?>c<?php $x = 1; ?>d<?php
}

/**
 * Holds tag text in strings and comments.
 */
function corpus_pair_in_text(): string {
  /* <?php ?> */
  return '<?php ?>' . "<?php ?>" . <<<EOT
    <?php ?>
    EOT;
}

/**
 * Writes short echo tags.
 */
function corpus_short_echo(string $a, string $b): void {
  // @mago-expect lint:drupal/short-echo-tag(4)
  ?><?= $a ?><?=$b?><?= /* note */ $a ?><?=
  $b
?><?php
}

/**
 * Writes the long echo form and tag text in strings and comments.
 */
function corpus_long_echo(string $a): string {
  ?><?php echo $a ?><?php
  /* <?= $a ?> */
  return '<?= $a ?>' . "<?= $a ?>" . <<<EOT
    <?= $a ?>
    EOT;
}
// @mago-format-ignore-end
