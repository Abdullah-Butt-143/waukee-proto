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

## Recent tasks
T9 · /schools directory design · `templates/layout/page--schools.html.twig`, `js/schools-filter.js`, `templates/includes/tw-header.html.twig` + `tw-footer.html.twig` (shared header/footer, also used by page--front), libraries `front` + `schools` · done (not yet browser-verified by me)
T10 · Waukee High School page · route `waukee_core.school_waukee_high` (`/schools/waukee-high-school`) → `SchoolsController::designPage()` (empty markup); `templates/layout/page--schools--waukee-high-school.html.twig` (all content hardcoded); WHS "Explore School" link on /schools updated · done (not browser-verified)
T11 · User asked: clicking ANY school should show its own data dynamically (currently only WHS has a page) · NOT STARTED

## Current state
- Homepage, /schools and /schools/waukee-high-school render from hardcoded Twig (school data lives in page--schools.html.twig: 2 HS, 2 9th-grade, 4 middle, 11 elementary (Twig array), 3 specialized).
- All other "Explore" links on /schools are `#`.
- Existing data model: Group type `school` with fields field_grades, field_principal, field_address, field_phone, field_summary (`scripts/phase15_schools.php`); only 2 sample groups exist ("Sample Elementary", "Sample High School"). `SchoolsController::build()` still queries them but its output is ignored by page--schools.

## Key files
- `web/modules/custom/waukee_core/waukee_core.routing.yml` — /schools and /schools/waukee-high-school routes.
- `web/modules/custom/waukee_core/src/Controller/SchoolsController.php` — build() + designPage().
- `web/modules/custom/waukee_core/waukee_core.module` — hook_theme (`waukee_schools`).
- `web/themes/custom/waukee_theme/templates/layout/page--schools.html.twig` — current source of all school listing data.
- `web/themes/custom/waukee_theme/templates/layout/page--schools--waukee-high-school.html.twig` — detail-page design to generalize.
- `web/themes/custom/waukee_theme/waukee_theme.theme` — css_alter keyed on `waukee_theme/front` library.

## Decisions
- Keep mockup markup verbatim; only swap `#` hrefs to real site paths, never invent external URLs.
- Real wording from waukeeschools.org beats mockup copy; don't fabricate missing text (omit + flag instead, e.g. Advisory schedule tab omitted).
- Page templates selected by path suggestion (page--schools…); internal path matters, not alias.
- Recommended for T11: Drupal content (school groups) as the single data source, editable by admins — consistent with the user's earlier choice to "wire to real Drupal content".

## Next step
Implement T11:
1. New idempotent `scripts/phase17_schools_data.php`: add group fields (slug/tier, feeder, mascot, enrollment, image URL, attendance phone, bell hours, badges, description; optional long-form fields for principal message/staff/schedule) and create/update all 22 schools from the data in page--schools.html.twig (+ WHS detail values).
2. Replace `waukee_core.school_waukee_high` with `/schools/{slug}` in SchoolsController that loads the group by slug (404 if missing) and passes it to a new theme hook/template generalized from page--schools--waukee-high-school.html.twig; sections like principal letter, staff, bell schedule render only when data exists.
3. Render /schools cards from the same groups (Twig loops over controller-provided data, keep markup/classes, keep `.school-card` data-* attrs for schools-filter.js) so every Explore link points to `/schools/{slug}`.
4. Hand user the commands below.

## Commands for you to run
- After T11 lands: `ddev drush php:script scripts/phase17_schools_data.php` then `ddev drush cache:rebuild` (route changes need the rebuild).
- If not yet done for T9/T10: `ddev drush cache:rebuild`, then visually check /, /schools, /schools/waukee-high-school.

## Gotchas
- User's CLAUDE.md Strict tier: never run ddev/drush/build commands yourself — print them.
- Design pastes get truncated at 50k chars; rebuild missing sections from the screenshot (crop/upscale via PowerShell System.Drawing into the scratchpad) and flag guesses.
- Data conflict to raise: WHS principal is "Michael Copeland" on /schools but "Judi Luther-Roland" on the WHS page.
- Advisory schedule data for WHS was cut off in the paste — tab omitted until user provides it.
- Mockup images are lh3.googleusercontent.com "aida-public" URLs (may expire); map uses OpenStreetMap embed.
- Tailwind `hidden` class (not the `hidden` attribute) must be used for JS show/hide; flex utility classes would override the attribute.
- Uncommitted work: everything since commit 9a2e45e on branch `feat-ahmad` (plus pre-existing staged CSS/JS and .ddev/traefik certs) — nothing committed this session.
