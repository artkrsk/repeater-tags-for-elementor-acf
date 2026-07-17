/**
 * The Live Preview demo page, as Elementor `_elementor_data`.
 *
 * Generated rather than hand-written: every dynamic-tag binding goes through tag(), so the
 * shortcode encoding has exactly one implementation to get right. Element ids are derived from
 * a counter (not random) so `pnpm blueprint:build` is byte-stable and CI can diff it.
 *
 * Free-tier only. Deliberately absent, because free Elementor offers no target for them:
 * arts-repeater-date (free Elementor has no DATE_TIME control at all; the only dynamic-enabled
 * one anywhere is Pro's Countdown `due_date`), and the term/user contexts (they need a Pro
 * Theme Builder archive template to make get_queried_object() a WP_Term/WP_User).
 */

const SHOWCASE = 'field_rtb_showcase'
const SPECS = 'field_rtb_si_specs'
const SECTIONS = 'field_rtb_sections'
const NOTICE = 'field_rtb_notice'

const INK = '#0f172a'
const PAPER = '#ffffff'
const MUTED = '#64748b'
const CHALK = '#f1f5f9'
const DIM = '#94a3b8'

/**
 * Fill + border for the dashed "this came from ACF" boxes, keyed by the section background
 * they sit on. The hero tells the reader what the boxes mean, so the page explains its own
 * convention rather than relying on a caption per box.
 */
const DEMO_TONE = {
  paper: { fill: '#f8fafc', line: '#cbd5e1' },
  chalk: { fill: '#ffffff', line: '#cbd5e1' },
  ink: { fill: '#16233c', line: '#3b4b66' }
}

let counter = 0

/** Deterministic 7-hex-char id, matching the shape Elementor generates. */
function uid() {
  counter += 1

  return (0x1000000 + counter * 0x9e37).toString(16).slice(-7)
}

/**
 * PHP's urlencode(), which is what Elementor's Manager::tag_to_text() uses: like
 * encodeURIComponent but space becomes `+` and !'()*~ are escaped too.
 */
