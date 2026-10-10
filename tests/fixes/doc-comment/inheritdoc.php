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
     * Has a summary, so the bare tag below stays as it is.
     *
     * @inheritdoc
     */
    public function afterSummary(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function alreadyInline(): void
    {
    }
}
