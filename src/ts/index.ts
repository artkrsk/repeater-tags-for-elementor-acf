/**
 * RowPicker editor view: extends Elementor's native Select2 control view and populates
 * its options via Elementor's editor ajax (action registered through
 * elementor/ajax/register_actions — nonce/auth handled by Elementor core).
 * Plumbing pattern: artkrsk/query-control-for-elementor BaseQueryControlView (trimmed).
 */

interface IRowOption {
  id: number
  text: string
}

const CONTROL_TYPE = 'arts-repeater-tags-row-picker'
const AJAX_ACTION = 'arts_repeater_tags_get_rows'

function registerRowPicker(): void {
  const elementor = window.elementor

  if (!elementor) {
    return
  }

  const Select2View = elementor.modules.controls.Select2

  const RowPickerView = Select2View.extend({
    /**
     * onRender is the live lifecycle hook (parent inits select2 in applySavedValue there);
     * onReady is a hard-deprecated stub since Elementor 3.0 — don't build on it.
     * Sibling dependency: core `condition` only shows/hides — repopulation must be
     * done by the control itself (the ArtsQueryControl pattern).
     */
    onRender(this: any, ...args: unknown[]) {
      Select2View.prototype.onRender.apply(this, args)

      // A child-tier instance re-enumerates when the parent Sub-field selection OR the
      // parent row changes; the top tier keys off the repeater itself.
      const parentControl = this.getParentControl()
      const watched = parentControl ? [parentControl, 'row_index'] : ['repeater_field']

      for (const setting of watched) {
        this.listenTo(this.container.settings, `change:${setting}`, () => this.fetchRows())
      }

      this.fetchRows()
    },

    getRepeaterKey(this: any): string {
      return String(this.container.settings.get('repeater_field') ?? '')
    },

    /**
     * Child-tier instance args baked into the control by PHP (custom add_control() args
     * land on the control model as-is): parent_control = the parent Sub-field setting id,
     * child_path = the nested repeater's path within a row. Both '' = top-tier instance.
     */
    getParentControl(this: any): string {
      return String(this.model.get('parent_control') ?? '')
    },

    getChildPath(this: any): string {
      return String(this.model.get('child_path') ?? '')
    },

    /**
     * Elementor auto-forwards only editor_post_id (the edited document — for a Theme
     * Builder template that's the TEMPLATE post). The preview target ("Preview Dynamic
     * Content as") is a document PAGE SETTING (preview_id): read the live settings
     * model first (tracks mid-session changes), then the boot-config snapshot, then
     * fall back to the document's own post (regular pages have no preview_id at all).
     */
    getPreviewPostId(this: any): number {
      const pageSettings = window.elementor?.settings?.page?.model?.attributes ?? {}
      const doc = window.elementor?.config?.document ?? {}
      const configSettings = doc.settings?.settings ?? {}

      return (
        Number(pageSettings.preview_id) || Number(configSettings.preview_id) || Number(doc.id) || 0
      )
    },

    /**
     * Theme Builder templates also store preview_type ('{category}/{object}', e.g.
     * 'taxonomy/category', 'post_type_archive/book', 'single/post') — for non-singular
     * previews the server translates it + preview_id into the same context the frontend
     * ladder resolves (term_{id} / first archive post). '' on regular pages.
     */
    getPreviewType(this: any): string {
      const pageSettings = window.elementor?.settings?.page?.model?.attributes ?? {}
      const configSettings = window.elementor?.config?.document?.settings?.settings ?? {}

      return String(pageSettings.preview_type ?? configSettings.preview_type ?? '')
    },

    getRequestData(this: any): Record<string, unknown> {
      const data: Record<string, unknown> = {
        repeater_key: this.getRepeaterKey(),
        post_id: this.getPreviewPostId(),
        preview_type: this.getPreviewType(),
        // 'loop-item' unlocks the "Current loop row" sentinel server-side.
        document_type: String(window.elementor?.config?.document?.type ?? '')
      }

      if (this.getParentControl()) {
        data.child_path = this.getChildPath()
        // Raw row_index pass-through ('-1'/'-2' included) — the server maps sentinels
        // for enumeration the same way render does.
        data.parent_row_index = String(this.container.settings.get('row_index') ?? '0')
      }

      return data
    },

    fetchRows(this: any) {
      if (!this.getRepeaterKey()) {
        this.populateOptions([])
        return
      }

      // Inactive child instance (the parent Sub-field points elsewhere, so this control
      // is condition-hidden): skip the request instead of enumerating for nobody.
      const parentControl = this.getParentControl()

      if (
        parentControl &&
        String(this.container.settings.get(parentControl) ?? '') !== this.getChildPath()
      ) {
        this.populateOptions([])
        return
      }

      window.elementor?.ajax.addRequest(AJAX_ACTION, {
        // Elementor's ajax BUNDLES requests sharing a unique_id (default: the action
        // name) within its send debounce — the loser is overwritten in the pending
        // map and its Deferred silently never resolves. A per-control id keeps
        // multiple row pickers in one popover from colliding.
        unique_id: `${AJAX_ACTION}_${String(this.model.get('name') ?? '')}`,
        data: this.getRequestData(),
        success: (response: { options?: IRowOption[] }) => {
          this.populateOptions(response?.options ?? [])
        },
        error: () => {
          this.populateOptions([])
        }
      })
    },

    /**
     * No-data contract: when the current post/preview target has no rows for the
     * chosen repeater, the dropdown offers NOTHING to select — select2's native
     * noResults message explains why instead of listing phantom rows.
     */
    getSelect2DefaultOptions(this: any): Record<string, unknown> {
      return {
        ...Select2View.prototype.getSelect2DefaultOptions.apply(this),
        placeholder: 'Select row',
        language: {
          noResults: () =>
            this.getParentControl()
              ? 'No nested rows in the selected row'
              : 'No rows found on this post'
        }
      }
    },

    populateOptions(this: any, options: IRowOption[]) {
      const $select = this.$el.find('select')
      const saved = String(this.getControlValue() ?? '0')
      const savedNum = Number.parseInt(saved, 10) || 0
      const items = [...options]

      // Keep a stale saved index selectable (e.g. the preview post has fewer rows).
      if (options.length > 0 && !items.some((item: IRowOption) => String(item.id) === saved)) {
        items.push({
          id: savedNum,
          text: `Row ${savedNum + 1} (not on preview post)`
        })
      }

      $select.empty()

      // Blank first option = select2's single-select placeholder slot (per select2 docs);
      // selected when there is nothing real to select.
      $select.append(new Option('', '', items.length === 0, items.length === 0))

      for (const item of items) {
        $select.append(new Option(item.text, String(item.id), false, String(item.id) === saved))
      }

      // Namespaced trigger: re-render select2 WITHOUT firing Elementor's model write —
      // a plain 'change' here would overwrite a legitimately saved row index with ''
      // whenever the preview post happens to have no rows.
      $select.trigger('change.select2')
    }
  })

  elementor.addControlView(CONTROL_TYPE, RowPickerView)
}

if (window.elementor) {
  registerRowPicker()
} else {
  window.jQuery(window).on('elementor:init', registerRowPicker)
}

export {}
