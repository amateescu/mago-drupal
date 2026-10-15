# Comment text

These rules read only the text of a comment, and don't compare it against the declaration that it
documents. They cover the part of `Drupal.Commenting.*` that works that way, the `@author` ban and
the line-length check for comments. For the comment whitespace set by `mago format`, see
[Comment whitespace and the formatter](../coder/index.md#comment-whitespace-and-the-formatter).

---

## drupal/author-tag

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Commenting.AuthorTag.AuthorFound`
- **Off with `--core`**

DrupalPractice asks for no `@author` tag. The tag goes out of date as other people edit the file,
and git already records who wrote what.

**Reports:**

- an `@author` tag, in any letter case, such as `@Author`.

??? note "Compared with Coder"

    - Coder reports only `@author`, not `@Author` or other letter cases.

---

## drupal/comment-line-length

- **Level:** warning
- **Fix:** none
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation)
- **Ports:** `Drupal.Files.LineLength.TooLong`

> Lines containing comments (including a docblock) must wrap as close to 80 characters as possible
> without going over, with a few exceptions (noted in the Tag Reference below).

**Reports:**

- a comment line that is longer than 80 characters.

**Not reported:**

- docblock tag lines;
- `@code` examples, up to `@endcode` or the next tag;
- `// @see`-style reference lines;
- annotation values;
- the `Implements hook_foo()` and `Contains ...` lines;
- a line whose last word is too long for a line of its own, such as a URL or a long class path.

Each of these holds text that can't be wrapped without damage.

`mago format` wraps code to its line width, but leaves comment text as written, so the formatter
can't do this check. A deeper indent from the formatter can push a comment past 80 characters.

??? note "Compared with Coder"

    - The rule reports a line that ends in `*/`. That includes a one-line `/* ... */` or
      `/** ... */` comment, a `/* ... */` comment after code, and the last line of a longer comment
      with text before the `*/`. phpcs skips these lines, because each one ends on a whitespace
      token and not on a comment token. Both tools report the lines above the closer.

---

## drupal/doc-comment-array-syntax

- **Level:** warning
- **Fix:** none
- **Standard:** [Arrays](https://project.pages.drupalcode.org/coding_standards/php/coding/#arrays)
- **Ports:** `Drupal.Commenting.DocCommentLongArraySyntax.DocLongArray`

> Arrays should be formatted using short array syntax with a space separating each element (after
> the comma), and spaces around the => key association operator, if applicable:

**Reports:**

- the `array()` syntax inside a docblock `@code` example.

Mago doesn't parse the example as code, so no other rule sees it.

??? example "Example"

    ```php
    // Reported.
    /**
     * Returns the default options.
     *
     * @code
     * $options = array('limit' => 10);
     * @endcode
     */
    function example_options(): array {
      return [];
    }

    // Fine.
    /**
     * Returns the default options.
     *
     * @code
     * $options = ['limit' => 10];
     * @endcode
     */
    function example_options(): array {
      return [];
    }
    ```

??? note "Compared with Coder"

    - The rule reports `array()` in a `@code` example that has no `@endcode`, up to the end of the
      docblock. Coder takes such a docblock as malformed and skips the rest of it.

---

## drupal/doc-type-namespace

- **Level:** warning
- **Fix:** safe. It writes the fully qualified name, and removes the import when nothing else uses
  it.
- **Standard:** [Classes and namespaces](https://project.pages.drupalcode.org/coding_standards/php/documentation/#classes-and-namespaces),
  [Data types](https://project.pages.drupalcode.org/coding_standards/php/documentation/#data-types-in-documentation)
- **Ports:** `SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse` (partly)

> Immediately after an @tag (@param, @return, @var, etc.), class and interface names must always
> include the fully-qualified namespace.

**Reports:**

- a `@param`, `@return`, `@var` or `@throws` type written as the short name of a class that only
  docblocks use. The rule reads every name in the type, including generic arguments, array shape
  values and callable parameters, such as `Cc` in `array<string, Cc>`.

**Not reported:**

- a short name whose import the code uses too;
- a name after the first space outside brackets, where the type ends, so the return type in
  `callable(Foo): Bar` isn't read.

The fix writes the fully qualified name for each such name in the type, when the whole type is on
the tag's line and the docblock is below the import, in a file with one namespace. It removes the
import when every mention of the name in the file's docblocks is rewritten.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php title="src/TitleFormatter.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\node\NodeInterface;

    /**
     * Formats node titles.
     */
    class TitleFormatter {

      /**
       * Returns the title of a node.
       *
       * @param NodeInterface $node
       *   The node.
       *
       * @return string
       *   The title.
       */
      public function format($node): string {
        return $node->label();
      }

    }

    // Fine.
    namespace Drupal\example;

    /**
     * Formats node titles.
     */
    class TitleFormatter {

      /**
       * Returns the title of a node.
       *
       * @param \Drupal\node\NodeInterface $node
       *   The node.
       *
       * @return string
       *   The title.
       */
      public function format($node): string {
        return $node->label();
      }

    }
    ```

??? note "Compared with Coder"

    - Coder 9 accepts short names in docblocks, but its `UnusedUses` sniff doesn't read docblocks.
      It reports such an import as unused, and phpcbf deletes it, which leaves the docblock naming a
      class that no longer resolves. Mago counts a docblock mention as a use, so its
      `no-redundant-use` reports only the imports that nothing uses, and this rule reports the ones
      that only docblocks use.
    - An import that only a `@see` tag mentions is reported by neither rule, and the same goes for
      any tag other than `@param`, `@return`, `@var` and `@throws`. Coder reports it as unused.
    - Coder 8's `Drupal.Commenting.DataTypeNamespace`, which is still run by core 11.4's
      `phpcs.xml.dist`, reports a short name even when the code uses its import too. No such sniff
      exists in Coder 9.

---

## drupal/expected-exception-tag

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Commenting.ExpectedException.TagFound`

DrupalPractice asks for no legacy PHPUnit `@expectedException*` docblock tags. PHPUnit no longer has
these tags. Use `expectException()` and the related methods.

**Reports:**

- an `@expectedException*` tag at the start of a docblock line, after the star and any indent.
  That includes a tag indented under the description of another tag.

**Not reported:**

- a tag name after other text on the line.

??? example "Example"

    ```php title="tests/src/Unit/ParserTest.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    use Drupal\Tests\UnitTestCase;

    /**
     * Tests the parser.
     */
    class ParserTest extends UnitTestCase {

      /**
       * Tests a broken input.
       *
       * @expectedException \InvalidArgumentException
       */
      public function testBrokenInput(): void {
        $this->parser->parse('{');
      }

    }

    // Fine.
    namespace Drupal\Tests\example\Unit;

    use Drupal\Tests\UnitTestCase;

    /**
     * Tests the parser.
     */
    class ParserTest extends UnitTestCase {

      /**
       * Tests a broken input.
       */
      public function testBrokenInput(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('{');
      }

    }
    ```

??? note "Compared with Coder"

    - The rule matches the tag in any letter case, such as `@ExpectedException`. Coder reports only
      the exact spelling, such as `@expectedException`.

---

## drupal/gender-neutral-comment

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Commenting.GenderNeutralComment.GenderNeutral`

Coder asks for no gendered pronouns in comments.

**Reports:**

- a gendered pronoun in a comment: he, her, hers, him, his or she, in any letter case. Each line of
  a comment that holds one is reported on its own. On a docblock line the tag name is skipped and
  the text after it is read.

---

## drupal/inline-comment

- **Level:** warning
- **Fix:** safe. It uppercases the first letter and sets the space after `//`. The other checks
  have no fix.
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation),
  [In-line code comments](https://project.pages.drupalcode.org/coding_standards/php/documentation/#in-line-code-comments)
- **Ports:** `Drupal.Commenting.InlineComment`: `DocBlock`, `NoSpaceBefore`, `NotCapital`, `SpacingBefore`, `TabBefore`, `WrongStyle`

> Put a space between the comment character (the `*` or the `//`) and the first letter of the
> sentence.
>
> C style comments (`/* */`) and standard C++ comments (`//`) are both fine, though the former is
> discouraged within functions (even for multiple lines, repeat the `//` single-line comment). Use
> of Perl/shell style comments (`#`) is discouraged.

**Reports:**

- a `//` comment that starts with a lower-case letter;
- a `#` comment. `mago format` also writes it as `//`;
- a `//` comment that doesn't have one space after `//`, or has a tab. More spaces are fine to line
  up with a list item or `@todo` on the line above. A line indented deeper than the comment line
  above for no such reason is reported without a fix;
- a `/**` docblock inside a body, such as a function, a class or an `if`, that doesn't start with a
  tag and isn't in front of a declaration, an enum case, an include or a modifier.

**Not reported:**

- a `//` comment after a `}` on its line, such as `} // end if`. The `//` lines right below such a
  comment are checked as a comment of their own;
- an `@code` example and a `phpcs:` line;
- a docblock outside every body.

??? example "Example"

    ```php
    // Reported.
    function example_total(array $items): int {
      /** Adds up the prices. */
      return array_sum(array_column($items, 'price'));
    }

    // Fine.
    function example_total(array $items): int {
      // Adds up the prices.
      return array_sum(array_column($items, 'price'));
    }

    function example_title(array $nodes): string {
      /** @var \Drupal\node\NodeInterface $node */
      $node = reset($nodes);
      return $node->label();
    }
    ```

??? note "Compared with Coder"

    - A tab after spaces after `//` is reported once, as a tab.
    - The rule doesn't report an empty docblock inside a body, which is reported by Mago's
      `no-empty-comment`. It treats `/***` and `/**/` as no docblock, as Coder does.
    - Coder reports the docblock of an enum case and of a property declared `readonly` with no
      visibility keyword, because its list of declaration keywords has neither. The rule takes both
      for declarations, as PHP does.

---

## drupal/inline-comment-blank-line

- **Level:** warning
- **Fix:** safe. It removes the line.
- **Standard:** [In-line code comments](https://project.pages.drupalcode.org/coding_standards/php/documentation/#in-line-code-comments)
- **Ports:**
    - `Drupal.Commenting.InlineComment.SpacingAfter` (partly)
    - `DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter` (partly)
- **Off with `--core`**

> Comments should be on a separate line immediately before the code line or block they reference.

**Reports:**

- a blank line below a `//` or `#` comment on its own line. A `@codingStandardsIgnore*` line counts
  as a comment, as it does for Coder 9, which runs on PHP_CodeSniffer 4, where those annotations no
  longer work.

**Not reported:**

- a `phpcs:` directive line;
- a `//` comment with no text, a comment right before a docblock, and a `// @code` example;
- a blank line before a closing bracket, which is handled by `mago format`. The formatter takes such
  a line out, except before the closing brace of a class, interface, trait or enum with members,
  where it always writes one, after a comment too.

`drupal/parameter-blank-line` also reports a blank line below a `//` comment in a parameter list,
with a fix for the same line. When both rules run in one pass, Mago skips one edit, and the next
pass applies it.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_reset(array &$cache): void {
      // Start from an empty cache.

      $cache = [];
    }

    // Fine.
    function example_reset(array &$cache): void {
      // Start from an empty cache.
      $cache = [];
    }
    ```

??? note "Compared with Coder"

    - Coder reports a blank line before a closing bracket, and that includes its
      `SpacingAfterAtFunctionEnd`. A fix that removes the line would undo the formatter on every
      run.
    - `Drupal.Commenting.InlineComment` skips a `//` comment with no text, a comment right before a
      docblock and a `// @code` example, as the rule does. DrupalPractice's `CommentEmptyLine`
      reports all three.
    - Coder reports the blank line below a `#` comment once its own fix has turned the comment into
      `//`. The rule reports it at once.

---

## drupal/inline-comment-punctuation

- **Level:** warning
- **Fix:** safe. It appends a full stop, as phpcbf does, so a comment that ends with `,` ends with
  `,.`.
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation)
- **Ports:** `Drupal.Commenting.InlineComment.InvalidEndChar`
- **Off with `--core`**

> All documentation and comments should form proper sentences, use proper grammar and punctuation,
> and generally follow the same style guidelines as Drupal.org content:
> [http://drupal.org/style-guide/content](http://drupal.org/style-guide/content)

**Reports:**

- a `//` comment that doesn't end with a full stop, an exclamation mark, a question mark, a colon or
  a closing parenthesis.

**Not reported:**

- a comment whose first word doesn't start with a letter;
- a numbered list item;
- a comment with a `cspell:` line;
- a comment whose last word holds a url or a function call such as `foo()`, or starts with `@`;
- a comment after a `}` on its line, such as `} // end if`. The `//` lines right below such a
  comment are checked as a comment of their own.

---

## drupal/long-description-punctuation

- **Level:** warning
- **Fix:** safe. It adds a full stop.
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation)
- **Ports:** `Drupal.Commenting.DocComment.LongFullStop`
- **Off with `--core`**

> All documentation and comments should form proper sentences, use proper grammar and punctuation,
> and generally follow the same style guidelines as Drupal.org content:
> [http://drupal.org/style-guide/content](http://drupal.org/style-guide/content)

**Reports:**

- a docblock long description that ends with a letter.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    /**
     * Counts the items.
     *
     * Hidden items are left out
     */
    function example_count(array $items): int {
      return count($items);
    }

    // Fine.
    /**
     * Counts the items.
     *
     * Hidden items are left out.
     */
    function example_count(array $items): int {
      return count($items);
    }
    ```

---

## drupal/post-statement-comment

- **Level:** warning
- **Fix:** safe. It moves the comment to its own line above the statement, or below the opening
  brace of a class.
- **Standard:** [In-line code comments](https://project.pages.drupalcode.org/coding_standards/php/documentation/#in-line-code-comments)
- **Ports:**
    - `Drupal.Classes.ClassDeclaration.ContentAfterBrace`
    - `Drupal.Commenting.PostStatementComment.Found`

> Comments should be on a separate line immediately before the code line or block they reference.

**Reports:**

- a `//` comment on the same line as the statement before it;
- a `//`, `#` or `/* */` comment after the opening brace of a class, interface, trait, enum or
  anonymous class, on the brace's line.

**Not reported:**

- a trailing `phpcs:`, `@mago-`, `@phpstan-`, `@psalm-` or `@codingStandardsIgnore` comment,
  because moving a tool pragma can change the lines it covers. An `@phpstan-ignore-line` moved to a
  line of its own would cover no statement;
- a docblock after the opening brace of a class, which
  [`drupal/doc-comment`](docblock-structure.md#drupaldoc-comment) reports.

The fix puts a comment from the line of a class's opening brace directly above the first member,
with no blank line between them, or on the line below the brace when the body is empty. `mago format`
then adds the blank line after the brace. A method's docblock or attributes get one blank line
above them, which Coder's `Squiz.WhiteSpace.FunctionSpacing.Before` counts from the comment.

There is no fix where the move could attach the comment to something else:

- a line that opens a block, other than a class body, or closes a construct;
- a line below a docblock or another comment, such as an `@phpstan-ignore` for the statement;
- a line inside a string;
- a comment that the next line continues;
- a comment that applies to one line, such as `cspell:disable-line` or `@codeCoverageIgnore`;
- a comment after a class's opening brace with code after it on the same line.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_sum(array $prices): int {
      $total = 10;
      $total += array_sum($prices); // Prices are in cents.
      return $total;
    }

    // Fine.
    function example_sum(array $prices): int {
      $total = 10;
      // Prices are in cents.
      $total += array_sum($prices);
      return $total;
    }
    ```

??? note "Compared with Coder"

    - Coder skips only a `phpcs:` comment, not the other tool pragmas.
    - phpcbf moves every comment it reports, also one below a docblock or another comment. It
      writes the comment at the start of the line and leaves a space after the statement.
    - Coder doesn't report a block comment after the opening brace of an anonymous class as
      `ContentAfterBrace`. It reports the space before it as
      `Drupal.WhiteSpace.OpenBracketSpacing.OpeningWhitespace`, which the move removes too.
    - phpcbf turns a `//` comment after the opening brace of a class into a second docblock above
      the class.
    - For a `//` comment that the fix puts above a method's docblock, DrupalPractice's
      `CommentEmptyLine.SpacingAfter` reports the blank line between them, and Coder's
      `FunctionSpacing.Before` reports the layout without it. The fix keeps the blank line, which
      [`drupal/inline-comment-blank-line`](#drupalinline-comment-blank-line) allows.

---

## drupal/todo-comment

- **Level:** warning
- **Fix:** safe. It writes `@todo ` in place of the spelling and the dashes, colons and spaces after
  it.
- **Standard:** [@todo](https://project.pages.drupalcode.org/coding_standards/php/documentation/#todo-to-do-notes)
- **Ports:** `Drupal.Commenting.TodoComment.TodoFormat`

The standard has no sentence for the format. Its `@todo` examples write `@todo` followed by the
text, in a docblock and in a `//` comment.

**Reports:**

- a to-do comment that doesn't follow the
  [`@todo Fix problem X here.`](https://www.drupal.org/node/1354) format, such as `TODO:`, `@TODO`,
  `to-do` or `@todo - fix`.

**Not reported:**

- a to-do with no text;
- a word that only starts with "todo", such as "todos".

Mago's own `tagged-todo` rule asks for a `TODO(@user)` or `TODO(#123)` reference. That format
contradicts Drupal's, so don't run the two rules on the same codebase. The
[configuration](../setup/configuration.md#turn-off-what-drupals-standard-doesnt-ask-for) turns
`tagged-todo` off.
