# Arts Repeater Tags for Elementor and ACF

ACF repeater sub-fields as native Elementor dynamic tags. Pick the repeater, the row, the sub-field — the value lands in whatever control you attached the tag to. No widgets to place, no templates to maintain.

Seven tags, one per value shape: text, media, URL, gallery, number, color, date. Rows are addressed by index or "Last row". Nested groups, repeater-in-repeater and flexible content work one level deep, and repeaters on ACF options pages, terms and users work too.

Elementor Pro isn't required, but with it tags resolve per post in Theme Builder templates, "Repeat by ACF Repeater" renders one Loop Grid/Carousel card per repeater row, and a "row count" display condition shows or hides sections.

More on the [plugin page](https://artemsemkin.com/plugins/repeater-tags-for-elementor-acf).

## Requirements

WordPress 6.0+ · PHP 8.0+ · Elementor 3.5+ (free is enough) · ACF Pro or Secure Custom Fields

## Development

```bash
composer install
pnpm dev        # watch build, sync to a local dev site
pnpm build      # production ZIP in dist/
pnpm typecheck  # tsc --noEmit
pnpm phpstan    # level max
```

## License

GPL-3.0-or-later
