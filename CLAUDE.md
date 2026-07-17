# Arts Repeater Tags for Elementor and ACF

Standalone wp.org plugin: ACF repeater sub-fields as native Elementor **dynamic tags** with
direct row addressing (repeater → row → sub-field pickers in the tag popover). Seven tags
(see Frozen contracts), nested structures depth-1 (group sub-fields as dot paths,
repeater-in-repeater via a child picker tier, flexible content with layout-aware rows),
options-page/term/user contexts, Theme Builder support. With Elementor Pro active it also
offers a repeat mode (rows as Loop Grid/Carousel items) and a row-count display condition.
No custom widgets, no templates, no data-shape assumptions. Clean fork of
`arts-store-elementor-labels` (ArtsStore monorepo) — the donor stays in the shop; this repo
lives its own life. Project state that changes over time (release/review status, deferred
work) lives in the auto-memory, not in this file.

## Stack & requirements

PHP 8.0+ · WP 6.0+ · Elementor free ≥3.5 (NO Elementor Pro code dependency — Pro's Theme Builder
is the use case, not a requirement) · ACF Pro or Secure Custom Fields (repeater provider; SCF
verified identical). Dependency handling: `Requires Plugins: elementor`
header (WP 6.5+ native; hand-maintained — the build's meta updater doesn't manage that key);
NO runtime guard for Elementor (every entry point is an `elementor/*` hook — absence is inert).
The ACF side can't be pinned in Requires Plugins (the header ANDs slug-exact entries, and the
repeater provider may be ACF Pro or the SCF fork under different slugs); the single ACF soft-check
lives in `Schema::get_repeaters()` (fails closed to empty enumeration — all other ACF call
sites are reachable only through enumerated keys). No other `class_exists` sprinkling.

## Commands

```bash
pnpm dev        # fixtures sync, then watch: esbuild TS → src/php/libraries/..., sync plugin → fluid-ds Local site
pnpm sync       # push dev/mu-plugins fixtures to the Local site
pnpm build      # production ZIP in dist/
pnpm blueprint:build  # regenerate .wordpress-org/blueprints/blueprint.json (wp.org Live Preview)
pnpm blueprint:check  # assert the demo page only binds field keys the demo seeder registers
pnpm typecheck  # tsc --noEmit (esbuild does NOT type-check)
pnpm phpstan    # PHPStan level max, src/php + tests (composer install first)
pnpm lint       # biome check (build/, dev/, src/ts — config in biome.json)
pnpm test       # build → wp-env start → all three PHPUnit suites (Docker required)
pnpm test:php   # inner loop: main suite only (env already up; rebuild after src/php edits)
pnpm test:php:no-acf       # inner loop: the Elementor-without-ACF suite
pnpm test:php:no-provider  # inner loop: the no-Elementor/no-SCF suite
```

Fresh clone: `composer install`, create `.env` with `DEV_TARGET=<Local site plugin dir>`, then
`pnpm dev`. `arts/base` gets Strauss-prefixed
into `vendor-prefixed/` automatically via `post-install-cmd`/`post-update-cmd` (a deliberate
deviation from the ArtsStore monorepo's manual `composer prefix-namespaces`, which has two paper
cuts: a fresh clone hard-fails scanning a missing `vendor-prefixed/` classmap dir, and a forgotten
re-run after `composer update` silently runs stale prefixed code). `composer prefix-namespaces`
still exists for a manual re-run if needed.

Dev site: `/Users/art/Local Sites/fluid-ds/app/public` (shared with the FDS plugin — no overlap).
wp-cli against it (Local ships its own PHP/MySQL/wp-cli per site — source the env first, one invocation):

```bash
source <(grep -E '^[[:space:]]*(export|cd|unset)' "/Users/art/Library/Application Support/Local/ssh-entry/wNlVYAQFQ.sh")
wp plugin list
```

