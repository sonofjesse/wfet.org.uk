# CTA with Icon

## Structure

```
blocks/cta-with-icon/
├── block.json       # ACF Block API v3 registration
├── functions.php    # Block setup (image sizes, etc.)
├── template.php     # Render template
├── css/
│   ├── style.scss   # Block styles (source)
│   └── style.css    # Compiled (via webpack)
├── js/
│   └── script.js    # Block JavaScript (bundled in main.js)
├── group_6a22a2a0.json # ACF field group (edit in WP admin)
└── preview.png      # Inserter preview image
```

## Usage

1. Edit fields in WP Admin › ACF › Field Groups › Block : CTA with Icon
2. Style in `css/style.scss`
3. Add logic in `js/script.js`
4. Replace `preview.png` with a screenshot
