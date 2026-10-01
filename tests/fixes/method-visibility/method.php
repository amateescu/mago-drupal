<?php

class MethodVisibility
{
    function plain(): void
    {
    }

    static function shared(): void
    {
    }

    final function sealed(): void
    {
    }

    abstract function todo(): void;
}
