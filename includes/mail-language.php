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
     * the same placeholders (names and types) as the source, server-filled
     * built-ins aside: one that lost, renamed or retyped a placeholder would
     * send a mail missing the visitor's data. An empty translated subject or
     * content keeps the source one.
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
     * The fields a visitor fills, by name, with their type: the mail replaces
     * a placeholder written with the type of the source mail only, so a
     * translation writing [phone tel] for [phone] would leave it literal.
     * Server-filled built-ins such as [submissionLanguage] may be added or
     * left out by a translation.
     *
     * @return array<string, string> Name => type, sorted by name.
     */
    private static function placeholderNames(string ...$sources): array
    {
        $types = array_map(
            static fn(array $field) => $field['type'],
            FieldParser::formFields(FieldParser::parse(...$sources))
        );
        ksort($types);

        return $types;
    }
}
