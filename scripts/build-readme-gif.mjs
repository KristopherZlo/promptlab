import { existsSync } from 'node:fs';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const frames = [
  'dashboard',
  'task-directory',
  'task-detail',
  'prompt-details',
  'experiment-results',
  'library-catalog',
];

const inputDir = path.resolve(process.cwd(), 'docs', 'screenshots', 'latest', 'light');
const outputPath = path.resolve(process.cwd(), 'resources', 'images', 'readme-light-showcase.gif');
const frameWidth = 1120;
const frameHeight = 680;

async function main() {
  const missing = frames.filter((name) => !existsSync(path.join(inputDir, `${name}.png`)));

  if (missing.length > 0) {
    console.error(
      `[ui:readme-gif] missing source screenshots: ${missing.map((name) => `${name}.png`).join(', ')}`,
    );
    process.exit(1);
  }

  await mkdir(path.dirname(outputPath), { recursive: true });

  const magickArgs = [];

  for (const frame of frames) {
    magickArgs.push(
      '(',
      '-delay',
      '100',
      path.join(inputDir, `${frame}.png`),
      '-resize',
      `${frameWidth}x`,
      '-background',
      'white',
      '-gravity',
      'north',
      '-extent',
      `${frameWidth}x${frameHeight}`,
      '-strip',
      ')',
    );
  }

  magickArgs.push(
    '-loop',
    '0',
    '-dither',
    'None',
    '-colors',
    '128',
    '-layers',
    'Optimize',
    outputPath,
  );

  const result = spawnSync('magick', magickArgs, {
    cwd: process.cwd(),
    stdio: 'inherit',
  });

  if (result.error) {
    console.error(`[ui:readme-gif] failed to start ImageMagick: ${result.error.message}`);
    process.exit(1);
  }

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }

  console.log(`[ui:readme-gif] output: ${outputPath}`);
}

main().catch((error) => {
  console.error('[ui:readme-gif] failed:', error instanceof Error ? error.message : error);
  process.exit(1);
});
