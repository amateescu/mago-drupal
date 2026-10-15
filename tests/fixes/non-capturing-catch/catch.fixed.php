<?php

/**
 * Catches without reading the exception.
 */
function non_capturing_catch(): ?string
{
    try {
        return non_capturing_catch();
    } catch (\RuntimeException) {
        return null;
    } catch (\LogicException|\TypeError) {
        return null;
    }
}
