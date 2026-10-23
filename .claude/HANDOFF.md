# Handoff — Waukee Drupal prototype: Tailwind page designs

## Goal
Rebuild public pages of the Waukee CSD Drupal 11 prototype (DDEV, `waukee-proto.ddev.site`) to match the user's Tailwind mockups, using real waukeeschools.org wording. Next: make school detail pages dynamic for every school.

## Stack
- Drupal 11, theme `web/themes/custom/waukee_theme` (Olivero sub-theme), custom module `web/modules/custom/waukee_core`.
- Config/content is built by idempotent `scripts/phaseN_*.php` run via `ddev drush php:script scripts/<file>.php` (helpers pattern: `scripts/phase6_layout_builder.php`, `scripts/phase13a_pages.php`).
- Tailwind designs are page templates using the Tailwind Play CDN + mockup config, attached via library `waukee_theme/front`; `waukee_theme_css_alter()` (in `waukee_theme.theme`) strips Olivero + `css/waukee.css` on any page attaching that library.
- Demo logins: password `Demo#2026` (`admin.demo`, `comms.publisher`, `school.editor`, `teacher.staff`); see `docs/DEMO-GUIDE.md`.

## Archive
- T1–T6 · Phase 16 Layout Builder homepage (stat strip, photo/competency grids, pathway + resource pages, GD art), header utility bar, gold accent, Plus Jakarta Sans — all superseded visually by page--front, but script/config still exist (`scripts/phase16_homepage.php`).
- T7 · Homepage = exact mockup in `templates/layout/page--front.html.twig`, then real waukeeschools.org text (read from user screenshot; site returns 403 to fetches).
- T8 · Answered: Layout Builder roles; "Access denied" was /layout on a job_posting (LB only enabled on Basic page).
- T9 · /schools directory design · `templates/layout/page--schools.html.twig`, `js/schools-filter.js`, shared `tw-header.html.twig`/`tw-footer.html.twig`.
- T10 · Waukee High School page (superseded by T11's generic `/schools/{slug}` detail page).
- T11 · (landed since, by another contributor — not this session) Schools are now real Group entities (`field_tier`, `field_slug`, `field_mascot`, `field_badge`, `field_photo_url`, `field_full_profile`, etc., seeded by `scripts/phase17_schools_data.php`). `SchoolsController::detail()` + `waukee_core_school_by_slug()`/`waukee_core_school_data()`/`waukee_core_schools_by_tier()` in `waukee_core.module` drive `/schools/{slug}` (`page--schools--detail.html.twig`) and `/schools` (`schools` var in `waukee_theme_preprocess_page()`). News on school pages added in a later commit (`beb43fc`/`c34ae61`).
- T12 · /enrollment real wording + design · `templates/layout/page--enrollment.html.twig`, superseding the Layout Builder version in `scripts/phase22_enroll.php`/`phase26_enroll_photos.php` (kept for reference).

## Recent tasks
T13 · Header "Schools" nav dropdown rebuilt as a full mega-menu (search box, tier filter chips, 4-column directory, feeder-matrix banner, featured-school spotlight card, campus-essentials widget, enrollment notice strip), from a pasted Tailwind mockup, but fully data-driven off the T11 Group entities (not hardcoded) — every tier/count/card updates as schools are added/edited in Drupal. `waukee_core_schools_menu()` (waukee_core.module) rewritten to return `{tiers: {high, transition, middle, elementary, specialized} (each: label/items/count), total, featured}` (`featured` = first specialized-tier school with an image, e.g. WILC) instead of the old bare name/url list; consumed in `tw-header.html.twig`'s Schools dropdown. New `js/schools-mega-menu.js` (search + chip filtering, registered in the theme's `global` library since the header loads on every page) · done (not browser-verified by me)

## Current state
- Homepage, /schools and /schools/{slug} all render from the same Group-entity data (`field_tier` etc.) — no more hardcoded school lists in Twig.
- The header's Schools dropdown (`tw-header.html.twig`) now shows the same richer per-school fields (mascot, address, badge, grades) as /schools, via `wk_schools_menu` (set in `waukee_theme_preprocess_page()`), live on every page.

