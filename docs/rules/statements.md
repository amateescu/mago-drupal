# Statements and declarations

Rules for switch statements, ternaries and returns, and for how methods, properties and parameters
are declared.

---

## drupal/case-break-blank-line

- **Level:** error
- **Fix:** safe. It adds the line, or removes the extra ones.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration.SpacingAfterBreak`

The standard has no sentence for this. Its switch example puts a blank line after the `break` that
ends each case.

**Reports:**

- a `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` that ends a switch case and
  isn't followed by exactly one blank line. A comment on the statement's line doesn't count, and one
  on a later line does.

**Not reported:**

- the last case before the closing brace or `endswitch`, and a `default` case;
- a case that falls through from a `default`.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported, on the first break.
    switch ($op) {
      case 'view':
        $access = TRUE;
        break;
      case 'edit':
        $access = FALSE;
        break;

      default:
        $access = NULL;
    }

    // Fine.
    switch ($op) {
      case 'view':
        $access = TRUE;
        break;

      case 'edit':
        $access = FALSE;
        break;

      default:
        $access = NULL;
    }
    ```

---

## drupal/case-fall-through

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `PSR2.ControlStructures.SwitchDeclaration.TerminatingComment`
- **Off with `--core`**

A case that runs on into the next one needs a comment there, so a reader can tell that the missing
`break` is on purpose.

**Reports:**

- a non-empty `case` that falls through to the next case with no comment right before that case.
  A comment of any kind counts, whatever it says, except a directive such as `// @mago-expect` or
  `// phpcs:ignore`.

**Not reported:**

- a case that a `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` ends;
- a case whose last statement ends it in every branch: an `if` with an `else`, a `try` with its
  `catch` blocks or with a `finally` block, or a nested `switch` with a `default` case;
- a `default` case, and the last case.

???+ example "Example"

    ```php
    // Reported, on the page case.
    switch ($type) {
      case 'page':
        $count++;
      case 'article':
        $count++;
        break;
    }

    // Fine.
    switch ($type) {
      case 'page':
        $count++;
        // Pages count twice.
      case 'article':
        $count++;
        break;
    }
    ```

??? note "Compared with Coder"

    - A case that ends in `$x or exit()` or `$x ?? throw ...` falls through when the left side
      allows it, and the rule reports it. Coder ends the case at the keyword.
    - A braceless `if (...) return; else return;` ends a case. Coder reports it.
    - A case whose body is a `{ }` block with no ending statement is reported. Coder stops with an
      internal exception for the whole file.
    - A `break` inside a nested `switch` doesn't end the outer case, as in Coder.
    - Coder accepts a directive such as `// phpcs:ignore` as the comment. The rule doesn't.

---

## drupal/case-semicolon

- **Level:** error
- **Fix:** safe. It writes the colon, and removes the blank space in front of the semicolon. A
  comment in front of it stays.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `PSR2.ControlStructures.SwitchDeclaration`: `WrongOpenercase` (partly), `WrongOpenerdefault` (partly)

The standard has no sentence for this. Its switch example ends each `case` and `default` label with
a colon.

**Reports:**

- a `case` or `default` label that ends with a semicolon, as in `case 1;`. The rule reads both the
  brace and the colon syntax, and checks the labels of a nested switch with that switch.

??? note "Compared with Coder"

    - Core's config runs only `WrongOpenercase`. The rule reports `default;` too, also under
      `--core`.
    - Mago's parser doesn't accept a label that ends with a close tag, as in `case 1 ?>`, which PHP
      accepts. Coder reports those, and the rule can't. Mago reports a parse error there and leaves
      that switch out, but still checks the rest of the file.
    - The fix writes the colon and removes the blank space in front of it in one pass. phpcbf
      needs a second pass for the space, which the sniff's `SpaceBeforeColonCASE` or
      `Squiz.ControlStructures.SwitchDeclaration.SpaceBeforeColonCase` removes. The result is the
      same.

---

## drupal/comment-in-expression

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:**
    - `Generic.Formatting.SpaceAfterCast.CommentFound`
    - `Generic.WhiteSpace.LanguageConstructSpacing.IncorrectYieldFromWithComment`

Coder doesn't allow a comment inside a cast expression or between `yield` and `from`.

**Reports:**

- a comment right after a cast, as in `(int) /* note */ $x`. Every cast spelling counts, `(void)`
  included, whatever the target PHP version;
- a comment between `yield` and `from`.

**Not reported:**

- a comment before the cast, after the operand or after `from`.

---

## drupal/else-if

- **Level:** error
- **Fix:** safe. It joins the keywords.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `PSR2.ControlStructures.ElseIfDeclaration.NotAllowed`

