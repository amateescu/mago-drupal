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
| `drupal/translatable-string` | Warning | A translatable string that is built by concatenation or interpolation, is padded with whitespace, or is empty. The rule covers `t()`, `formatPlural()` and `new TranslatableMarkup()`. |
| `drupal/translated-exception` | Warning | An exception message that is passed through `t()`. |
| `drupal/unsilenced-deprecation` | Error | A `trigger_error()` deprecation notice without the `@` prefix. Drupal turns an unsilenced notice into a test failure. A fix adds the `@`. |

## Procedural files

These rules report only in `.module` and `.install` files.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/constant-prefix` | Warning | A `define()` constant without the module prefix. |
| `drupal/empty-install-hook` | Error | An empty `hook_install()` or `hook_uninstall()` body. |
| `drupal/global-variable` | Error | A module global without the leading underscore. |
| `drupal/install-hook-location` | Error | A `hook_install()` or other install hook that is declared in `.module` and not in `.install`. |
| `drupal/t-in-hook-schema` | Error | A `t()` call inside `hook_schema()`. |

## Naming, imports and syntax

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/else-if` | Error | An `else if` written as two keywords. Drupal writes `elseif`. A fix joins the keywords. The rule skips a braced `else { if ... }`. |
| `drupal/enum-case-name` | Error | An enum case that is not UpperCamelCase. |
| `drupal/fully-qualified-name` | Error | A namespaced class written out in full where a `use` statement belongs. The rule skips a name with no namespace of its own, such as `\Exception`, and a namespaced function call or first-class callable. The rule skips an `.api.php` file completely. A fix adds the import and writes the short name everywhere the file writes the class in full. It skips a file with no namespace, several namespaces or a braced one, or an import below code, a constant, and a short name the file already uses for something else: another import, a class of that name, or a docblock type. |
| `drupal/method-visibility` | Error | A method declared without `public`, `protected` or `private`. A fix adds `public`. |
| `drupal/property-name` | Error | A class property that is not lowerCamelCase. |
| `drupal/redundant-use` | Error | A `use` statement that imports a class from the global namespace. A fix removes the import and writes `\Exception` at every reference, read from the resolved names. It is left out while a docblock in the file names the class without a leading backslash, in a type, an annotation or prose. `drupal/doc-type-namespace` fixes the types. |
| `drupal/use-leading-backslash` | Error | An import whose class name starts with a backslash. A fix removes the backslash. |

## Comment text

These rules read only a comment's text. They do not compare it against the declaration that it
documents. The group has the part of `Drupal.Commenting.*` that works that way, the `@author` ban,
and the line-length check for comments.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/author-tag` | Warning | An `@author` tag. The tag goes out of date as other people edit the file. |
| `drupal/comment-line-length` | Warning | A comment line that is longer than 80 characters. |
| `drupal/doc-comment-array-syntax` | Warning | The `array()` syntax inside a docblock `@code` example. |
| `drupal/doc-type-namespace` | Warning | A `@param`, `@return`, `@var` or `@throws` type written as an imported short name and not as the fully qualified name. A fix writes the fully qualified name for each such member of the type, when the type starts on the tag's line and the docblock is below the import, in a file with one namespace. |
| `drupal/expected-exception-tag` | Warning | A legacy PHPUnit `@expectedException*` docblock tag. |
| `drupal/gender-neutral-comment` | Warning | A gendered pronoun in a comment. |
| `drupal/inline-comment` | Warning | A `//` comment that starts with a lowercase letter, has no terminal punctuation, or uses `#` and not `//`. Also the space after `//`: one space and no tab, or more to line up with a list item or `@todo` on the line above. A fix sets the space. A line indented deeper than the comment line above for no such reason is reported without a fix. A comment after a `}` on its line, an `@code` example and a `phpcs:` line are skipped. |
| `drupal/post-statement-comment` | Warning | A `//` comment on the same line as the statement before it. A fix moves the comment to its own line above. It is left out where the move could attach the comment to something else: a line that opens a block or closes a construct, a line below a docblock or another comment, such as an `@phpstan-ignore` for the statement, a line inside a string, a comment that the next line continues, and a comment that applies to one line, such as `cspell:disable-line` or `@codeCoverageIgnore`. |
| `drupal/todo-comment` | Warning | A to-do comment that does not follow the `@todo Fix problem X here.` format. |

Mago's own `tagged-todo` rule must have a `TODO(@user)` or `TODO(#123)` reference. That format is
not compatible with Drupal's `@todo Fix problem X here.` convention, so `tagged-todo` is not a
substitute for `drupal/todo-comment`. Do not run the two rules on the same codebase.

## Docblock structure

