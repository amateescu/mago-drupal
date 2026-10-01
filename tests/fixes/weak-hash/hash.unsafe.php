<?php

function weak_hash(string $data): string
{
    return md5($data) . hash('sha1', $data);
}
