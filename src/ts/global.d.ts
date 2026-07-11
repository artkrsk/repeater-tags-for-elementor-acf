import type { ElementorModules } from '@artemsemkin/elementor-types'

declare global {
  interface Window {
    /**
     * Editor-context global. elementor-types does type the editor surface (ElementorMain)
     * but ships no Window augmentation, and its ajax options type omits unique_id — so
     * the slice consumed here is typed pragmatically instead.
     */
    elementor?: {
      modules: { controls: Record<string, any> }
      ajax: {
        addRequest: (action: string, options: Record<string, unknown>) => void
      }
      addControlView: (type: string, view: unknown) => void
      config: Record<string, any>
      settings?: { page?: { model?: { attributes?: Record<string, any> } } }
    }
    elementorModules?: ElementorModules
    jQuery: JQueryStatic
  }
}
