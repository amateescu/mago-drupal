# Linter rules

Every rule that the extension registers, in groups by what it checks. The rules in each group are in
alphabetical order.

## Bugs and security

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/insecure-unserialize` | Error | An `unserialize()` call that does not limit `allowed_classes`. The payload then decides which objects get built. The rule skips a payload that the same function built with `serialize()`: `unserialize(serialize($x))`, or a local variable whose every assignment is a `serialize()` call. |
| `drupal/preg-security` | Error | A `preg_*` pattern that uses the `e` modifier. That modifier evaluates the replacement as PHP. |
| `drupal/remote-address` | Error | A read of `$_SERVER['REMOTE_ADDR']`. Such a read ignores the reverse-proxy settings. |
| `drupal/weak-hash` | Warning | A `md5()`, `sha1()` or `crc32()` call, and a `hash()` call with `md5`, `sha1`, `crc32` or `crc32b`. An unsafe fix changes the call to `hash('xxh64', …)`. |

## Using the right API

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/deprecation-message` | Warning | An `E_USER_DEPRECATED` message that breaks the deprecation grammar. The grammar includes the version format and the change-record format. |
| `drupal/global-function` | Warning | A procedural wrapper that is called from inside a class. The most frequent case is `t()` where `$this->t()` applies. |
| `drupal/translatable-string` | Warning | A translatable string that is built by concatenation or interpolation, is padded with whitespace, or is empty. A nowdoc counts as a literal and a heredoc does not, as in Coder 9. Also a quoted string joined with `.` right after the call, as in `t('Name') . ':'`, when the message is a literal. A string that is empty, or one of `(`, `)`, `[`, `]`, `-`, `<`, `>`, `«`, `»` and `\n` once its quotes, HTML tags and spaces are gone, is fine, as in Coder 9. The rule covers `t()`, `formatPlural()` and `new TranslatableMarkup()`. |
| `drupal/translated-exception` | Warning | An exception message that is passed through `t()`. |
| `drupal/unsilenced-deprecation` | Error | A `trigger_error()` deprecation notice without the `@` prefix. Drupal turns an unsilenced notice into a test failure. A fix adds the `@`. |

## Procedural files

