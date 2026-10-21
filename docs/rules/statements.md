# Statements and declarations

Rules for switch statements, ternaries and returns, for variables that are written and never read,
and for how methods, properties and parameters are declared.

---

## drupal/case-break-blank-line

- **Level:** error
- **Fix:** safe. It adds the line, or removes the extra ones.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration.SpacingAfterBreak`

The standard doesn't have a sentence for this. Its switch example puts a blank line after the
`break` that ends each case.

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

## drupal/case-label-line

- **Level:** error
- **Fix:** safe. It puts the label on the next line, at the indent of the comment's line.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration`: `ContentBeforeCase`, `ContentBeforeDefault`

The standard doesn't have a sentence for this. Its switch example puts each `case` and `default`
label at the start of its line.

**Reports:**

- a block comment or a docblock that ends on the line of a `case` or `default` label, before the
  label. The rule reads both the brace and the colon syntax.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    switch ($op) {
      /* Read access. */ case 'view':
        $access = TRUE;
        break;

      default:
        $access = FALSE;
    }

    // Fine.
    switch ($op) {
      /* Read access. */
      case 'view':
        $access = TRUE;
        break;

      default:
        $access = FALSE;
    }
    ```

??? note "Compared with Coder"

    - Coder reports any code before a label too. `mago format` moves that code to a line of its
      own, so the rule reports only a comment.
    - When code or another comment comes before the comment on its line, the fix moves the comment
      to a line of its own too. `mago format` puts the label back on the comment's line otherwise.
      phpcbf only moves the label.
    - Before a label that isn't the first one, `mago format` indents the comment as the case body
      above it, and Coder reports that line as `Drupal.WhiteSpace.ScopeIndent.IncorrectExact`.

---

## drupal/case-semicolon

- **Level:** error
- **Fix:** safe. It writes the colon, and removes the blank space in front of the semicolon. A
  comment in front of it stays.
- **Standard:** [Control structures](https://project.pages.drupalcode.org/coding_standards/php/coding/#control-structures)
- **Ports:** `PSR2.ControlStructures.SwitchDeclaration`: `WrongOpenercase` (partly), `WrongOpenerdefault` (partly)

The standard doesn't have a sentence for this. Its switch example ends each `case` and `default`
label with a colon.

**Reports:**

- a `case` or `default` label that ends with a semicolon, as in `case 1;`. The rule reads both the
  brace and the colon syntax, and checks the labels of a nested switch with that switch.

??? note "Compared with Coder"

    - Core's config runs only `WrongOpenercase`, so core's phpcs doesn't report `default;`. The
      rule reports it in core too.
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
    - A file that doesn't parse doesn't get a report. Coder reports a statement placed directly in a
      switch body, which is invalid PHP.

---

## drupal/explicit-nullable-param

- **Level:** error
- **Fix:** safe. It writes `?T` for one type and `A|B|null` for a union. For an intersection, it
  writes `(A&B)|null` when `php-version` is 8.2 or later, and nothing below 8.2.
- **Source:** Coder's `Drupal` standard
- **Ports:** `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing`

A `null` default makes a parameter's type nullable even when the type doesn't say so, as in
`int $id = NULL`. PHP 8.4 deprecates that. The fix writes the type that PHP already uses, so the
code runs the same. Its forms are the ones `mago format` writes with the drupal preset.

Mago's own `explicit-nullable-param` reports the same parameters, but it runs only when
`php-version` is 8.4 or later. This rule runs below 8.4 and stays silent from 8.4, so only one of
them reports a parameter. The issue code changes with `php-version`, so a `@mago-expect` comment or
a baseline entry written for one code doesn't cover the other.

**Reports:**

- a parameter of a function, method, closure or arrow function that has a type and a `null` default,
  in any case and with or without a leading backslash, when the type isn't `?T`, isn't `mixed` and
  doesn't include `null`. A parameter passed by reference counts.

**Not reported:**

- a parameter with no type;
- any parameter when `php-version` is 8.4 or later. Mago's rule reports it there, except for a
  `\null` default.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_load(int $id = NULL, int|string $key = NULL): void {
    }

    // Fine.
    function example_load(?int $id = NULL, int|string|null $key = NULL): void {
    }
    ```

??? note "Compared with Coder"

    - For an intersection, phpcbf writes `?A&B`, which PHP doesn't parse. The fix writes
      `(A&B)|null` from PHP 8.2, and leaves the type alone below 8.2.

