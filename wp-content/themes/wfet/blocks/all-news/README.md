# All News

Lists every published post in a three-column grid using the shared `template-parts/news/card` component. Posts per page follows **Settings → Reading → Blog pages show at most**. Pagination uses the host page URL (`/your-page/page/2/`).

Optional ACF fields: **Title**, **Content**. Margin top/bottom come from the universal block fields group.

## Structure

```
blocks/all-news/
├── block.json       # ACF Block API v3 registration
├── functions.php    # Block setup (image sizes, etc.)
├── template.php     # Render template
├── css/
│   ├── style.scss   # Block styles (source)
│   └── style.css    # Compiled (via webpack)
├── js/
│   └── script.js    # Block JavaScript (bundled in main.js)
├── group_6a21ea32.json # ACF field group (edit in WP admin)
└── preview.png      # Inserter preview image
```

## Usage

1. Edit fields in WP Admin › ACF › Field Groups › Block : All News
2. Style in `css/style.scss`
3. Add logic in `js/script.js`
4. Replace `preview.png` with a screenshot
