---
hide:
  - navigation
---

# Development

```shell
composer install
just check-all
```

`just check` runs `validate`, `format-check`, `test`, `lint` and `analyze`. `just check-all` also
runs `test-presets`, `test-corpus`, `test-fixes` and `test-examples`.

- `test-presets` loads `drupal.mago.toml` and `drupal-core.mago.toml`, the config files that
  projects extend, and fails when Mago doesn't know one of their settings or rule names.
- `test-corpus` starts a real worker over `tests/corpus/src/` and fails if an `@mago-expect`
  annotation isn't fulfilled. `tests/corpus/expected-rules.txt` pins the registered rule codes. Add
  your code there when you add a rule.
- `test-fixes` runs every rule's fix over the before and after pairs in `tests/fixes/`.
- `test-examples` checks the examples in the rule and analyzer docs, see [Rule entries](#rule-entries)
  and [Analyzer entries](#analyzer-entries).

Set `MAGO=/path/to/mago` to run the checks against a different build.

## Layers

The linter rules live in the `amateescu\MagoDrupal\Linter` namespace, the analyzer plugins in
`amateescu\MagoDrupal\Analyzer`, and the code that both use in `amateescu\MagoDrupal\Internal`. The
linter doesn't name the analyzer, the analyzer doesn't name the linter, and `Internal` doesn't name
either of them. `LayersTest` fails when a file breaks this. Only `DrupalExtension` wires the two
halves together.

## Reading code

Rules and checks read code structure from the syntax tree. When the tree doesn't have what they
need, such as the code past the end of a node in a linter snapshot, or a file that isn't in the
run, they read the text through the toolkit classes in `src/Internal`. Those know that a comment
can sit between two tokens, that `?>` ends a `//` comment, that `#[` opens an attribute, and that
a string or a heredoc can hold text that looks like code:

- `SourceText`: `GAP`, a regex fragment for the whitespace and comments between two tokens, and
  `skipBlank()`, `skipLineBlank()` and `commentEnd()`, which step over them.
- `CodeMask`: a file or a piece of code with its comments turned into spaces, and with the text
  of its strings and heredocs too when you ask for it. The offsets don't change, so a regex over
  the mask reads only code.
- `PhpTokens`: the tokens of a file, kept for the last file, and moves over them, such as the
  token at an offset, the next or previous code token, the closer of a bracket and the end of a
  declaration.
- `DeclarationTokens`: the name that a `function` token declares, and where an attribute starts.
- `ClassLikeRanges`: the byte range and name of each class-like in a file, and the quick checks
  for a file that can declare one.
- `PhpcsTokens`: a file split into tokens the way phpcs splits it, for its ignore comments.
- `QuotedStrings`, `LiteralText` and `EscapedQuotes`: the value of a string literal, of literals
  joined with `.`, and a literal that escapes its own quote.
- `LineEnding`: the line ending that a fix writes.
- `CommentAttachment::hasCommentBetween()`: whether the parser's comments include one between two
  offsets.

A raw regex over code is only for a gate: a quick reject before the real work, where a match in a
comment or a string costs time and nothing else. A gate must pass every file that the real check
reports on, so it lets comments sit between tokens with `SourceText::GAP`, matches PHP's keywords
and names in any case, and allows a leading `\` on a name.

## Rule codes

Rule codes are stable. Projects that we don't control write them into baselines and into
`// @mago-expect lint:<code>` comments. For that reason, the codes don't get a vendor prefix or a
new name.

## Docs

The docs are Markdown files in `docs/`, built into a site with [Zensical](https://zensical.org/)
from `zensical.toml`. `just docs` serves the site on http://127.0.0.1:8000 with live reload, and
`just docs-build` builds it into `site/` and fails on a broken link or anchor. Both install the
Zensical version pinned by the `Justfile` into `.venv`.

Some parts of the pages are generated, between `<!-- docs-gen -->` markers: the tables of the
[rules index](rules/index.md), the tables of the [Coming from Coder](coder/index.md) pages, the
[phpstan-drupal](phpstan/phpstan-drupal.md) and [PHPStan identifiers](phpstan/identifiers.md)
pages, and the table of checks on the [analyzer index](analyzer/index.md). They come from the
registered rules, the analyzer entries, `tests/data/coder-map.json`, which says what handles each
code of Coder's two standards, and `tests/data/phpstan-drupal-map.json`, which says what takes the
place of each rule and extension of phpstan-drupal. `just docs-gen` rewrites them. `DocsTest` fails
when they are out of date, when a rule doesn't have an entry on a rule page, and when an entry's
level or Ports codes differ from the rule and the map.

The two maps are edited by hand, and the worker doesn't read them. `just ports-gen` writes the codes
that the rules and checks port into `src/Internal/Ignore/CoderPorts.php` and
`src/Internal/Ignore/PHPStanDrupalPorts.php`, for the phpcs and PHPStan comments that name them.
`PortClassesTest` fails when those classes are out of date, and `just docs-gen` runs
`just ports-gen` first.

When you add a rule, add its entry to the page of its group, mark the Coder codes ported by the
rule with `ext:drupal/<code>` in the map, and run `just docs-gen`.

## Rule entries

A `---` line above each entry's heading separates it from the entry before, and `DocsTest` checks
it. An entry starts with its facts, which are also checked by `DocsTest`:

- the level;
- what the fix does;
- a Standard line that links the sections of Drupal's coding standards checked by the rule, or a
  Source line that names where the check comes from;
- the Coder codes ported by the rule.

Then come:

- for a rule with a Standard line, the standard's own sentence, quoted, so its "must" or "should"
  stays as written;
- a **Reports:** list, and a **Not reported:** list when the rule skips cases;
- an example, when it shows something that the text can't;
- the differences from Coder, in a collapsed `??? note "Compared with Coder"` block.

How to show an example:

- open, as `???+ example "Example"`, when the rule checks a layout across lines or a shape that the
  text describes badly, such as the blank line after a `break` or `!X ? B : X`;
- collapsed, as `??? example "Example"`, when the reported code fits in a sentence, but the fine
  version still helps;
- not at all, when the Reports list already shows the reported code and the fix changes one word,
  such as `else if` or `var`.

An example holds one `php` or `yaml` code block. A `// Reported` comment line opens it, and a
`// Fine.` line splits it into the reported half and the fine half, with `#` in YAML. When the fix
turns one into the other, add the line "The fix turns the reported code into the fine code." above
the code.

`just test-examples` lints both halves with the entry's rule. The reported half must get an issue
and the fine half must not get any, and the fix must give the fine half where the example says so. A
half runs as `example.module`. A `title` on the code block, such as
`title="tests/src/Unit/FooTest.php"`, runs it under that path. A YAML example needs one, such as
`title="example.info.yml"`.

## Analyzer entries

An analyzer page under `docs/analyzer/` starts with the types for its topic, then has an entry for
each check, in the same form as a rule entry. The facts are the level, a Source line that names
where the check comes from, and the phpstan-drupal rules or Coder codes ported by the check. A check
doesn't have a fix, so there isn't a Fix line. A Level line with two levels names the case for the
second, as in "error, or warning for extra arguments". Then come a **Reports:** list, a
**Not reported:** list, an example, and the differences from phpstan-drupal or Coder in a collapsed
`??? note "Compared with phpstan-drupal"` or `??? note "Compared with Coder"` block. A check that
ports a Coder sniff is marked `check:drupal/<code>` in `tests/data/coder-map.json`.
`AnalyzerDocsTest` checks that every check the corpus pins has one entry, with its facts, and
`just docs-gen` builds the table of checks on the analyzer index from the entries.

An analyzer example can hold several code blocks, since a check often needs a services file or a
class next to the reported code. A block with a `// Fine.` line, or `# Fine.` in YAML, splits into
the reported and the fine half, and a block without one belongs to both halves. `just test-examples`
analyzes each half on its own, with the corpus's stand-ins for core: the reported half must get an
issue from the entry's check, at a level that the Level line names, and the fine half must not get
any. A block runs as `modules/example/example.module`, or at the path that its `title` gives from
the workspace root, such as `title="modules/example/src/Greeter.php"`.