---

## drupal/function-blank-line

- **Level:** error
- **Fix:** safe. It adds a blank line above the function's docblock.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Squiz.WhiteSpace.FunctionSpacing.Before` (partly)

Coder asks for a blank line between a function and the code or comment before it. `mago format`
writes that line after a `use`, `namespace` or `declare` statement, a class and another function,
and keeps one that is there. It doesn't add one after a plain statement, such as a `define()` call
or the `}` of an `if`.

**Reports:**

- a function declared after a statement or a comment with no blank line between them. The line goes
  above the function's docblock and attributes. As in Coder, only the nearest docblock counts, so a
  second docblock above it needs the blank line too.

**Not reported:**

- a function that is first in a block, after the `{` or the `:` that opens it. `mago format` removes
  a blank line there;
- a function right below a `//`, `#` or `/* */` comment, as in Coder;
- a function after another function, which `mago format` separates;
- a function at the start of a PHP block that doesn't start the file, as in Coder;
- a method, which `mago format` separates.

The fix is left out when the function starts on the line of the code before it. `mago format` moves
the function to a line of its own, and the next fix adds the blank line.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    define('EXAMPLE_LIMIT', 10);
    /**
     * Returns the limit.
     */
    function example_limit(): int {
      return EXAMPLE_LIMIT;
    }

    // Fine.
    define('EXAMPLE_LIMIT', 10);

    /**
     * Returns the limit.
     */
    function example_limit(): int {
      return EXAMPLE_LIMIT;
    }
    ```

??? note "Compared with Coder"

    - Coder counts the blank line from a `//` comment right above the docblock, and DrupalPractice's
      `CommentEmptyLine.SpacingAfter` then reports a blank line below that comment. A layout
      can't satisfy both. The rule follows `FunctionSpacing`, and
      [`drupal/inline-comment-blank-line`](comment-text.md#drupalinline-comment-blank-line) allows
      a blank line between a comment and a docblock.
    - A run of comment lines right above the docblock that holds a tool comment, such as
      `// @mago-expect` or `// phpcs:ignore`, counts with the docblock, so the fix keeps the tool
      comment next to the function. Coder counts from the tool comment and reports the function
      again.
    - Coder also reports a function that is first in a `case` body or in a block of the alternative
      syntax, such as `if ($a):`. `mago format` removes the blank line there, so the rule doesn't
      report it.

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

## drupal/non-capturing-catch

- **Level:** error
- **Fix:** safe. It removes the variable.
- **Source:** core's `phpcs.xml.dist`
- **Ports:** core's `SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch`, which is left out
  of Coder's rulesets.

From PHP 8.0, a `catch` clause can leave out its variable. A variable that's never read only makes
it look like the code uses the exception.

**Reports:**

- a `catch` variable whose name doesn't appear in the catch block, in the `finally` block, or after
  the whole `try` statement in the same function or file, when `php-version` is 8.0 or later. A
  name in a closure's `use` list, in an arrow function and in an interpolated string counts. A
  name inside a nested function, closure body or class doesn't count, since those have variables
  of their own.

**Not reported:**

- a function that may read the variable by a name built at runtime, through `compact()`,
  `get_defined_vars()` or a variable variable. The fix would remove a variable that it reads.

[`drupal/unused-variable`](#drupalunused-variable) allows an unused `catch` variable, as Coder's
`UnusedVariable` does, so this rule is the one that reports it.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_load(string $path): ?string {
      try {
        return file_get_contents($path);
      }
      catch (\RuntimeException $e) {
        return NULL;
      }
    }

    // Fine.
    function example_load(string $path): ?string {
      try {
        return file_get_contents($path);
      }
      catch (\RuntimeException) {
        return NULL;
      }
    }
    ```

??? note "Compared with the sniff"

    - The sniff doesn't skip a function that calls `compact()`, so its fix can remove a variable that
      `compact()` reads.

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

Operands match by syntax tree, so quotes, spacing and parentheses around an operand don't matter.
A condition in parentheses, as in `(isset(X)) ? X : B`, isn't reported, as with the sniff.
`mago format` removes those parentheses, and the ternary is then reported.

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
    - It also reports a parenthesized operand, operands that differ only in quotes, and an `isset`
      whose key holds a call with a comma, such as `isset($a[max(1, 2)])`. The sniff skips all of
      these. It ignores spacing and comments in the operands, but not quotes or parentheses, and it
      skips an `isset` with any comma.
    - It also reports a `null` check with `null` first and an operand longer than one token, such
      as `NULL === $a['k'] ? '' : $a['k']`. The sniff reads only the token after `===` as the
      operand.
    - It doesn't report `!$a === null ? '' : $a`, `(string) $a === null ? '' : $a` or
      `$b + $a === null ? '' : $a`. The compared operand is `!$a`, `(string) $a` or `$b + $a`, and a
      fix would change the result.
    - It doesn't report `$a === null ? '' : $a ?? 'z'`, whose else part is `$a ?? 'z'`, or an
      `(array)` or `(object)` cast before `isset`, which always gives `true`.
    - It also reports `X === null ? B : X` where B is a ternary without parentheses, such as
      `$a === null ? $b ?: 1 : $a`, and reports B as well when B matches. The sniff reports neither.
    - The sniff's fix drops comments and casts without notice. Here a comment makes the fix
      potentially unsafe, and a cast doesn't get a fix.

---

## drupal/parameter-blank-line

- **Level:** error
- **Fix:** safe. It removes the line and keeps the line endings.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Functions.MultiLineFunctionDeclaration.EmptyLine`

Coder doesn't allow blank lines in a parameter list that spans lines. The standard's format for such
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

There isn't a fix, because a split would have to sort out attributes, the docblock and the comments
between the names.

??? note "Compared with Coder"

    - The rule reads the names of the declaration. Coder looks for the next variable before the next
      semicolon, so it reports a property hook that reads `$this` or takes a `$value` parameter, and
      a property that is followed by a hook with no semicolon in it. The rule doesn't report any of
      those, because a hooked property has one name.
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
  isn't a fix when a comment sits inside the statement, such as `return /* note */;`.
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
matter. A condition in parentheses, as in `(X) ? X : B`, isn't reported, as with the sniff, unless
the middle part has parentheses too. `mago format` removes those parentheses, and the ternary is
then reported.

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

    - The sniff compares the source text, so it skips operands that differ in quotes, spacing or
      comments. The rule reports them.
    - The sniff stops reading the condition at a comma, so it skips a condition such as
      `foo($a, $b) ? foo($a, $b) : 'x'`. The rule reports it.

---

## drupal/short-list

- **Level:** error
- **Fix:** safe. It writes `[...]` and removes the blank space between `list` and the parenthesis.
  It is potentially unsafe when it would drop a comment there. An empty `list()` doesn't have a fix.
- **Source:** Coder's `Drupal` standard
- **Ports:** `SlevomatCodingStandard.PHP.ShortList.LongListUsed`

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
      doesn't offer a fix.

---

## drupal/unused-variable

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable`

A variable that is written and never read is dead code, or the code reads another name by mistake.
The check works per scope and doesn't follow the order of the code: a scope that reads a name
anywhere uses it. A scope is a function, a method, a closure or the top level of a file. An arrow
function uses the scope around it, except for the names of its parameters.

**Reports:**

- every write of a name that its scope never reads. A write is the left side of an assignment,
  compound ones such as `.=` and `??=` included, an element of a list that is assigned, a `foreach`
  key, a `foreach` value without a key, a `static` declaration, and a `global` declaration that
  the function never assigns;
- a variable of a closure's `use` list that the closure never reads, and every write to it in the
  closure.

Every other use is a read, such as `$count++`, `$items[] = $item`, `$node->title = $title`,
`isset($x)` and `unset($x)`. A name in an interpolated string or a heredoc and a name in `compact()`
are reads too, and so is every variable that the scope used before a `get_defined_vars()` call. An
assignment that is the value of another assignment, or an argument of a call, also counts as a read,
as in `$a = $b = 1` and `example_save($id = 5)`.

**Not reported:**

- a parameter, also one that the function assigns;
- a `catch` variable, and other writes of its name in the same scope.
  [`drupal/non-capturing-catch`](#drupalnon-capturing-catch) reports one that's never read;
- the value of a `$key => $value` foreach, and other writes of its name in the same scope;
- `$this`, the superglobals, `$argv`, `$argc`, `$http_response_header` and `$HTTP_RAW_POST_DATA`;
- a variable of a `use (&$x)` list;
- a reference that the code writes through: a `$ref = &$value` binding that a `$ref = ...`
  assignment writes through, and a `foreach ($items as &$item)` value that an `$item = ...`
  assignment writes through;
- a property, and a static property such as `self::$count`;
- a `*.tpl.php` file, which Coder's ruleset leaves out.

Mago's own `no-redundant-variable` reports a value that isn't read later, which is a different
check. The [configuration](../setup/configuration.md) turns it off.

??? example "Example"

    ```php
    // Reported, on $count and on $key.
    function example_names(array $items): array {
      $count = count($items);
      $names = [];
      foreach ($items as $key => $item) {
        $names[] = $item['name'];
      }
      return $names;
    }

    // Fine.
    function example_names(array $items): array {
      $names = [];
      foreach ($items as $item) {
        $names[] = $item['name'];
      }
      return $names;
    }
    ```

??? note "Compared with Coder"

    - A read in an `else` or `elseif` branch counts. Coder skips it when every earlier write of the
      name is in the branches above it, so it also reports a value that a loop writes in the `if`
      branch and reads in the `else` branch on a later pass.
    - A `= &` binding that walks down an array, as `$target = &$target[$key]` in a loop, reads
      `$target`. Coder reports the binding before it.
    - Coder treats a `foreach` variable as the value of a `$key => $value` pair when any `=>` comes
      before it inside the parentheses, the looped array included. So it never reports the key in
      `foreach (['a' => 1] as $key => $value)`, the value in `foreach (['a' => 1] as $value)`, or
      the list elements in `foreach (['k' => [1, 2]] as [$a, $b])` and
      `foreach ($rows as ['x' => $x])`. The rule reports each of them when it's never read.
    - In `fn($a) => fn($b) => $a + $b`, the inner `$a` reads the outer arrow function's parameter.
      Coder counts it as a read of an `$a` around the arrow functions.
    - `compact(['name'])` reads `$name`. Coder reads only string arguments and `array(...)`.
    - The array in `[$map[$key]] = $pair` and the object in `foreach ($items as $object->value)` are
      read, as in any write to an index or a property. Coder counts them as written.
    - An assignment in a later element of an array that another assignment holds, as
      `$x = ['a' => 1, 'b' => $y = 2]`, counts as a read. Coder counts it as a read only in the first
      element.

---

## drupal/variable-redeclaration

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration`

A function that declares a name a second time usually overwrites a value by mistake, such as a
parameter that a `static` or `global` declaration replaces. PHP rejects some of these shapes: a
repeated parameter, a closure's `use` variable that repeats a parameter, and, from PHP 8.3, a
repeated `static` declaration. The rule compares the declarations of each name in source order, in
the scopes of [`drupal/unused-variable`](#drupalunused-variable), so a declaration in another branch
counts too.

**Reports:**

- a parameter declared again by `static`, `global`, a `catch` variable, a `= &` binding, a
  closure's `use` list or another parameter;
- a variable that the scope wrote before, then declared `global` or `static`;
- a `global` or `static` declaration of a name that the scope already declared as `global` or
  `static`;
- a `catch` variable or a `= &` binding of a name declared as `global`, `static` or a closure's
  `use` variable.

A write is an assignment, a `foreach` variable or an element of a list that is assigned.

**Not reported:**

- a `catch` variable or a `= &` binding of a name that the scope only wrote, or bound before;
- `static $x = 1;`, which Coder treats as an assignment, not as a declaration;
- a name that the scope only read before the declaration;
- a `*.tpl.php` file, which Coder's ruleset leaves out.

A rebinding on purpose, such as `$elements = &$form;` over a parameter, needs a
`// @mago-expect lint:drupal/variable-redeclaration` comment above it. Mago reports a repeated
parameter as a `semantics` error too.

??? example "Example"

    ```php
    // Reported.
    function example_counter(int $count): int {
      static $count;
      return $count ?? 0;
    }

    // Fine.
    function example_counter(int $start): int {
      static $count;
      $count ??= $start;
      return $count;
    }
    ```

??? note "Compared with Coder"

    - Coder skips a closure's `use` variable that isn't defined before the closure, so it doesn't
      report one that repeats a parameter of the closure then. The rule reports it.
    - Coder counts a write through a by-reference argument, such as the `$matches` of
      `preg_match()`, and `$items[] = ...` on a new variable as assignments, and reports a `global`
      or `static` declaration of the name after them. The rule counts both as uses of the name.
