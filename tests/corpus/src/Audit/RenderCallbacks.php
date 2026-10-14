<?php

/**
 * @file
 * Render callbacks core trusts and ones it rejects.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Render\Element\RenderCallbackInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Trusts one method through the attribute.
 */
class AttributeCallbacks {

  /**
   * Trusted by its attribute.
   */
  #[TrustedCallback]
  public static function preRender(array $element): array {
    return $element;
  }

  /**
   * Trusted by nothing.
   */
  public static function plain(array $element): array {
    return $element;
  }

}

/**
 * Overrides the trusted method and leaves the attribute off.
 */
class DroppedAttributeCallbacks extends AttributeCallbacks {

  /**
   * PHP does not inherit the attribute, so core does not trust this.
   */
  // @mago-expect analysis:drupal/trusted-callback-override
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * Overrides the trusted method and repeats the attribute.
 */
class KeptAttributeCallbacks extends AttributeCallbacks {

  /**
   * Still trusted.
   */
  #[TrustedCallback]
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * Overrides the trusted method without the attribute, but lists it.
 */
class ListedOverrideCallbacks extends AttributeCallbacks implements TrustedCallbackInterface {

  /**
   * Lists the override.
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  /**
   * Trusted through the list.
   */
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * Overrides the trusted method without the attribute in an element class.
 */
class ElementOverrideCallbacks extends AttributeCallbacks implements RenderCallbackInterface {

  /**
   * Every method of a RenderCallbackInterface class is trusted for render.
   */
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * Trusts the methods its trustedCallbacks() lists.
 */
class ListedCallbacks implements TrustedCallbackInterface {

  /**
   * One literal name.
   */
  public static function trustedCallbacks(): array {
    return ['listed'];
  }

  /**
   * Listed.
   */
  public static function listed(array $element): array {
    return $element;
  }

  /**
   * Not listed.
   */
  public static function unlisted(array $element): array {
    return $element;
  }

}

/**
 * Adds to its parent's list.
 */
class GrownListCallbacks extends ListedCallbacks {

  /**
   * The parent's list and one more.
   */
  public static function trustedCallbacks(): array {
    $callbacks = parent::trustedCallbacks();
    $callbacks[] = 'grown';
    return $callbacks;
  }

  /**
   * Listed here.
   */
  public static function grown(array $element): array {
    return $element;
  }

}

/**
 * Merges its parent's list.
 */
class MergedListCallbacks extends ListedCallbacks {

  /**
   * The parent's list and one more.
   */
  public static function trustedCallbacks(): array {
    return array_merge(parent::trustedCallbacks(), ['merged']);
  }

  /**
   * Listed here.
   */
  public static function merged(array $element): array {
    return $element;
  }

}

/**
 * Lists a method under another case than its declaration.
 */
class LowercaseListCallbacks implements TrustedCallbackInterface {

  /**
   * Core compares the names case-sensitively.
   */
  public static function trustedCallbacks(): array {
    return ['prerender'];
  }

  /**
   * Not listed under this spelling.
   */
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * The root of a long chain of lists.
 */
class DeepList0 implements TrustedCallbackInterface {

  /**
   * One literal name.
   */
  public static function trustedCallbacks(): array {
    return ['deepListed'];
  }

  /**
   * Listed at the root.
   */
  public static function deepListed(array $element): array {
    return $element;
  }

  /**
   * Listed nowhere in the chain.
   */
  public static function deepUnlisted(array $element): array {
    return $element;
  }

}

/**
 * Level 1 of the chain, which merges its parent's list.
 */
class DeepList1 extends DeepList0 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 2 of the chain, which merges its parent's list.
 */
class DeepList2 extends DeepList1 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 3 of the chain, which merges its parent's list.
 */
class DeepList3 extends DeepList2 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 4 of the chain, which merges its parent's list.
 */
class DeepList4 extends DeepList3 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 5 of the chain, which merges its parent's list.
 */
class DeepList5 extends DeepList4 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 6 of the chain, which merges its parent's list.
 */
class DeepList6 extends DeepList5 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 7 of the chain, which merges its parent's list.
 */
class DeepList7 extends DeepList6 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 8 of the chain, which merges its parent's list.
 */
class DeepList8 extends DeepList7 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Level 9 of the chain, which merges its parent's list.
 */
class DeepList9 extends DeepList8 {