Sync target: `DEV_TARGET` in the gitignored `.env` (native `process.loadEnvFile()`, no dotenv
dep) → `project.config.js` `devTarget`; machine-specific values never live in committed config.
`pnpm build` needs no `.env` (CI-safe); `pnpm dev` fails fast without it. Build tooling is the in-repo
`build/` (8 flat ESM modules; the reference copy to vendor into future plugin extractions —
keep it project-agnostic). ONE config file, `project.config.js` — unknown keys are hard errors (every
key must be read by the build); dev/prod behavior is intrinsic to the command, there are no env
config files. Production compiles into a staging dir under `dist/` and never writes the source
tree; dev mirrors per-file to the Local site and never creates `dist/`.
Dev fixtures live IN the repo at `dev/mu-plugins/rt-demo-fixtures.php` — the source of truth,
synced to the Local site's `wp-content/mu-plugins/` by `pnpm sync` / on `pnpm dev` startup
(`dev/sync-fixtures.js` derives the target from `devTarget`). Field groups
(shop-shaped `product_mockups`/`product_counters` + `rt_demo_items` + options/term/book-CPT groups),
idempotent seeders (guard options + sideloaded picsum media), and the seeded "Repeater Tags
Demo" page — which is the visual test stand (bound sections + assertion notes; Elementor JSON
exports in `dev/`).

## Tests (wp-env + PHPUnit)

`pnpm test` runs everything; the committed `.wp-env.json` defines the environment. The harness
mounts the BUILT `dist/<slug>` as the plugin (tests exercise the shipped artifact — SmokeTest
pins that with ReflectionClass file-origin asserts), `dev/mu-plugins/` as mu-plugins (the
fixture field groups ARE the test schema; tests write their own content via `update_field()`
with fixture field KEYS, mirroring the seeder value shapes), and the repo root as
`test-workspace` (phpunit + root vendor). Main-suite providers, loaded by `tests/bootstrap.php`
at muplugins_loaded: Elementor free, PRO Elements (GPL redistribution of Elementor Pro's PHP —
a CI-only dev dependency that unlocks the Pro seams: `Conditions\RowCount`, `LoopRepeat`
expansion, the real `loop-item` document in ContextTest; the manual stand stays authoritative
for real Pro), and SCF. THREE suites, one bootstrap, two env-var gates
(`RT_TESTS_WITHOUT_PROVIDER`, `RT_TESTS_WITHOUT_ACF`) — each has its own phpunit config: the
main one; `no-acf` (Elementor + Pro, NO ACF — the one combo `Requires Plugins` can't express,
so it's what Schema's soft-check exists for, and the only suite where the Elementor-facing
seams run with nothing behind them); and `no-provider` (neither — the plugin boots inert, and
Context is unreachable there because it dereferences `\Elementor\Plugin::$instance`).
Ports are 8890/8891 on purpose: 8888/8889 are
parked by another wp-env on the dev Mac, 8880/8881 by plugin-check on the CI runner; local
overrides go in the gitignored `.wp-env.override.json`. All environment pins move TOGETHER
when bumping: `.wp-env.json` core ↔ composer `wp-phpunit` (`~X.Y.0`, mirrors core releases) ↔
the Elementor/PRO Elements/SCF zips.

