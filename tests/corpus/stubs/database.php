<?php

namespace Drupal\Core\Database {
    abstract class Connection
    {
        public function startTransaction(string $name = ''): Transaction
        {
            throw new \RuntimeException('stub');
        }
    }

    class Transaction
    {
        public function commitOrRelease(): void {}

        public function rollBack(): void {}

        public function name(): string
        {
            return '';
        }
    }
}