  /**
   * The parent's list.
   */
  public static function trustedCallbacks(): array {
    return parent::trustedCallbacks();
  }

}

/**
 * Builds its list in a way no one can read without running it.
 */
class ComputedListCallbacks implements TrustedCallbackInterface {

  private const CALLBACKS = ['computed'];

  /**
   * Not a literal list.
   */
  public static function trustedCallbacks(): array {
    return self::CALLBACKS;
  }

  /**
   * Maybe listed.
   */
  public static function computed(array $element): array {
    return $element;
  }

  /**
   * Maybe listed too, as far as a reader can tell.
   */
  public static function other(array $element): array {
    return $element;
  }

}

/**
 * Trusted for render through the extra interface the renderer passes.
 */
class ElementCallbacks implements RenderCallbackInterface {

  /**
   * Trusted by the renderer, not by the date elements.
   */
  public static function anything(array $element): array {
    return $element;
  }

}

/**
 * A trait whose method carries the attribute.
 */
trait TrustedTraitCallbacks {

  /**
   * Trusted by its attribute.
   */
  #[TrustedCallback]
  public static function fromTrait(array $element): array {
    return $element;
  }

}

/**
 * Takes the trusted method from the trait.
 */
class TraitHostCallbacks {

  use TrustedTraitCallbacks;

}

/**
 * Overrides the trait's trusted method and leaves the attribute off.
 */
class TraitOverrideCallbacks extends TraitHostCallbacks {

  /**
   * PHP does not inherit the attribute, so core does not trust this.
   */
  // @mago-expect analysis:drupal/trusted-callback-override
  public static function fromTrait(array $element): array {
    return $element;
  }

}

/**
 * Methods core cannot reach from its own classes.
 */
class HiddenCallbacks {

  /**
   * Protected.
   */
  #[TrustedCallback]
  protected static function protectedRender(array $element): array {
    return $element;
  }

  /**
   * Private, so only a callback names it.
   */
  // @mago-expect analysis:unused-method
  #[TrustedCallback]
  private static function privateRender(array $element): array {
    return $element;
  }

  /**
   * Protected here, public in the subclass.
   */
  #[TrustedCallback]
  protected function widened(array $element): array {
    return $element;
  }

}

/**
 * Makes a protected method public.
 */
final class WidenedCallbacks extends HiddenCallbacks {

  /**
   * Public.
   */
  #[TrustedCallback]
  public function widened(array $element): array {
    return $element;
  }

}

/**
 * Names a method only its subclass declares.
 */
abstract class ChildOnlyCallbacks {

  /**
   * The subclass the callback runs on has the method.
   */
  public function build(): array {
    return ['#pre_render' => [[static::class, 'childOnly']]];
  }

}

/**
 * Declares the method its parent names.
 */
final class ChildOnlyChild extends ChildOnlyCallbacks {

  /**
   * Trusted by the attribute.
   */
  #[TrustedCallback]
  public static function childOnly(array $element): array {
    return $element;
  }

}

/**
 * Leaves its own method untrusted; the subclass lists it.
 */
abstract class LateBoundCallbacks {

  /**
   * Written against $this, so a subclass can trust it.
   */
  public function build(): array {
    return ['#pre_render' => [[$this, 'lateRender']]];
  }

  /**
   * Untrusted here.
   */
  public function lateRender(array $element): array {
    return $element;
  }

}

/**
 * Trusts the method its parent names through $this.
 */
final class LateBoundChild extends LateBoundCallbacks implements TrustedCallbackInterface {

  /**
   * Lists the parent's method.
   */
  public static function trustedCallbacks(): array {
    return ['lateRender'];
  }

}

/**
 * Builds render arrays with trusted and untrusted callbacks.
 */
class RenderCallbackBuilder {

