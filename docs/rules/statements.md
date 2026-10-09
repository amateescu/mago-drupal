# Statements and declarations

Rules for switch statements, ternaries and returns, and for how methods, properties and parameters
are declared.

## drupal/case-break-blank-line

- **Level:** error
- **Fix:** safe. It adds the line, or removes the extra ones.
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration.SpacingAfterBreak`

A `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` that ends a switch case and is
not followed by exactly one blank line. A comment on the statement's line does not count, and one
on a later line does.

The last case before the closing brace and a `default` case are skipped, and so is a case that
falls through from a `default`, as in Coder 9. A `switch (): ... endswitch;` body is skipped too,
because `mago format` removes the blank lines between its cases.

## drupal/case-fall-through

- **Level:** error
- **Fix:** none
- **Ports:** `PSR2.ControlStructures.SwitchDeclaration.TerminatingComment`
- **Off with `--core`**

A non-empty `case` that falls through to the next case with no comment right before it. A comment
of any kind counts, whatever it says, except a directive such as `// @mago-expect` or
`// phpcs:ignore`, which Coder counts too.

The case is fine when a `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` ends it, or
when its last statement ends it in every branch:

- an `if` with an `else`;
- a `try` with its `catch` blocks, or with a `finally` block;
- a nested `switch` with a `default` case.

A `default` case and the last case are skipped.

**Compared with Coder:**

- A case that ends in `$x or exit()` or `$x ?? throw ...` falls through when the left side allows
  it, and the rule reports it. Coder ends the case at the keyword.
- A braceless `if (...) return; else return;` ends a case. Coder reports it.
- A case whose body is a `{ }` block with no ending statement is reported. Coder stops with an
  internal exception for the whole file.
- A `break` inside a nested `switch` does not end the outer case, as in Coder.

## drupal/case-semicolon

- **Level:** error
- **Fix:** safe. It writes the colon, and removes the blank space in front of the semicolon. A
  comment in front of it stays.
- **Ports:** `PSR2.ControlStructures.SwitchDeclaration`: `WrongOpenercase`, `WrongOpenerdefault`

A `case` or `default` label that ends with a semicolon, as in `case 1;`. The rule reads both the
brace and the colon syntax, and checks the labels of a nested switch with that switch.

**Compared with Coder:**

- Core's config runs only `WrongOpenercase`. The rule reports `default;` too, also under `--core`.
- Mago's parser does not accept a label that ends with a close tag, as in `case 1 ?>`, which PHP
  accepts. Coder reports those. The rule cannot, because Mago reports a parse error for the file.
- The fix removes the blank space in front of the colon. phpcbf leaves `case 1 :` for another sniff
  to move.

## drupal/comment-in-expression

- **Level:** error
- **Fix:** none
- **Ports:**
    - `Generic.Formatting.SpaceAfterCast.CommentFound`
    - `Generic.WhiteSpace.LanguageConstructSpacing.IncorrectYieldFromWithComment`

A comment right after a cast, as in `(int) /* note */ $x`, or between `yield` and `from`. A comment
before the cast, after the operand or after `from` is fine. Every cast spelling counts, `(void)`
included, whatever the target PHP version.

## drupal/else-if

- **Level:** error
- **Fix:** safe. It joins the keywords.
- **Ports:** `PSR2.ControlStructures.ElseIfDeclaration.NotAllowed`

An `else if` written as two keywords. Drupal writes `elseif`. The rule skips a braced
`else { if ... }`.

## drupal/empty-switch

- **Level:** error
- **Fix:** none
- **Ports:** `Squiz.ControlStructures.SwitchDeclaration.MissingCase`

A switch with no `case` label. A switch that holds only `default` counts, and so does an empty one.
A `case` label of a nested switch does not count for the outer switch.

**Compared with Coder:** the rule reads the labels in the switch's own body. Coder walks tokens, so
it needs special cases for nested switches. A file that does not parse gives no report. Coder
reports a statement placed directly in a switch body, which is invalid PHP.

## drupal/method-visibility

- **Level:** error
- **Fix:** safe. It adds `public`.
- **Ports:** `Drupal.Scope.MethodScope.Missing`

A method declared without `public`, `protected` or `private`.

## drupal/null-coalesce

- **Level:** error
- **Fix:** safe, or potentially unsafe when it drops a comment.
- **Ports:** `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator.NullCoalesceOperatorNotUsed`

A ternary that `??` replaces, with `null` on either side of the comparison:

- `isset(X) ? X : B`
- `X === null ? B : X`
- `X !== null ? X : B`

Operands match by syntax tree, so quotes, spacing and parentheses do not matter, and the report is
the same before and after `mago format`.

The fix writes `X ?? B` when X is a plain variable, property, index or constant read and B does
not need parentheses after `??`. A call as X, a side effect in an index, or a cast before `isset`
is reported without a fix.

