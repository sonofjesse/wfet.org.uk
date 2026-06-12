#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Get block name from command line arguments
const blockInput = process.argv.slice(2).join(' ').trim();

if (!blockInput) {
    console.error('❌ Please provide a block name');
    console.log('Usage: npm run create-block "Block Title"');
    console.log('Example: npm run create-block "Call to Action"');
    console.log('Example: npm run create-block "hero-section" (also works)');
    process.exit(1);
}

/**
 * Slugify a string to create a valid block name
 */
function slugify(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Convert slug back to proper title case
 */
function slugToTitle(slug) {
    return slug
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Convert slug to a PascalCase JS function name
 */
function slugToFunctionName(slug) {
    return slug
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join('');
}

/**
 * Convert slug to snake_case for PHP identifiers
 */
function slugToSnake(slug) {
    return slug.replace(/-/g, '_');
}

/**
 * Generate an ACF-style key using the current unix timestamp (hex)
 */
function generateAcfKey(prefix) {
    return prefix + '_' + Math.floor(Date.now() / 1000).toString(16);
}

// Process the input
const blockSlug  = slugify(blockInput);
const blockTitle = blockInput.includes('-') && !blockInput.includes(' ')
    ? slugToTitle(blockInput)
    : blockInput;

if (!blockSlug || !/^[a-z0-9-]+$/.test(blockSlug)) {
    console.error('❌ Invalid block name. Please use letters, numbers, and spaces only.');
    console.log('Example: "Call to Action", "Hero Section", "Image Gallery"');
    process.exit(1);
}

const blockDir = path.join(__dirname, '../blocks', blockSlug);

if (fs.existsSync(blockDir)) {
    console.error(`❌ Block "${blockTitle}" (${blockSlug}) already exists`);
    process.exit(1);
}

fs.mkdirSync(blockDir, { recursive: true });

const snake        = slugToSnake(blockSlug);
const functionName = slugToFunctionName(blockSlug);
const groupKey     = generateAcfKey('group');
const fieldKeyTitle   = generateAcfKey('field');
const fieldKeyContent = generateAcfKey('field');

// ─── block.json ───────────────────────────────────────────────────────────────
// ACF v3 block.json format - block registration (replaces acf_register_block_type).
// Two separate version settings are used:
//   - apiVersion 3 (top level): WordPress Block API v3 -> editor iframe (WP 7.0 ready).
//   - acf.blockVersion 3: ACF Block v3 -> unlocks acf_block_version >= 3 features
//     such as autoInlineEditing. This is the real "ACF v3" switch.

const blockDesc = blockTitle.toLowerCase().endsWith(' block')
    ? `A custom ${blockTitle.toLowerCase()}`
    : `A custom ${blockTitle.toLowerCase()} block`;

const blockJson = {
    name: `acf/${blockSlug}`,
    apiVersion: 3,
    title: blockTitle,
    description: blockDesc,
    category: 'soj-blocks',
    icon: 'admin-comments',
    keywords: [blockSlug, blockTitle.toLowerCase().replace(/\s+/g, ' ')],
    style: ['file:./css/style.css'],
    acf: {
        mode: 'preview',
        renderTemplate: 'template.php',
        // blockVersion 3 = ACF Block v3. This is the real "ACF v3" switch;
        // apiVersion above only controls the WordPress editor iframe.
        // Fields are edited in the block sidebar (inspector) for consistency.
        blockVersion: 3
    },
    example: {
        attributes: {
            mode: 'preview',
            data: {
                // Used in templates to switch to preview.png when inserting the block.
                preview_image_help: true
            }
        }
    },
    supports: {
        align: false,
        mode: true
    }
};

fs.writeFileSync(
    path.join(blockDir, 'block.json'),
    JSON.stringify(blockJson, null, 4)
);

// ─── functions.php ────────────────────────────────────────────────────────────
// Block setup only (image sizes, etc). Registration is via block.json.

const functionsContent = `<?php

/**
 * ${blockTitle} Block - Setup
 * Block registration is via block.json (ACF Block API v3)
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

// Add image sizes or other block-specific setup here if needed.
// add_image_size('${blockSlug}-thumb', 400, 300, true);
`;

fs.writeFileSync(path.join(blockDir, 'functions.php'), functionsContent);

// ─── template.php ─────────────────────────────────────────────────────────────

const templateContent = `<?php
/**
 * ${blockTitle} Block Template.
 *
 * @param array \$block The block settings and attributes.
 * @param string \$content The block inner HTML (empty).
 * @param bool \$is_preview True during AJAX preview.
 * @param int \$post_id The post ID this block is saved to.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array \$block ACF block settings and attributes (injected by ACF at render). */
\$id = '${blockSlug}-' . \$block['id'];
if (!empty(\$block['anchor'])) {
    \$id = \$block['anchor'];
}

\$className = '${blockSlug}';
if (!empty(\$block['className'])) {
    \$className .= ' ' . \$block['className'];
}
if (!empty(\$block['align'])) {
    \$className .= ' align' . \$block['align'];
}

\$title   = get_field('title');
\$content = get_field('content');

if (!empty(\$is_preview) && isset(\$block['data']['preview_image_help'])) {
    \$preview_image_url = get_template_directory_uri() . '/blocks/${blockSlug}/preview.png';
    echo '<img src="' . esc_url(\$preview_image_url) . '" alt="${blockTitle} preview" style="width:100%;height:auto;" />';
    return;
}

\$margin_top    = get_field('margin_top') ?: 'mt-0';
\$margin_bottom = get_field('margin_bottom') ?: 'mb-0';
?>

<section id="<?php echo esc_attr(\$id); ?>"
    class="<?php echo esc_attr(\$className); ?>
        <?php if (\$margin_top) echo esc_attr(\$margin_top); ?>
        <?php if (\$margin_bottom) echo esc_attr(\$margin_bottom); ?>">

    <?php if (\$title): ?>
        <h2 class="${blockSlug}__title"><?php echo esc_html(\$title); ?></h2>
    <?php endif; ?>

    <?php if (\$content): ?>
        <div class="${blockSlug}__content">
            <?php echo wp_kses_post(\$content); ?>
        </div>
    <?php endif; ?>

</section>
`;

fs.writeFileSync(path.join(blockDir, 'template.php'), templateContent);

// ─── css/style.scss ───────────────────────────────────────────────────────────

fs.mkdirSync(path.join(blockDir, 'css'), { recursive: true });

const styleContent = `@import "../../../src/scss/partials/variables";
@import "../../../src/scss/partials/mixins";

.${blockSlug} {

    &__title {
    }

    &__content {
    }
}
`;

fs.writeFileSync(path.join(blockDir, 'css', 'style.scss'), styleContent);

// ─── js/script.js ─────────────────────────────────────────────────────────────

fs.mkdirSync(path.join(blockDir, 'js'), { recursive: true });

const scriptLabel = blockTitle.toLowerCase().endsWith(' block') ? blockTitle : `${blockTitle} Block`;
const scriptContent = `/**
 * ${scriptLabel} JavaScript
 */

(function () {
    'use strict';

    function init${functionName}() {
        document.querySelectorAll('.${blockSlug}').forEach(block => {
            // Add your block functionality here
        });
    }

    // Barba-safe: use barba:pageReady when active (avoids double-init on first load)
    // Fall back to DOMContentLoaded when Barba is not used
    function onPageReady(e) {
        const container = e?.detail?.container || document;
        if (container.querySelector('.${blockSlug}')) {
            init${functionName}();
        }
    }

    if (document.body?.hasAttribute?.('data-barba')) {
        document.addEventListener('barba:pageReady', onPageReady);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => onPageReady({ detail: { container: document } }));
        } else {
            onPageReady({ detail: { container: document } });
        }
    }
})();
`;

fs.writeFileSync(path.join(blockDir, 'js', 'script.js'), scriptContent);

// ─── field-group.json ─────────────────────────────────────────────────────────
// Starter ACF field group with title + content fields pre-wired to this block.
// margin_top / margin_bottom are provided by the shared Universal Block Fields group.
// Edit fields visually in the WP admin — they auto-save back here via the
// acf/update_field_group hook in functions.php.

const fieldGroup = {
    key: groupKey,
    title: `Block : ${blockTitle}`,
    fields: [
        {
            key: fieldKeyTitle,
            label: 'Title',
            name: 'title',
            type: 'text',
            instructions: '',
            required: 0,
            conditional_logic: 0,
            wrapper: { width: '', class: '', id: '' },
            default_value: '',
            placeholder: '',
            prepend: '',
            append: '',
            maxlength: '',
        },
        {
            key: fieldKeyContent,
            label: 'Content',
            name: 'content',
            type: 'wysiwyg',
            instructions: '',
            required: 0,
            conditional_logic: 0,
            wrapper: { width: '', class: '', id: '' },
            default_value: '',
            tabs: 'all',
            toolbar: 'full',
            media_upload: 1,
            delay: 0,
        },
    ],
    location: [
        [
            {
                param: 'block',
                operator: '==',
                value: `acf/${blockSlug}`,
            },
        ],
    ],
    menu_order: 0,
    position: 'normal',
    style: 'default',
    label_placement: 'top',
    instruction_placement: 'label',
    hide_on_screen: '',
    active: true,
    description: '',
    show_in_rest: 0,
    modified: Math.floor(Date.now() / 1000),
};

fs.writeFileSync(
    path.join(blockDir, `${groupKey}.json`),
    JSON.stringify(fieldGroup, null, 4)
);

// ─── README.md ───────────────────────────────────────────────────────────────

const readmeContent = `# ${blockTitle}

## Structure

\`\`\`
blocks/${blockSlug}/
├── block.json       # ACF Block API v3 registration
├── functions.php    # Block setup (image sizes, etc.)
├── template.php     # Render template
├── css/
│   ├── style.scss   # Block styles (source)
│   └── style.css    # Compiled (via webpack)
├── js/
│   └── script.js    # Block JavaScript (bundled in main.js)
├── ${groupKey}.json # ACF field group (edit in WP admin)
└── preview.png      # Inserter preview image
\`\`\`

## Usage

1. Edit fields in WP Admin › ACF › Field Groups › Block : ${blockTitle}
2. Style in \`css/style.scss\`
3. Add logic in \`js/script.js\`
4. Replace \`preview.png\` with a screenshot
`;

fs.writeFileSync(path.join(blockDir, 'README.md'), readmeContent);

// ─── preview.png (1×1 placeholder) ───────────────────────────────────────────

const previewImageBuffer = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77mgAAAABJRU5ErkJggg==',
    'base64'
);
fs.writeFileSync(path.join(blockDir, 'preview.png'), previewImageBuffer);

// ─── Build block assets ─────────────────────────────────────────────────────
// Webpack compiles block CSS (css/style.scss → css/style.css) and bundles block JS

const { execSync } = require('child_process');
const themeDir = path.join(__dirname, '..');
try {
    execSync('npx webpack --mode production', {
        cwd: themeDir,
        stdio: 'pipe'
    });
} catch (e) {
    console.warn('⚠️  Webpack build failed. Run "npm run build" to compile block assets.');
}

// ─── Success ──────────────────────────────────────────────────────────────────

console.log(`✅ Block "${blockTitle}" created successfully!`);
console.log(`📁 Slug: ${blockSlug}  →  registered as acf/${blockSlug}`);
console.log(`📁 Location: ${blockDir}`);
console.log('');
console.log('🔧 Next steps:');
console.log('   1. Edit fields in WP Admin › ACF › Field Groups › Block : ' + blockTitle);
console.log('      Changes auto-save back to blocks/' + blockSlug + '/' + groupKey + '.json');
console.log('   2. Replace preview.png with a screenshot of the block');
console.log('   3. Style the block in css/style.scss');
console.log('   4. Update template.php with your ACF field names');
console.log('   5. Customize block.json (icon, example data) if needed');
console.log('');
console.log('📖 Block structure:');
console.log('   blocks/' + blockSlug + '/');
console.log('   ├── block.json');
console.log('   ├── functions.php');
console.log('   ├── template.php');
console.log('   ├── README.md');
console.log('   ├── css/');
console.log('   │   ├── style.scss');
console.log('   │   └── style.css');
console.log('   ├── js/');
console.log('   │   └── script.js');
console.log('   ├── ' + groupKey + '.json');
console.log('   └── preview.png');
console.log('');
console.log('🚀 The block is automatically discovered and registered via block.json!');