These rules report only in `.module` and `.install` files.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/const-prefix` | Warning | A `const` constant at the top level of the file, or after `namespace Foo;`, whose first name does not start with the module name and an underscore. The check is the one of `drupal/constant-prefix`. The rule skips a `const` inside a braced `namespace Foo { }`, as Coder 9 does. Off with `--core`, see below. |
| `drupal/constant-prefix` | Warning | A `define()` constant without the module prefix. |
| `drupal/empty-install-hook` | Error | An empty `hook_install()` or `hook_uninstall()` body. |
| `drupal/function-prefix` | Error | A function in a `.module` file whose name does not start with the module name and an underscore, with an optional leading underscore. A name that starts with `template_preprocess` or `theme` is fine. `.install` files are not checked, as in Coder 9. Off with `--core`, see below. |
| `drupal/global-variable` | Error | A module global without the leading underscore. |
| `drupal/install-hook-location` | Error | A `hook_install()` or other install hook that is declared in `.module` and not in `.install`. |
| `drupal/t-in-hook-schema` | Error | A `t()` call inside `hook_schema()`. |

## Naming, imports and syntax

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/case-break-blank-line` | Error | A `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` that ends a switch case and is not followed by exactly one blank line. A fix adds the line, or removes the extra ones. The last case before the closing brace and a `default` case are skipped, and so is a case that falls through from a `default`, as in Coder 9. A comment on the statement's line does not count, and one on a later line does. The rule skips a `switch (): ... endswitch;` body, because `mago format` removes the blank lines between its cases. |
| `drupal/case-fall-through` | Error | A non-empty `case` that falls through to the next case with no comment right before it. A comment of any kind counts, whatever it says. The case is fine when a `break`, `continue`, `return`, `throw`, `exit`, `die` or `goto` ends it, or when its last statement ends it in every branch: an `if` with an `else`, a `try` with its `catch` blocks or a `finally` block, or a nested `switch` with a `default` case. A `default` case and the last case are skipped. Reports only, with no fix. Off with `--core`, see below. |
| `drupal/case-semicolon` | Error | A `case` or `default` label that ends with a semicolon, as in `case 1;`. A fix writes the colon, and removes the blank space in front of the semicolon. A comment in front of it stays. The rule reads both the brace and the colon syntax, and checks the labels of a nested switch with that switch. |
| `drupal/class-name-acronym` | Error | A class, interface, trait or enum name that starts with three upper-case letters in a row, such as `HTTPClient`. Digits and underscores after the capitals do not end the acronym, and a multi-byte letter does not count as lower case, as in Coder 9. The report is on the name. |
| `drupal/comment-in-expression` | Error | A comment right after a cast, as in `(int) /* note */ $x`, or between `yield` and `from`. A comment before the cast, after the operand or after `from` is fine. Every cast spelling counts, `(void)` included, whatever the target PHP version. Reports only, with no fix. |
| `drupal/define-name` | Error | A `define()` constant name that is not upper case. The rule reads the first argument, a string literal or literals joined with `.`, and checks the part after the last backslash. Only ASCII letters count. It reports in every scanned file type and at any depth. Mago's `constant-name` covers `const`. |
| `drupal/else-if` | Error | An `else if` written as two keywords. Drupal writes `elseif`. A fix joins the keywords. The rule skips a braced `else { if ... }`. |
| `drupal/empty-switch` | Error | A switch with no `case` label. A switch that holds only `default` counts, and so does an empty one. A `case` label of a nested switch does not count for the outer switch. There is no fix. |
| `drupal/enum-case-name` | Error | An enum case that is not UpperCamelCase. |
| `drupal/fully-qualified-name` | Error | A namespaced class written out in full where a `use` statement belongs. The rule skips a name with no namespace of its own, such as `\Exception`, and a namespaced function call or first-class callable. The rule skips an `.api.php` file completely. A fix adds the import and writes the short name everywhere the file writes the class in full. It skips a file with no namespace, several namespaces or a braced one, or an import below code, a constant, and a short name the file already uses for something else: another import, a class of that name, or a docblock that writes the short name in the same case. |
| `drupal/hook-attribute-name` | Warning | A `Drupal\Core\Hook\Attribute\Hook` attribute whose first argument, positional or `hook:`, is a hook name that starts with `hook_`. The attribute holds the whole hook name, so such a name is never invoked. The rule has no fix, because removing the prefix changes which hook runs. Off with `--core`, see below. |
| `drupal/method-name-underscore` | Warning | A method name that starts with one underscore to mark it private, as Coder 9's `PSR2.Methods.MethodDeclaration.Underscore` reports. Also a name that starts with two underscores and is not a PHP magic method or a `SoapClient` method, as Coder 9's `MethodDoubleUnderscore` reports. The names are matched without regard to case. A name that is only two underscores, or starts with three, is fine. Off with `--core`, see below. |
| `drupal/method-visibility` | Error | A method declared without `public`, `protected` or `private`. A fix adds `public`. |
| `drupal/null-coalesce` | Error | A ternary that `??` replaces: `isset(X) ? X : B`, `X === null ? B : X` or `X !== null ? X : B`, with `null` on either side. Operands match by syntax tree, so quotes, spacing and parentheses do not matter. A fix writes `X ?? B` when X is a plain variable, property, index or constant read and B does not need parentheses after `??`. It is potentially unsafe when it drops a comment. A call as X, a side effect in an index, or a cast before `isset` is reported without a fix. |
| `drupal/parameter-blank-line` | Error | A blank line, or a line with only spaces, in the declaration of a function, method or closure whose parameter list spans lines. The check covers the lines between the parentheses and, for a closure, the `use` list, which counts only when the parameter list spans lines. The rule skips a blank line inside a default value that holds an array, a call, parentheses or a string, inside an attribute and inside a comment, and it skips arrow functions. A fix removes the line and keeps the line endings. |
| `drupal/property-name` | Error | A class property that is not lowerCamelCase. |
| `drupal/property-per-statement` | Error | A statement that declares more than one property, as in `public $a, $b;`. The rule reports once, on the first name. There is no fix. |
| `drupal/property-visibility` | Error | A property declared with `var`, which a fix writes as `public`, or declared without `public`, `protected` or `private`, such as `static $count;`. A fix adds `public`, which is what PHP makes such a property. A `var` property is reported once, where Coder 9 also reports the missing visibility. |
| `drupal/redundant-return` | Warning | A `return;` that is the last statement of a function, method or closure body, or of a `{ }` block that ends the body. The function ends the same way without it. A fix removes the statement, and the line when the statement is alone on it. It is left out when a comment sits inside the statement, such as `return /* note */;`. A `return;` in an `if`, loop, `try` or `switch`, a `return` with a value, and one that code follows are fine. |
| `drupal/redundant-use` | Error | A `use` statement that imports a class from the global namespace. A fix removes the import and writes `\Exception` at every reference, read from the resolved names. It is left out while a docblock in the file names the class in the same case without a leading backslash, in a type, an annotation or prose. `drupal/doc-type-namespace` fixes the types. |
| `drupal/short-list` | Error | A destructuring written as `list(...)`, in an assignment, a `foreach` or a nested position. A fix writes `[...]` and removes the blank space between `list` and the parenthesis. A fix that would drop a comment between them is potentially unsafe. An empty `list()` has no fix. Off with `--core`, see below. |
| `drupal/use-leading-backslash` | Error | An import whose class name starts with a backslash. A fix removes the backslash. |

## Files and PHP tags

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/byte-order-mark` | Error | A UTF-8 or UTF-16 byte order mark at the first bytes of a file. PHP sends the mark to the browser before any code runs. No fix. |
| `drupal/empty-php-tags` | Warning | A `<?php` or `<?=` tag that a `?>` follows with only whitespace between, in any position, also inside a function or an alternative-syntax block. A comment between the tags keeps the pair from being reported. A fix removes the pair and the line break that PHP drops after `?>`, so the output stays the same. An empty `<?= ?>` has no fix, because PHP rejects it. |
| `drupal/file-encoding` | Warning | A file that is not valid UTF-8. The report sits on the first open tag or run of inline text, not on the bad byte, and the message names no bytes. A file whose only tags are `<?=` is skipped. No fix. |
| `drupal/file-start-whitespace` | Error | Whitespace before the first `<?php` of a file. A potentially unsafe fix deletes it, because a template can print that text. Text that is not whitespace, such as a byte order mark, a zero-width space or a `#!` line, is not reported. |
| `drupal/short-echo-tag` | Error | A `<?=` tag that has a value to echo. A fix writes `<?php echo` with one space before the value, and keeps a line break and any comment where they are. An echo tag with no value is left to `drupal/empty-php-tags`. |

