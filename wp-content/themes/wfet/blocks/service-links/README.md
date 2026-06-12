# Service Links

## Structure

```
blocks/service-links/
├── block.json       # ACF Block API v3 registration
├── functions.php    # Block setup (image sizes, etc.)
├── template.php     # Render template
├── css/
│   ├── style.scss   # Block styles (source)
│   └── style.css    # Compiled (via webpack)
├── js/
│   └── script.js    # Block JavaScript (bundled in main.js)
├── group_6a1d55e7.json # ACF field group (edit in WP admin)
└── preview.png      # Inserter preview image
```

## Usage

1. Edit fields in WP Admin › ACF › Field Groups › Block : Service Links
2. Style in `css/style.scss`
3. Add logic in `js/script.js`
4. Replace `preview.png` with a screenshot
