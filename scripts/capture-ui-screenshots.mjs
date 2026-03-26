import { promises as fs } from 'node:fs';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = normalizeBaseUrl(
  process.env.SCREENSHOT_BASE_URL ?? readAppUrl() ?? 'http://localhost/PromptFactory/public',
);
const OUTPUT_ROOT = process.env.SCREENSHOT_OUTPUT_DIR ?? 'interface-screenshots-auto';
const PUBLISH_ROOT = process.env.SCREENSHOT_PUBLISH_DIR ?? 'docs/screenshots/latest';
const ARCHIVE_ROOT = process.env.SCREENSHOT_ARCHIVE_DIR ?? 'docs/screenshots/archive';
const VIEWPORT = parseViewport(process.env.SCREENSHOT_VIEWPORT ?? '1600x1000');
const WAIT_MS = parsePositiveInt(process.env.SCREENSHOT_WAIT_MS, 900);
const TIMEOUT_MS = parsePositiveInt(process.env.SCREENSHOT_TIMEOUT_MS, 30000);
const AUTH_EMAIL = process.env.SCREENSHOT_AUTH_EMAIL ?? 'showcase@evala.local';
const AUTH_PASSWORD = process.env.SCREENSHOT_AUTH_PASSWORD ?? 'password';
const USE_HMR = process.env.SCREENSHOT_USE_HMR === '1';
const SHOWCASE_USE_CASE_SLUG = 'customer-email-summarization';
const SHOWCASE_PROMPT_NAME = 'Customer email summarizer';
const SHOWCASE_PROMPT_VERSION = 'v3';

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
    slug: 'task-detail',
    label: 'Task Detail',
    mode: 'auth',
    publish: true,
    path: ({ showcase }) => `/use-cases/${showcase.useCase.id}`,
  },
  {
    slug: 'prompt-revisions',
    label: 'Prompt Revisions',
    mode: 'auth',
    publish: true,
    path: ({ showcase }) =>
      `/prompts/${showcase.promptTemplate.id}?tab=versions&prompt_version_id=${showcase.promptVersion.id}`,
  },
  {
    slug: 'experiment-compare',
    label: 'Experiment Compare',
    mode: 'auth',
    publish: true,
    path: ({ showcase }) => `/experiments/${showcase.compareExperiment.id}?tab=results`,
  },
  {
    uri: '/library',
    slug: 'library-catalog',
    label: 'Library Catalog',
    mode: 'auth',
    publish: true,
  },
  {
    slug: 'library-entry',
    label: 'Library Entry',
    mode: 'auth',
    publish: true,
    path: ({ showcase }) => `/library/${showcase.libraryEntry.id}`,
  },
  {
    slug: 'playground',
    label: 'Experiment Playground',
    mode: 'auth',
    publish: true,
    path: ({ showcase }) => {
      const modelName = encodeURIComponent(
        showcase.promptVersion.preferred_model
          || showcase.promptTemplate.preferred_model
          || '',
      );

      return `/playground?mode=single&use_case_id=${showcase.useCase.id}&prompt_template_id=${showcase.promptTemplate.id}&prompt_version_id=${showcase.promptVersion.id}&model_name=${modelName}&step=review`;
    },
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
      archived: 0,
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
        const publishSummary = await publishScreenshots(path.join(themeDir, 'auth'), timestamp);
        summary.counts.published += publishSummary.publishedCount;
        summary.counts.archived += publishSummary.archivedCount;
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
      archiveRoot: ARCHIVE_ROOT,
      screenshotProfile: AUTH_EMAIL,
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
  console.log(`[ui:screenshots] archived: ${path.resolve(process.cwd(), ARCHIVE_ROOT)}`);
  console.log(
    `[ui:screenshots] captured=${summary.counts.captured} failed=${summary.counts.failed} published=${summary.counts.published} archived=${summary.counts.archived}`,
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
    const showcase = mode === 'auth' ? await loadShowcaseLookup(page) : null;

    for (const target of routes) {
      const outputPath = path.join(groupDir, `${target.slug}.png`);

      try {
        const targetPath = await resolveTargetPath(target, { showcase });
        const response = await page.goto(toNavigableRoute(targetPath), { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle', { timeout: Math.min(TIMEOUT_MS, 5000) }).catch(() => {});
        await page.waitForTimeout(WAIT_MS);

        if (!response) {
          throw new Error(`No HTTP response for ${targetPath}`);
        }

        const status = response.status();
        if (status >= 400) {
          throw new Error(`HTTP ${status} for ${targetPath}`);
        }

        const contentType = String(response.headers()['content-type'] ?? '');
        if (!contentType.includes('text/html')) {
          throw new Error(`Unexpected content type for ${targetPath}: ${contentType || 'unknown'}`);
        }

        await page.screenshot({
          path: outputPath,
          fullPage: true,
        });

        captured.push({
          slug: target.slug,
          label: target.label,
          uri: targetPath,
          publish: target.publish,
          file: outputPath,
          finalUrl: page.url(),
        });
      } catch (error) {
        failed.push({
          slug: target.slug,
          uri: target.uri ?? target.slug,
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

async function publishScreenshots(sourceDir, timestamp) {
  const publishDir = path.resolve(process.cwd(), PUBLISH_ROOT);
  const archiveDir = path.resolve(process.cwd(), ARCHIVE_ROOT, timestamp);
  let publishedCount = 0;
  let archivedCount = 0;
  const existingFiles = existsSync(publishDir)
    ? (await fs.readdir(publishDir)).filter((file) => file.toLowerCase().endsWith('.png'))
    : [];

  if (existingFiles.length > 0) {
    await fs.mkdir(archiveDir, { recursive: true });

    for (const file of existingFiles) {
      await fs.copyFile(path.join(publishDir, file), path.join(archiveDir, file));
      await fs.rm(path.join(publishDir, file), { force: true });
      archivedCount += 1;
    }
  }

  for (const target of SCREENSHOT_TARGETS.filter((item) => item.publish)) {
    const source = path.join(sourceDir, `${target.slug}.png`);
    if (!existsSync(source)) {
      continue;
    }

    await fs.copyFile(source, path.join(publishDir, `${target.slug}.png`));
    publishedCount += 1;
  }

  return { publishedCount, archivedCount };
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

async function resolveTargetPath(target, context) {
  if (typeof target.path === 'function') {
    return target.path(context);
  }

  return target.uri ?? target.path ?? '/';
}

async function loadShowcaseLookup(page) {
  const useCasesPayload = await fetchJson(page, '/api/use-cases');
  const useCases = useCasesPayload.data ?? [];
  const useCase = useCases.find((item) => item.slug === SHOWCASE_USE_CASE_SLUG);

  if (!useCase) {
    throw new Error(`Showcase use case not found: ${SHOWCASE_USE_CASE_SLUG}`);
  }

  const promptsPayload = await fetchJson(page, '/api/prompts');
  const templates = promptsPayload.data ?? [];
  const promptTemplate = templates.find((item) =>
    item.use_case_id === useCase.id && item.name === SHOWCASE_PROMPT_NAME,
  );

  if (!promptTemplate) {
    throw new Error(`Showcase prompt template not found: ${SHOWCASE_PROMPT_NAME}`);
  }

  const promptVersion = (promptTemplate.versions ?? []).find((version) => version.version_label === SHOWCASE_PROMPT_VERSION)
    ?? (promptTemplate.versions ?? []).at(-1)
    ?? null;

  if (!promptVersion) {
    throw new Error(`Showcase prompt version not found: ${SHOWCASE_PROMPT_VERSION}`);
  }

  const useCaseDetail = await fetchJson(page, `/api/use-cases/${useCase.id}`);
  const compareExperiment = (useCaseDetail.recentExperiments ?? []).find((experiment) => experiment.mode === 'compare');

  if (!compareExperiment) {
    throw new Error(`Compare experiment not found for use case: ${SHOWCASE_USE_CASE_SLUG}`);
  }

  const libraryEntriesPayload = await fetchJson(page, '/api/library-entries');
  const libraryEntries = libraryEntriesPayload.data ?? [];
  const libraryEntry = libraryEntries.find((entry) => entry.prompt_version?.id === promptVersion.id)
    ?? libraryEntries.find((entry) => entry.prompt_version?.prompt_template_id === promptTemplate.id)
    ?? null;

  if (!libraryEntry) {
    throw new Error(`Library entry not found for showcase prompt: ${SHOWCASE_PROMPT_NAME} ${promptVersion.version_label}`);
  }

  return {
    useCase,
    promptTemplate,
    promptVersion,
    compareExperiment,
    libraryEntry,
  };
}

async function fetchJson(page, uri) {
  return page.evaluate(async (relativeUri) => {
    const nextUrl = new URL(String(relativeUri ?? '').replace(/^\/+/, ''), window.location.href).toString();
    const response = await fetch(nextUrl, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status} for ${nextUrl}`);
    }

    return response.json();
  }, uri);
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
