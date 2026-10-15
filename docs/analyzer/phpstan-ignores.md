# PHPStan ignores

The `phpstan-ignores` plugin is for a project that also runs PHPStan. It lets a `@phpstan-ignore`
comment drop the Mago issues that report the same finding, so the line needs no `@mago-expect` on
top.

## Turning it on

The plugin is off by default. Turn it on in `mago.toml`:

```toml
[analyzer]
plugins = ["phpstan-ignores"]
```

```php
// @phpstan-ignore return.type (the caller handles NULL)
return NULL;  // no invalid-return-statement
```

## Comment forms

The plugin reads PHPStan's forms:

- `@phpstan-ignore` with identifiers separated by commas, and an optional reason in parentheses
  after any of them. It sits at the end of the line, or above it, where it covers the next line of
  code;
- `@phpstan-ignore-next-line`;
- `@phpstan-ignore-line`.

PHPStan wants that code on the very next line, and calls a comment with anything in between unused.
The plugin skips blank lines and other comments, so a `@mago-expect` can sit between the two.

## Which issues a comment drops

An identifier drops only the Mago codes listed for it by the plugin, so
`@phpstan-ignore return.type` still lets a missing method on the same line through. The two line
forms drop every listed code, and an identifier not listed by the plugin drops nothing.

The list covers PHPStan's identifiers for argument, return and property types, argument counts,
undefined symbols, static calls to instance methods, deprecations, always-true and always-false
conditions, unreachable code, missing types and offset access, and the deprecation identifiers of
phpstan-deprecation-rules.
[PHPStan identifiers](../phpstan/identifiers.md) lists each identifier with its Mago codes.

**Not dropped:**

- phpstan-drupal's identifiers, which aren't in the list;
- the `drupal/` and `phpunit/` checks. They report after Mago runs the plugin, so a comment never
  drops one of them.

An issue counts as being on the covered line when it starts there. Unreachable code is the one
exception. PHPStan reports the first unreachable statement of a block, and Mago reports every one of
them, so a comment that ignores `deadCode.unreachable` also drops Mago's `unevaluated-code` on the
lines after it, up to the end of its block.

```php
$this->markTestSkipped('Blocked by a core issue.');
// @phpstan-ignore deadCode.unreachable
$result = $this->build();          // no unevaluated-code
$this->assertSame('x', $result);   // none here either
```

## Pragmas

Mago matches `@mago-expect` pragmas before the plugin runs. So a pragma next to a `@phpstan-ignore`
for the same issue still counts as used: it can be removed, but Mago doesn't point it out.
