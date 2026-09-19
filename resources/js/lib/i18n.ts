export type Params = Record<string, string | number>;
export type Translate = (key: string, params?: Params) => string;

/**
 * Builds a translate function over the dictionary the server shared for the current language.
 * A missing key shows the key itself, so a gap is visible instead of blank.
 * Placeholders use Laravel's ":name" style so one string works on both sides.
 *
 * Substitution is a single pass over the ORIGINAL text, so a value that itself contains
 * something like ":old" (a title, a person's name) is never expanded a second time.
 */
export function makeTranslator(dictionary: Record<string, string>): Translate {
    return (key, params) => {
        const text = dictionary[key] ?? key;

        if (!params) {
            return text;
        }

        return text.replace(
            /:([A-Za-z_][A-Za-z0-9_]*)/g,
            (match, name: string) =>
                Object.hasOwn(params, name) ? String(params[name]) : match,
        );
    };
}
