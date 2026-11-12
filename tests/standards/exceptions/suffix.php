<?php

namespace Drupal\example;

// Reported.
class WidgetNotFound extends \RuntimeException {}

// Fine.
class WidgetNotFoundException extends \RuntimeException {}

class WidgetGone extends WidgetNotFound {}
