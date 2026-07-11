import { execSync } from 'node:child_process'
import {
  copyFileSync,
  cpSync,
  createWriteStream,
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync
} from 'node:fs'
import path from 'node:path'
import { ZipArchive } from 'archiver'
import { buildJs } from './js.js'
import { log } from './log.js'
import { buildCss } from './sass.js'

const COMPOSER_AUTOLOAD_FILES = [
  'ClassLoader.php',
  'InstalledVersions.php',
  'LICENSE',
  'autoload_classmap.php',
  'autoload_files.php',
  'autoload_namespaces.php',
  'autoload_psr4.php',
  'autoload_real.php',
  'autoload_static.php',
  'installed.php',
  'platform_check.php'
]

const notHidden = (src) => !path.basename(src).startsWith('.')

export async function buildRelease(ctx) {
  const { staging, zip } = ctx.paths
  const slug = ctx.config.slug

  rmSync(staging, { recursive: true, force: true })
  rmSync(zip, { force: true })
  mkdirSync(staging, { recursive: true })

  cpSync(ctx.paths.plugin, staging, { recursive: true, filter: notHidden })
  cpSync(ctx.paths.php, path.join(staging, 'src/php'), {
    recursive: true,
    filter: (src) =>
      notHidden(src) &&
      src !== ctx.paths.libraryDir &&
      !src.startsWith(ctx.paths.libraryDir + path.sep)
  })
  cpSync(ctx.paths.vendorPrefixed, path.join(staging, 'vendor-prefixed'), {
    recursive: true,
    filter: notHidden
  })
  copyFileSync(ctx.paths.composerJson, path.join(staging, 'composer.json'))
  // composer.lock deliberately not shipped (dev-dependency metadata, no runtime role)

  if (ctx.config.vendor.autoloaderOnly) {
    // Packages live prefixed in vendor-prefixed/ — vendor/ ships the autoloader only
    mkdirSync(path.join(staging, 'vendor/composer'), { recursive: true })
    copyFileSync(
      path.join(ctx.paths.vendor, 'autoload.php'),
      path.join(staging, 'vendor/autoload.php')
    )
    for (const file of COMPOSER_AUTOLOAD_FILES) {
      const src = path.join(ctx.paths.vendor, 'composer', file)
      if (existsSync(src)) copyFileSync(src, path.join(staging, 'vendor/composer', file))
    }
  } else {
    cpSync(ctx.paths.vendor, path.join(staging, 'vendor'), { recursive: true, filter: notHidden })
  }

  const libraryOut = path.join(staging, 'src/php/libraries', slug)
  await buildJs(ctx, { dev: false, outfile: path.join(libraryOut, `${slug}.js`) })
  buildCss(ctx, { dev: false, outfile: path.join(libraryOut, `${slug}.css`) })

  log.info('Generating optimized autoloader in staging…')
  execSync('composer dump-autoload --optimize --no-dev', { cwd: staging, stdio: 'inherit' })

  await zipDirectory(staging, zip, slug)
  assertRelease(ctx)
  const size = (statSync(zip).size / 1024).toFixed(0)
  log.success(`Release ready: ${zip} (${size} KB)`)
}

// Staging is assembled clean by our own copy filters, so no zip-entry filter is needed
function zipDirectory(dir, zipPath, rootName) {
  return new Promise((resolve, reject) => {
    const output = createWriteStream(zipPath)
    const archive = new ZipArchive()
    output.on('close', resolve)
    archive.on('error', reject)
    archive.pipe(output)
    archive.directory(dir, rootName)
    archive.finalize().catch(reject)
  })
}

function assertRelease(ctx) {
  const slug = ctx.config.slug
  const entries = readdirSync(ctx.paths.staging, { recursive: true }).map(String)
  const offenders = entries.filter(
    (e) =>
      e.endsWith('.map') || path.basename(e) === 'composer.lock' || path.basename(e) === '.DS_Store'
  )
  if (offenders.length > 0) {
    throw new Error(`Dev artifacts leaked into release: ${offenders.join(', ')}`)
  }
  const required = [
    `${slug}.php`,
    'readme.txt',
    'composer.json',
    'vendor/autoload.php',
    `src/php/libraries/${slug}/${slug}.js`
  ]
  for (const rel of required) {
    if (!existsSync(path.join(ctx.paths.staging, rel))) {
      throw new Error(`Missing from release: ${rel}`)
    }
  }
  const bundleHead = readFileSync(
    path.join(ctx.paths.staging, `src/php/libraries/${slug}/${slug}.js`),
    'utf8'
  ).slice(0, 200)
  if (!bundleHead.includes(`v${ctx.version}`)) {
    throw new Error(`Bundle banner does not carry v${ctx.version}`)
  }
  const mainFile = readFileSync(path.join(ctx.paths.staging, `${slug}.php`), 'utf8')
  if (!mainFile.includes(`Version: ${ctx.version}`)) {
    throw new Error('Staged main file header Version mismatch')
  }
  if (statSync(ctx.paths.zip).size === 0) throw new Error('Empty zip')
}