**Compared with Coder:**

- The rule also reports a ternary after `and`, `or` and `xor`. The formatter adds parentheses
  there, and the sniff then reports it.
- It also reports a parenthesized condition or operand, operands that differ only in quotes or
  spacing, and an `isset` whose key holds a call with a comma, such as `isset($a[max(1, 2)])`. The
  sniff skips those on any comma.
- It does not report `!$a === null ? '' : $a`, `(string) $a === null ? '' : $a` or
  `$b + $a === null ? '' : $a`. The compared operand is `!$a`, `(string) $a` or `$b + $a`, and a
  fix would change the result.
- It does not report `$a === null ? '' : $a ?? 'z'`, whose else part is `$a ?? 'z'`, or an
  `(array)` or `(object)` cast before `isset`, which always gives `true`.
- The sniff's fix drops comments and casts without notice. Here a comment makes the fix
  potentially unsafe, and a cast gets no fix.

## drupal/parameter-blank-line

- **Level:** error
- **Fix:** safe. It removes the line and keeps the line endings.
- **Ports:** `Drupal.Functions.MultiLineFunctionDeclaration.EmptyLine`

A blank line, or a line with only spaces, in the declaration of a function, method or closure whose
parameter list spans lines. The check covers the lines between the parentheses and, for a closure,
the `use` list, which counts only when the parameter list spans lines.

The rule skips a blank line inside a default value that holds an array, a call, parentheses or a
string, inside an attribute and inside a comment. It skips arrow functions.

`drupal/inline-comment-blank-line` also reports a blank line below a `//` comment in a parameter
list, with a fix for the same line. When both rules run in one pass, Mago skips one edit, and the
next pass applies it.

## drupal/property-per-statement

- **Level:** error
- **Fix:** none
- **Ports:** `PSR2.Classes.PropertyDeclaration.Multiple`

A statement that declares more than one property, as in `public $a, $b;`. The rule reports once,
on the first name. The sniff has no fix, and a split would have to sort out attributes, the
docblock and the comments between the names, so the rule has none either.

**Compared with Coder:**

- The rule reads the names of the declaration. Coder looks for the next variable before the next
  semicolon, so it reports a property hook that reads `$this` or takes a `$value` parameter, and a
  property that is followed by a hook with no semicolon in it. The rule reports none of those,
  because a hooked property has one name.
- Coder skips a property in an enum. The rule does not look at the enclosing type, so it also
  reports a multi-name statement in an enum, which PHP rejects and Mago reports as well.
- It also reports a multi-name statement whose default is not a constant expression. The sniff
  reports the same, and Mago reports that default on its own.

## drupal/property-visibility

- **Level:** error
- **Fix:** safe. It writes `public` in place of `var`, or adds it, which is what PHP makes such a
  property.
- **Ports:**
    - `Drupal.Classes.PropertyDeclaration.VarUsed`
    - `PSR2.Classes.PropertyDeclaration.ScopeMissing`

A property declared with `var`, or declared without `public`, `protected` or `private`, such as
`static $count;`.

**Compared with Coder:** a `var` property is reported once. Coder 9 also reports the missing
visibility.

## drupal/redundant-return

- **Level:** warning
- **Fix:** safe. It removes the statement, and the line when the statement is alone on it. There
  is no fix when a comment sits inside the statement, such as `return /* note */;`.
- **Ports:** `Squiz.PHP.NonExecutableCode.ReturnNotRequired`

A `return;` that is the last statement of a function, method or closure body, or of a `{ }` block
that ends the body. The function ends the same way without it. A `return;` in an `if`, loop, `try`
or `switch`, a `return` with a value, and one that code follows are fine.

**Compared with Coder:**

- The rule skips a `return;` that is the whole body of an unbraced loop, because removing it
  changes the loop. Coder reports it.
- It reports a `return;` that ends a `{ }` block at the end of a body. Coder stops with an internal
  exception there and loses the rest of the file's reports.

## drupal/short-list

- **Level:** error
- **Fix:** safe. It writes `[...]` and removes the blank space between `list` and the parenthesis.
  It is potentially unsafe when it would drop a comment there. An empty `list()` has no fix.
- **Ports:** `SlevomatCodingStandard.PHP.ShortList.LongListUsed`
- **Off with `--core`**

A destructuring written as `list(...)`, in an assignment, a `foreach` or a nested position. The
rule reports every `list` keyword, in any letter case, as Coder does. A name that only looks like
the keyword is not a destructuring, so the rule does not see it: `->list`, `::list`, a method,
constant, property or enum case called `list`, a named argument, and text in a string or a comment.

**Compared with Coder:**

- Coder's fix drops a comment between `list` and the parenthesis. Here such a fix is potentially
  unsafe, and a plain `--fix` leaves it.
- Coder fixes an empty `list()` into `[]`, which PHP rejects as well. The rule reports it and
  offers no fix.
