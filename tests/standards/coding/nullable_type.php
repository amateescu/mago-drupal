// Before.
function example_label(null|string $label, int|null $weight): string|null {
  return $label;
}

// After.
function example_label(?string $label, ?int $weight): ?string {
  return $label;
}
