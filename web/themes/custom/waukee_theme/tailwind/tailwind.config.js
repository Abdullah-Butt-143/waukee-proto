// Builds css/tw-chrome.css: the Tailwind utilities used by the shared header
// and footer (templates/includes/tw-*.html.twig) for pages that do not load the
// Tailwind CDN. The design tokens come from js/tailwind-config.js (the same file
// the home page uses). Everything is scoped under .wk-chrome (see page.html.twig)
// and preflight is off, so nothing leaks into page content.
const fs = require('fs');
const path = require('path');
const holder = {};
new Function('tailwind', fs.readFileSync(path.join(__dirname, '../js/tailwind-config.js'), 'utf8'))(holder);

module.exports = {
  ...holder.config,
  content: [path.join(__dirname, '../templates/includes/*.twig')],
  important: '.wk-chrome',
  corePlugins: { preflight: false },
};
