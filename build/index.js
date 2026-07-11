#!/usr/bin/env node
import process from 'node:process'
import { loadCtx } from './config.js'
import { watchJs } from './js.js'
import { log } from './log.js'
import { stampAll } from './meta.js'
import { buildRelease } from './package.js'
import { buildCss, watchCss } from './sass.js'
import { initialMirror, watchComposer, watchSources } from './sync.js'

async function dev(ctx) {
  if (!ctx.paths.devTarget) {
    throw new Error('No dev sync target — set DEV_TARGET in .env (see project.config.js devTarget)')
  }
  stampAll(ctx)
  const js = await watchJs(ctx, ctx.paths.jsOut)
  buildCss(ctx, { dev: true, outfile: ctx.paths.cssOut })
  await js.firstBuild
  initialMirror(ctx)
  const watchers = [watchSources(ctx), watchCss(ctx, ctx.paths.cssOut), watchComposer(ctx)].filter(
    Boolean
  )
  log.success('Dev mode running — Ctrl+C to stop')
  process.on('SIGINT', async () => {
    log.info('Shutting down…')
    await js.dispose()
    await Promise.all(watchers.map((w) => w.close()))
    process.exit(0)
  })
}

const command = process.argv[2] ?? 'build'
try {
  const ctx = await loadCtx()
  if (command === 'dev') {
    await dev(ctx)
  } else if (command === 'build') {
    stampAll(ctx)
    await buildRelease(ctx)
  } else {
    log.error(`Unknown command "${command}" — use: dev | build`)
    process.exit(1)
  }
} catch (err) {
  log.error(err)
  process.exit(1)
}
