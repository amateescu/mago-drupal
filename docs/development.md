# Development

```shell
composer install
just check-all
```

`just check` runs `validate`, `format-check`, `test`, `lint` and `analyze`. `just check-all` also
runs `test-corpus` and `test-fixes`.

- `test-corpus` starts a real worker over `tests/corpus/src/` and fails if an `@mago-expect`
  annotation is not fulfilled. `tests/corpus/expected-rules.txt` pins the registered rule codes.
  Add your code there when you add a rule.
- `test-fixes` runs every rule's fix over the before and after pairs in `tests/fixes/`.

Set `MAGO=/path/to/mago` to run the checks against a different build.

## Rule codes

Rule codes are stable. Projects that we do not control write them into baselines and into
`// @mago-expect lint:<code>` comments. For that reason, the codes get no vendor prefix and no new
name.

## Docs

The docs are Markdown files in `docs/`, built into a site with [Zensical](https://zensical.org/)
from `zensical.toml`. `just docs` serves the site on http://127.0.0.1:8000 with live reload, and
`just docs-build` builds it into `site/` and fails on a broken link or anchor. Both install the
Zensical version that the `Justfile` pins into `.venv`.

The tables of the [rules index](rules/index.md) and of the [Coming from Coder](coder/index.md) pages
are generated, between `<!-- docs-gen -->` markers. They come from the registered rules and from
`tests/coder-map.json`, which says what handles each code of Coder's two standards. `just docs-gen`
rewrites them. `DocsTest` fails when they are out of date, when a rule has no entry on a rule page,
and when an entry's level, `--core` line or Ports codes differ from the rule and the map.

When you add a rule, add its entry to the page of its group, mark the Coder codes that it ports
with `ext:drupal/<code>` in the map, and run `just docs-gen`.
