<?php

/**
 * @file
 * Form state and messenger signatures for the messages that users see.
 */

declare(strict_types=1);

namespace Drupal\Core\Form {
    class FormState implements FormStateInterface
    {
        public function setErrorByName($name, $message = '')
        {
            return $this;
        }

        public function setError(array &$element, $message = '')
        {
            return $this;
        }
    }

    abstract class FormStateDecoratorBase implements FormStateInterface
    {
        public function setErrorByName($name, $message = '')
        {
            return $this;
        }

        public function setError(array &$element, $message = '')
        {
            return $this;
        }
    }

    interface SubformStateInterface extends FormStateInterface {}

    class SubformState extends FormStateDecoratorBase implements SubformStateInterface
    {
        public static function createForSubform(array &$subform, array &$parent_form, FormStateInterface $parent_form_state): static
        {
            throw new \RuntimeException('stub');
        }

        public function setErrorByName($name, $message = '')
        {
            return $this;
        }
    }
}

namespace Drupal\Core\Messenger {
    interface MessengerInterface
    {
        const TYPE_STATUS = 'status';

        const TYPE_WARNING = 'warning';

        const TYPE_ERROR = 'error';

        /**
         * @param string|\Stringable $message
         * @return $this
         */
        public function addMessage($message, $type = 'status', $repeat = false);

        /**
         * @param string|\Stringable $message
         * @return $this
         */
        public function addStatus($message, $repeat = false);

        /**
         * @param string|\Stringable $message
         * @return $this
         */
        public function addError($message, $repeat = false);

        /**
         * @param string|\Stringable $message
         * @return $this
         */
        public function addWarning($message, $repeat = false);
    }

    trait MessengerTrait
    {
        public function messenger(): MessengerInterface
        {
            throw new \RuntimeException('stub');
        }
    }
}
