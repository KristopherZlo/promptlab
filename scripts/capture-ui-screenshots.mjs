import { promises as fs } from 'node:fs';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = normalizeBaseUrl(
  process.env.SCREENSHOT_BASE_URL ?? readAppUrl() ?? 'http://localhost/PromptFactory/public',
);
const OUTPUT_ROOT = process.env.SCREENSHOT_OUTPUT_DIR ?? 'interface-screenshots-auto';
const PUBLISH_ROOT = process.env.SCREENSHOT_PUBLISH_DIR ?? 'docs/screenshots/latest';
const VIEWPORT = parseViewport(process.env.SCREENSHOT_VIEWPORT ?? '1600x1000');
const WAIT_MS = parsePositiveInt(process.env.SCREENSHOT_WAIT_MS, 900);
const TIMEOUT_MS = parsePositiveInt(process.env.SCREENSHOT_TIMEOUT_MS, 30000);
const AUTH_EMAIL = process.env.SCREENSHOT_AUTH_EMAIL ?? 'showcase@evala.local';
const AUTH_PASSWORD = process.env.SCREENSHOT_AUTH_PASSWORD ?? 'password';
const USE_HMR = process.env.SCREENSHOT_USE_HMR === '1';

const THEMES = ['light', 'dark'];
const SCREENSHOT_TARGETS = [
  {
    uri: '/login',
    slug: 'login',
    label: 'Login',
    mode: 'guest',
    publish: false,
  },
  {
    uri: '/register',
    slug: 'register',
    label: 'Register',
    mode: 'guest',
    publish: false,
  },
  {
    uri: '/dashboard',
    slug: 'dashboard',
    label: 'Dashboard',
    mode: 'auth',
    publish: true,
  },
  {
    uri: '/use-cases',
    slug: 'task-directory',
    label: 'Task Directory',
    mode: 'auth',
    publish: true,
  },
  {
    uri: '/prompts',
    slug: 'prompt-catalog',
    label: 'Prompt Catalog',
    mode: 'auth',
    publish: true,
  },
  {
    uri: '/playground',
    slug: 'playground',
    label: 'Playground',
    mode: 'auth',
    publish: true,
  },
];

