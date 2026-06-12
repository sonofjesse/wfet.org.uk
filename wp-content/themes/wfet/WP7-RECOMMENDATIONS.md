# WordPress 7.0 Adaptation Guide

This document analyses the **soj-core-2026** theme (built for WordPress 6.9 with
ACF Blocks API v2) and lists prioritised recommendations for adapting it to
WordPress 7.0.

Reference: [WordPress 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/).

## Current state (snapshot)

- **ACF Pro 6.8.2** is installed (`wp-content/plugins/advanced-custom-fields-pro/acf.php`).
  This version supports Block API v3 (introduced in ACF 6.3).
- Blocks are auto-discovered from `blocks/*/block.json` and registered with
  `register_block_type()` in `functions.php` (`soj_register_acf_blocks`).
- Every existing block omits `apiVersion`, so they all register as **Block API v2**.
- The block scaffolder (`npm run create-block`, `scripts/create-block.js`) now emits
  **Block API v3** for all newly created blocks.

## What this means for the WP 7.0 iframed editor

WordPress 7.0 enforces the iframed post editor **only when every block inserted in
the post is API v3 or higher**. If any v2 block is present, the iframe is removed for
backwards compatibility.

Because the v3 switch is currently scoped to the **generator only**, existing blocks
remain v2 and the editor will stay non-iframed until they are migrated. This is
intentional and safe — new and old blocks coexist without breakage.

---

## 1. Block API v3 / iframed editor (priority: high when ready)

New blocks are v3. To eventually unlock the WP 7.0 iframed editor for a given post,
**all** blocks used in that post must be v3.

### Per-block migration checklist

For each block under `blocks/<slug>/`:

1. Add `"apiVersion": 3` to `block.json` (top-level key, e.g. right after `name`).
2. Open the block in the editor and confirm the **preview renders inside the iframe**
   with the correct theme styles.
3. Confirm the per-block stylesheet still applies — blocks declare
   `"style": ["file:./css/style.css"]`, which WordPress injects into the v3 iframe.
4. Confirm any **inline `<style>` or markup-dependent JS** in `template.php` still
   behaves inside the iframe.
5. Note that front-end JavaScript (GSAP animations, Barba transitions bundled in
   `dist/js/main.min.js`) does **not** run inside the editor iframe. Blocks that rely
   on `preview.png` for their editor preview (the theme's default pattern) are
   unaffected; blocks expecting live JS in the preview are not.

> Tip: migrate in small batches and test the post types that use the affected blocks
> (see the default block templates in `soj_default_block_templates`).

## 2. Editor fonts inside the iframe (priority: high)

Google Fonts and Typekit are enqueued via `enqueue_block_editor_assets` in
`functions.php` (the `soj-editor-fonts-google` / `soj-editor-fonts-typekit` styles).

These load into the editor **chrome**, but **not** inside a v3 block iframe, so v3
previews will fall back to default fonts. Recommended fixes:

- Move the font stylesheets to `add_editor_style()` (it accepts remote URLs and is
  iframe-aware — the theme already uses it for `dist/css/main.min.css` in
  `soj_add_editor_styles`), **or**
- Add them to the `styles` array via the `block_editor_settings_all` filter.

The main theme stylesheet is already injected into the iframe via `add_editor_style()`,
so no change is needed there.

## 3. HTML5 script/style theme support (priority: medium)

`functions.php` calls:

```php
add_theme_support('html5', [
    'search-form', 'comment-form', 'comment-list',
    'gallery', 'caption', 'style', 'script',
]);
```

WP 7.0 deprecates/removes **HTML5 `script` and `style`** theme support (this behaviour
is now always the default). Recommendation: drop `'style'` and `'script'` from the
array to avoid deprecation notices. The remaining values stay.

## 4. theme.json schema bump (priority: low)

`theme.json` pins `$schema` to the `6.7` schema:

```json
"$schema": "https://schemas.wp.org/wp/6.7/theme.json"
```

Recommendation: update to the WordPress 7.0 schema URL (keep `"version": 3`) so any
new 7.0 `theme.json` settings validate correctly in editors/tooling. This is a
tooling/validation nicety, not a runtime requirement.

## 5. Frontend block CSS strategy review (priority: low / informational)

`inc/block-manager.php` dequeues `wp-block-library` / `wp-block-library-theme` and
disables global styles on the front end. WP 7.0 makes **margin-free block styles the
default** (bottom margins removed from components), which reduces the need for some of
these overrides. No change is required, but it's worth reviewing whether any of the
defensive overrides can now be relaxed.

## 6. Optional WP 7.0 opportunities (informational only)

Not actioned — listed for future consideration:

- **Block-level Custom CSS** — per-block CSS controls in the editor.
- **Breadcrumbs block** — core now ships a Breadcrumbs block; the theme currently uses
  Yoast breadcrumbs (`soj_wpseo_*` filters in `functions.php`).
- **Pattern Overrides / Block Bindings for custom blocks** — bound attributes can now
  be overridden per-instance; relevant if any ACF blocks are turned into patterns.
- **Responsive editing mode** — per-device block visibility.
- **PHP-only block registration** — server-side block/pattern registration with
  auto-generated DataForm inspector controls.

---

## Summary of actioned changes

| Change | Status |
| --- | --- |
| `create-block` generator emits `apiVersion: 3` (new blocks are v3) | Done |
| This recommendations document | Done |
| Migrate existing blocks to v3 | Deferred (checklist in section 1) |
| Editor iframe fonts, HTML5 support, theme.json schema, CSS review | Recommendations only (sections 2-6) |
