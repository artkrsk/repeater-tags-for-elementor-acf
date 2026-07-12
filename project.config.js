import process from 'node:process'

export default {
  slug: 'repeater-tags-for-elementor-acf',
  entry: { ts: './src/ts/index.ts', sass: null },
  paths: { php: './src/php', plugin: './src/wordpress-plugin', dist: './dist' },
  // Machine-specific: the Local site's plugin dir, from the gitignored .env (DEV_TARGET)
  devTarget: process.env.DEV_TARGET ?? null,
  esbuildTarget: 'es2018',
  versionConstant: 'ARTS_REPEATER_TAGS_PLUGIN_VERSION',
  vendor: { autoloaderOnly: true }
}
