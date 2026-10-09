<?php

function escaped_quotes(string $name): array
{
    return [
        t('It\'s here'),
        t("Say \"hi\""),
        t('It\'s ' . $name),
        t('Path \\'),
        t("Path \\"),
        t('Cost \$5, it\'s here'),
        t('Say "hi", it\'s here'),
        t('It\'s a \\ path'),
        t('It\'s $5'),
        t('Line\nbreak, it\'s here'),
        t("Say \"hi\"\n"),
        t("Say \"hi\", it's here"),
        t("Say \"hi\" \\ there"),
        T('It\'s here'),
        new TranslatableMarkup('It\'s here'),
        \t('It\'s here'),
        $translation->formatPlural(2, 'It\'s one', 'It\'s many'),
    ];
}
