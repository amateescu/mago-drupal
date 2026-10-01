<?php

function unsilenced(): void
{
    trigger_error('Deprecated in drupal:11.0.0.', E_USER_DEPRECATED);
    \trigger_error('Deprecated in drupal:11.0.0.', E_USER_DEPRECATED);
    @trigger_error('Already silenced.', E_USER_DEPRECATED);
    @ trigger_error('Silenced with a gap.', E_USER_DEPRECATED);
}
