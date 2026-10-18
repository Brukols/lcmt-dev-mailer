<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Picks the subject and content of a mail: its translation in the submission
 * language when one can be trusted, else the source mail.
 */
class MailLanguage
{
    /**
     * A translation is used only when it is published, not empty, and has
     * the same placeholder names as the source: one that lost or renamed a
     * placeholder would send a mail missing the visitor's data. An empty
     * translated subject or content keeps the source one.
     *
     * @param array{subject: string, content: string, published: bool}|null $translation
     * @return array{subject: string, content: string, translated: bool}
     */
    public static function pick(string $sourceSubject, string $sourceContent, ?array $translation): array
    {
        $source = ['subject' => $sourceSubject, 'content' => $sourceContent, 'translated' => false];

        if ($translation === null || empty($translation['published'])) {
            return $source;
        }

        $subject = (string) ($translation['subject'] ?? '');
        $content = (string) ($translation['content'] ?? '');

        if ($subject === '' && $content === '') {
            return $source;
        }

        $picked = [
            'subject'    => $subject !== '' ? $subject : $sourceSubject,
            'content'    => $content !== '' ? $content : $sourceContent,
            'translated' => true,
        ];

        if (self::placeholderNames($picked['subject'], $picked['content']) !== self::placeholderNames($sourceSubject, $sourceContent)) {
            return $source;
        }

        return $picked;
    }

    /**
     * @return list<string> Sorted placeholder names.
     */
    private static function placeholderNames(string ...$sources): array
    {
        $names = array_keys(FieldParser::parse(...$sources));
        sort($names);

        return $names;
    }
}
