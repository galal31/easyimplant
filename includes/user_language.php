<?php

function userInterfaceLanguage(): string
{
    $language = strtolower(trim((string) ($_COOKIE['easyimplant_language'] ?? 'en')));

    return in_array($language, ['en', 'ar'], true) ? $language : 'en';
}

function userLanguageAttribute(): string
{
    return userInterfaceLanguage();
}

function userDirectionAttribute(): string
{
    return userInterfaceLanguage() === 'ar' ? 'rtl' : 'ltr';
}

function userLocalized(string $english, string $arabic): string
{
    return userInterfaceLanguage() === 'ar' ? $arabic : $english;
}
