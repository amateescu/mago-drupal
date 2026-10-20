# Fixes

Many rules have a fix, the way phpcbf fixes Coder's sniffs. `mago fix` applies the safe ones and
runs `mago format`, in turn until the code stops changing, so it's the one command to run. It
applies the analyzer's fixes too, unless `--no-analyze` is given. `mago lint --fix` applies only the
lint fixes, and `--dry-run` shows their diff first. Run `mago format` after it. The fixes don't
change the indentation, and some write code that the formatter changes, such as `strict: true`,
which Drupal writes as `TRUE`. Two flags add riskier fixes to either command:

- `--potentially-unsafe` adds the fixes that turn a comment into a docblock, which is then read by
  the analyzers. It also adds the fixes that drop or move comment or docblock text, and the
  `deprecated-tag` rewrite of an old deprecation wording.
- `--unsafe` adds the `weak-hash` rewrite, which changes stored digests.

Both flags also apply Mago's own fixes of that level, such as `strict: true` from
`strict-behavior`. Each rule's entry under [Linter rules](../rules/index.md) says what its fix does.

## The strict-behavior fix

With `--potentially-unsafe`, Mago's `strict-behavior` adds `strict: true` to a call such as
`in_array()` or `array_search()`, which changes what a loose comparison returns. On core, it would
break code that relies on the loose comparison, such as a float compared with a list of ints, or a
value from `explode()` that keeps its spaces. So review each change. Mago doesn't have a flag that
skips one rule's fix. To apply the other potentially unsafe fixes without it, use a config file that
extends the project's `mago.toml` and turns the rule off:

```toml
# mago-fix.toml, next to mago.toml.
extends = "mago.toml"

[linter.rules]
strict-behavior = { enabled = false }
```

```shell
vendor/bin/mago --config mago-fix.toml lint --fix --potentially-unsafe
```

`mago --config mago-fix.toml fix --potentially-unsafe` works the same way.
