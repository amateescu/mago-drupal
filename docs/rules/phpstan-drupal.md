# Ported from phpstan-drupal

These rules port checks of [phpstan-drupal](https://github.com/mglaman/phpstan-drupal) that need
only the syntax.

---

## drupal/discouraged-function

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard and phpstan-drupal
- **Ports:**
    - `Drupal.Functions.DiscouragedFunctions.Discouraged`
    - phpstan-drupal's `DiscouragedFunctionsRule`

Coder and phpstan-drupal report calls to debug helpers and to functions that aren't always
available.

**Reports:**

- a call to a dump helper of the devel module, such as `dpm()`, `dsm()`, `ksm()` or `kint()`. The
  devel helpers are the ones listed by Coder 9. A first-class callable such as `dpm(...)` counts as
  a call;
- a call to `fnmatch()`, which some PHP builds don't have.

Coder 9 also lists `eval`, which is reported by Mago's own `no-eval` rule.

??? note "Compared with Coder and phpstan-drupal"

    - phpstan-drupal's list leaves out `ddebug_backtrace()`, `ddm()`, `kint()` and `ksm()`, and it
      doesn't report a first-class callable such as `dpm(...)`.
    - Coder reports each call as a warning, where the rule reports an error.

---

## drupal/render-callback

- **Level:** error
- **Fix:** none
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the part that needs only the syntax

phpstan-drupal reports a [render callback](https://www.drupal.org/node/2966725) not trusted by
Drupal. Drupal trusts only closures, `service:method` strings and class methods.

**Reports:**

- a render callback that is a plain function name string, in the callbacks of:
    - `#pre_render`, `#post_render`, `#lazy_builder` and `#access_callback`;
    - the date callbacks `#date_date_callbacks` and `#date_time_callbacks`;
    - the component callbacks `#propsAlter` and `#slotsAlter`;
- a value of those keys that isn't an array literal at all, such as `'#pre_render' => $callbacks`,
  because nothing can be checked there.

**Not reported:**

- `#lazy_builder` in core's `Renderer` and `PlaceholderGenerator`, because they pass the key
  through `array_intersect_key()`.

??? example "Example"

    ```php
    // Reported.
    $build = [
      '#markup' => '',
      '#pre_render' => ['example_pre_render'],
    ];

    // Fine.
    $build = [
      '#markup' => '',
      '#pre_render' => [static fn (array $element): array => $element],
    ];
    ```

Whether Drupal trusts the class method that a callback names is the analyzer's
`drupal/untrusted-callback`, and a closure under these keys in a form is its
`drupal/form-closure-callback`. See [Render and form callbacks](../analyzer/render-and-forms.md#render-and-form-callbacks).

??? note "Compared with phpstan-drupal"

    - phpstan-drupal doesn't check the component callbacks `#propsAlter` and `#slotsAlter`.

---

## drupal/symfony-yaml-parse

- **Level:** warning
- **Fix:** none
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `SymfonyYamlParseRule`

phpstan-drupal reports a parse with Symfony's YAML component. Drupal's own
`\Drupal\Component\Serialization\Yaml::decode()` picks the fast parser when it is available and
applies Drupal's parser flags.

**Reports:**

- a `Symfony\Component\Yaml\Yaml::parse()` call.

??? example "Example"

    ```php
    // Reported.
    use Symfony\Component\Yaml\Yaml;

    $data = Yaml::parse($text);

    // Fine.
    use Drupal\Component\Serialization\Yaml;

    $data = Yaml::decode($text);
    ```

---

## drupal/todo-current-issue

- **Level:** error
- **Fix:** none
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `TodoCommentWithIssueUrlRule`

phpstan-drupal reports a `@todo` comment that links the drupal.org issue of the current merge
request. A merge request should resolve its own to-dos rather than land them.

**Reports:**

- a `@todo` comment that links the issue as `https://www.drupal.org/i/3456789`,
  `https://www.drupal.org/node/3456789` or
  `https://www.drupal.org/project/mymodule/issues/3456789`.

**Not reported:**

- anything outside a GitLab merge request pipeline. The issue number comes from the variables set by
  GitLab CI there: the digits at the start of `CI_MERGE_REQUEST_SOURCE_BRANCH_NAME`, as in
  `3456789-fix-thing`, or at the end of `CI_MERGE_REQUEST_SOURCE_PROJECT_PATH`, as in
  `issue/mymodule-3456789`. Without them, the rule has nothing to compare, so it is on by default,
  where phpstan-drupal's rule has to be added by hand.

??? note "Compared with phpstan-drupal"

    - phpstan-drupal doesn't read a `https://www.drupal.org/node/3456789` link as the issue. The
      rule does.
