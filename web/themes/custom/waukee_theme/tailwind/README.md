# Tailwind build for the shared header and footer

`css/tw-chrome.css` is generated; do not edit it by hand. It holds only the
Tailwind classes used in `templates/includes/tw-header.html.twig` and
`tw-footer.html.twig`, scoped to `.wk-chrome`, so inner pages can show the same
header/footer as the home page without loading the Tailwind CDN.

Rebuild after adding or changing Tailwind classes in those two files:

    ddev exec "cd web/themes/custom/waukee_theme && npx --yes tailwindcss@3.4.17 -c tailwind/tailwind.config.js -i tailwind/chrome.css -o css/tw-chrome.css --minify"

Design tokens (colors, spacing, fonts) come from `js/tailwind-config.js`.