Tag suites construct tags directly (`new RepeaterText( [ 'id' => …, 'settings' => … ] )`, the
same seam Elementor's own `Dynamic_Tags\Manager::create_tag()` uses) — see
`tests/Integration/TagTestCase.php` for the two mechanics that constrain how. The
`RT Type Matrix` fixture group is test-only (no seeder, not on the demo page): its sub-fields
are NAMED AFTER THEIR ACF TYPE, which is what lets `TagCompatMapTest` drive itself off each
tag's `get_accepted_sub_field_types()` and prove every accepted type is both offered and
renders — add a type to a tag's map without a fixture sub-field and that test fails loudly.

## Architecture (one line each)

- `src/wordpress-plugin/` — plugin shell (main file: header, autoloader, boot guard).
- `src/php/Plugin.php` — singleton; lazy service accessors; wires managers + services onto `elementor/*` hooks.
- `src/php/Base/` — thin `arts/base` bridges re-typing `$managers` for PHPStan (`@property` container).
- `src/php/Managers/` — WP-facing registration only: `Elementor.php` (tag group + tags + control +
  Pro display condition), `Ajax.php` (elementor/ajax/register_actions), `Assets.php` (editor JS
  enqueue).
- `src/php/Services/Schema.php` — ACF enumeration: site-wide repeaters (key ⇒ label) + sub-fields;
  options-page scoping, collapsed-label + return_format capture.
- `src/php/Services/Rows.php` — rows read (`get_field` + direct index), dev-API filter, memoization,
  row-label derivation.
- `src/php/Services/Context.php` — read-target ladder: options store → loop-item doc → queried
  term/user → `get_the_ID()`.
- `src/php/Services/LoopRepeat.php` — Pro repeat mode: "Repeat by ACF Repeater" on loop widgets,
  query expansion (one item per row), current-row registry for the `-2` sentinel.
- `src/php/Tags/` — `BaseRepeaterTag` trait + one tag class per frozen tag name.
- `src/php/Conditions/RowCount.php` — Pro Display Condition ("row count vs N"); loads only via the
  Pro-only registration hook.
- `src/php/Controls/RowPicker.php` + `src/ts/` — the one custom control (Select2 + Elementor ajax).
- `dev/blueprint/` — the wp.org Live Preview (WordPress Playground). `seed.php` (mu-plugin: own
  `field_rtb_*` groups, GD-drawn images, seeded rows, the demo page) + `demo-page.js` (the
  `_elementor_data`, generated so the `__dynamic__` encoding has one implementation) →
  `build-blueprint.js` inlines both into `.wordpress-org/blueprints/blueprint.json`. Ships to SVN
  `assets/`, never in the plugin ZIP.

## Frozen contracts (never rename after first release)

Namespace `Arts\RepeaterTags` · tags `arts-repeater-{text,media,url,gallery,number,color,date}` ·
group `arts-repeater-tags` · control `arts-repeater-tags-row-picker` · ajax action
`arts_repeater_tags_get_rows` · filter `arts_repeater_tags/rows` ($rows, $field_name, $post_id —
$post_id may be an ACF context string like 'options'/'term_5'; $field_name may be a dot path like
'grp.rep' for group-hosted entries) · text domain
`repeater-tags-for-elementor-acf` · settings keys `repeater_field` (ACF field KEY — repeater or
flexible content, incl. group-hosted), `row_index` (0-based int; `-1` = last row, `-2` = current
loop row), `sub_field_{$repeater_key}` (depth-1 PATH within a row — plain sub-field name valid;
a nested repeater's path engages the child tier), `child_row_index_{$child_key}` (0-based int;
`-1` = last row; NO `-2`), `child_sub_field_{$child_key}` (path within the child row) · loop
repeat control `arts_repeater_tags_repeat_field` (on Pro loop widgets) · display condition
`arts-repeater-row-count` (keys `repeater_field`, `comparator`, `rows_number`).

## Research routing — do this BEFORE implementing against a third party

**Never answer third-party questions from training data** — these libraries move; trace the real
source. Route via the Task/Agent tool:

| Layer | Agent |
| --- | --- |
| Elementor PHP (controls, dynamic tags, ajax module, render) | `elementor-backend` |
| Elementor editor JS (control views, tag popover, `$e`, Backbone) | `elementor-frontend` |
| ACF Pro internals (field objects, enumeration, format_value) | `plugin-internals` |
| WordPress core (hooks, caps, meta, WP_Query, caching) | `wordpress-internals` |
| Arts Framework lift sources (ArtsQueryControl JS plumbing, elementor-types) | `arts-framework` |

- **context7 MCP** for library/tooling docs: Select2 (options/ajax/events API), esbuild, sass,
  TypeScript config — pull current docs, don't recall from memory.
- Built-in web search for anything without an agent or indexed source (e.g. Secure Custom Fields,
  wp.org guideline checks).

## Gotchas (hard-won — each traced to source)

- **`composer.json` is the source of truth for plugin meta AND the version.** Its `version`
  field is the single version source, stamped into the header `Version`, readme `Stable tag`,
  the `ARTS_REPEATER_TAGS_PLUGIN_VERSION` define, `package.json` version, and JS/CSS banners;
  its `plugin`/`wordpress` blocks drive the other header/readme fields (License, Requires at
  least/PHP, Tested up to, Text Domain, title — the `plugin` block wins over root-level keys).
  `build/meta.js` stamps on every `pnpm dev`/`pnpm build` and live via a `composer.json`
  watcher while dev runs. Edit `composer.json`, not the header or readme directly — a direct
  edit to those fields gets silently overwritten. Stamping replaces existing lines only (never
  inserts); `Requires Plugins` is the one header field it never touches (hand-maintained).
- **ACF name→field resolution is unreliable on posts with no saved value** (hidden `_{name}`
  reference meta) and ambiguous across groups. Always enumerate + store field KEYS; resolve
  key → field object → name server-side.
- **Formatted repeater rows are keyed by sub-field NAME; raw rows by KEY.** This plugin uses
  formatted/name exclusively. Don't mix.
- **Never `have_rows()`/`the_row()` in render callbacks** — global loop-stack mutation. Use
  `get_field($name, $post_id, true)` + direct indexing.
- **`get_the_ID()`, not `get_queried_object_id()`** — Elementor's `ajax_render_tags` uses
  `switch_to_post()` which doesn't touch the queried object.
- **PHP `get_type()` must EXACTLY match the JS `addControlView()` key** — a mismatch fails
  silently: Elementor falls back to its generic base control view, so the picker renders
  inert/unpopulated rather than as the Select2 row picker (no error, no empty render).
- **The tag popover is destroyed + rebuilt on every open** — control views refetch naturally;
  don't build stale-state workarounds.
- **Elementor STRIPS a control's UI-only args (`label`, `options`, `placeholder`, …) on any
  non-admin request** — `Controls_Stack::add_control()` unsets them when
  `Performance::should_optimize_controls()` is true (`! is_admin() && ! preview_mode &&
  ! REST_REQUEST`); the frontend only needs type/default/condition to resolve a value. Fine in
  production (the editor and admin-ajax are admin requests), but under PHPUnit it means a
  control's `options` simply don't exist. Asserting them needs `set_current_screen( 'edit-post' )`
  PLUS resetting two statics: `Performance::$is_frontend` (its verdict is cached) and the
  per-tag control stack (`controls_manager->delete_stack()`), which Elementor caches
  process-wide keyed by `'tag-' . get_name()` — so `register_controls()` runs ONCE PER CLASS
  PER PROCESS, not per instance. `TagCompatMapTest` does exactly this.
- **Elementor ajax auto-forwards only `editor_post_id`** (the edited document — for a TB template
  that's the template itself). The row picker explicitly sends the preview target post id, which
  lives in the document PAGE SETTINGS: `elementor.settings.page.model.attributes.preview_id`
  (live) / `config.document.settings.settings.preview_id` (boot snapshot) — `config.document`
  itself has NO `preview_id` key (verified empirically on a Pro Single template).
- **`acf_get_attachment()`'s `filesize` is best-effort, not trustworthy** (meta → filter → disk,
  falling back to 0 — e.g. offloaded media with no local file) — never read it.
- Elementor's V4 "atomic widgets" React editor is experimental — do NOT build against it.
- **Writing `_elementor_data` raw via wp-cli leaves `_elementor_element_cache` stale** — the
  frontend serves old markup until you `delete_post_meta(…, '_elementor_element_cache')` (editor
  saves invalidate it automatically; only raw meta writes hit this).
- **`dist/` staging is emptied IN PLACE by the build — never delete the dir itself.** wp-env
  bind-mounts it; removing a mounted dir's inode severs the mount (Linux AND macOS Docker
  Desktop, verified) until the env restarts. If tests suddenly see stale/absent plugin code
  after touching the build, `wp-env stop && wp-env start` once.