## Key files
- `web/modules/custom/waukee_core/waukee_core.module` — `waukee_core_school_by_slug()`, `waukee_core_school_data()`, `waukee_core_schools_by_tier()`, `waukee_core_schools_menu()` (header mega-menu data + `featured` pick).
- `web/modules/custom/waukee_core/src/Controller/SchoolsController.php` — `build()`/`detail()`/`detailTitle()`, all empty-markup (theme does the rendering).
- `web/themes/custom/waukee_theme/templates/layout/page--schools.html.twig` / `page--schools--detail.html.twig` — directory + per-school page, both entity-driven.
- `web/themes/custom/waukee_theme/templates/includes/tw-header.html.twig` — shared header; Schools dropdown is the mega-menu from T13.
- `web/themes/custom/waukee_theme/js/schools-mega-menu.js` — header mega-menu search/chip filtering (new, T13).
- `web/themes/custom/waukee_theme/waukee_theme.theme` — `waukee_theme_preprocess_page()` sets `wk_schools_menu` (every page), `schools`/`school` (route-specific).
- `web/themes/custom/waukee_theme/templates/layout/page--enrollment.html.twig` — /enrollment design (hardcoded, T12).
- `web/themes/custom/waukee_theme/tailwind/README.md` — rebuild command for `css/tw-chrome.css` (compiled Tailwind for non-front pages); required after any class change in `tw-header.html.twig`/`tw-footer.html.twig`.

## Decisions
- Keep mockup markup verbatim where practical; only swap `#` hrefs to real site paths, never invent external URLs or pages that don't exist (e.g. mega-menu's "Campus Essentials" links only to real anchors/fields: `/lunch-menus`, `/schools#feederMapSection`, `{school.url}#schedules-section`, `tel:{attendance_phone}` — dropped the mockup's "Health Office" link since no such page/field exists).
- Real wording from waukeeschools.org beats mockup copy; don't fabricate missing text (omit + flag instead).
- Page templates selected by path suggestion (page--schools…); internal path matters, not alias.
- Real photos of actual students aren't scraped/embedded on our own initiative (copyright + depicts-real-minors concerns); the mega-menu's featured-school photo reuses the existing WILC `field_photo_url` (an aida-public mockup image already seeded by phase17, not a new photo).

## Next step
None queued. If more pages/components get Tailwind mockups, follow the T13 pattern: drive content from the existing Group-entity data (`waukee_core_school_data()`/`waukee_core_schools_by_tier()`) rather than hardcoding school names again.

## Commands for you to run
- After T13: `ddev exec "cd web/themes/custom/waukee_theme && npx --yes tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/chrome.css -o css/tw-chrome.css --minify"` (rebuilds compiled chrome CSS for the new dropdown classes), then `ddev drush cache:rebuild`.
- Then visually check the Schools dropdown on both a front-page (`/`, Tailwind CDN) and an inner page (e.g. `/academics`, compiled CSS) to confirm it renders identically.

## Gotchas
- User's CLAUDE.md Strict tier: never run ddev/drush/build commands yourself — print them.
- Design pastes get truncated at 50k chars; rebuild missing sections from the screenshot (crop/upscale via PowerShell System.Drawing into the scratchpad) and flag guesses.
- Data conflict to raise: WHS principal is "Michael Copeland" on /schools but "Judi Luther-Roland" on the WHS page.
- Advisory schedule data for WHS was cut off in the paste — tab omitted until user provides it.
- Mockup images are lh3.googleusercontent.com "aida-public" URLs (may expire); map uses OpenStreetMap embed.
- Tailwind `hidden` class (not the `hidden` attribute) must be used for JS show/hide; flex utility classes would override the attribute.
- Uncommitted work: everything since commit 9a2e45e on branch `feat-ahmad` (plus pre-existing staged CSS/JS and .ddev/traefik certs) — nothing committed this session.
- WebFetch gets 403 on every waukeeschools.org URL (and its staging mirror); plain `curl` with a desktop-browser User-Agent gets 200 on the same URLs. Use `curl` (Bash tool) for future scraping of this domain, not WebFetch.
