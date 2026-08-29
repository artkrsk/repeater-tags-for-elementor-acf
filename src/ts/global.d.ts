import type { ElementorEditor, ElementorModules } from '@artemsemkin/elementor-types'

declare global {
  interface Window {
    /** Editor-context global (absent on the plain frontend). */
    elementor?: ElementorEditor
    elementorModules?: ElementorModules
    jQuery: JQueryStatic
  }
}