- **The staging build pins composer's `autoloader-suffix`.** `dump-autoload` otherwise REUSES
  the suffix from the copied repo `autoload_real.php`, and two identically-named
  `ComposerAutoloaderInit*` classes fatal when the repo and the built plugin load in one PHP
  process — which every PHPUnit run does.
- **WP test transactions don't reset in-process caches.** ACF's value store and the Plugin
  singleton's Rows memo persist across tests — the base `tests/Integration/TestCase.php` resets
  both; use fresh service instances in tests where memo isolation matters.
- **Never `do_action('admin_init')` under PHPUnit** — every fixture seeder hooks it, including
  the picsum.photos sideloads (network, flaky in CI). Seeders are inert in the test env by
  construction; keep it that way.
- **The Playground demo seeder must stay OUT of `dev/mu-plugins/`.** That dir is bind-mounted as
  mu-plugins by the wp-env harness, so a stray repeater there lands in `Schema::get_repeaters()`
  enumeration and breaks the Schema suite. It lives in `dev/blueprint/` and is delivered to
  Playground by a `writeFile` step instead.
- **Elementor stores a dynamic tag as `settings.__dynamic__.{control} = '[elementor-tag id="…"
  name="…" settings="…"]'`, where settings is `urlencode( wp_json_encode( $s, JSON_FORCE_OBJECT ) )`**
  (`Core\DynamicTags\Manager::tag_to_text()`) — `urlencode`, so spaces are `+`, NOT
  `rawurlencode`. `row_index`/`child_row_index_*` are JSON *strings* ("0", "-1") because Select2
  stores strings. The tag `id` is decorative: `create_tag()` resolves the class by `name` alone.
