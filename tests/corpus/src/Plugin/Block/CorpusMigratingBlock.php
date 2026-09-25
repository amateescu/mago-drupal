<?php

/**
 * @file
 * Blocks whose docblocks carry annotations that must not count.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Carries the attribute and a stale annotation; the attribute wins.
 *
 * @Block(
 *   id = "corpus_stale",
 * )
 */
#[Block(id: 'corpus_migrating')]
final class CorpusMigratingBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnMigratingBlock(): void {
  }

}

/**
 * Documents an annotation in a code sample.
 *
 * @code
 * @Block(
 *   id = "corpus_doc_sample",
 * )
 * @endcode
 *
 * @internal
 * (an unbalanced remark that must not swallow the declaration below)
 *
 * @Block(
 *   id = "corpus_documented",
 * )
 */
final class CorpusDocumentedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnDocumentedBlock(): void {
  }

}

/**
 * One of two annotated blocks claiming the same id.
 *
 * @Block(
 *   id = "corpus_annotated_twin",
 * )
 */
final class CorpusAnnotatedTwinBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}

/**
 * The other one.
 *
 * @Block(
 *   id = "corpus_annotated_twin",
 * )
 */
final class CorpusAnnotatedOtherTwinBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}

/**
 * An abstract base never registers an id.
 *
 * @Block(
 *   id = "corpus_abstract_annotated",
 * )
 */
abstract class CorpusAbstractAnnotatedBlock extends BlockBase {}
