/**
 * Capture SIGDoc portfolio screenshots from the live Render URL.
 * Usage: node scripts/capture-screenshots.mjs
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'screenshots');
fs.mkdirSync(outDir, { recursive: true });

const base = process.env.SIGDOC_URL || 'https://sigdoc-1fsj.onrender.com';

const edgePaths = [
  'C:\\\\Program Files (x86)\\\\Microsoft\\\\Edge\\\\Application\\\\msedge.exe',
  'C:\\\\Program Files\\\\Microsoft\\\\Edge\\\\Application\\\\msedge.exe',
  'C:\\\\Program Files\\\\Google\\\\Chrome\\\\Application\\\\chrome.exe',
];

const executablePath = edgePaths.find((p) => fs.existsSync(p));
if (!executablePath) {
  console.error('No Edge/Chrome found');
  process.exit(1);
}

const browser = await chromium.launch({
  executablePath,
  headless: true,
});

const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
page.setDefaultTimeout(60000);

async function shot(name) {
  const file = path.join(outDir, name);
  await page.screenshot({ path: file, fullPage: false });
  console.log('saved', name);
}

await page.goto(`${base}/`, { waitUntil: 'networkidle' });
await shot('02-landing.png');

await page.goto(`${base}/auth/login.php`, { waitUntil: 'networkidle' });
await shot('01-login.png');

await page.fill('input[type="email"], input[name="email"]', 'admin@sigdoc.local');
await page.fill('input[type="password"], input[name="senha"]', 'Admin@123');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }),
  page.click('button[type="submit"], button:has-text("Entrar")'),
]);
await shot('03-dashboard.png');

await page.goto(`${base}/documentos/listar.php`, { waitUntil: 'networkidle' });
await shot('04-documentos.png');

await page.goto(`${base}/mapa.php`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);
await shot('05-mapa.png');

await browser.close();
console.log('done →', outDir);
