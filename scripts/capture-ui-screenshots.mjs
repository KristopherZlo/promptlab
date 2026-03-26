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
const UNVERIFIED_EMAIL = process.env.SCREENSHOT_UNVERIFIED_EMAIL ?? 'unverified@evala.local';
const UNVERIFIED_PASSWORD = process.env.SCREENSHOT_UNVERIFIED_PASSWORD ?? 'password';
const SHOWCASE_INVITATION_TOKEN = process.env.SCREENSHOT_INVITATION_TOKEN ?? 'evala-showcase-invite';
const USE_HMR = process.env.SCREENSHOT_USE_HMR === '1';
const SHOWCASE_USE_CASE_SLUG = 'customer-email-summarization';
const SHOWCASE_PROMPT_NAME = 'Customer email summarizer';
const SHOWCASE_PROMPT_VERSION = 'v3';

const THEMES = ['light', 'dark'];
const SCREENSHOT_SESSIONS = {
  guest: {
    id: 'guest',
    label: 'Guest visitor',
    initialPath: '/login',
    requiresLogin: false,
    requiresShowcaseLookup: false,
  },
  showcase: {
    id: 'showcase',
    label: 'Showcase workspace',
    initialPath: '/dashboard',
    requiresLogin: true,
    requiresShowcaseLookup: true,
    credentials: {
      email: AUTH_EMAIL,
      password: AUTH_PASSWORD,
    },
  },
  unverified: {
    id: 'unverified',
    label: 'Unverified user',
    initialPath: '/verify-email',
    requiresLogin: true,
    requiresShowcaseLookup: false,
    credentials: {
      email: UNVERIFIED_EMAIL,
      password: UNVERIFIED_PASSWORD,
    },
  },
};
const SCREENSHOT_TARGETS = [
  {
    category: 'Authentication',
    slug: 'login',
    label: 'Login',
    session: 'guest',
    publish: true,
    uri: '/login',
  },
  {
    category: 'Authentication',
    slug: 'register',
    label: 'Register',
    session: 'guest',
    publish: true,
    uri: '/register',
  },
  {
    category: 'Authentication',
    slug: 'forgot-password',
    label: 'Forgot password',
    session: 'guest',
    publish: true,
    uri: '/forgot-password',
  },
  {
    category: 'Authentication',
    slug: 'reset-password',
    label: 'Reset password',
    session: 'guest',
    publish: true,
    uri: '/reset-password/evala-demo-reset-token?email=showcase%40evala.local',
  },
  {
    category: 'Authentication',
    slug: 'verify-email',
    label: 'Verify email',
    session: 'unverified',
    publish: true,
    uri: '/verify-email',
  },
  {
    category: 'Authentication',
    slug: 'confirm-password',
    label: 'Confirm password',
    session: 'showcase',
    publish: true,
    uri: '/confirm-password',
  },
  {
    category: 'Invitations',
    slug: 'invitation',
    label: 'Invitation landing',
    session: 'guest',
    publish: true,
    path: ({ invitationToken }) => `/join/${invitationToken}`,
  },
  {
    category: 'Invitations',
    slug: 'invitation-login',
    label: 'Invitation login',
    session: 'guest',
    publish: true,
    path: ({ invitationToken }) => `/login?invitation=${encodeURIComponent(invitationToken)}`,
  },
  {
    category: 'Invitations',
    slug: 'invitation-register',
    label: 'Invitation register',
    session: 'guest',
    publish: true,
    path: ({ invitationToken }) => `/register?invitation=${encodeURIComponent(invitationToken)}`,
  },
  {
    category: 'Workspace',
    slug: 'getting-started',
    label: 'Getting started',
    session: 'showcase',
    publish: true,
    uri: '/start-here',
  },
  {
    category: 'Workspace',
    slug: 'dashboard',
    label: 'Dashboard',
    session: 'showcase',
    publish: true,
    uri: '/dashboard',
  },
  {
    category: 'Workspace',
    slug: 'task-directory',
    label: 'Task directory',
    session: 'showcase',
    publish: true,
    uri: '/use-cases',
  },
  {
    category: 'Workspace',
    slug: 'task-detail',
    label: 'Task detail',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) => `/use-cases/${showcase.useCase.id}`,
  },
  {
    category: 'Prompts',
    slug: 'prompt-catalog',
    label: 'Prompt library',
    session: 'showcase',
    publish: true,
    uri: '/prompts',
  },
  {
    category: 'Prompts',
    slug: 'prompt-create',
    label: 'Prompt create',
    session: 'showcase',
    publish: true,
    uri: '/prompts/create',
  },
  {
    category: 'Prompts',
    slug: 'prompt-details',
    label: 'Prompt details',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) => `/prompts/${showcase.promptTemplate.id}?tab=template`,
  },
  {
    category: 'Prompts',
    slug: 'prompt-revisions',
    label: 'Prompt history',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) =>
      `/prompts/${showcase.promptTemplate.id}?tab=versions&prompt_version_id=${showcase.promptVersion.id}`,
  },
  {
    category: 'Prompts',
    slug: 'prompt-optimize',
    label: 'Prompt optimize',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) =>
      `/prompts/${showcase.promptTemplate.id}?tab=optimize&prompt_version_id=${showcase.promptVersion.id}`,
  },
  {
    category: 'Prompts',
    slug: 'prompt-library',
    label: 'Prompt library',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) =>
      `/prompts/${showcase.promptTemplate.id}?tab=library&prompt_version_id=${showcase.promptVersion.id}`,
  },
  {
    category: 'Experiments',
    slug: 'experiment-results',
    label: 'Experiment results',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) => `/experiments/${showcase.compareExperiment.id}?tab=results`,
  },
  {
    category: 'Experiments',
    slug: 'experiment-summary',
    label: 'Experiment summary',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) => `/experiments/${showcase.compareExperiment.id}?tab=summary`,
  },
  {
    category: 'Experiments',
    slug: 'playground',
    label: 'Experiment playground',
    session: 'showcase',
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
  {
    category: 'Library',
    slug: 'library-catalog',
    label: 'Library catalog',
    session: 'showcase',
    publish: true,
    uri: '/library',
  },
  {
    category: 'Library',
    slug: 'library-entry',
    label: 'Library entry',
    session: 'showcase',
    publish: true,
    path: ({ showcase }) => `/library/${showcase.libraryEntry.id}`,
  },
  {
    category: 'Administration',
    slug: 'admin-members',
    label: 'Users access members',
    session: 'showcase',
    publish: true,
    uri: '/admin/users-access?tab=members',
  },
  {
    category: 'Administration',
    slug: 'admin-invitations',
    label: 'Users access invitations',
    session: 'showcase',
    publish: true,
    uri: '/admin/users-access?tab=invitations',
  },
  {
    category: 'Administration',
    slug: 'admin-roles',
    label: 'Users access roles',
    session: 'showcase',
    publish: true,
    uri: '/admin/users-access?tab=roles',
  },
  {
    category: 'Administration',
    slug: 'admin-workspaces',
    label: 'Workspaces current',
    session: 'showcase',
    publish: true,
    uri: '/admin/workspaces?tab=current',
  },
  {
    category: 'Administration',
    slug: 'admin-workspaces-create',
    label: 'Workspaces create',
    session: 'showcase',
    publish: true,
    uri: '/admin/workspaces?tab=create',
  },
  {
    category: 'Administration',
    slug: 'admin-ai-connections',
    label: 'Model connections',
    session: 'showcase',
    publish: true,
    uri: '/admin/ai-connections?tab=connections',
  },
  {
    category: 'Administration',
    slug: 'admin-ai-connections-editor',
    label: 'Model connections editor',
    session: 'showcase',
    publish: true,
    uri: '/admin/ai-connections?tab=editor',
  },
  {
    category: 'Administration',
    slug: 'admin-audit-log',
    label: 'Audit history',
    session: 'showcase',
    publish: true,
    uri: '/admin/audit-log',
  },
  {
    category: 'Account',
    slug: 'profile',
    label: 'Profile',
    session: 'showcase',
    publish: true,
    uri: '/profile',
  },
  {
    category: 'Account',
    slug: 'acknowledgements',
    label: 'Acknowledgements',
    session: 'showcase',
    publish: true,
    uri: '/acknowledgements',
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
    publishedThemes: THEMES,
    counts: {
      captured: 0,
      failed: 0,
      published: 0,
      archived: 0,
    },
    runs: [],
  };

  try {
    const sessionIds = uniqueSessionIds();

    for (const theme of THEMES) {
      const themeDir = path.join(runDir, theme);
      await fs.mkdir(themeDir, { recursive: true });

      for (const sessionId of sessionIds) {
        const session = SCREENSHOT_SESSIONS[sessionId];
        const routes = SCREENSHOT_TARGETS.filter((target) => target.session === sessionId);

        const result = await captureSession({
          browser,
          theme,
          session,
          routes,
          outputDir: themeDir,
        });

        summary.runs.push(result);
        summary.counts.captured += result.captured.length;
        summary.counts.failed += result.failed.length;
      }
    }

    const publishSummary = await publishScreenshots(runDir, timestamp);
    summary.counts.published += publishSummary.publishedCount;
    summary.counts.archived += publishSummary.archivedCount;
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
    JSON.stringify(buildPublishedManifest(timestamp), null, 2),
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

async function captureSession({
  browser,
  theme,
  session,
  routes,
  outputDir,
}) {
  const sessionDir = path.join(outputDir, session.id);
  await fs.mkdir(sessionDir, { recursive: true });

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
    if (session.requiresLogin) {
      await login(
        context,
        session.credentials?.email ?? AUTH_EMAIL,
        session.credentials?.password ?? AUTH_PASSWORD,
      );
    }

    const page = await context.newPage();
    page.setDefaultTimeout(TIMEOUT_MS);

    if (session.initialPath) {
      await page.goto(toNavigableRoute(session.initialPath), { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(WAIT_MS);
    }

    const showcase = session.requiresShowcaseLookup ? await loadShowcaseLookup(page) : null;

    for (const target of routes) {
      const outputPath = path.join(sessionDir, `${target.slug}.png`);

      try {
        const targetPath = await resolveTargetPath(target, {
          showcase,
          invitationToken: SHOWCASE_INVITATION_TOKEN,
        });
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
          category: target.category,
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
    session: session.id,
    label: session.label,
    captured: captured.map((item) => ({
      category: item.category,
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

async function publishScreenshots(runDir, timestamp) {
  const publishDir = path.resolve(process.cwd(), PUBLISH_ROOT);
  const archiveDir = path.resolve(process.cwd(), ARCHIVE_ROOT, timestamp);
  let archivedCount = 0;
  let publishedCount = 0;

  if (existsSync(publishDir)) {
    const existingEntries = await fs.readdir(publishDir, { withFileTypes: true });

    if (existingEntries.length > 0) {
      await fs.mkdir(archiveDir, { recursive: true });

      for (const entry of existingEntries) {
        const sourcePath = path.join(publishDir, entry.name);
        const archivePath = path.join(archiveDir, entry.name);

        await fs.cp(sourcePath, archivePath, { recursive: true, force: true });
        archivedCount += await countFiles(archivePath);
        await fs.rm(sourcePath, { recursive: true, force: true });
      }
    }
  }

  for (const theme of THEMES) {
    const themePublishDir = path.join(publishDir, theme);
    await fs.mkdir(themePublishDir, { recursive: true });

    for (const target of SCREENSHOT_TARGETS.filter((item) => item.publish)) {
      const source = path.join(runDir, theme, target.session, `${target.slug}.png`);

      if (!existsSync(source)) {
        continue;
      }

      await fs.copyFile(source, path.join(themePublishDir, `${target.slug}.png`));
      publishedCount += 1;
    }
  }

  return { publishedCount, archivedCount };
}

async function countFiles(targetPath) {
  const stats = await fs.stat(targetPath);

  if (stats.isFile()) {
    return 1;
  }

  const entries = await fs.readdir(targetPath, { withFileTypes: true });
  let count = 0;

  for (const entry of entries) {
    count += await countFiles(path.join(targetPath, entry.name));
  }

  return count;
}

function buildPublishedManifest(timestamp) {
  return {
    baseUrl: BASE_URL,
    generatedAt: timestamp,
    archiveRoot: ARCHIVE_ROOT,
    themes: THEMES,
    screenshotProfiles: {
      showcase: AUTH_EMAIL,
      unverified: UNVERIFIED_EMAIL,
      invitationToken: SHOWCASE_INVITATION_TOKEN,
    },
    routes: SCREENSHOT_TARGETS
      .filter((target) => target.publish)
      .map((target) => ({
        slug: target.slug,
        label: target.label,
        category: target.category,
        session: target.session,
        files: Object.fromEntries(
          THEMES.map((theme) => [theme, `${theme}/${target.slug}.png`]),
        ),
      })),
  };
}

function uniqueSessionIds() {
  return [...new Set(SCREENSHOT_TARGETS.map((target) => target.session))];
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
  return page.evaluate(async ({ relativeUri, baseUrl }) => {
    const nextUrl = new URL(String(relativeUri ?? '').replace(/^\/+/, ''), baseUrl).toString();
    const response = await fetch(nextUrl, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status} for ${nextUrl}`);
    }

    return response.json();
  }, { relativeUri: uri, baseUrl: BASE_URL });
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
