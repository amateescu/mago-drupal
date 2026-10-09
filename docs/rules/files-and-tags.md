# Files and PHP tags

Rules for the bytes of a file and for its open and close tags.

## drupal/byte-order-mark

- **Level:** error
- **Fix:** none
- **Ports:** `Generic.Files.ByteOrderMark.Found`

A UTF-8 or UTF-16 byte order mark at the first bytes of a file. PHP sends the mark to the browser
before any code runs. There is no fix, because removing the mark changes what the file sends. The
rule reports one mark per file, at the first bytes only, as Coder does. A mark further in is not
reported.

`drupal/file-comment` skips a byte order mark before the open tag, so a procedural file with a mark
and a correct docblock gets only this rule's report.

## drupal/empty-php-tags

- **Level:** warning
- **Fix:** safe. It removes the pair and the line break that PHP drops after `?>`, so the output
  stays the same. An empty `<?= ?>` has no fix, because PHP rejects it.
- **Ports:** `Generic.CodeAnalysis.EmptyPHPStatement.EmptyPHPOpenCloseTagsDetected`

A `<?php` or `<?=` tag that a `?>` follows with only whitespace between, in any position, also
inside a function or an alternative-syntax block. A comment between the tags keeps the pair from
being reported.

**Compared with Coder:**

- The sniff has a second code, for a stray `;`. It stays with Mago's `no-noop`, which reports the
  same spot.
- The rule also reports a pair at the end of a file, where `no-closing-tag` reports the `?>` too.

## drupal/file-encoding

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Files.FileEncoding.InvalidEncoding`

A file that is not valid UTF-8. The report sits on the first open tag or run of inline text, not on
the bad byte, and the message names no bytes. A file whose only tags are `<?=` is skipped.

**Compared with Coder:** the rule reports at the first open tag or inline text, as Coder does, with
a PCRE UTF-8 check where Coder calls `mb_check_encoding()`. The text that PHP drops after a `?>`
does not count as inline text. The rule has no `allowedEncodings` option.

## drupal/file-start-whitespace

- **Level:** error
- **Fix:** potentially unsafe. It deletes the whitespace, which a template can print.
- **Ports:** `Squiz.WhiteSpace.SuperfluousWhitespace.StartFile`

Whitespace before the first `<?php` of a file. Text that is not whitespace, such as a byte order
mark, a zero-width space or a `#!` line, is not reported.

**Compared with Coder:** the rule uses Coder's pattern for the text, ASCII whitespace and Unicode
separators. It does not report the later open tags of a file, as Coder does not. Coder's fix is
safe. This fix is potentially unsafe, because PHP sends the removed text as output.

## drupal/short-echo-tag

- **Level:** error
- **Fix:** safe. It writes `<?php echo` with one space before the value, and keeps a line break and
  any comment where they are.
- **Ports:** `Generic.PHP.DisallowShortOpenTag.EchoFound`

A `<?=` tag that has a value to echo. An echo tag with no value is left to `drupal/empty-php-tags`.

**Compared with Coder:**

- Mago's `no-short-opening-tag` covers the `<?` tag, so the other two codes of the sniff are not
  ported.
- Coder's fix leaves a tab or a line break after `echo`. This fix turns spaces and tabs after the
  tag into one space.
- Mago stops parsing a file that ends right after a bare `<?=`, so no rule sees that file. Coder
  reports it, and its fixer crashes on it.