- **A raw `_elementor_data` write needs `wp_slash()`** — `update_metadata()` unslashes on the way
  in, so an unslashed JSON string comes back corrupted (verified by byte-diff round trip).
- **The free tier has no target for `arts-repeater-date`**: its only consumer is Pro's Countdown
  `due_date`, the sole `Controls_Manager::DATE_TIME` control in Elementor. Bind a
  `date_time_picker` sub-field through `arts-repeater-text` instead. Likewise term/user contexts
  need a Pro Theme Builder archive template to make `get_queried_object()` a WP_Term/WP_User —
  but the options-page context works free (`Context` checks `options_post_id` first).
- **Only `RepeaterText`/`RepeaterNumber` support `before`/`after`/`fallback`** — they extend
  `Tag`; the other five extend `Data_Tag`, whose `get_content()` has no affix handling. Any
  fail-closed demo has to go through text or number.
- **wp-env v11 deprecates the built-in tests-environment keys** (`testsPort`, `env`) in favor
  of a `--config` file split. The pinned version still supports them; revisit the split when
  bumping `@wordpress/env`.

## Conventions

- wp.org discipline: everything self-contained, GPL-compatible, prefixed; no external requests.
- One runtime composer dep, `arts/base`, Strauss-prefixed under `ArtsRepeaterTags\` into
  `vendor-prefixed/` (namespace collision safety — other Arts plugins on the same site ship their
  own differently-prefixed copy). `vendor/` ships autoloader-only in production; stubs/PHPStan
  never ship.
- Tests are logic-only — no UI/markup/rendering tests.
- Frozen contracts above are storage contracts; treat like DB schema.
