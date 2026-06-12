// Run webpack (main bundle) and block SCSS compiler in parallel.
const path = require('path');
const { spawn } = require('child_process');

const themeDir = path.resolve(__dirname, '..');

console.log('SOJ dev: webpack watch + block SCSS watch (Ctrl+C to stop)\n');

function run(label, command, args) {
  const child = spawn(command, args, {
    cwd: themeDir,
    stdio: 'inherit',
    shell: process.platform === 'win32'
  });

  child.on('exit', (code) => {
    if (code !== 0 && code !== null) {
      console.error(`[${label}] exited with code ${code}`);
      process.exit(code);
    }
  });

  return child;
}

const webpackBin = path.join(themeDir, 'node_modules', '.bin', 'webpack');
const nodeBin = process.execPath;

const webpack = run('webpack', webpackBin, ['--mode', 'development', '--watch']);
const blocks = run('blocks', nodeBin, [path.join(__dirname, 'build-block-assets.js'), '--watch']);

function shutdown() {
  webpack.kill();
  blocks.kill();
  process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
