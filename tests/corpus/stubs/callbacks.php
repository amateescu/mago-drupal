<?php

/**
 * @file
 * Core's trusted callback attribute and the render callback interface.
 */

declare(strict_types=1);

namespace Drupal\Core\Security\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class TrustedCallback {}
}

namespace Drupal\Core\Render\Element {
    interface RenderCallbackInterface {}
}
