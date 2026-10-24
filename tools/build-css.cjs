const { spawn, spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const cli = path.join(path.dirname(require.resolve('@tailwindcss/cli/package.json')), 'dist/index.mjs');
const watch = process.argv.includes('--watch');
const files = [
  'tailwind.css',
  'app.css',
  'workspace.css',
  'modules/news/news.css',
  'modules/tournaments.css',
  'modules/admin-users.css',
  'scoring/score.css',
  'scoring/set.css',
];
const children = [];
for (const file of files) {
  const args = [cli, '-i', `resources/css/${file}`, '-o', `public/assets/${file}`, '--minify'];
  if (watch) {
    children.push(spawn(process.execPath, [...args, '--watch=always'], {
      cwd: root, stdio: 'inherit', windowsHide: true,
    }));
  } else {
    const result = spawnSync(process.execPath, args, { cwd: root, stdio: 'inherit', windowsHide: true });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status || 1);
  }
}
if (watch) {
  const stop = () => { for (const child of children) child.kill(); };
  process.on('SIGINT', stop);
  process.on('SIGTERM', stop);
  for (const child of children) {
    child.on('error', error => { console.error(error); stop(); process.exitCode = 1; });
    child.on('exit', code => { if (code) { stop(); process.exitCode = code; } });
  }
}