## Comment text

These rules read only a comment's text. They do not compare it against the declaration that it
documents. The group has the part of `Drupal.Commenting.*` that works that way, the `@author` ban,
and the line-length check for comments.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/author-tag` | Warning | An `@author` tag. The tag goes out of date as other people edit the file. |
| `drupal/comment-line-length` | Warning | A comment line that is longer than 80 characters. |
| `drupal/doc-comment-array-syntax` | Warning | The `array()` syntax inside a docblock `@code` example. |
| `drupal/doc-type-namespace` | Warning | A `@param`, `@return`, `@var` or `@throws` type written as the short name of a class that only docblocks use. Coder 9 accepts short names, but its `UnusedUses` sniff does not read docblocks, so it reports such an import as unused and phpcbf deletes it, which leaves the docblock naming a class that no longer resolves. A short name whose import the code uses too is fine. A fix writes the fully qualified name for each such member of the type, when the type starts on the tag's line and the docblock is below the import, in a file with one namespace. It removes the import when every mention of the name in the file's docblocks is rewritten. |
| `drupal/expected-exception-tag` | Warning | A legacy PHPUnit `@expectedException*` docblock tag. |
| `drupal/gender-neutral-comment` | Warning | A gendered pronoun in a comment. |
| `drupal/inline-comment` | Warning | A `//` comment that starts with a lowercase letter, which a fix uppercases, or uses `#` and not `//`. Also the space after `//`: one space and no tab, or more to line up with a list item or `@todo` on the line above. A fix sets the space. A line indented deeper than the comment line above for no such reason is reported without a fix. A comment after a `}` on its line, an `@code` example and a `phpcs:` line are skipped. |
| `drupal/inline-comment-blank-line` | Warning | A blank line below a `//` comment on its own line, which a fix removes. A blank line before a closing bracket is left to `mago format`. Off with `--core`, see below. |
| `drupal/inline-comment-punctuation` | Warning | A `//` comment that does not end with a full stop, an exclamation mark, a question mark, a colon or a closing parenthesis. A comment whose first word does not start with a letter, one with a `cspell:` line, and a last word that is a url, a tag or a function call are skipped. A fix appends a full stop, as phpcbf does, so a comment that ends with `,` ends with `,.`. Off with `--core`, see below. |
| `drupal/long-description-punctuation` | Warning | A docblock long description that ends with a letter. A fix adds a full stop. Off with `--core`, see below. |
| `drupal/post-statement-comment` | Warning | A `//` comment on the same line as the statement before it. A fix moves the comment to its own line above. It is left out where the move could attach the comment to something else: a line that opens a block or closes a construct, a line below a docblock or another comment, such as an `@phpstan-ignore` for the statement, a line inside a string, a comment that the next line continues, and a comment that applies to one line, such as `cspell:disable-line` or `@codeCoverageIgnore`. |
| `drupal/todo-comment` | Warning | A to-do comment that does not follow the `@todo Fix problem X here.` format. A fix writes `@todo ` in place of the spelling and the dashes, colons and spaces after it. It skips a to-do with no text, and a word that only starts with "todo", such as "todos". |

Mago's own `tagged-todo` rule must have a `TODO(@user)` or `TODO(#123)` reference. That format is
not compatible with Drupal's `@todo Fix problem X here.` convention, so `tagged-todo` is not a
substitute for `drupal/todo-comment`. Do not run the two rules on the same codebase.

## Docblock structure

