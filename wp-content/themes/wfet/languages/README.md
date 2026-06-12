# SOJ Core Modern Theme Translations

This directory contains translation files for the SOJ Core Modern theme.

## Files

- `soj-core.pot` - The template file containing all translatable strings
- `soj-core-{locale}.po` - Translation files for specific languages (e.g., `soj-core-fr_FR.po` for French)
- `soj-core-{locale}.mo` - Compiled translation files (generated from .po files)

## How to Translate

1. Copy `soj-core.pot` to `soj-core-{locale}.po` (replace `{locale}` with your language code, e.g., `fr_FR`)
2. Edit the .po file with a translation editor like Poedit, Loco Translate, or any text editor
3. Translate all the strings in the `msgstr` fields
4. Compile the .po file to generate the .mo file
5. Upload both .po and .mo files to this directory

## Language Codes

Common language codes:
- `fr_FR` - French (France)
- `es_ES` - Spanish (Spain)
- `de_DE` - German (Germany)
- `it_IT` - Italian (Italy)
- `pt_BR` - Portuguese (Brazil)
- `ja` - Japanese
- `zh_CN` - Chinese (Simplified)
- `ru_RU` - Russian

## Using WP-CLI

To generate a new POT file after adding new translatable strings:

```bash
wp i18n make-pot wp-content/themes/soj-core-modern wp-content/themes/soj-core-modern/languages/soj-core.pot --domain=soj-core
```

To compile a .po file to .mo:

```bash
wp i18n make-mo wp-content/themes/soj-core-modern/languages/soj-core-{locale}.po wp-content/themes/soj-core-modern/languages/
```

## Text Domain

All translatable strings in this theme use the text domain `soj-core`. The text domain is loaded in the theme's `functions.php` file using:

```php
load_theme_textdomain('soj-core', get_template_directory() . '/languages');
```
