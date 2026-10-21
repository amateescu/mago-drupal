<?php

class MethodVisibilityAttribute
{
    #[Note('Moved to the ] public method.')]
    function moved(): void
    {
    }

    #[Note(/* ] public */)]
    function noted(): void
    {
    }

    #[Note('Kept.')]
    public function kept(): void
    {
    }
}
