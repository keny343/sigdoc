/**
 * Apply database/install_aiven.sql to Aiven (.env credentials).
 * Usage: node scripts/apply-schema.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');

function loadEnv(file) {
  const env = {};
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const i = t.indexOf('=');
    if (i === -1) continue;
    env[t.slice(0, i)] = t.slice(i + 1);
  }
  return env;
}

/** Strip -- comments, then split on semicolons. */
function splitSql(sql) {
  const noComments = sql
    .split(/\r?\n/)
    .map((line) => {
      const idx = line.indexOf('--');
      return idx === -1 ? line : line.slice(0, idx);
    })
    .join('\n');

  return noComments
    .split(';')
    .map((s) => s.trim())
    .filter((s) => s.length > 0);
}

const env = loadEnv(path.join(root, '.env'));
const sqlFile = path.join(root, 'database', 'install_aiven.sql');
const statements = splitSql(fs.readFileSync(sqlFile, 'utf8'));

const conn = await mysql.createConnection({
  host: env.SIGDOC_DB_HOST,
  port: Number(env.SIGDOC_DB_PORT || 3306),
  user: env.SIGDOC_DB_USER,
  password: env.SIGDOC_DB_PASS,
  database: env.SIGDOC_DB_NAME,
  ssl: { rejectUnauthorized: false },
});

console.log(`Connected → ${env.SIGDOC_DB_HOST}/${env.SIGDOC_DB_NAME}`);
console.log(`Statements: ${statements.length}`);

for (let i = 0; i < statements.length; i++) {
  const stmt = statements[i];
  const preview = stmt.replace(/\s+/g, ' ').slice(0, 90);
  try {
    await conn.query(stmt);
    console.log(`OK  [${i + 1}/${statements.length}] ${preview}`);
  } catch (err) {
    console.error(`FAIL [${i + 1}] ${preview}`);
    console.error(`  ${err.message}`);
    await conn.end();
    process.exit(1);
  }
}

const [tables] = await conn.query('SHOW TABLES');
const [users] = await conn.query('SELECT id, email, perfil FROM usuarios');
console.log('\nTables:', tables.map((r) => Object.values(r)[0]).join(', '));
console.log('Users:', users);

await conn.end();
console.log('\nSchema OK — login: admin@sigdoc.local / Admin@123');