These rules read a docblock's summary, description and tag list. They do not need the declaration's
real signature. The group has the rest of `Drupal.Commenting.*`.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/class-comment` | Error | A class, interface, trait or enum with no docblock, with the wrong comment style, or with a summary that only repeats the name. Also a blank line between the docblock and the declaration, which a fix removes. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |
| `drupal/deprecated-tag` | Warning | A `@deprecated` tag that breaks the version-and-reason grammar, or that has no `@see` tag after it. The change-record url is the first line of the `@see` tag. Coder 9 accepts `https://www.drupal.org/node/n`, `https://www.drupal.org/project/name/issues/n` and `https://git.drupalcode.org/project/name/-/work_items/n`. A fix removes the punctuation after it. A potentially unsafe fix rewrites an old core wording on the text's first line, such as `in Drupal 8.5.x and will be removed before Drupal 9.0.0.`, as phpcbf does. It writes `drupal:` versions with three parts and drops the text before `in` or `as of` and between the two versions. |
| `drupal/doc-comment` | Warning | A docblock with no summary, or with `@param` tags that are not in one group, or that are not in the first group. Also a summary that does not start with an upper-case letter, that has no punctuation, or that spans more than one line. As in Coder 9, a summary that starts with a digit, `#`, `_` or other punctuation counts, and one that starts with a multi-byte character does not. `{@inheritdoc}` and a summary that is the file's name are fine. A long description that starts with a lower-case letter is reported too. The summary and the long description of a file docblock are the lines after `@file`, checked like any other docblock's. A fix uppercases a first lower-case letter. A fix adds a full stop to a one-line summary that ends with a letter or a digit. Also `@inheritdoc` without braces, which a fix writes as `{@inheritdoc}`. Also the docblock's whitespace, each with a fix: text on the line of the opening `/**`, blank lines at its start or end, more than one blank line between the summary and the description, not exactly one blank line before the tags, no blank line between the `@param`, `@return` and `@throws` sections and the tags next to them, more than one after a section, and not exactly one space before the summary or after a tag. Also the space after the star of every docblock line, in a docblock that comes before a declaration keyword or right after the `<?php` tag: no space, and more than one space or a tab before `@param`, `@return`, `@throws`, `@ingroup` or `@var`. A fix writes one space. Also a closer other than `*/`, such as `**/`, which a potentially unsafe fix replaces with `*/`, and a description line that ends in two dots, where a potentially unsafe fix removes one. |
| `drupal/file-comment` | Error | A procedural file that does not start with a docblock that has the `@file` tag. A directive such as `// phpcs:ignoreFile` above the docblock is skipped. A fix adds `@file` below the docblock's opener when a blank line parts the docblock from the code. Another adds that blank line when the code starts right below the docblock. A potentially unsafe fix turns a comment in the wrong style into a docblock with `@file`, see below. |
| `drupal/function-comment` | Error | A function or method with no docblock or with the wrong comment style. Also a `@param`, `@return`, `@throws` or `@see` tag that is malformed, has no description, or is not capitalized, and a `@param` or `@return` type name that Coder wants written another way, such as `integer` for `int`. A `@return void`, `@return static` or `@return $this` needs no description. A `@param` or `@return` type that has a space, a `@return` with no type on its line, and a `@throws` description on the tag's line, which a fix moves to the line below. A constructor with a docblock is checked. A `@return` variable name and a `@see` reference are read from the tag's own line. Fixes write Coder's type name, remove a period after a `@param` name, a variable name after a `@return` type that has a description below, and punctuation after a one-word `@see` reference, and add a full stop to a `@param` description that does not end in a url, a tag or `:`, `,` or `;`. Also, each with a fix: a blank line between the docblock and the function, a `@param` description on the tag's line, not exactly one space between a `@param` type and its variable, and a `@param`, `@return` or `@throws` description that is not indented three spaces from the star. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |
| `drupal/hook-comment` | Warning | A hook implementation that is not documented as `Implements hook_foo().`, or that duplicates the `@param` or `@return` documentation. |
| `drupal/inline-variable-comment` | Warning | An inline `@var` declaration that uses `//` and not `/** */`, or that writes the variable name before the type. A `//` or `#` comment that holds `*/`, such as a commented-out docblock, is skipped. Two fixes are potentially unsafe, because the analyzers start to trust the type: one moves a variable name written first after the type, and the other turns a comment that holds only the tag, alone on its line, into a docblock. |
| `drupal/variable-comment` | Error | A class property with no docblock, with the wrong comment style, or with more than one `@var` tag. A property with a native type needs a docblock but no `@var` tag. Also a `@var` type name that Coder wants written another way, such as `integer` for `int`, `Boolean` for `bool` or `NULL` for `null`. Fixes remove a property name repeated after the `@var` type and write Coder's type name. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |

## Docblock types

This rule compares a docblock with the declaration's signature. Coder has no matching sniff.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/nullable-param-tag` | Warning | An untyped parameter with a `NULL` default whose `@param` type has no `null`, such as `@param string $rel` on `toUrl($rel = NULL)`. A union member `null`, a `?T` type, `mixed` and a `@template` name all count. A `null` inside a generic, as in `array<string\|null>`, does not. A `@phpstan-param` or `@psalm-param` tag wins over `@param`. A safe fix appends `\|null` to a type that fits on the tag's first line. |

The docblock is the only type of such a parameter, and tools read it differently. PHPStan adds the
`null` from the default. Mago accepts the default but keeps the documented type, so it reports an
explicit `NULL` argument and does not see that the parameter can be null in the body. A parameter
with a native type is not reported, since every tool reads the native type. After the fix, the
analyzer sees the `null`, and it may report code in the body that does not handle it.

## DrupalPractice checks

These rules port sniffs of Coder's `DrupalPractice` standard. Core's `phpcs.xml.dist` does not run
them, so the worker's `--core` argument turns them off.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/class-prefix` | Warning | A class or interface in a `.module`, `.install`, `.profile` or `.theme` file whose name does not start with the module name, with or without the underscores of the name. The rule skips every declaration from the first `namespace` statement on. Traits, enums and anonymous classes are not checked. Off with `--core`. |
| `drupal/curl-ssl-verify` | Warning | A `curl_setopt()` call that sets `CURLOPT_SSL_VERIFYPEER` to `FALSE` or `0`. The rule reads the arguments by position or by name, and skips a call that spreads its arguments. Off with `--core`. |
| `drupal/form-alter-comment` | Warning | A function in a `.module`, `.install`, `.profile` or `.theme` file with a docblock line that starts with `Implements hook_form_alter().` and a name other than the module name and `_form_alter`. The docblock must be right above the `function` keyword, with no modifier, attribute or comment between them. Off with `--core`. |
| `drupal/global-constant` | Warning | A `const` statement at the top level of a file, and a `define()` call at the top level of a `.module` file. A statement in a class, a function, a closure, an arrow function, a block of `if`, `switch`, `try`, a loop or `declare`, or a braced namespace is not at the top level. A docblock with a `@deprecated` tag right above the statement exempts it. Off with `--core`. |
| `drupal/request-superglobal` | Error | A use of `$_GET`, `$_POST`, `$_COOKIE` or `$_FILES`, including a write, a parameter or `global` name, and a use inside a string. The message names the matching property of the request. `$_REQUEST` is left to Mago's `no-request-variable`. Off with `--core`. |
| `drupal/strict-config-schema` | Error | A `$strictConfigSchema` property of a test class, where the first of `TRUE`, `FALSE` and `NULL` in the default is not `TRUE`, or where there is none. A test class has `Test` or `Tests` as a word of its name. Off with `--core`. |
| `drupal/untranslated-options` | Warning | A plain string label in the `#options` of an element with `#type` `checkboxes`, `radios`, `select` or `tableselect`. A label counts when it is not a number and has more than three characters. Labels in nested option groups count. Off with `--core`. |

## Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/link-text-translatable` | Error | Literal link text passed to `l()` without `t()`. |
| `drupal/t-in-hook-menu` | Error | A `t()` call inside `hook_menu()`. |
| `drupal/watchdog-message` | Error | A `watchdog()` message that is wrapped in `t()` or built by concatenation. |

## Parity notes

The two comment groups port `Drupal.Commenting.*`, whitespace included. The last paragraphs of this
section say which whitespace `mago format` covers instead, and where the ports differ from Coder.

`Drupal.Commenting.FunctionComment` compares a docblock against a function's real parameter list
and return type. Most of those checks are redundant with `mago analyze`. The analyzer reads
`@param`, `@return`, `@var` and `@throws` as authoritative types when there is no native hint, the
same way phpstan and psalm do. It already reports an `@return void` on a function that returns a
value, a function with no `return` at all, an `@param` that names an unknown parameter, and a type
name that does not resolve as a class, such as `Boolean`. It reads `integer` and `boolean` as `int`
and `bool` without a report, so the rules check Coder's type names themselves.

`drupal/function-comment` covers the rest: docblock presence, prose quality, type names,
and one check that depends on the signature. That check reports a method with partial `@param`
coverage that has no entry for a real parameter. The terminal-punctuation check skips a `@param`
description that ends in a `@code` example. The sniff makes the same exemption, because such a
description ends on the example and not on a sentence.

`Drupal.Files.LineLength` is a linter rule here and not a formatter task. The formatter does the
rest of the line-width work. The sniff measures only lines that end in a comment. `mago format`
wraps code but leaves comment text as written, so the formatter cannot produce the sniff's result.
The rule keeps the sniff's exemptions. All of them are for text that cannot be wrapped
without damage: docblock tag lines, `@code` examples, `// @see`-style reference lines, annotation
values, and the `Implements hook_foo()` and `Contains ...` lines. The rule also skips a line whose
last word is too long for a line of its own. That exemption lets a URL or a long class path through.

Two things differ from a phpcs run of the same sniffs. A phpcs suppression comment
(`phpcs:ignore`, `phpcs:disable`, `phpcs:ignoreFile`, `@codingStandardsIgnoreFile`) does not
suppress a Mago issue. A codebase that uses those comments must convert them to
`// @mago-expect lint:<code>` or to a Mago exclude. Core's generated `ProxyClass` files are the most
frequent case. Also, the rule reports a `/* ... */` line that holds the closing `*/`, where phpcs
skips it. Such a line ends on a whitespace token and not on a comment token, so the sniff's own
check never runs on it. Both tools report the lines above the closer.

Two `Drupal.Commenting.*` sniffs are pure whitespace. `mago format` produces the result of
`DocCommentStar` (a star on a docblock line that has none) and the star column of
`DocCommentAlignment`, so those are not ported. It does not touch the text after a star, so
`drupal/doc-comment` ports the space after the star (`SpaceAfterStar` and `NoSpaceAfterStar`).

The rules port the rest of the comment whitespace, with a fix wherever phpcbf has one, and for
`TrhowsCommentIndentation` too. The checks read a docblock the way Coder does. A tag is any line
that starts with `@`, at any indent, and only the tags at the column of the first one form the
groups that the blank-line checks look at. The formatter turns several blank lines into one, so
`drupal/file-comment` reports only a file docblock with no blank line below it, and leaves
`SpacingAfterComment` for more than one to `mago format`. The formatter also sets the blank line
between a comment and a closing bracket. It takes the line out, except before the closing brace of a
class, interface, trait or enum with members, where it always writes one, after a comment too.
Coder reports that line as `SpacingAfter`, and a fix that removes it would undo the formatter on
every run. `drupal/inline-comment-blank-line` leaves all of these blank lines to the formatter,
`SpacingAfterAtFunctionEnd` of `Drupal.Commenting.InlineComment` included, and reports the rest of
`SpacingAfter`. The template check of
`Drupal.Commenting.FileComment` is not ported: `drupal/file-comment` reads only procedural files,
and `.tpl.php` templates are a Drupal 7 format.

