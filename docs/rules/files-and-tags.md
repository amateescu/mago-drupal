# Files and PHP tags

Rules for the bytes of a file and for its open and close tags.

---

## drupal/byte-order-mark

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.Files.ByteOrderMark.Found`

Coder asks for no byte order mark at the start of a file. PHP sends the mark to the browser before
any code runs.

**Reports:**

- a UTF-8 or UTF-16 byte order mark at the first bytes of a file, once per file.

**Not reported:**

- a mark further in.

There is no fix, because removing the mark changes what the file sends.

`drupal/file-comment` and `drupal/missing-file-comment` skip a byte order mark before the open tag,
so a procedural file with a mark and a correct docblock gets only this rule's report.

---

## drupal/empty-php-tags

- **Level:** warning
- **Fix:** safe. It removes the pair and the line break that PHP drops after `?>`, so the output
  stays the same. An empty `<?= ?>` has no fix, because PHP rejects it.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.CodeAnalysis.EmptyPHPStatement.EmptyPHPOpenCloseTagsDetected`

Coder asks for no empty pair of PHP tags.

**Reports:**

- a `<?php` or `<?=` tag that a `?>` follows with only whitespace between, as in `<?php ?>`, in any
  position, also inside a function or an alternative-syntax block.

**Not reported:**

- a pair with a comment between the tags.

??? note "Compared with Coder"

    - The sniff has a second code, for a stray `;`. It stays with Mago's `no-noop`, which reports
      the same spot.
    - The rule also reports a pair at the end of a file, where `no-closing-tag` reports the `?>`
      too.

---

## drupal/file-encoding

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Files.FileEncoding.InvalidEncoding`

Coder asks for files in UTF-8.

**Reports:**

- a file that is not valid UTF-8. The report sits on the first open tag or run of inline text, not
  on the bad byte, and the message names no bytes. The text that PHP drops after a `?>` does not
  count as inline text.

**Not reported:**

- a file whose only tags are `<?=`.

??? note "Compared with Coder"

    - The rule checks UTF-8 with a PCRE pattern, where Coder calls `mb_check_encoding()`.
    - Coder has an `allowedEncodings` option. The rule has none.

---

## drupal/file-start-whitespace

- **Level:** error
- **Fix:** potentially unsafe. It deletes the whitespace, which a template can print.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Squiz.WhiteSpace.SuperfluousWhitespace.StartFile`

Coder asks for no whitespace before the first `<?php` of a file.

**Reports:**

- whitespace before the first `<?php` of a file. Whitespace is ASCII whitespace and Unicode
  separators, Coder's pattern.

**Not reported:**

- text that is not whitespace, such as a byte order mark, a zero-width space or a `#!` line;
- whitespace before a later open tag of the file.

??? note "Compared with Coder"

    - Coder's fix is safe. This fix is potentially unsafe, because PHP sends the removed text as
      output.

---

## drupal/short-echo-tag

- **Level:** error
- **Fix:** safe. It writes `<?php echo` with one space before the value, and keeps a line break and
  any comment where they are.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.PHP.DisallowShortOpenTag.EchoFound`

Coder asks for `<?php echo` in place of the `<?=` short echo tag.

**Reports:**

- a `<?=` tag that has a value to echo.

**Not reported:**

- an echo tag with no value, which is left to `drupal/empty-php-tags`.

??? note "Compared with Coder"

    - Mago's `no-short-opening-tag` covers the `<?` tag, so the other two codes of the sniff are not
      ported.
    - Coder's fix leaves a tab or a line break after `echo`. This fix turns spaces and tabs after
      the tag into one space.
    - Mago stops parsing a file that ends right after a bare `<?=`, so no rule sees that file. Coder
      reports it, and its fixer crashes on it.
