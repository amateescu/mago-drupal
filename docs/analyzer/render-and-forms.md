# Render arrays and forms

The analyzer accepts a response from a form's `buildForm()`, and checks the arguments that
`getForm()` passes on to it. The checks report callbacks in render and form arrays that can't be
called, trusted or serialized by core, and cacheable dependencies that can't have cacheability.

## Form responses

`FormBuilder::retrieveForm()` accepts a `Response` from a form's `buildForm()`. It throws an
`EnforcedResponseException`, and Drupal sends that response instead of the page. Confirmation forms
use this to redirect when there is nothing left to confirm. `FormInterface::buildForm()` documents
an array, so Mago reports the return as `invalid-return-statement`. The analyzer drops that report
when the method belongs to a class that implements `FormInterface`, and every type that it returns
there is a `Response`.

```php
public function buildForm(array $form, FormStateInterface $form_state) {
  if ($this->selection === []) {
    // Not reported.
    return new RedirectResponse($this->getCancelUrl()->toString());
  }
  // ...
}
```

Mago also reports the return under a native `RedirectResponse|array`, since the inherited docblock
narrows the native type, and that report is dropped as well.

**Still reported:**

- a return under a native type that doesn't allow the response, such as `: array`. PHP throws a
  `TypeError` on that return before Drupal sees the response;
- any other returned value;
- a `buildForm()` on a class that isn't a form.

The method and the returned types are read from the wording of Mago's report. If Mago words the
report differently, the report shows again rather than staying hidden.

## Form arguments

`FormBuilderInterface::getForm($form_arg, mixed ...$args)` hands `$args` to the form's
`buildForm($form, $form_state, ...$args)`. When the first argument names a form class, the analyzer
gives the call the parameters of that class's `buildForm()` after the form state, so Mago checks the
arguments as it does on a direct call. The reports have Mago's own codes, such as
`too-many-arguments`, `too-few-arguments`, `invalid-argument`, `invalid-named-argument`,
`possibly-null-argument` and `mixed-argument`, and name the form's method:

```php
// buildForm(array $form, FormStateInterface $form_state, int $nid = 0)
$this->formBuilder->getForm('Drupal\x\Form\NodeIdForm', 'one');      // invalid-argument
$this->formBuilder->getForm('Drupal\x\Form\NodeIdForm', 1, 'extra'); // too-many-arguments
```

The first argument names the class as a fully qualified `\Drupal\x\Form\Foo::class` or as a
single-quoted class name, as in `\Drupal::formBuilder()->getForm('Drupal\x\Form\Foo', $arg)`. The
class must be a concrete class that implements `FormInterface`, with every ancestor scanned.

**Not checked,** so the call keeps core's signature:

- a relative name such as `Foo::class`. Mago asks for the signature before it analyzes the
  arguments, and the request has the text of the call but not the `use` imports of the file. The
  name could be an import alias for a different class, even when only one scanned class has that
  short name;
- a first argument that is `self::class`, `static::class`, a double-quoted string, computed, or an
  object;
- a call that unpacks an argument;
- a call to a form whose `buildForm()` doesn't take anything after the form state. Such a form reads
  what it is passed from `$form_state->getBuildInfo()['args']`, if at all, as core's
  `ThemeExperimentalConfirmForm` does, and alter hooks can read it there too. Its calls take any
  number of arguments.

The count is checked as on a direct call. PHP drops extra arguments to a method, so
`too-many-arguments` marks an argument that the form never reads. The arguments are passed by
value, since they go through the variadic parameter of `getForm()`, and a named argument goes to the
`buildForm()` parameter of that name, as `call_user_func_array()` does. The types are the native
and docblock types of the parameters, so a wrong docblock is reported at the `getForm()` call, as it
would be on a direct call.

## Render and form callbacks

Core runs the callbacks in a render or form array in four ways, and each one fails differently:

