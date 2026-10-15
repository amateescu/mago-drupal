# Fixes

Many rules have a fix, the way phpcbf fixes Coder's sniffs. `mago lint --fix` applies the safe ones,
and `mago fix` applies them until the code stops changing. `--dry-run` shows the diff first. Two
flags add riskier fixes:

- `--potentially-unsafe` adds the fixes that turn a comment into a docblock, which is then read by
  the analyzers. It also adds the fixes that drop or move comment or docblock text, and the
  `deprecated-tag` rewrite of an old deprecation wording.
- `--unsafe` adds the `weak-hash` rewrite, which changes stored digests.

Both flags also apply Mago's own fixes of that level, such as `strict: true` from
`strict-behavior`, which can change what a loose comparison returns. Each rule's entry under
[Linter rules](../rules/index.md) says what its fix does.
