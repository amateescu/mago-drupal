<?php

/**
 * Catches without reading the exception.
 */
function non_capturing_catch(): ?string
{
    try {
        return non_capturing_catch();
    } catch (\RuntimeException $e) {
        return null;
    } catch (\LogicException|\TypeError $error) {
        return null;
    }
}
