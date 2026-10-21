<?php

class MethodVisibilityAttribute
{
    #[Note('Moved to the ] public method.')]
    public function moved(): void
    {
    }

    #[Note(/* ] public */)]
    public function noted(): void
    {
    }

    #[Note('Kept.')]
    public function kept(): void
    {
    }
}
