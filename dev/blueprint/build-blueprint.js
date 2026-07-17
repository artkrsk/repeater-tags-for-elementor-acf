/**
 * Generates .wordpress-org/blueprints/blueprint.json — the wp.org Live Preview blueprint.
 *
 * Self-contained by design: seed.php and the demo page are inlined as writeFile steps rather
 * than fetched. wp.org's SVN serves no CORS headers so a blueprint cannot pull its own assets
 * back down, and a GitHub-raw dependency would put the live preview at the mercy of a repo URL
 * plus a tag bump every release.
 *
 * The release workflow copies .wordpress-org/ into SVN assets/ wholesale (cp -R + svn add
 * --depth infinity), so the blueprints/ subdir needs no build wiring of its own.
 */

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { elements } from './demo-page.js'

const MU_PLUGINS = '/wordpress/wp-content/mu-plugins'
const OUT = fileURLToPath(
  new URL('../../.wordpress-org/blueprints/blueprint.json', import.meta.url)
)

const seed = readFileSync(new URL('./seed.php', import.meta.url), 'utf8')

// Single source of truth for the page id: the landingPage and the seeder cannot drift.
const pageId = seed.match(/define\(\s*'RTB_DEMO_PAGE_ID',\s*(\d+)\s*\)/)?.[1]

if (!pageId) {
  console.error('build-blueprint: could not read RTB_DEMO_PAGE_ID out of seed.php')
  process.exit(1)
}

const installPlugin = (slug) => ({
  step: 'installPlugin',
  pluginData: { resource: 'wordpress.org/plugins', slug },
  options: { activate: true }
})

const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  landingPage: `/wp-admin/post.php?post=${pageId}&action=elementor`,
  preferredVersions: { php: '8.2', wp: 'latest' },
  // gd — the seeder draws its demo images rather than sideloading them.
  phpExtensionBundles: ['kitchen-sink'],
  // Required: without it the wordpress.org installs fail on CORS.
  features: { networking: true },
  login: true,
  steps: [
    installPlugin('elementor'),
    // The repeater provider. ACF Pro is not on wp.org; SCF is the same field API.
    installPlugin('secure-custom-fields'),
    installPlugin('repeater-tags-for-elementor-acf'),
    {
      step: 'installTheme',
      themeData: { resource: 'wordpress.org/themes', slug: 'hello-elementor' },
      options: { activate: true }
    },
    { step: 'mkdir', path: `${MU_PLUGINS}/assets` },
    { step: 'writeFile', path: `${MU_PLUGINS}/rt-demo-seed.php`, data: seed },
    {
      step: 'writeFile',
      path: `${MU_PLUGINS}/assets/demo-page.json`,
      data: JSON.stringify(elements)
    },
    {
      // The seeder hooks admin_init; nothing has made an admin request yet at this point.
      step: 'runPHP',
      code: "<?php require_once '/wordpress/wp-load.php'; require_once '/wordpress/wp-admin/includes/admin.php'; do_action( 'admin_init' );"
    }
  ]
}

mkdirSync(dirname(OUT), { recursive: true })
writeFileSync(OUT, `${JSON.stringify(blueprint, null, 2)}\n`)

console.log(
  `blueprint:build OK — ${OUT} (${(JSON.stringify(blueprint).length / 1024).toFixed(1)} KB, page id ${pageId})`
)
