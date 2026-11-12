// Reported.
function nullable_param_label(string $label = NULL): string {
  return (string) $label;
}

// Fine.
function nullable_param_label(?string $label = NULL): string {
  return (string) $label;
}
