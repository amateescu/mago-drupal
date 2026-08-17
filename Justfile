set dotenv-load := false

# Override with MAGO=/path/to/mago to use a binary the Composer downloader
# cannot fetch, such as a local build or a release that does not match the
# installed package version.
mago := env_var_or_default("MAGO", "vendor/bin/mago")

validate:
    composer validate --strict --no-check-publish

test:
    vendor/bin/phpunit --configuration phpunit.xml

lint:
    {{mago}} --config mago.toml lint

analyze:
    {{mago}} --config mago.toml analyze

format:
    {{mago}} --config mago.toml format

format-check:
    {{mago}} --config mago.toml format --check

# Starts a real worker, so it needs a Mago binary with extension-host support.
test-corpus:
    {{mago}} --workspace tests/corpus lint --only drupal/weak-hash --reporting-format count
    {{mago}} --workspace tests/corpus analyze --reporting-format count

check: validate format-check test lint analyze

check-all: check test-corpus

