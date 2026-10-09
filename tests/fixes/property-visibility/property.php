<?php

class PropertyVisibility
{
    var $legacy;

    var int $typed = 1;

    static $shared, $other;

    #[SomeAttribute]
    static $attributed;

    readonly int $value;

    final int $sealed;

    public static $fine;

    protected $alsoFine;
}
