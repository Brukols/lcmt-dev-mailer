# Placeholder syntax

Placeholders are written in the **To**, **Subject**, and **Content** fields of a mail post.

## Syntax

```
[name]            → optional field, type defaults to text
[name*]           → required field, type defaults to text
[name type]       → optional field with explicit type
[name* type]      → required field with explicit type
```

- `name` must start with a letter or underscore, followed by alphanumeric or underscores
- `*` marks the field as required (server-side validation + generated `required` attribute)
- `type` is optional, defaults to `text`

## Supported types

| Type       | HTML output               | Alias   |
|------------|---------------------------|---------|
| `text`     | `<input type="text">`     | default |
| `email`    | `<input type="email">`    |         |
| `tel`      | `<input type="tel">`      | `phone` |
| `url`      | `<input type="url">`      |         |
| `number`   | `<input type="number">`   |         |
| `password` | `<input type="password">` |         |
| `date`     | `<input type="date">`     |         |
| `textarea` | `<textarea>`              |         |
| `hidden`   | `<input type="hidden">`   |         |

Unknown types fall back to `text`.

## Duplicate handling

When the same field name appears multiple times across to/subject/content:
- **Required wins:** if any occurrence has `*`, the field is required
- **Explicit type wins:** an explicit type overrides the default `text`

Example: `[email*]` in the To field + `[email* email]` in the Content → required, type `email`.

## Built-in placeholders

These are always injected by the Mailer and don't need to appear in the content:

| Placeholder          | Value                                    |
|----------------------|------------------------------------------|
| `[currentUserLink]`  | Admin URL to edit the current user       |
| `[currentUserEmail]` | Email of the current logged-in user      |

## Regex

The parser uses this pattern:
```
/\[([a-zA-Z_][a-zA-Z0-9_]*)(\*)?(?:\s+([a-zA-Z]+))?\]/
```

- Group 1: field name
- Group 2: `*` (optional)
- Group 3: type (optional)

## TypeScript generation

`FieldParser::toTypeScript($key, $fields)` generates an interface:

```typescript
export interface ContactFormData {
  firstname: string;
  email: string;
  phone?: string;
  message: string;
}
```

- Required fields have no `?`
- `number` type maps to TypeScript `number`, all others to `string`
