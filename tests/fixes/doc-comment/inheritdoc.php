<?php

class InheritdocFix
{
    /**
     * @inheritdoc
     */
    public function plain(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function camel(): void
    {
    }

    /**
     * @inheritdoc}
     */
    public function strayBrace(): void
    {
    }

    /** @inheritdoc */
    public function oneLine(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function alreadyInline(): void
    {
    }
}