- The renderer passes `#pre_render`, `#post_render`, `#lazy_builder` and `#access_callback` through
  the callable resolver and `doTrustedCallback()`. That call throws an `UntrustedCallbackException`
  for a method that it doesn't trust. The date elements (`#date_date_callbacks`,
  `#date_time_callbacks`) and the component element (`#propsAlter`, `#slotsAlter`) call
  `doTrustedCallback()` on a plain PHP callable.
- The Form API passes `#validate`, `#submit`, `#element_validate`, `#process`, `#after_build`,
  `#entity_builders` and the `callback` of `#ajax` through `FormState::prepareCallback()`, which
  turns `'::method'` into a method of the form object, and then through the callable resolver,
  which throws for anything that it can't call.
- `#value_callback` runs only when `is_callable()` accepts it. Otherwise the element's default value
  callback runs, without a warning.
- The machine name element passes the `exists` of `#machine_name` to `call_user_func()`, and the
  managed file element calls each of `#file_value_callbacks` as it is. Neither resolves or trusts
  anything, and both throw for anything that PHP can't call.

The callback checks below report an error where core throws, and a warning on `#value_callback`,
where core silently skips the callback. They read the callbacks written under those keys:

- in an array literal, as in `'#submit' => ['::save']`;
- in an assignment or an append, as in `$form['actions']['submit']['#submit'][] = '::save'`;
- in `array_unshift()` and `array_push()` on the key;
- in the literal lists that an `array_merge()` takes;
- in the `callback` of an `#ajax` array and the `exists` of a `#machine_name` array.

A callback counts when it names its target literally:

```php
'::save'                              // a method of the form object
'mymodule_form_submit'                // a function
'Drupal\mymodule\Helper::build'       // a class method, a leading backslash allowed
[Helper::class, 'build']              // the same, also as ['Drupal\mymodule\Helper', 'build']
[static::class, 'build']              // self::class, __CLASS__, get_class($this) and
static::class . '::build'             //   get_called_class() work the same way
[$class, 'build']                     // after $class = get_class($this), or any of the above
[$this, 'build']                      // an object; [$object, 'build'] when its type is one class
```

`[$class, 'build']` counts when the function assigns `$class` once, with `=`, and no `foreach` or
reference binds it. Otherwise the type of the variable decides, as for an object: a class-string
that names one class, such as a parameter declared `class-string<Helper>`, counts as that class or a
subclass.

`Foo::class`, `self::class` and a string name that exact class. `static::class`,
`get_class($this)`, `$this` and an object may be a subclass at runtime, so the class and every
descendant in the codebase are asked, and one that has the method, or trusts it, keeps the callback
quiet.

**Not checked:**