  /**
   * Callbacks core accepts.
   */
  public function trusted(callable $callback): array {
    return [
      '#pre_render' => [
        [AttributeCallbacks::class, 'preRender'],
        [KeptAttributeCallbacks::class, 'preRender'],
        [ListedOverrideCallbacks::class, 'preRender'],
        [ElementOverrideCallbacks::class, 'preRender'],
        'Drupal\corpus\Audit\ListedCallbacks::listed',
        '\Drupal\corpus\Audit\GrownListCallbacks::grown',
        [GrownListCallbacks::class, 'listed'],
        [MergedListCallbacks::class, 'merged'],
        [ComputedListCallbacks::class, 'other'],
        [ElementCallbacks::class, 'anything'],
        [$this, 'ownTrusted'],
        [static::class, 'staticTrusted'],
        self::class . '::staticTrusted',
        'Drupal\corpus\Audit\RenderCallbackBuilder::ownTrusted',
        $callback,
        static fn (array $element): array => $element,
        'corpus.thing:render',
      ],
      '#lazy_builder' => [static::class . '::staticTrusted', []],
      '#post_render' => [[self::class, 'staticTrusted']],
      '#access_callback' => [AttributeCallbacks::class, 'preRender'],
    ];
  }

  /**
   * A method nothing trusts.
   */
  public function plainMethod(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[AttributeCallbacks::class, 'plain']],
    ];
  }

  /**
   * An override that lost the attribute.
   */
  public function droppedAttribute(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[DroppedAttributeCallbacks::class, 'preRender']],
    ];
  }

  /**
   * A class whose list, parent's included, leaves the method out.
   */
  public function unlisted(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => ['Drupal\corpus\Audit\GrownListCallbacks::unlisted'],
    ];
  }