function phpUrlencode(value) {
  return encodeURIComponent(value)
    .replace(/[!'()*~]/g, (c) => `%${c.charCodeAt(0).toString(16).toUpperCase()}`)
    .replace(/%20/g, '+')
}

/**
 * Builds the tag shortcode exactly as Elementor stores it:
 *   [elementor-tag id="…" name="…" settings="urlencode(json_encode($settings))"]
 * The id is decorative — Manager::create_tag() resolves the class by name alone.
 */
function tag(name, settings) {
  return `[elementor-tag id="${uid()}" name="${name}" settings="${phpUrlencode(JSON.stringify(settings))}"]`
}

/** A cell in the top-level repeater. row is a STRING ("0", "-1") — Select2 stores strings. */
function cell(row, subField, extra = {}) {
  return { repeater_field: SHOWCASE, row_index: row, [`sub_field_${SHOWCASE}`]: subField, ...extra }
}

/** A cell in the nested `specs` repeater — the child picker tier. */
function specCell(row, childRow, childSubField, extra = {}) {
  return {
    repeater_field: SHOWCASE,
    row_index: row,
    [`sub_field_${SHOWCASE}`]: 'specs',
    [`child_row_index_${SPECS}`]: childRow,
    [`child_sub_field_${SPECS}`]: childSubField,
    ...extra
  }
}

function widget(widgetType, settings) {
  return { id: uid(), elType: 'widget', settings, elements: [], widgetType }
}

function container(settings, elements, isInner = false) {
  return { id: uid(), elType: 'container', settings, elements, isInner }
}

function section({ background, elements }) {
  return container(
    {
      container_type: 'flex',
      content_width: 'boxed',
      background_background: 'classic',
      background_color: background,
      padding: { top: '80', right: '24', bottom: '80', left: '24', unit: 'px', isLinked: false },
      flex_direction: 'column',
      flex_gap: { size: 20, unit: 'px' }
    },
    elements
  )
}

function row(elements) {
  return container(
    {
      container_type: 'flex',
      content_width: 'full',
      flex_direction: 'row',
      flex_gap: { size: 24, unit: 'px' },
      flex_align_items: 'stretch'
    },
    elements,
    true
  )
}

/**
 * Wraps bound widgets in a dashed, rounded box so a reader can tell at a glance which parts of
 * the page are repeater data and which parts are prose about it.
 */
function demo(elements, tone = 'paper') {
  const { fill, line } = DEMO_TONE[tone]

  return container(
    {
      container_type: 'flex',
      content_width: 'full',
      flex_direction: 'column',
      flex_gap: { size: 18, unit: 'px' },
      background_background: 'classic',
      background_color: fill,
      border_border: 'dashed',
      border_width: { top: '2', right: '2', bottom: '2', left: '2', unit: 'px', isLinked: true },
      border_color: line,
      border_radius: {
        top: '14',
        right: '14',
        bottom: '14',
        left: '14',
        unit: 'px',
        isLinked: true
      },
      padding: { top: '28', right: '28', bottom: '28', left: '28', unit: 'px', isLinked: true }
    },
    elements,
    true
  )
}

function heading(text, { size = 'h2', color = INK, dynamic } = {}) {
  return widget('heading', {
    title: text,
    header_size: size,
    title_color: color,
    ...(dynamic ? { __dynamic__: dynamic } : {})
  })
}

function prose(html, color = MUTED) {
  return widget('text-editor', { editor: `<p>${html}</p>`, text_color: color })
}

/** Kicker: the small label that introduces each section. */
function kicker(text, color = DIM) {
  return widget('heading', { title: text, header_size: 'h6', title_color: color })
}

/** One product card, entirely fed by a single repeater row. */
function card(rowIndex) {
  return widget('image-box', {
    image: { url: '' },
    title_text: 'Product name',
    description_text: 'Product tagline',
    link: { url: '', is_external: '', nofollow: '' },
    image_space: { size: 16, unit: 'px' },
    title_color: INK,
    description_color: MUTED,
    __dynamic__: {
      image: tag('arts-repeater-media', cell(rowIndex, 'image')),
      title_text: tag('arts-repeater-text', cell(rowIndex, 'name')),
      description_text: tag('arts-repeater-text', cell(rowIndex, 'tagline')),
      link: tag('arts-repeater-url', cell(rowIndex, 'link'))
    }
  })
}

export const elements = [
  // ── Hero ────────────────────────────────────────────────────────────────────────
  section({
    background: INK,
    elements: [
      kicker('REPEATER TAGS FOR ELEMENTOR & ACF'),
      heading('Pull any repeater row into any widget', { size: 'h1', color: PAPER }),
      prose(
        'Every value on this page came out of an ACF repeater. The plugin adds dynamic tags that let a widget point at one specific row and one sub-field. Your data stays exactly where it is, stored how ACF already stores it.',
        DIM
      ),
      prose(
        'Anything sitting in a dashed box came out of the repeater. The rest is me explaining what you are looking at.',
        DIM
      ),
      prose(
        'The part worth looking at is in the editor, not here. Open any widget below and click its dynamic tag icon. That row picker is the whole plugin.',
        DIM
      )
    ]
  }),

  // ── 1. Row addressing ───────────────────────────────────────────────────────────
  section({
    background: PAPER,
    elements: [
      kicker('ROW ADDRESSING'),
      heading('Point at a row, not at a value'),
      prose(
        'These three cards all read the same repeater. The first one takes row 1, the second takes row 2, and so on. None of it is copied into the template, so when you reorder the rows in ACF the cards reorder with them. The line underneath asks for row -1, which just means the last row, however many you end up adding.'
      ),
      demo(
        [
          row([card('0'), card('1'), card('2')]),
          // -1 keeps tracking the last row no matter how many get added.
          heading('Newest in the catalog', {
            size: 'h5',
            color: MUTED,
            dynamic: {
              title: tag(
                'arts-repeater-text',
                cell('-1', 'name', { before: 'Newest in the catalog: ' })
              )
            }
          })
        ],
        'paper'
      )
    ]
  }),

  // ── 2. Value types ──────────────────────────────────────────────────────────────
  section({
    background: CHALK,
    elements: [
      kicker('ONE ROW, MANY TAGS'),
      heading('Text, color, a number and a gallery, all from row 1'),
      prose(
        'Each sub-field goes through whichever tag suits it. The heading below gets both its text and its color out of the same row. The counter reads the price field. The gallery reads one gallery cell that happens to hold several images.'
      ),
      demo(
        [
          row([
            heading('Product name', {
              size: 'h3',
              dynamic: {
                title: tag('arts-repeater-text', cell('0', 'name')),
                title_color: tag('arts-repeater-color', cell('0', 'accent'))
              }
            }),
            widget('counter', {
              starting_number: 0,
              ending_number: 100,
              prefix: '$',
              duration: 1200,
              title: 'Price',
              __dynamic__: {
                ending_number: tag('arts-repeater-number', cell('0', 'price'))
              }
            })
          ]),
          widget('image-gallery', {
            gallery_columns: '3',
            thumbnail_size: 'medium',
            __dynamic__: {
              wp_gallery: tag('arts-repeater-gallery', cell('0', 'gallery'))
            }
          })
        ],
        'chalk'
      )
    ]
  }),

  // ── 3. Options-page context ─────────────────────────────────────────────────────
  section({
    background: PAPER,
    elements: [
      kicker('BEYOND THE POST'),
      heading('Content from an Options Page'),
      prose(
        "This notice isn't stored on this page at all. It's under <em>Demo Site Settings</em> in the admin menu if you want to poke at it. Change it there and every page bound to it changes with it."
      ),
      demo(
        [
          heading('Site notice', {
            size: 'h4',
            dynamic: {
              title: tag('arts-repeater-text', {
                repeater_field: NOTICE,
                row_index: '0',
                [`sub_field_${NOTICE}`]: 'caption'
              })
            }
          }),
          widget('button', {
            text: 'See the details',
            size: 'sm',
            background_color: INK,
            button_text_color: PAPER,
            __dynamic__: {
              link: tag('arts-repeater-url', {
                repeater_field: NOTICE,
                row_index: '0',
                [`sub_field_${NOTICE}`]: 'link'
              })
            }
          })
        ],
        'paper'
      ),
      prose(
        "Category, author and user contexts work the same way, but they need an archive template to sit on, and archive templates come with Elementor Pro. The tag itself isn't fussy about where it lives."
      )
    ]
  }),

  // ── 4. Nested structures ────────────────────────────────────────────────────────
  section({
    background: CHALK,
    elements: [
      kicker('NESTED STRUCTURES'),
      heading('When the data gets nested'),
      prose(
        "A sub-field inside a group gets addressed with a dot path, like <em>meta.material</em>. Put a repeater inside a repeater and the picker grows a second tier for the child rows. Flexible content is read per layout, so you're only offered the sub-fields the row actually has."
      ),
      demo(
        [
          heading('Material', {
            size: 'h5',
            dynamic: {
              // A group sub-field is just a dot path within the row.
              title: tag('arts-repeater-text', cell('0', 'meta.material', { before: 'Material: ' }))
            }
          }),
          row([
            heading('First spec', {
              size: 'h5',
              dynamic: {
                title: tag(
                  'arts-repeater-text',
                  specCell('0', '0', 'spec_name', { before: 'First spec: ' })
                )
              }
            }),
            // -1 works at the child tier too (but -2 never does — that sentinel is top-tier only).
            heading('Last spec value', {
              size: 'h5',
              dynamic: {
                title: tag(
                  'arts-repeater-text',
                  specCell('0', '-1', 'spec_value', { before: 'Last spec: ' })
                )
              }
            })
          ]),
          // Row 3's specs are empty on purpose — the child tier fails closed like the top tier.
          heading('Empty child repeater', {
            size: 'h5',
            color: MUTED,
            dynamic: {
              title: tag(
                'arts-repeater-text',
                specCell('2', '0', 'spec_name', {
                  fallback:
                    'Row 3 never got a spec sheet, so this line falls back instead of erroring.'
                })
              )
            }
          }),
          row([
            heading('Layout heading', {
              size: 'h5',
              dynamic: {
                // Both layouts define `heading`, so it is offered once, not twice.
                title: tag('arts-repeater-text', {
                  repeater_field: SECTIONS,
                  row_index: '0',
                  [`sub_field_${SECTIONS}`]: 'heading'
                })
              }
            }),
            heading('Testimonial author', {
              size: 'h5',
              dynamic: {
                // Row 2 IS a testimonial, so `author` resolves.
                title: tag('arts-repeater-text', {
                  repeater_field: SECTIONS,
                  row_index: '1',
                  [`sub_field_${SECTIONS}`]: 'author',
                  before: '— '
                })
              }
            })
          ]),
          heading('Author on a hero row', {
            size: 'h5',
            color: MUTED,
            dynamic: {
              // Row 1 is a hero, which has no `author` — layout mismatch, fails closed.
              title: tag('arts-repeater-text', {
                repeater_field: SECTIONS,
                row_index: '0',
                [`sub_field_${SECTIONS}`]: 'author',
                fallback:
                  'Row 1 uses the hero layout, which has no author field. You get the fallback.'
              })
            }
          })
        ],
        'chalk'
      )
    ]
  }),

  // ── 5. Fail-closed ──────────────────────────────────────────────────────────────
  section({
    background: INK,
    elements: [
      kicker('SAFETY'),
      heading('When the row is missing', { color: PAPER }),
      prose(
        'Content drifts. Somebody deletes a row, or the template ships before the content does. The line below asks for row 6 of a repeater that only has three, and gets the fallback you typed in the editor rather than a PHP warning or a silent gap.',
        DIM
      ),
      demo(
        [
          heading('Row 6', {
            size: 'h5',
            color: DIM,
            dynamic: {
              title: tag(
                'arts-repeater-text',
                cell('5', 'name', { fallback: "There's no row 6. This is the fallback talking." })
              )
            }
          })
        ],
        'ink'
      ),
      // Outside the dashed box on purpose: a real link, not repeater data.
      widget('button', {
        text: 'Get the plugin',
        size: 'md',
        background_color: PAPER,
        button_text_color: INK,
        link: {
          url: 'https://wordpress.org/plugins/repeater-tags-for-elementor-acf/',
          is_external: 'on',
          nofollow: ''
        }
      })
    ]
  })
]
