<?php

function weak_hash(string $data): string
{
    return hash('xxh64', $data) . hash('xxh64', $data);
}