> (Note: Don't use "else if" -- always use elseif.)

**Reports:**

- an `else if` written as two keywords.

**Not reported:**

- a braced `else { if ... }`.

??? note "Compared with Coder"

    - Coder reports `else if` as a warning. The rule reports it as an error.

---

## drupal/empty-switch

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration.MissingCase`

Coder asks every switch to have at least one `case` label.

**Reports:**

- a switch with no `case` label. A switch that holds only `default` counts, and so does an empty
  one. A `case` label of a nested switch doesn't count for the outer switch.

??? example "Example"

    ```php
    // Reported.
    switch ($op) {
      default:
        $access = FALSE;
    }

    // Fine.
    $access = FALSE;
    ```

??? note "Compared with Coder"

    - The rule reads the labels in the switch's own body. Coder walks tokens, so it needs special
      cases for nested switches.
    - A file that doesn't parse gives no report. Coder reports a statement placed directly in a
      switch body, which is invalid PHP.

---

## drupal/method-visibility

- **Level:** error
- **Fix:** safe. It adds `public`.
- **Standard:** [Methods and functions](https://project.pages.drupalcode.org/coding_standards/php/coding/#methods-and-functions)
- **Ports:** `Drupal.Scope.MethodScope.Missing`

> Visibility must be declared on all methods.

**Reports:**

- a method declared without `public`, `protected` or `private`.

---

## drupal/null-coalesce

- **Level:** error
- **Fix:** safe, or potentially unsafe when it drops a comment.
- **Standard:** [Operators](https://project.pages.drupalcode.org/coding_standards/php/coding/#operators)
- **Ports:** `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator.NullCoalesceOperatorNotUsed`

> The null coalescing operator `??` should be used instead of a ternary operator with an isset()
> condition, to make code more readable.

**Reports:**

- a ternary that `??` replaces, with `null` on either side of the comparison:
    - `isset(X) ? X : B`
    - `X === null ? B : X`
    - `X !== null ? X : B`

Operands match by syntax tree, so quotes, spacing and parentheses don't matter, and the report is
the same before and after `mago format`.

The fix writes `X ?? B` when X is a plain variable, property, index or constant read and B doesn't
need parentheses after `??`. These are reported without a fix:

- an X that is a call, a variable variable such as `$$a`, or a property with its name in braces,
  such as `$a->{$b}`;
- a side effect in an index, or a cast before `isset`;
- a B without parentheses that is a ternary, an assignment, or a `throw`, `print`, `yield`,
  `include` or `require` expression.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    $title = isset($form['title']) ? $form['title'] : '';
    $name = $account === NULL ? 'Anonymous' : $account;

    // Fine.
    $title = $form['title'] ?? '';
    $name = $account ?? 'Anonymous';
    ```

??? note "Compared with Coder"

    - The rule also reports a ternary after `and`, `or` and `xor`. The formatter adds parentheses
      there, and the sniff then reports it.
    - It also reports a parenthesized condition or operand, operands that differ only in quotes,
      and an `isset` whose key holds a call with a comma, such as `isset($a[max(1, 2)])`. The sniff
      skips all of these. It ignores spacing and comments in the operands, but not quotes or
      parentheses, and it skips an `isset` with any comma.
    - It doesn't report `!$a === null ? '' : $a`, `(string) $a === null ? '' : $a` or
      `$b + $a === null ? '' : $a`. The compared operand is `!$a`, `(string) $a` or `$b + $a`, and a
      fix would change the result.
    - It doesn't report `$a === null ? '' : $a ?? 'z'`, whose else part is `$a ?? 'z'`, or an
      `(array)` or `(object)` cast before `isset`, which always gives `true`.
    - It also reports `X === null ? B : X` where B is a ternary without parentheses, such as
      `$a === null ? $b ?: 1 : $a`, and reports B as well when B matches. The sniff reports neither.
    - The sniff's fix drops comments and casts without notice. Here a comment makes the fix
      potentially unsafe, and a cast gets no fix.

---

## drupal/parameter-blank-line

- **Level:** error
- **Fix:** safe. It removes the line and keeps the line endings.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Functions.MultiLineFunctionDeclaration.EmptyLine`

Coder asks for no blank lines in a parameter list that spans lines. The standard's format for such
a list puts one parameter on each line, and doesn't mention blank lines.

**Reports:**

- a blank line, or a line with only spaces, in the declaration of a function, method or closure
  whose parameter list spans lines. The check covers the lines between the parentheses and, for a
  closure, the `use` list, which counts only when the parameter list spans lines.

**Not reported:**

- a blank line inside a default value that holds an array, a call, parentheses or a string;
- a blank line inside an attribute or a comment;
- an arrow function.

`drupal/inline-comment-blank-line` also reports a blank line below a `//` comment in a parameter
list, with a fix for the same line. When both rules run in one pass, Mago skips one edit, and the
next pass applies it.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_build(
      string $id,

      array $options,
    ): array {
      return [];
    }

    // Fine.
    function example_build(
      string $id,
      array $options,
    ): array {
      return [];
    }
    ```

---

## drupal/property-per-statement

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `PSR2.Classes.PropertyDeclaration.Multiple`

Coder asks for one property in each declaration statement.

**Reports:**

- a statement that declares more than one property, as in `public $a, $b;`. The report is on the
  first name.

There is no fix, because a split would have to sort out attributes, the docblock and the comments
between the names.

??? note "Compared with Coder"

    - The rule reads the names of the declaration. Coder looks for the next variable before the next
      semicolon, so it reports a property hook that reads `$this` or takes a `$value` parameter, and
      a property that is followed by a hook with no semicolon in it. The rule reports none of those,
      because a hooked property has one name.
    - Coder skips a property in an enum. The rule doesn't look at the enclosing type, so it also
      reports a multi-name statement in an enum, which is rejected by PHP and reported by Mago as
      well.
    - It also reports a multi-name statement whose default isn't a constant expression. The sniff
      reports the same, and Mago reports that default on its own.

---

## drupal/property-visibility

- **Level:** error
- **Fix:** safe. It writes `public` in place of `var`, or adds it, which is what PHP makes such a
  property.
- **Standard:** [Properties](https://project.pages.drupalcode.org/coding_standards/php/coding/#properties)
- **Ports:**
    - `Drupal.Classes.PropertyDeclaration.VarUsed`
    - `PSR2.Classes.PropertyDeclaration.ScopeMissing`

> Visibility must be declared on all properties.

**Reports:**

- a property declared with `var`;
- a property declared without `public`, `protected` or `private`, such as `static $count;`.

??? note "Compared with Coder"

    - A `var` property is reported once. Coder 9 also reports the missing visibility.
    - A fix for a property declared without a visibility doesn't exist in phpcbf.
    - Coder reports a missing visibility on `$this` or a `$value` parameter inside a property hook.
      The rule reads only the declared names, so it doesn't.

---

## drupal/redundant-return

- **Level:** warning
- **Fix:** safe. It removes the statement, and the line when the statement is alone on it. There
  is no fix when a comment sits inside the statement, such as `return /* note */;`.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Squiz.PHP.NonExecutableCode.ReturnNotRequired`

A function ends the same way without a `return;` as its last statement.

**Reports:**

- a `return;` that is the last statement of a function, method or closure body, or of a `{ }`
  block that ends the body.

**Not reported:**

- a `return;` in an `if`, loop, `try` or `switch`;
- a `return` with a value;
- a `return;` that code follows.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_reset(array &$cache): void {
      $cache = [];
      return;
    }

    // Fine.
    function example_reset(array &$cache): void {
      $cache = [];
    }
    ```

??? note "Compared with Coder"

    - The rule skips a `return;` that is the whole body of an unbraced loop, because removing it
      changes the loop. Coder reports it.
    - It reports a `return;` that ends a `{ }` block at the end of a body. Coder stops with an
      internal exception there and loses the rest of the file's reports.
    - A fix for the statement doesn't exist in Coder.

---

## drupal/short-ternary

- **Level:** error
- **Fix:** safe, or potentially unsafe when it drops a comment.
- **Standard:** [Operators](https://project.pages.drupalcode.org/coding_standards/php/coding/#operators)
- **Ports:** core's `SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator`, which
  is left out of Coder's rulesets.

> The short ternary operator `?:` must be used where the first operand of a ternary expression
> matches the condition.

**Reports:**

- a ternary that repeats its condition, which `?:` replaces:
    - `X ? X : B`
    - `!X ? B : X`

Both become `X ?: B`. Operands match by syntax tree, as in
[`drupal/null-coalesce`](#drupalnull-coalesce), so quotes, spacing, comments and parentheses don't
matter.

The fix runs when X is a plain variable, property, index or constant read, since `X ?: B` reads X
once where the ternary reads it twice. In the second shape B moves to the end, so it also needs to
bind tighter than `??`, as in `drupal/null-coalesce`. Other matches are reported without a fix.

Mago's own `no-shorthand-ternary` asks for the opposite. The
[configuration](../setup/configuration.md#turn-off-what-drupals-standard-doesnt-ask-for) turns it
off.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    $label = $title ? $title : 'Untitled';
    $name = !$nickname ? $username : $nickname;

    // Fine.
    $label = $title ?: 'Untitled';
    $name = $nickname ?: $username;
    ```

??? note "Compared with the sniff"

    - The sniff compares the source text, so it skips `($a) ? $a : $b`, operands that differ in
      quotes, spacing or comments, and `(!$a) ? $b : $a`.

---

## drupal/short-list

- **Level:** error
- **Fix:** safe. It writes `[...]` and removes the blank space between `list` and the parenthesis.
  It is potentially unsafe when it would drop a comment there. An empty `list()` has no fix.
- **Source:** Coder's `Drupal` standard
- **Ports:** `SlevomatCodingStandard.PHP.ShortList.LongListUsed`
- **Off with `--core`**

Coder asks for the short `[...]` form for destructuring, as for arrays.

**Reports:**

- a destructuring written as `list(...)`, in an assignment, a `foreach` or a nested position. The
  rule reports every `list` keyword, in any letter case.

**Not reported:**

- a name that only looks like the keyword, which isn't a destructuring: `->list`, `::list`, a
  method, constant, property or enum case called `list`, a named argument, and text in a string or
  a comment.

??? note "Compared with Coder"

    - Coder's fix drops a comment between `list` and the parenthesis. Here such a fix is potentially
      unsafe, and a plain `--fix` leaves it.
    - Coder fixes an empty `list()` into `[]`, which PHP rejects as well. The rule reports it and
      offers no fix.
