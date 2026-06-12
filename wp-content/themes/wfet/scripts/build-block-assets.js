// Build per-block CSS for block.json style loading.
// Compiles each block's css/style.scss to css/style.css.
// Run via: npm run build:blocks  (or npm run dev:blocks -- --watch)
const path = require('path');
const fs = require('fs');
const sass = require('sass');

const themeDir = path.resolve(__dirname, '..');
const blocksDir = path.join(themeDir, 'blocks');
const isWatch = process.argv.includes('--watch');

function compileBlock(blockName) {
  const scssPath = path.join(blocksDir, blockName, 'css', 'style.scss');
  const cssPath = path.join(blocksDir, blockName, 'css', 'style.css');

  if (!fs.existsSync(scssPath)) return false;

  try {
    const result = sass.compile(scssPath, {
      loadPaths: [themeDir],
      style: 'compressed',
      quietDeps: true,
      silenceDeprecations: ['legacy-js-api', 'import', 'global-builtin', 'color-functions', 'slash-div']
    });
    fs.writeFileSync(cssPath, result.css);
    console.log(`  + ${blockName}/css/style.css`);
    return true;
  } catch (err) {
    console.error(`  x ${blockName}: ${err.message}`);
    return false;
  }
}

function buildAll() {
  if (!fs.existsSync(blocksDir)) {
    console.log('No blocks directory found');
    return 0;
  }

  const blockDirs = fs.readdirSync(blocksDir, { withFileTypes: true })
    .filter(d => d.isDirectory())
    .map(d => d.name);

  let built = 0;
  blockDirs.forEach(blockName => {
    if (compileBlock(blockName)) built++;
  });

  console.log(`Built ${built} block stylesheet(s)`);
  return built;
}

function blockNameFromScssPath(filePath) {
  const relative = path.relative(blocksDir, filePath);
  const parts = relative.split(path.sep);
  if (parts.length >= 3 && parts[1] === 'css' && parts[2] === 'style.scss') {
    return parts[0];
  }
  return null;
}

function getBlockNames() {
  if (!fs.existsSync(blocksDir)) return [];
  return fs.readdirSync(blocksDir, { withFileTypes: true })
    .filter(d => d.isDirectory())
    .map(d => d.name)
    .filter(name => fs.existsSync(path.join(blocksDir, name, 'css', 'style.scss')));
}

function watchBlocks() {
  console.log('Watching blocks/**/css/style.scss and src/scss/partials/** …');

  let debounceTimer = null;
  const pending = new Set();

  const flush = () => {
    debounceTimer = null;
    if (pending.has('*')) {
      getBlockNames().forEach(blockName => compileBlock(blockName));
    } else {
      pending.forEach(blockName => compileBlock(blockName));
    }
    pending.clear();
  };

  const queueBlock = (filePath) => {
    const blockName = blockNameFromScssPath(filePath);
    if (!blockName) return;
    pending.add(blockName);
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(flush, 100);
  };

  const queueAllBlocks = () => {
    pending.add('*');
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(flush, 100);
  };

  fs.watch(blocksDir, { recursive: true }, (_event, filename) => {
    if (!filename || !filename.endsWith('style.scss')) return;
    queueBlock(path.join(blocksDir, filename));
  });

  const partialsDir = path.join(themeDir, 'src', 'scss', 'partials');
  if (fs.existsSync(partialsDir)) {
    fs.watch(partialsDir, { recursive: true }, (_event, filename) => {
      if (!filename || !/\.scss$/.test(filename)) return;
      queueAllBlocks();
    });
  }
}

buildAll();

if (isWatch) {
  watchBlocks();
}
