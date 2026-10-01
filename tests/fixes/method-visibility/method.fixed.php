<?php

class MethodVisibility
{
    public function plain(): void
    {
    }

    public static function shared(): void
    {
    }

    final public function sealed(): void
    {
    }

    abstract public function todo(): void;
}