  /**
   * A lazy builder named by a static::class string.
   */
  public function lazyBuilder(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#lazy_builder' => [static::class . '::staticUntrusted', []],
    ];
  }

  /**
   * The date elements trust no extra interface.
   */
  public function dateCallback(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#date_date_callbacks' => [[ElementCallbacks::class, 'anything']],
    ];
  }

  /**
   * A method of this class through $this, which no subclass trusts.
   */
  public function ownMethod(): array {
    $element = [];
    // @mago-expect analysis:drupal/untrusted-callback
    $element['#post_render'][] = [$this, 'ownUntrusted'];

    return $element;
  }

  /**
   * An object callback that no subclass trusts either.
   */
  public function accessCallback(ListedCallbacks $callbacks): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#access_callback' => [$callbacks, 'unlisted'],
    ];
  }

  /**
   * An object callback that a subclass may trust.
   *
   * A subclass implements RenderCallbackInterface, the way elements do.
   */
  public function subclassTrusts(AttributeCallbacks $callbacks): array {
    return ['#access_callback' => [$callbacks, 'plain']];
  }

  /**
   * A method listed under another case.
   */
  public function lowercaseListed(): array {
    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[LowercaseListCallbacks::class, 'preRender']],
    ];
  }

  /**
   * A class whose list is read through nine parents.
   */
  public function deepList(): array {
    return [
      '#post_render' => [[DeepList9::class, 'deepListed']],
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[DeepList9::class, 'deepUnlisted']],
    ];
  }

  /**
   * A variable holding static::class, as element info callbacks do.
   */
  public function classStringUntrusted(): array {
    $class = static::class;

    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[$class, 'staticUntrusted']],
    ];
  }

  /**
   * A variable holding a class name constant.
   */
  public function namedClassString(): array {
    $class = AttributeCallbacks::class;

    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#post_render' => [[$class, 'plain']],
    ];
  }

  /**
   * A class-string variable naming a missing method.
   */
  public function classStringMissing(): array {
    $class = static::class;

    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#pre_render' => [[$class, 'missingFromClassString']],
    ];
  }

  /**
   * A class-string variable naming an instance method, called statically.
   */
  public function classStringInstance(): array {
    $class = static::class;

    return [
      // @mago-expect analysis:drupal/non-static-callback
      '#pre_render' => [[$class, 'ownTrusted']],
    ];
  }

  /**
   * A variable set to get_class($this), as element info callbacks do.
   */
  public function assignedClassMissing(): array {
    $class = get_class($this);

    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#pre_render' => [[$class, 'missingFromAssignedClass']],
    ];
  }

  /**
   * A variable set to get_class($this) naming an instance method.
   */
  public function assignedClassInstance(): array {
    $class = get_class($this);

    return [
      // @mago-expect analysis:drupal/non-static-callback
      '#post_render' => [[$class, 'ownTrusted']],
    ];
  }

  /**
   * Variables that may hold another class by the time the callback runs.
   */
  public function reassignedClass(bool $other): array {
    $class = AttributeCallbacks::class;
    if ($other) {
      $class = get_class($this);
    }
    $build = ['#pre_render' => [[$class, 'preRender']]];
    $looped = get_class($this);
    foreach ([AttributeCallbacks::class] as $looped) {
      $build['#post_render'][] = [$looped, 'preRender'];
    }

    return $build;
  }

  /**
   * A class-string parameter, which may name a subclass.
   *
   * @param class-string<ListedCallbacks> $class
   *   The class.
   */
  public function classStringParameter(string $class): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#pre_render' => [[$class, 'missingFromParameter']],
    ];
  }

  /**
   * A variable set twice to the same class.
   */
  public function literalClassString(bool $again): array {
    $class = ListedCallbacks::class;
    if ($again) {
      $class = ListedCallbacks::class;
    }

    return [
      // @mago-expect analysis:drupal/untrusted-callback
      '#pre_render' => [[$class, 'unlisted']],
    ];
  }

  /**
   * Class-string variables naming trusted static methods.
   */
  public function classStringTrusted(): array {
    $class = static::class;
    $self = self::class;

    return ['#pre_render' => [[$class, 'staticTrusted'], [$self, 'staticTrusted']]];
  }

  /**
   * A protected method in a class array.
   */
  public function protectedArray(): array {
    return [
      // @mago-expect analysis:drupal/non-public-callback
      '#pre_render' => [[HiddenCallbacks::class, 'protectedRender']],
    ];
  }

  /**
   * A private method in a class string.
   */
  public function privateString(): array {
    return [
      // @mago-expect analysis:drupal/non-public-callback
      '#pre_render' => ['Drupal\corpus\Audit\HiddenCallbacks::privateRender'],
    ];
  }

  /**
   * A protected method of this class through $this.
   */
  public function protectedObject(): array {
    return [
      // @mago-expect analysis:drupal/non-public-callback
      '#access_callback' => [$this, 'ownProtected'],
    ];
  }

  /**
   * A protected method of this class through a static::class string.
   */
  public function protectedConcatenation(): array {
    return [
      // @mago-expect analysis:drupal/non-public-callback
      '#lazy_builder' => [static::class . '::staticProtected', []],
    ];
  }

  /**
   * A protected method a subclass makes public.
   */
  public function widenedObject(HiddenCallbacks $callbacks): array {
    return ['#pre_render' => [[$callbacks, 'widened']]];
  }

  /**
   * A form object method on a render key.
   */
  public function formObjectRender(): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#date_time_callbacks' => ['::staticTrusted'],
    ];
  }

  /**
   * A method the class does not have.
   */
  public function missingMethod(): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#pre_render' => [[AttributeCallbacks::class, 'missing']],
    ];
  }

  /**
   * An instance method in a static call.
   */
  public function instanceMethod(): array {
    return [
      // @mago-expect analysis:drupal/non-static-callback
      '#pre_render' => [[self::class, 'ownTrusted']],
    ];
  }

  /**
   * A string naming an instance method as a date callback.
   *
   * The date elements take a PHP callable, which such a string is not.
   */
  public function instanceString(): array {
    return [
      // @mago-expect analysis:drupal/non-static-callback
      '#date_time_callbacks' => ['Drupal\corpus\Audit\RenderCallbackBuilder::ownTrusted'],
    ];
  }

  /**
   * Trusted by the attribute.
   */
  #[TrustedCallback]
  public function ownTrusted(array $element): array {
    return $element;
  }

  /**
   * Trusted by the attribute.
   */
  #[TrustedCallback]
  public static function staticTrusted(array $element): array {
    return $element;
  }

  /**
   * Not trusted.
   */
  public function ownUntrusted(array $element): array {
    return $element;
  }

  /**
   * Not trusted.
   */
  public static function staticUntrusted(array $element): array {
    return $element;
  }

  /**
   * Trusted by the attribute, but protected.
   */
  #[TrustedCallback]
  protected function ownProtected(array $element): array {
    return $element;
  }

  /**
   * Trusted by the attribute, but protected.
   */
  #[TrustedCallback]
  protected static function staticProtected(array $element): array {
    return $element;
  }

}