- a `service:method` string, since the container may return a decorator or a lazy proxy;
- closures, first-class callables, variables and anything computed. A closure in a form is
  [`drupal/form-closure-callback`](#drupalform-closure-callback), and a value whose type can never
  be called is [`drupal/invalid-callback`](#drupalinvalid-callback);
- a class not scanned by Mago, or one whose hierarchy couldn't be resolved;
- the `form_submit` of a batch set. `_batch_next_set()` checks it with a plain `is_callable()`
  before it resolves it, so a `'::method'` or a `'Class::method'` that names an instance method is
  silently skipped there;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).
  Tests build broken callbacks on purpose to exercise core's errors.

The lint rule [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback) reports a
plain function name under the render, date and component keys that is written in an array literal.

---

## drupal/unknown-callback

- **Level:** error, or warning on `#value_callback`
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule` (partly)

A callback that names a method or a function that doesn't exist throws when core runs it, or is
silently skipped on `#value_callback`.

**Reports:**

- a callback that names a method its class doesn't have. A method from a parent or a trait counts,
  and a class with `__call()` or `__callStatic()` has every method;
- a `'::method'` on a form key, written in a class that implements `FormInterface`, when that class
  doesn't have such a method. The form object is the class that the callback is written in;
- a `'::method'` on any other key, wherever it is written. Only `prepareCallback()` turns
  `'::method'` into a method, so on other keys it doesn't name a class at all;
- a plain function name on the form keys, on `#value_callback`, on `exists` and on
  `#file_value_callbacks`, when the function would be in the codebase if it existed. That is a name
  that starts with `<module>_` or `_<module>_`, after the module directory that the file is in, when
  no file of that module declares it. The module's procedural files and its `.php` files outside
  `src` are read from disk, so a function in a file that the run leaves out still counts.

**Not reported:**

- a `'::method'` on a form key in a trait, a hook implementation, a form alter or any other class
  that doesn't implement `FormInterface`. The form object is some other class there;
- a plain function name on the render, date and component keys. `doTrustedCallback()` rejects every
  one of them, and [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback)
  reports those written in an array literal, such as `'#pre_render' => ['my_function']`. One
  appended or assigned to such a key isn't reported;
- a function named after another module, since that module may not be in the run. That includes a
  module whose longer name also starts the function's name, such as `foo_bar_submit()` written in
  `foo` while a `foo_bar` module exists;
- on the form keys, a name that the services files define, since the callable resolver runs a
  service through its `__invoke()`;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php
    // Reported.
    function example_build_form(array $form): array {
      $form['#submit'][] = 'example_form_sumbit';
      return $form;
    }

    function example_form_submit(array &$form): void {
    }

    // Fine.
    function example_build_form(array $form): array {
      $form['#submit'][] = 'example_form_submit';
      return $form;
    }

    function example_form_submit(array &$form): void {
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks only the six render keys `#pre_render`, `#post_render`,
      `#access_callback`, `#lazy_builder`, `#date_date_callbacks` and `#date_time_callbacks`. It
      doesn't check the form keys, `#value_callback`, `exists`, `#file_value_callbacks` or the
      component keys.
    - phpstan-drupal reads a callback only from an array literal, not from an assignment, an append,
      `array_unshift()`, `array_push()` or `array_merge()`.
    - phpstan-drupal resolves a `service:method` string through the service map and checks the
      service's class. The check leaves it alone, since the container may return a decorator or a
      lazy proxy.
    - phpstan-drupal also reports in test code.

---

## drupal/non-static-callback

- **Level:** error, or warning on `#value_callback`
- **Source:** PHP's rules for callables

PHP 8 rejects a static call to an instance method, so a callback written as one throws, or is
skipped on `#value_callback`.

**Reports:**

- an array written as a static call that names an instance method. Examples are
  `[static::class, 'method']`, `[Helper::class, 'method']` and
  `['Drupal\mymodule\Helper', 'method']`;
- a `'Class::method'` string that names an instance method, on the date and component keys, which
  take a PHP callable, on `exists`, on `#file_value_callbacks` and on `#value_callback`.

**Not reported:**

- a `'Class::method'` string that names an instance method on the render and form keys. The callable
  resolver creates an instance of the class for it, so the method runs;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php title="modules/example/src/ExampleElement.php"
    // Reported.
    namespace Drupal\example;

    class ExampleElement {

      public function build(): array {
        return ['#type' => 'container', '#process' => [[static::class, 'process']]];
      }

      public function process(array $element): array {
        return $element;
      }

    }

    // Fine.
    namespace Drupal\example;

    class ExampleElement {

      public function build(): array {
        return ['#type' => 'container', '#process' => [[static::class, 'process']]];
      }

      public static function process(array $element): array {
        return $element;
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal's render callback rule reports such an array, since PHPStan doesn't find a static
    call to an instance method callable. It reads only the six render keys `#pre_render`,
    `#post_render`, `#access_callback`, `#lazy_builder`, `#date_date_callbacks` and
    `#date_time_callbacks`, and only array literals. It doesn't check the form keys, the component
    keys, `exists`, `#file_value_callbacks` or `#value_callback`.

---

## drupal/non-public-callback

- **Level:** error, or warning on `#value_callback`
- **Source:** PHP's rules for callables

Core calls every callback from its own classes: the callable resolver, `is_callable()` in the form
builder, a `callable` parameter of the date and component elements, or the machine name and managed
file elements. They can't reach a protected or private method, so such a callback throws, or
is skipped on `#value_callback`.

**Reports:**

- a callback that names a protected or private method, a `'::method'` of a form included.

**Not reported:**

- a callback on a class with `__call()` or `__callStatic()`, since PHP sends the call there;
- a callback that names `static::class`, `get_class($this)`, `$this` or an object, when a subclass
  in the codebase makes the method public;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php title="modules/example/src/Form/ExampleForm.php"
    // Reported.
    namespace Drupal\example\Form;

    use Drupal\Core\Form\FormInterface;
    use Drupal\Core\Form\FormStateInterface;

    class ExampleForm implements FormInterface {

      public function getFormId(): string {
        return 'example_form';
      }

      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['#validate'][] = '::validateName';
        return $form;
      }

      protected function validateName(array &$form, FormStateInterface $form_state): void {
      }

      public function validateForm(array &$form, FormStateInterface $form_state): void {
      }

      public function submitForm(array &$form, FormStateInterface $form_state): void {
      }

    }

    // Fine.
    namespace Drupal\example\Form;

    use Drupal\Core\Form\FormInterface;
    use Drupal\Core\Form\FormStateInterface;

    class ExampleForm implements FormInterface {

      public function getFormId(): string {
        return 'example_form';
      }

      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['#validate'][] = '::validateName';
        return $form;
      }

      public function validateName(array &$form, FormStateInterface $form_state): void {
      }

      public function validateForm(array &$form, FormStateInterface $form_state): void {
      }

      public function submitForm(array &$form, FormStateInterface $form_state): void {
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't report it. Its render callback rule reports a callback not found callable
    by PHPStan, and PHPStan finds a protected or private method callable, from inside the class and
    from outside it.

---

## drupal/untrusted-callback

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the part that needs the class

phpstan-drupal reports a [render callback](https://www.drupal.org/node/2966725) not trusted by core.
Core's `doTrustedCallback()` throws an `UntrustedCallbackException` for it. Core trusts a method
when:

- the class implements the extra interface that the caller passes: `RenderCallbackInterface` for
  the renderer's keys, and none for the date and component keys;
- the class implements `TrustedCallbackInterface`, and its `trustedCallbacks()` lists the method;
- the method has the `#[TrustedCallback]` attribute. Core reads the attribute by reflection off the
  method that the class has, so an override that doesn't repeat it isn't trusted, see
  [`drupal/trusted-callback-override`](#drupaltrusted-callback-override).

The check reads `trustedCallbacks()` from its body. It understands `return` with a list of string
literals, `parent::trustedCallbacks()` or an `array_merge()` of those, and a variable set to one of
them, grown with `$callbacks[] = 'name'` and returned. Any other body counts as trusting every
method, since its result can't be known without running it. Core compares the names in the list
case-sensitively, and so does the check.

**Reports:**

- a callback under a render, date or component key that names a class method not trusted by core.
  The render keys are `#pre_render`, `#post_render`, `#lazy_builder` and `#access_callback`, the
  date keys `#date_date_callbacks` and `#date_time_callbacks`, and the component keys `#propsAlter`
  and `#slotsAlter`.

**Not reported:**

- a plain function name. Core rejects every one there, and
  [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback) reports those written
  in an array literal;
- a callback that names `static::class`, `get_class($this)`, `$this` or an object, when a subclass
  in the codebase trusts the method;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php title="modules/example/src/ExampleBuilder.php"
    // Reported.
    namespace Drupal\example;

    class ExampleBuilder {

      public function build(): array {
        return ['#markup' => '', '#pre_render' => [[self::class, 'addClass']]];
      }

      public static function addClass(array $element): array {
        return $element;
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Security\Attribute\TrustedCallback;

    class ExampleBuilder {

      public function build(): array {
        return ['#markup' => '', '#pre_render' => [[self::class, 'addClass']]];
      }

      #[TrustedCallback]
      public static function addClass(array $element): array {
        return $element;
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal trusts every method of a class that implements `TrustedCallbackInterface`,
      whatever its `trustedCallbacks()` lists. The check reads the list.
    - phpstan-drupal trusts a class that implements `RenderCallbackInterface` on the date keys too,
      where core doesn't trust any extra interface.
    - phpstan-drupal doesn't check the component keys `#propsAlter` and `#slotsAlter`.
    - phpstan-drupal reads a callback only from an array literal, not from an assignment, an append,
      `array_unshift()`, `array_push()` or `array_merge()`.
    - phpstan-drupal resolves a `service:method` string through the service map and checks the
      service's class. The check leaves it alone.
    - phpstan-drupal also reports in test code.

---

## drupal/form-closure-callback

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the closure check

phpstan-drupal reports a closure under a render callback key in a form. Drupal serializes a form
cached between requests, as for an AJAX rebuild, and PHP can't serialize a closure.

**Reports:**

- a closure, an arrow function or a first-class callable under a render or date key, written in a
  class that implements `FormInterface`. The keys are `#pre_render`, `#post_render`,
  `#lazy_builder`, `#access_callback`, `#date_date_callbacks` and `#date_time_callbacks`. A
  first-class callable looks like `$this->build(...)`.

**Not reported:**

- a closure under the form API keys, such as `#submit`, and under the component keys, as in
  phpstan-drupal's rule;
- a closure written outside a class that implements `FormInterface`, such as in a form alter hook;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php title="modules/example/src/Form/ExampleForm.php"
    // Reported.
    namespace Drupal\example\Form;

    use Drupal\Core\Form\FormInterface;
    use Drupal\Core\Form\FormStateInterface;

    class ExampleForm implements FormInterface {

      public function getFormId(): string {
        return 'example_form';
      }

      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['preview'] = [
          '#markup' => '',
          '#pre_render' => [fn (array $element): array => $element],
        ];
        return $form;
      }

      public function validateForm(array &$form, FormStateInterface $form_state): void {
      }

      public function submitForm(array &$form, FormStateInterface $form_state): void {
      }

    }

    // Fine.
    namespace Drupal\example\Form;

    use Drupal\Core\Form\FormInterface;
    use Drupal\Core\Form\FormStateInterface;
    use Drupal\Core\Security\Attribute\TrustedCallback;

    class ExampleForm implements FormInterface {

      public function getFormId(): string {
        return 'example_form';
      }

      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['preview'] = [
          '#markup' => '',
          '#pre_render' => [[static::class, 'preRenderPreview']],
        ];
        return $form;
      }

      #[TrustedCallback]
      public static function preRenderPreview(array $element): array {
        return $element;
      }

      public function validateForm(array &$form, FormStateInterface $form_state): void {
      }

      public function submitForm(array &$form, FormStateInterface $form_state): void {
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal reads a callback only from an array literal. The check also reads a closure
    assigned or appended to the key, or added with `array_unshift()`, `array_push()` or
    `array_merge()`.

---

## drupal/invalid-callback

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the check of the value's type

phpstan-drupal reports a value under a render callback key whose type may not be callable. Core
hands such a value to `doTrustedCallback()`, which throws for anything that PHP can't call.

**Reports:**

- a value under a render, date or component key whose type PHP can never call: an int, a float, a
  bool, null, an array that can't be a two-element pair, or an object without `__invoke()`. Every
  member of a union must be one of them, as in `?int`.

The keys are `#pre_render`, `#post_render`, the first element of `#lazy_builder`,
`#access_callback`, `#date_date_callbacks`, `#date_time_callbacks`, `#propsAlter` and
`#slotsAlter`. An object counts when its class doesn't have `__invoke()`, and no descendant in the
codebase has it either, unless the class is final.

**Not reported:**

- a string, which may name a function or a method, and `mixed`, which may be anything. So a callback
  read from config or passed in as a string stays quiet;
- a closure, a `callable`, an array that may be a pair, such as `[$object, $method]` or
  `array<int, string>`, and an object of a class not scanned by Mago;
- the callbacks that the other checks read, such as `[Helper::class, 'build']`;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php
    // Reported.
    function example_build(?int $weight): array {
      return [
        '#markup' => '',
        '#pre_render' => [$weight],
      ];
    }

    // Fine.
    function example_build(callable $callback): array {
      return [
        '#markup' => '',
        '#pre_render' => [$callback],
      ];
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reports any value that may not be callable, so it also reports a `string`, a
      `?string`, `mixed` and an array such as `array<int, string>`. Core runs a string from config
      or from a parameter when it names a trusted callback, so the check leaves those alone.
    - phpstan-drupal reports `[$class, 'method']` with a `string $class` as not callable. The check
      leaves it alone too.
    - phpstan-drupal reports an array that isn't a pair, such as
      `[Helper::class, 'build', 'extra']`, under its `renderCallback.nonCallableCallback`
      identifier. The check reports it with the other values that can never be called.
    - phpstan-drupal reads a callback only from an array literal, and doesn't check the component
      keys. The check also reads a value assigned or appended to the key, or added with
      `array_unshift()`, `array_push()` or `array_merge()`.

---

## drupal/trusted-callback-override

- **Level:** warning
- **Source:** Drupal's [`#[TrustedCallback]`](https://www.drupal.org/node/3349470) attribute

PHP doesn't inherit attributes, and core reads `#[TrustedCallback]` by reflection off the method
that the class has. So a method that overrides a trusted callback without repeating the attribute
isn't trusted as a render callback.

**Reports:**

- a method that overrides one with `#[TrustedCallback]`, declared in a parent class or in a
  trait that a parent uses, when the override doesn't repeat the attribute. The report is a warning,
  since the method may never be a callback on the subclass.

**Not reported:**

- a class that implements `RenderCallbackInterface`, which trusts all its methods as render
  callbacks;
- a class whose `trustedCallbacks()` lists the method, or can't be read.

The classes and traits that declare a method with the attribute are read from the PHP files of
`core/lib`, `core/tests` and the `src` directory of every module, profile and theme before the
analysis starts. The descendants of those classes are checked, and for a trait, the descendants of
the classes that use it.

??? example "Example"

    ```php title="modules/example/src/ExampleBase.php"
    namespace Drupal\example;

    use Drupal\Core\Security\Attribute\TrustedCallback;

    class ExampleBase {

      #[TrustedCallback]
      public static function preRender(array $element): array {
        return $element;
      }

    }
    ```

    ```php title="modules/example/src/ExampleChild.php"
    // Reported.
    namespace Drupal\example;

    class ExampleChild extends ExampleBase {

      public static function preRender(array $element): array {
        return $element;
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Security\Attribute\TrustedCallback;

    class ExampleChild extends ExampleBase {

      #[TrustedCallback]
      public static function preRender(array $element): array {
        return $element;
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't check overrides of a trusted callback.

---

## drupal/cacheable-dependency

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `CacheableDependencyRule`

phpstan-drupal reports a cacheable dependency that can't have cacheability. Core treats a dependency
that doesn't implement `CacheableDependencyInterface` as uncacheable, so the object that it is added
to drops to max-age 0.

**Reports:**

- an `addCacheableDependency()` call whose dependency is a scalar, an array, null, or a final class
  that doesn't implement `CacheableDependencyInterface`. The calls checked are those on a
  `RefinableCacheableDependencyInterface`, a `CacheableResponseInterface` or a `ContextInterface`,
  which take the dependency first, and on a `RendererInterface`, which takes it second, as
  `$dependency`.

**Not reported:**

- a dependency typed as `mixed`, a bare `object`, an interface, a class not scanned by Mago, or a
  class that can be extended. The value passed may be a subclass that implements the interface, as
  core's `CacheableHttpException` does.

??? example "Example"

    ```php
    // Reported.
    use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
    use Drupal\Core\Config\ImmutableConfig;

    function example_add_site(RefinableCacheableDependencyInterface $metadata, ImmutableConfig $config): void {
      $metadata->addCacheableDependency((string) $config->get('name'));
    }

    // Fine.
    use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
    use Drupal\Core\Config\ImmutableConfig;

    function example_add_site(RefinableCacheableDependencyInterface $metadata, ImmutableConfig $config): void {
      $metadata->addCacheableDependency($config);
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal reads the renderer's dependency as its second argument by position, so a call
    that names its arguments in another order is checked against the render array. The check
    matches the `dependency:` name.
