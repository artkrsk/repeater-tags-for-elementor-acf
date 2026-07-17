/**
 * Guards the one coupling in the blueprint: demo-page.js binds ACF field KEYS that seed.php
 * registers, and nothing at runtime would notice if they drifted apart — a renamed key just
 * makes the live preview render blanks.
 *
 * Text-only: no WordPress, no ACF, no PHP runtime.
 */

import { readFileSync } from 'node:fs'
import { elements } from './demo-page.js'

const seed = readFileSync(new URL('./seed.php', import.meta.url), 'utf8')

const registered = new Set(
  [...seed.matchAll(/'key'\s*=>\s*'((?:field|layout)_rtb_[a-z0-9_]+)'/g)].map((m) => m[1])
)

const failures = []

/** Every [elementor-tag …] shortcode anywhere in the tree, with its decoded settings. */
function* bindings(nodes) {
  for (const node of nodes) {
    for (const shortcode of Object.values(node.settings?.__dynamic__ ?? {})) {
      const match = shortcode.match(/name="([^"]+)" settings="([^"]+)"/)

      if (!match) {
        failures.push(`Unparseable tag shortcode: ${shortcode.slice(0, 80)}`)
        continue
      }

      // urldecode(): `+` is a space in PHP's encoding, decodeURIComponent won't do that.
      yield {
        name: match[1],
        settings: JSON.parse(decodeURIComponent(match[2].replace(/\+/g, ' ')))
      }
    }

    yield* bindings(node.elements ?? [])
  }
}

for (const { name, settings } of bindings(elements)) {
  const parent = settings.repeater_field

  if (!registered.has(parent)) {
    failures.push(`${name}: repeater_field "${parent}" is not registered by seed.php`)
  }

  for (const key of Object.keys(settings)) {
    const sub = key.match(/^sub_field_(.+)$/)

    // Catches the copy-paste failure an existence check alone would miss: a sub_field_
    // suffix naming a different repeater than the one this binding actually reads.
    if (sub && sub[1] !== parent) {
      failures.push(`${name}: "${key}" does not match repeater_field "${parent}"`)
    }

    const child = key.match(/^child_(?:row_index|sub_field)_(.+)$/)

    if (child && !registered.has(child[1])) {
      failures.push(`${name}: child key "${child[1]}" is not registered by seed.php`)
    }
  }
}

if (failures.length) {
  console.error(`blueprint:check failed\n${failures.map((f) => `  - ${f}`).join('\n')}`)
  process.exit(1)
}

console.log(
  `blueprint:check OK — ${registered.size} keys registered, all demo-page bindings resolve.`
)
