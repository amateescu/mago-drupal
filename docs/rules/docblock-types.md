# Docblock types

This rule compares a docblock with the declaration's signature. Coder has no matching sniff.

## drupal/nullable-param-tag

- **Level:** warning
- **Fix:** safe. It appends `|null` to a type that fits on the tag's first line.

An untyped parameter with a `NULL` default whose `@param` type has no `null`, such as
`@param string $rel` on `toUrl($rel = NULL)`. A union member `null`, a `?T` type, `mixed` and a
`@template` name all count. A `null` inside a generic, as in `array<string|null>`, does not. A
`@phpstan-param` or `@psalm-param` tag wins over `@param`.

The docblock is the only type of such a parameter, and tools read it differently. PHPStan adds the
`null` from the default. Mago accepts the default but keeps the documented type, so it reports an
explicit `NULL` argument and does not see that the parameter can be null in the body. A parameter
with a native type is not reported, since every tool reads the native type.

After the fix, the analyzer sees the `null`, and it may report code in the body that does not
handle it.
