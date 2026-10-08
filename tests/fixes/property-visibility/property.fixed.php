<?php

class PropertyVisibility
{
    public $legacy;

    public int $typed = 1;

    public static $shared, $other;

    #[SomeAttribute]
    public static $attributed;

    public readonly int $value;

    final public int $sealed;

    public static $fine;

    protected $alsoFine;
}