These rules read a docblock's summary, description and tag list. They do not need the declaration's
real signature. The group has the rest of `Drupal.Commenting.*`.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/class-comment` | Error | A class, interface, trait or enum with no docblock, with the wrong comment style, or with a summary that only repeats the name. Also a blank line between the docblock and the declaration, which a fix removes. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |
| `drupal/deprecated-tag` | Warning | A `@deprecated` tag that breaks the version-and-reason grammar, or that has no `@see` tag after it. The change-record url is the first line of the `@see` tag. A fix removes the periods after it. |
| `drupal/doc-comment` | Warning | A docblock with no summary, or with `@param` tags that are not in the first group. Also a summary that is not capitalized, that has no punctuation, or that spans more than one line, and `@inheritdoc` without braces, which a fix writes as `{@inheritdoc}`. Also the docblock's whitespace, each with a fix: text on the line of the opening `/**`, blank lines at its start or end, more than one blank line between the summary and the description, not exactly one blank line before the tags, no blank line between the `@param`, `@return` and `@throws` sections and the tags next to them, more than one after a section, and not exactly one space before the summary or after a tag. |
| `drupal/file-comment` | Error | A procedural file that does not start with a docblock that has the `@file` tag. A directive such as `// phpcs:ignoreFile` above the docblock is skipped. A fix adds `@file` below the docblock's opener when a blank line parts the docblock from the code. Another adds that blank line when the code starts right below the docblock. A potentially unsafe fix turns a comment in the wrong style into a docblock with `@file`, see below. |
| `drupal/function-comment` | Error | A function or method with no docblock or with the wrong comment style. Also a `@param`, `@return`, `@throws` or `@see` tag that is malformed, has no description, or is not capitalized. A `@return` variable name and a `@see` reference are read from the tag's own line. Fixes remove a period after a `@param` name, a variable name after a `@return` type that has a description below, and punctuation after a one-word `@see` reference, and add a full stop to a `@param` description that does not end in a url, a tag or `:`, `,` or `;`. Also, each with a fix: a blank line between the docblock and the function, a `@param` description on the tag's line, not exactly one space between a `@param` type and its variable, and a `@param`, `@return` or `@throws` description that is not indented three spaces from the star. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |
| `drupal/hook-comment` | Warning | A hook implementation that is not documented as `Implements hook_foo().`, or that duplicates the `@param` or `@return` documentation. |
| `drupal/inline-variable-comment` | Warning | An inline `@var` declaration that uses `//` and not `/** */`, or that writes the variable name before the type. A `//` or `#` comment that holds `*/`, such as a commented-out docblock, is skipped. Two fixes are potentially unsafe, because the analyzers start to trust the type: one moves a variable name written first after the type, and the other turns a comment that holds only the tag, alone on its line, into a docblock. |
| `drupal/variable-comment` | Error | A class property with no `@var` docblock, with the wrong comment style, or with more than one `@var` tag. A fix removes a property name repeated after the `@var` type. A potentially unsafe fix turns a comment in the wrong style into a docblock, see below. |

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
value, a function with no `return` at all, an `@param` that names an unknown parameter, and most
type aliases with the wrong case (`Boolean` does not resolve as a class).

`drupal/function-comment` covers the rest: docblock presence, prose quality,
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

Two `Drupal.Commenting.*` sniffs are pure whitespace and are not ported, because `mago format`
produces their result: `DocCommentAlignment` (star spacing and alignment) and `DocCommentStar` (a
star on a docblock line that has none).

The rules port the rest of the comment whitespace that core's `phpcs.xml.dist` enables, with a fix
wherever phpcbf has one, and for `TrhowsCommentIndentation` too. The checks read a
docblock the way Coder does. A tag is any line that starts with `@`, at any indent, and only the
tags at the column of the first one form the groups that the blank-line checks look at. The
formatter turns several blank lines into one, so `drupal/file-comment` reports only a file docblock
with no blank line below it, and leaves `SpacingAfterComment` for more than one to `mago format`.
`SpacingAfterAtFunctionEnd` of `Drupal.Commenting.InlineComment` is also the formatter's, which
removes a blank line before a closing brace. `SpacingAfter` of that sniff is not ported, because
core's `phpcs.xml.dist` excludes it, and neither is the template check of
`Drupal.Commenting.FileComment`.

A few cases differ from Coder. Text on the line of the opening `/**` is reported once, where Coder
also reports it as `SpacingBeforeShort`. A tab after spaces after `//` is reported once, as a tab.
A `@param` line with only trailing whitespace after the variable is not reported. Coder reads that
whitespace as a description on the tag's line. `drupal/function-comment` skips a constructor, so the
whitespace in a constructor's docblock is not checked. Coder checks it when the constructor has a
docblock.

The comment-style fixes of `drupal/function-comment`, `drupal/class-comment`,
`drupal/variable-comment` and `drupal/file-comment` turn a `//` run or a `/* */` comment right
above the declaration into a docblock, with an `@file` tag for a file. They are potentially unsafe,
because PHP's reflection returns a docblock and not a comment, so annotation discovery, PHPUnit and
the analyzers start to read the text. They skip a trailing comment of the line above, a comment that
a blank line parts from the declaration, a directive, and a line comment that holds `*/`. A file
comment that already starts with `@file` keeps it once.

## Ported from phpstan-drupal

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/discouraged-function` | Error | A call to a dump helper of the devel module (`dpm()`, `dsm()`, `dpr()`, `kpr()` and the other dump helpers), or a call to `fnmatch()`. Some PHP builds do not have `fnmatch()`. |
| `drupal/symfony-yaml-parse` | Warning | A `Symfony\Component\Yaml\Yaml::parse()` call. It bypasses `\Drupal\Component\Serialization\Yaml::decode()`. |
| `drupal/render-callback` | Error | A `#pre_render`, `#post_render`, `#lazy_builder`, `#access_callback` or date callback that is a plain function name string. Drupal trusts only closures, `service:method` strings and class methods. The rule also reports a value that is not an array literal at all, such as `'#pre_render' => $callbacks`, because nothing can be checked there. The rule skips core's `Renderer` and `PlaceholderGenerator` for `#lazy_builder`, because they pass the key through `array_intersect_key()`. |