async function main() {
  const restoreHotFile = await disableViteHotFileIfNeeded();
  const timestamp = new Date().toISOString().replace(/[.:]/g, '-');
  const runDir = path.resolve(process.cwd(), OUTPUT_ROOT, timestamp);

  await fs.mkdir(runDir, { recursive: true });
  await fs.mkdir(path.resolve(process.cwd(), PUBLISH_ROOT), { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const summary = {
    baseUrl: BASE_URL,
    generatedAt: timestamp,
    viewport: VIEWPORT,
    publishedTheme: 'dark',
    counts: {
      captured: 0,
      failed: 0,
      published: 0,
    },
    runs: [],
  };

  try {
    for (const theme of THEMES) {
      const themeDir = path.join(runDir, theme);
      await fs.mkdir(themeDir, { recursive: true });

      const guestResult = await captureGroup({
        browser,
        theme,
        mode: 'guest',
        routes: SCREENSHOT_TARGETS.filter((target) => target.mode === 'guest'),
        outputDir: themeDir,
      });
      summary.runs.push(guestResult);
      summary.counts.captured += guestResult.captured.length;
      summary.counts.failed += guestResult.failed.length;

      const authResult = await captureGroup({
        browser,
        theme,
        mode: 'auth',
        routes: SCREENSHOT_TARGETS.filter((target) => target.mode === 'auth'),
        outputDir: themeDir,
        credentials: {
          email: AUTH_EMAIL,
          password: AUTH_PASSWORD,
        },
      });
      summary.runs.push(authResult);
      summary.counts.captured += authResult.captured.length;
      summary.counts.failed += authResult.failed.length;

      if (theme === 'dark') {
        const publishedCount = await publishScreenshots(path.join(themeDir, 'auth'));
        summary.counts.published += publishedCount;
      }
    }
  } finally {
    await browser.close();
    await restoreHotFile();
  }

  await fs.writeFile(
    path.join(runDir, 'manifest.json'),
    JSON.stringify(summary, null, 2),
    'utf8',
  );

  await fs.writeFile(
    path.join(path.resolve(process.cwd(), PUBLISH_ROOT), 'manifest.json'),
    JSON.stringify({
      baseUrl: BASE_URL,
      generatedAt: timestamp,
      publishedTheme: 'dark',
      routes: SCREENSHOT_TARGETS.filter((target) => target.publish).map((target) => ({
        slug: target.slug,
        label: target.label,
        file: `${target.slug}.png`,
      })),
    }, null, 2),
    'utf8',
  );

  console.log('');
  console.log(`[ui:screenshots] output: ${runDir}`);
  console.log(`[ui:screenshots] published: ${path.resolve(process.cwd(), PUBLISH_ROOT)}`);
  console.log(
    `[ui:screenshots] captured=${summary.counts.captured} failed=${summary.counts.failed} published=${summary.counts.published}`,
  );
}

async function captureGroup({
  browser,
  theme,
  mode,
  routes,
  outputDir,
  credentials = null,
}) {
  const groupDir = path.join(outputDir, mode);
  await fs.mkdir(groupDir, { recursive: true });

  const context = await browser.newContext({
    baseURL: BASE_URL,
    viewport: VIEWPORT,
  });

  context.setDefaultTimeout(TIMEOUT_MS);
  await context.addInitScript((themeMode) => {
    try {
      localStorage.setItem('promptlab-theme-mode', themeMode);
    } catch (error) {
      // Ignore localStorage failures in restricted environments.
    }

    document.documentElement.dataset.theme = themeMode;
    document.documentElement.dataset.themeMode = themeMode;
  }, theme);

  const captured = [];
  const failed = [];

  try {
    if (mode === 'auth') {
      await login(context, credentials?.email ?? AUTH_EMAIL, credentials?.password ?? AUTH_PASSWORD);
    }

    const page = await context.newPage();
    page.setDefaultTimeout(TIMEOUT_MS);

    for (const target of routes) {
      const outputPath = path.join(groupDir, `${target.slug}.png`);

      try {
        const response = await page.goto(toNavigableRoute(target.uri), { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(WAIT_MS);

        if (!response) {
          throw new Error(`No HTTP response for ${target.uri}`);
        }

        const status = response.status();
        if (status >= 400) {
          throw new Error(`HTTP ${status} for ${target.uri}`);
        }

        const contentType = String(response.headers()['content-type'] ?? '');
        if (!contentType.includes('text/html')) {
          throw new Error(`Unexpected content type for ${target.uri}: ${contentType || 'unknown'}`);
        }

        await page.screenshot({
          path: outputPath,
          fullPage: true,
        });

        captured.push({
          slug: target.slug,
          label: target.label,
          uri: target.uri,
          publish: target.publish,
          file: outputPath,
          finalUrl: page.url(),
        });
      } catch (error) {
        failed.push({
          slug: target.slug,
          uri: target.uri,
          error: error instanceof Error ? error.message : String(error),
        });
      }
    }

    await page.close();
  } finally {
    await context.close();
  }

  return {
    theme,
    mode,
    captured: captured.map((item) => ({
      slug: item.slug,
      label: item.label,
      uri: item.uri,
      publish: item.publish,
      finalUrl: item.finalUrl,
      file: path.relative(outputDir, item.file).replace(/\\/g, '/'),
    })),
    failed,
  };
}

async function login(context, email, password) {
  const page = await context.newPage();
  page.setDefaultTimeout(TIMEOUT_MS);

  await page.goto(toNavigableRoute('/login'), { waitUntil: 'domcontentloaded' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);

  await page.locator('form button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
    timeout: TIMEOUT_MS,
  });
  await page.waitForTimeout(WAIT_MS);

  if (new URL(page.url()).pathname.endsWith('/login')) {
    throw new Error('Login failed: still on /login after submit');
  }

  await page.close();
}

async function publishScreenshots(sourceDir) {
  const publishDir = path.resolve(process.cwd(), PUBLISH_ROOT);
  let publishedCount = 0;

  for (const target of SCREENSHOT_TARGETS.filter((item) => item.publish)) {
    const source = path.join(sourceDir, `${target.slug}.png`);
    if (!existsSync(source)) {
      continue;
    }

    await fs.copyFile(source, path.join(publishDir, `${target.slug}.png`));
    publishedCount += 1;
  }

  return publishedCount;
}

async function disableViteHotFileIfNeeded() {
  if (USE_HMR) {
    return async () => {};
  }

  const hotPath = path.resolve(process.cwd(), 'public', 'hot');
  const tempHotPath = path.resolve(process.cwd(), 'public', 'hot.screenshots-disabled');

  if (!existsSync(hotPath)) {
    return async () => {};
  }

  await fs.rm(tempHotPath, { force: true });
  await fs.rename(hotPath, tempHotPath);
  console.log('[ui:screenshots] temporarily disabled public/hot to use built assets');

  return async () => {
    if (existsSync(tempHotPath)) {
      await fs.rename(tempHotPath, hotPath);
      console.log('[ui:screenshots] restored public/hot');
    }
  };
}

function readAppUrl() {
  for (const file of ['.env', '.env.example']) {
    const filePath = path.resolve(process.cwd(), file);
    if (!existsSync(filePath)) {
      continue;
    }

    const content = readFileSync(filePath, 'utf8');
    const match = content.match(/^APP_URL=(.+)$/m);
    if (!match) {
      continue;
    }

    return stripEnvWrapping(match[1]);
  }

  return null;
}

function stripEnvWrapping(value) {
  return String(value).trim().replace(/^['"]|['"]$/g, '');
}

function normalizeBaseUrl(url) {
  const trimmed = String(url ?? '').trim();
  if (trimmed === '') {
    return 'http://localhost/PromptFactory/public/';
  }
  return trimmed.endsWith('/') ? trimmed : `${trimmed}/`;
}

function toNavigableRoute(uri) {
  return String(uri ?? '')
    .replace(/^\/+/, '')
    .trim();
}

function parseViewport(raw) {
  const match = /^(\d{3,5})x(\d{3,5})$/i.exec(String(raw).trim());
  if (!match) {
    return { width: 1600, height: 1000 };
  }

  return {
    width: Number.parseInt(match[1], 10),
    height: Number.parseInt(match[2], 10),
  };
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }
  return parsed;
}

main().catch((error) => {
  console.error('[ui:screenshots] failed:', error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
