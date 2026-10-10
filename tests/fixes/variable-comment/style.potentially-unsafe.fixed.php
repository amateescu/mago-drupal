<?php

class PropertyStyle
{
    /**
     * The label shown to users.
     */
    public string $label = '';

    /**
     * The weight.
     */
    public int $weight = 0;

    /**
     * The size.
     */
    #[Size]
    // Between the attribute and the property.
    public int $size = 0;
}