Core's `phpcs.xml.dist` turns off four checks that the Drupal standard enables: `InvalidEndChar`
and `SpacingAfter` of `Drupal.Commenting.InlineComment`, `LongFullStop` of
`Drupal.Commenting.DocComment`, and `PSR2.Methods.MethodDeclaration.Underscore`. It also does not
run `Drupal.NamingConventions.ValidFunctionName`, whose `InvalidPrefix` check is
`drupal/function-prefix`. Each is a rule of its own here, `drupal/inline-comment-punctuation`,
`drupal/inline-comment-blank-line`, `drupal/long-description-punctuation`,
`drupal/method-name-underscore` and `drupal/function-prefix`, on by default for contrib and custom
code. The worker's `--core` argument turns them off. It also turns off `drupal/short-list`, because
core's config does not run `SlevomatCodingStandard.PHP.ShortList`. A project that turns other sub-codes off in
its phpcs config can turn off the matching rules with `--disable`, see the
[README](../README.md#install).

Core's `phpcs.xml.dist` enables only the `WrongOpenercase` check of `PSR2.ControlStructures.SwitchDeclaration`, so
`drupal/case-fall-through`, which ports its `TerminatingComment` check, is off with `--core`.

Four rules port statement checks, and each differs from Coder where Coder is wrong. A case that ends in
`$x or exit()` or `$x ?? throw ...` falls through when the left side allows it, and
`drupal/case-fall-through` reports it, where Coder ends the case at the keyword. A braceless
`if (...) return; else return;` ends a case, where Coder reports it. A case whose body is a `{ }`
block with no ending statement is reported, where Coder stops with an internal exception for the whole
file. A `break` inside a nested `switch` does not end the outer case, as in Coder.
`drupal/redundant-return` skips a `return;` that is the whole body of an unbraced loop, where Coder
reports it, because removing it changes the loop. It reports a `return;` that ends a `{ }` block at
the end of a body, where Coder stops with an internal exception and loses the rest of the file's
reports for the code. `drupal/parameter-blank-line` and `drupal/inline-comment-blank-line` both report
a blank line below a `//` comment in a parameter list, with fixes for the same line. When both run in
one pass, Mago skips one edit, and the next pass applies it.

`drupal/file-comment` skips a byte order mark before the open tag, so a procedural file with a mark
and a correct docblock gets only the report of `drupal/byte-order-mark`.

The tag rules and the encoding rules differ from Coder in these places:

- `Generic.CodeAnalysis.EmptyPHPStatement` has a second code, for a stray `;`. It stays with Mago's
  `no-noop`, which reports the same spot. `drupal/empty-php-tags` ports only the empty tag pair. It
  also reports a pair at the end of a file, where `no-closing-tag` reports the `?>` too.
- `drupal/short-echo-tag` ports `Generic.PHP.DisallowShortOpenTag.EchoFound`. Mago's
  `no-short-opening-tag` covers the `<?` tag, so the other two codes of that sniff are not ported.
  Coder's fix leaves a tab or a line break after `echo`. The fix here turns spaces and tabs after
  the tag into one space.
- Mago stops parsing a file that ends right after a bare `<?=`, so no rule sees that file. Coder
  reports it, and its fixer crashes on it.
- `drupal/file-encoding` reports at the first open tag or inline text, as Coder does, with a PCRE
  UTF-8 check where Coder calls `mb_check_encoding()`. The text that PHP drops after a `?>`
  does not count as inline text. The rule has no `allowedEncodings` option.
- `drupal/file-start-whitespace` uses Coder's pattern for the text, ASCII whitespace and Unicode
  separators. It does not report the later open tags of a file, as Coder does not. Coder's fix is
  safe. This fix is potentially unsafe, because the removed text is output.
- `drupal/byte-order-mark` reports one mark per file, at the first bytes only, as Coder does. A
  mark further in is not reported.
- The rules read only the extensions Mago scans. Coder also scans `.test`, and core's config
  scans `.yml`, which Mago does not read.

`drupal/function-prefix` and whose `MethodDoubleUnderscore` check is part of
`drupal/method-name-underscore`. It does not run `Drupal.Semantics.ConstantName.ConstConstantStart`
or `Drupal.Attributes.ValidHookName` either. Each is a rule of its own here,
`drupal/inline-comment-punctuation`, `drupal/inline-comment-blank-line`,
`drupal/long-description-punctuation`, `drupal/method-name-underscore`, `drupal/function-prefix`,
`drupal/const-prefix` and `drupal/hook-attribute-name`, on by default for contrib and custom
code. The worker's `--core` argument turns them off. A project that turns other sub-codes off in
its phpcs config can turn off the matching rules with `--disable`, see the
[README](../README.md#install).

The naming rules differ from Coder 9 where a sniff has a bug and not a scope choice:

- `drupal/const-prefix` and `drupal/constant-prefix` need the underscore after the module name.
  Coder 9 only tests that the name starts with the upper-case module name, so for a module named
  `mymod` it accepts `MYMODULE_X`.
- `drupal/define-name` matches `define` without regard to case, with or without a leading
  backslash. A name built from literals joined with `.` is checked as a whole. Coder 9 checks only
  the first literal. A name that has a variable in it is skipped. Coder 9 reports `'mymod_' . $x`.
- `drupal/hook-attribute-name` resolves the attribute name, so an alias or a fully qualified name
  counts and a bare `Hook` that is not imported from Drupal's class does not, and every attribute of a group is checked. It reads only the attribute's own first
  argument. Coder 9 reads the next string literal in the file, so it also reports an unrelated
  string below a `#[Hook(self::CRON)]` or a `#[Hook]` attribute. A hook name built from literals
  joined with `.` is checked as a whole.
- `drupal/class-name-acronym` and `drupal/method-name-underscore` report on the name. Coder 9
  reports on the `class` keyword and the `function` keyword, so the lines differ when a
  declaration spans several lines.

The `MethodDoubleUnderscore` check reads the same 29 names as Coder 9: the 17 magic methods of PHP
and the 12 methods of `SoapClient`. Mago's `method-name` rule reports no name that starts with two
underscores, so no other rule repeats this check.

A few cases differ from Coder. Text on the line of the opening `/**` is reported once, where Coder
also reports it as `SpacingBeforeShort`. A tab after spaces after `//` is reported once, as a tab.
A `@param` line with only trailing whitespace after the variable is not reported. Coder reads that
whitespace as a description on the tag's line.

`drupal/function-comment` checks a constructor that has a docblock, as Coder does. A constructor
with nothing above it needs no docblock. The name is matched in any case, because PHP ignores case
in method names, where Coder matches `__construct` exactly and reports `__CONSTRUCT` as missing a
docblock. A function named `__construct` outside a class is not a constructor and is reported.
Coder exempts it. A docblock tagged `@file` above a function is reported as no docblock for the
function, and its tags are not checked, as in Coder.

A few `drupal/function-comment` reports differ from Coder in these ways.

- `@param` and `@return` types with spaces: a `@param` type is the text before the variable and a
  `@return` type is the text on the tag's line, as in Coder. Both skip a type with a bracket. A
  `@return` type is reported only when a description follows below, and only when the docblock has
  one `@return`. A non-breaking space is not whitespace to Coder or to this rule.
- `@return` with no type on its line: the text below the tag is the description. A bare `@return`
  that is the last tag is left to the built-in `valid-docblock`. A `@return 0` has a type here. Coder
  reports it, because PHP's `empty("0")` is true. A `@return` variable name is a type and one
  variable on the line, as in Coder, so `callable(int $a): int` is not one.
- `@throws` text on the tag's line with nothing below: Coder counts the words on the line, which
  reports a type such as `\Foo2Bar` or `\A|\B` that has no text, and stops at the first `@throws`
  with no text below it. This rule reports only text after the type, and checks every `@throws`.
  The fix moves the text to the next line, three spaces from the star, and is not offered for a tag
  that shares its line with the closing `*/`.
- A `@param` indented inside a `@code` example is not a tag here. Coder reads it as one.

`drupal/doc-comment` checks the space after the star in the docblocks that Coder's
`DocCommentAlignment` picks: the ones whose next token is `class`, `interface`, `function`,
`public`, `private`, `protected`, `static`, `abstract` or `var`, and the one right after the `<?php`
tag. A `final class`, an enum, a trait, a constant or a docblock followed by an attribute is not
checked. The space before the summary is checked in every docblock outside a function body, as
`ShortStartSpace`, and that includes two spaces or a tab. Docblocks inside a function body get the
star check and the two-dot check only. The `@param` groups of a file docblock are checked too.

Where the fixes differ from phpcbf: the closer fix and the two-dot fix are potentially unsafe, since
they drop characters of the comment. phpcbf has no fix for a closer. Its two-dot fix also takes the
space after the opening `/**` or the star, and deletes a docblock that has nothing else in it. The
rule removes the dots and the spaces before them and leaves the docblock. The `@param` groups follow
Coder: a `@param` right below an `@code`, `@todo` or `@link` tag, with no blank line, is reported,
and so is a `@param` inside an `@code` example that sits at the column of the first tag. The
pattern in core's phpcs config that forbids `@inheritDoc` anywhere in a docblock line is not
ported.

The comment-style fixes of `drupal/function-comment`, `drupal/class-comment`,
`drupal/variable-comment` and `drupal/file-comment` turn a `//` run or a `/* */` comment right
above the declaration into a docblock, with an `@file` tag for a file. They are potentially unsafe,
because PHP's reflection returns a docblock and not a comment, so annotation discovery, PHPUnit and
the analyzers start to read the text. They skip a trailing comment of the line above, a comment that
a blank line parts from the declaration, a directive, and a line comment that holds `*/`. A file
comment that already starts with `@file` keeps it once.

`drupal/short-list` reports every `list` keyword, in any letter case, as Coder does. A name that
only looks like the keyword is not a destructuring, so the rule does not see it: `->list`,
`::list`, a method, constant, property or enum case called `list`, a named argument, and text in a
string or a comment. The fix drops what sits between `list` and the parenthesis. Coder drops a
comment there too. Here such a fix is potentially unsafe, and a plain `--fix` leaves it. Coder fixes
an empty `list()` into `[]`, which PHP rejects as well, so the rule reports it and offers no fix.
Coder also reports a `.test` file. Mago reads only the extensions in its `[source]` block, so
add `test` there to check such files.

`drupal/property-per-statement` reads the names of the declaration. Coder looks for the next
variable before the next semicolon, so it reports a property hook that reads `$this` or takes a
`$value` parameter, and a property that is followed by a hook with no semicolon in it. The rule
reports none of those, because a hooked property has one name. Coder skips a property in an enum.
The rule does not look at the enclosing type, so it also reports a multi-name statement in an enum,
which PHP rejects and Mago reports as well. It also reports a multi-name statement whose default is
not a constant expression. The sniff reports the same, and Mago reports that default on its own.
The sniff has no fix, and a split would have to sort out attributes, the docblock and the comments
between the names, so the rule has none either.

`drupal/case-semicolon` ports both `WrongOpenercase` and `WrongOpenerdefault`. Core's config runs
only the first, and the rule reports `default;` too, also under `--core`. Mago's parser does not
accept a label that ends with a close tag, as in `case 1 ?>`, which PHP accepts. Coder reports
those and the rule cannot, because Mago reports a parse error for the file. The rule's fix removes
the blank space in front of the colon, where phpcbf leaves `case 1 :` for another sniff to move.

`drupal/empty-switch` reports a switch with no `case` label, as `MissingCase` of
`Squiz.ControlStructures.SwitchDeclaration` does. It reads the labels in the switch's own body. Coder
walks tokens, so it needs special cases for nested switches. A file that does not parse gives
no report. Coder reports a statement placed directly in a switch body, which is invalid PHP.

`SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator` is `drupal/null-coalesce`. The
rule compares operands by syntax tree where the sniff compares text, so the report is the same
before and after `mago format`. Differences from Coder: the rule reports a ternary after `and`, `or`
and `xor` (the formatter adds parentheses there, and the sniff then reports it), a parenthesized
condition or operand, operands that differ only in quotes or spacing, and an `isset` whose key holds a call with a comma, such as
`isset($a[max(1, 2)])`, which the sniff skips on any comma. It does not report
`!$a === null ? '' : $a`, `(string) $a === null ? '' : $a` or `$b + $a === null ? '' : $a`, because
the compared operand is `!$a`, `(string) $a` or `$b + $a`, and a fix would change the result. It does
not report `$a === null ? '' : $a ?? 'z'`, whose else part is `$a ?? 'z'`, or an `(array)` or `(object)` cast
before `isset`, which always gives `true`. The sniff's fix
drops comments and casts without notice. Here a comment makes the fix potentially unsafe, and a cast
gets no fix.

The DrupalPractice rules read the file name and the syntax tree, and no file on disk. Coder's
`Project` class also reads the nearest `*.info.yml` or `*.info` file. That difference has three
effects. `drupal/class-prefix` and `drupal/form-alter-comment` take the module name from the file
name of a `.module`, `.install`, `.profile` or `.theme` file, and skip other files, where Coder
finds the name in the info file. The name is the part before the first dot, so `foo.bar.module` is
`foo`, where Coder uses `foo.bar`. `drupal/global-constant` and `drupal/request-superglobal` do not
skip a Drupal 7 module, where Coder skips a module whose `.info` file names core 7 or has no core
line.

Where Coder's result is an accident of its tokens, the rules report what the code means.
`drupal/global-constant` treats the body of an `if`, `elseif` or `else` written without braces, and
an arrow function, as nested, because Coder sees no enclosing scope there. It also reports `\define()`
and `DEFINE()`, which Coder misses, does not report `$object?->define()` or `define(...)`, and keeps
a `@deprecated` docblock that is above an attribute list. The `@deprecated` tag must be at the start
of a docblock line, and its name is case-sensitive. A one-line docblock with only the tag counts, and
a tag in the middle of a sentence does not. `drupal/form-alter-comment` compares the function name
without regard to case, because PHP does, and finds the hook line only at the start of a docblock
line, so `@see Implements hook_form_alter().` is not a hook line. `drupal/untranslated-options` takes
the `#type` from the array that holds the `#options`, in either order and in either quote style, where
Coder takes the first `'#type'` of the statement. It reports the last label of an array and a label
in an `array()` group, which Coder skips, and it does not need a comma after the label. It accepts
`Array(`, and does not read a `$form['x']['#options'] = [...]` assignment, which Coder reads only when
a `'#type'` is in the same statement. `drupal/strict-config-schema` uses the nearest named class or
trait for a property of an anonymous class, and not the outermost scope. It does not read a name
like `Testimonial` or `Testable` as a test class. `drupal/curl-ssl-verify` ignores the case of the
function name and of `FALSE`, accepts a leading backslash, a value in parentheses and a zero in any
integer base, and reads the arguments by name. It reads the whole value, so `FALSE ?: TRUE` does not
count, and a method call does not count, `$object?->curl_setopt()` included. It skips a call with
too few arguments, where Coder stops checking the file. `drupal/request-superglobal` reports a use
inside a double-quoted string or a heredoc, which Coder misses. It treats `$_GET /* note */ ['a']`
as an access with a key. The message gives the source text of the key.

## Ported from phpstan-drupal

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/discouraged-function` | Error | A call to a dump helper of the devel module (`dpm()`, `dsm()`, `ksm()`, `kint()` and the other dump helpers that Coder 9 lists), or a call to `fnmatch()`. Some PHP builds do not have `fnmatch()`. Coder 9 also lists `eval`, which Mago's own `no-eval` rule reports. |
| `drupal/symfony-yaml-parse` | Warning | A `Symfony\Component\Yaml\Yaml::parse()` call. It bypasses `\Drupal\Component\Serialization\Yaml::decode()`. |
| `drupal/render-callback` | Error | A `#pre_render`, `#post_render`, `#lazy_builder`, `#access_callback`, date (`#date_date_callbacks`, `#date_time_callbacks`) or component (`#propsAlter`, `#slotsAlter`) callback that is a plain function name string. Drupal trusts only closures, `service:method` strings and class methods. The rule also reports a value that is not an array literal at all, such as `'#pre_render' => $callbacks`, because nothing can be checked there. The rule skips core's `Renderer` and `PlaceholderGenerator` for `#lazy_builder`, because they pass the key through `array_intersect_key()`. |
