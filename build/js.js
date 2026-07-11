import { build, context } from 'esbuild'
import { log } from './log.js'

// Plain IIFE, no globalName: the bundle is a pure side-effect editor script.
// Banner goes through esbuild's own option so sourcemaps stay line-accurate.
function options(ctx, { dev, outfile }) {
  return {
    entryPoints: [ctx.paths.tsEntry],
    outfile,
    bundle: true,
    format: 'iife',
    platform: 'browser',
    target: ctx.config.esbuildTarget,
    minify: !dev,
    sourcemap: dev ? 'linked' : false,
    banner: { js: ctx.banner },
    logLevel: 'warning'
  }
}

export async function buildJs(ctx, { dev, outfile }) {
  await build(options(ctx, { dev, outfile }))
  log.success(`JS compiled: ${outfile}`)
}

export async function watchJs(ctx, outfile) {
  let resolveFirst
  const firstBuild = new Promise((resolve) => {
    resolveFirst = resolve
  })
  const c = await context({
    ...options(ctx, { dev: true, outfile }),
    plugins: [
      {
        name: 'notify',
        setup(b) {
          b.onEnd((result) => {
            if (result.errors.length > 0) return
            log.success(`JS compiled: ${outfile}`)
            resolveFirst()
          })
        }
      }
    ]
  })
  await c.watch()
  return { dispose: () => c.dispose(), firstBuild }
}
