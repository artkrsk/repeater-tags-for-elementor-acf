import process from 'node:process'

export default {
  slug: 'repeater-tags-for-elementor-acf',
  versionConstant: 'ARTS_REPEATER_TAGS_PLUGIN_VERSION',
  defineKey: '__ARTS_REPEATER_TAGS_VERSION__',
  esbuildTarget: 'es2018',
  entry: { ts: './src/ts/index.ts', sass: null },
  bundles: [],
  bannerLines: [],
  zip: { budgetMb: 0.5 },
  paths: { php: './src/php', plugin: './src/wordpress-plugin', dist: './dist' },
  // Machine-specific: the Local site's plugin dir, from the gitignored .env (DEV_TARGET)
  devTarget: process.env.DEV_TARGET ?? null,
  // null = derived from the slug (the old runner hardcoded ArtsRepeaterTagsPlugin)
  vendor: { autoloaderOnly: true, autoloaderSuffix: null },
  // The wp.org Live Preview blueprint is genuinely custom (two-file seed
  // composition + key-drift checker) — it lives in dev/blueprint/, not here.
  blueprint: null
}
