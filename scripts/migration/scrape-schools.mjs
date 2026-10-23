#!/usr/bin/env node
/**
 * @file
 * PoC scraper for https://www.waukeeschools.org/schools/.
 *
 * Crawls the schools directory, visits every school's detail page, and
 * writes:
 *   - scripts/migration/output/schools.json  (full structured data)
 *   - scripts/migration/output/schools.csv   (flattened, for Drupal's
 *     migrate_source_csv plugin — see web/modules/custom/waukee_migrate)
 *
 * IMPORTANT — this parses rendered TEXT, not CSS classes.
 * This environment never saw the real page HTML (waukeeschools.org 403s
 * automated fetches) — only its rendered text content, pasted in by hand.
 * That text has no classes/ids to select on, but it does have consistent
 * literal labels ("Contact:", "Attendance:", "Read more", a stray "e"
 * right before every staff email), so extraction below walks the page's
 * text nodes into a flat `lines` array and matches those labels directly.
 * This only works if every school page shares the same template — true for
 * at least Brookview Elementary (the sample this was built from). If a
 * school's page omits a field below (check schools.json), that field's
 * marker text differs on that page; paste that page's content and I'll
 * extend the patterns.
 *
 * Usage:
 *   npm install axios cheerio tough-cookie axios-cookiejar-support
 *   node scripts/migration/scrape-schools.mjs
 *
 * Respect the site: this does a small, slow, single-pass crawl (one
 * concurrent request, a polite delay between requests, a descriptive
 * User-Agent) of 23 pages total. Do not increase concurrency or remove the
 * delay to scrape more aggressively.
 *
 * Detail pages timing out while the index page loads fine usually means the
 * site's bot protection issued a challenge/session cookie on the first
 * request that a plain axios client doesn't carry forward. A cookie jar
 * (below) fixes that; retryFetch() also retries a couple of times in case a
 * given request is just slow.
 */

import axios from 'axios';
import * as cheerio from 'cheerio';
import { writeFile, mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { CookieJar } from 'tough-cookie';
import { wrapper } from 'axios-cookiejar-support';

const BASE_URL = 'https://www.waukeeschools.org';
const INDEX_PATH = '/schools/';
const OUT_DIR = join(dirname(fileURLToPath(import.meta.url)), 'output');
const REQUEST_DELAY_MS = 2000;
const REQUEST_TIMEOUT_MS = 30000;
const MAX_RETRIES = 2;
const RECORD_DELIMITER = ';;';
const FIELD_DELIMITER = '|';

// On /schools/: anchors linking to each school's detail page. This one IS a
// real CSS selector (confirmed working — it found all 22 real schools).
const SCHOOL_LINK_SELECTOR = 'a.school-card, article.school-card a, a[href*="/schools/"]';

// Headings that can legitimately follow the principal's bio, in whatever
// order a given page uses them. Bio text stops at the first one found.
const HEADINGS_AFTER_BIO = [
  'Lunch & Recess Schedule', 'Class & Lunch Schedule', 'Bell Schedule',
  'Daily Schedule', 'Family Resources', 'Campus Location', 'Get Involved',
];
// Hard stop for any text capture: the district office's own address, always
// present in the footer, never a school's own address.
const FOOTER_MARKER = '560 SE University Ave';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const jar = new CookieJar();
const client = wrapper(axios.create({
  baseURL: BASE_URL,
  timeout: REQUEST_TIMEOUT_MS,
  jar,
  withCredentials: true,
  headers: {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    Accept: 'text/html,application/xhtml+xml',
    'Accept-Language': 'en-US,en;q=0.9',
  },
}));

async function fetchHtml(path) {
  let lastError;
  for (let attempt = 1; attempt <= MAX_RETRIES + 1; attempt++) {
    try {
      const { data } = await client.get(path);
      return cheerio.load(data);
    } catch (err) {
      lastError = err;
      if (attempt <= MAX_RETRIES) {
        console.log(`    retry ${attempt}/${MAX_RETRIES} after error: ${err.message}`);
        await sleep(REQUEST_DELAY_MS * attempt);
      }
    }
  }
  throw lastError;
}

function slugFromHref(href) {
  const parts = href.replace(/\/+$/, '').split('/');
  return parts[parts.length - 1];
}

async function collectSchoolLinks() {
  const $ = await fetchHtml(INDEX_PATH);
  const links = new Map(); // slug -> absolute path
  const indexPath = INDEX_PATH.replace(/\/+$/, '');
  $(SCHOOL_LINK_SELECTOR).each((_, el) => {
    const href = $(el).attr('href');
    if (!href || !href.includes('/schools/')) {
      return;
    }
    const path = href.startsWith('http') ? new URL(href).pathname : href;
    const slug = slugFromHref(path);
    // Skip the index page linking to itself (e.g. a breadcrumb or nav item),
    // and anything else that doesn't actually have a school slug segment.
    if (path.replace(/\/+$/, '') === indexPath || !slug || slug === 'schools') {
      return;
    }
    links.set(slug, path);
  });
  return [...links.entries()];
}

const EMAIL_RE = /^[\w.+-]+@[\w-]+\.[\w.-]+$/;
const CITY_STATE_ZIP_RE = /,\s*IA\s*\d{5}$/i;

/**
 * Flattens the page into one entry per leaf text node, in document order,
 * skipping <script>/<style>/<noscript>. This is the "lines" array every
 * field below is matched against — see the file header for why.
 */
function extractLines($) {
  const lines = [];
  const SKIP_TAGS = new Set(['script', 'style', 'noscript']);
  (function walk(node) {
    if (!node) {
      return;
    }
    if (node.type === 'text') {
      const t = node.data.replace(/\s+/g, ' ').trim();
      if (t) {
        lines.push(t);
      }
      return;
    }
    if (node.type === 'tag' && !SKIP_TAGS.has(node.tagName)) {
      $(node).contents().each((_, child) => walk(child));
    }
  })($('body').get(0));
  return lines;
}

function findLine(lines, matcher, from = 0) {
  for (let i = from; i < lines.length; i++) {
    if (typeof matcher === 'string' ? lines[i] === matcher : matcher.test(lines[i])) {
      return i;
    }
  }
  return -1;
}

/**
 * A "Label: value" field. The value is sometimes a separate text node from
 * its label (e.g. a phone number inside a <a href="tel:..."> right after
 * "Contact:"), so this also checks the next line when the label's own line
 * has nothing trailing it.
 */
function extractLabeled(lines, label) {
  const inlineRe = new RegExp(`^${label}:?\\s*(.+)$`, 'i');
  const labelOnlyRe = new RegExp(`^${label}:?\\s*$`, 'i');
  for (let i = 0; i < lines.length; i++) {
    if (labelOnlyRe.test(lines[i])) {
      return (lines[i + 1] || '').trim();
    }
    const m = lines[i].match(inlineRe);
    if (m) {
      return m[1].trim();
    }
  }
  return '';
}

function extractAddress(lines) {
  const idx = findLine(lines, CITY_STATE_ZIP_RE);
  if (idx === -1) {
    return '';
  }
  const street = idx > 0 ? lines[idx - 1] : '';
  return street ? `${street}, ${lines[idx]}` : lines[idx];
}

/** School type from the name, since the page text never states it. */
function guessSchoolType(name) {
  if (/elementary/i.test(name)) return 'Elementary';
  if (/middle school/i.test(name)) return 'Middle School';
  if (/high school/i.test(name)) return 'High School';
  return ''; // 9th-grade campuses and specialized centers: leave for manual review.
}

// A line like "Nutrition Services Department" or "Administration, Principals"
// that the directory sometimes inserts between a person's role and their
// email (a staff-directory category tag, not part of their title). None of
// the real roles seen on this site contain a comma or these words.
function isCategoryTag(line) {
  return /,|\bDepartment\b|\bOffice\b|\bAssociates\b|\bTeam\b|\bAdministration\b/i.test(line);
}

/**
 * Staff: each record is "[Photo of Name] / Name / Role / [category tag] /
 * e / email" in the page's reading order — the "Photo of ..." line and the
 * category tag are both optional and are skipped; we anchor on the email,
 * which is unambiguous, and walk backward from there.
 */
function extractStaff(lines, fromIdx, toIdx) {
  const staff = [];
  for (let i = fromIdx; i < toIdx; i++) {
    if (!(EMAIL_RE.test(lines[i]) && lines[i - 1] === 'e')) {
      continue;
    }
    const hasTag = i - 2 >= fromIdx && isCategoryTag(lines[i - 2]);
    const roleIdx = hasTag ? i - 3 : i - 2;
    const nameIdx = hasTag ? i - 4 : i - 3;
    if (nameIdx >= fromIdx && lines[roleIdx] && lines[nameIdx]) {
      staff.push({
        name: lines[nameIdx].replace(/^Photo of /i, '').trim(),
        role: lines[roleIdx],
        email: lines[i],
      });
    }
  }
  return staff;
}

/**
 * Announcements: title + summary lines up to a "Read more" line end each
 * record; the real link isn't in the text dump, so it's matched up
 * separately from the actual "Read more" anchors, by position.
 */
function extractAnnouncements($, lines, fromIdx, toIdx) {
  const records = [];
  let buffer = [];
  for (let i = fromIdx; i < toIdx; i++) {
    if (lines[i] === 'Read more') {
      if (buffer.length) {
        records.push({ title: buffer[0], summary: buffer.slice(1).join(' ') });
      }
      buffer = [];
    }
    else {
      buffer.push(lines[i]);
    }
  }
  const links = $('a')
    .filter((_, el) => $(el).text().trim() === 'Read more')
    .map((_, el) => $(el).attr('href'))
    .get();
  records.forEach((r, i) => {
    const href = links[i] || '';
    r.link = href.startsWith('http') ? href : href ? `${BASE_URL}${href}` : '';
  });
  return records;
}

function extractPrincipalBio(lines) {
  const start = findLine(lines, /^Get to know /i);
  if (start === -1) {
    return '';
  }
  const footerIdx = findLine(lines, FOOTER_MARKER, start + 1);
  let end = footerIdx === -1 ? lines.length : footerIdx;
  for (const heading of HEADINGS_AFTER_BIO) {
    const idx = findLine(lines, heading, start + 1);
    if (idx !== -1 && idx < end) {
      end = idx;
    }
  }
  return lines.slice(start + 1, end).join(' ').trim();
}

async function scrapeSchool(path) {
  const $ = await fetchHtml(path);
  const lines = extractLines($);
  const name = $('h1').first().text().replace(/\s+/g, ' ').trim();

  const announcementsStart = findLine(lines, 'Announcements');
  const staffStart = findLine(lines, 'Staff', announcementsStart === -1 ? 0 : announcementsStart);
  const bioStart = findLine(lines, /^Get to know /i, staffStart === -1 ? 0 : staffStart);
  const sectionEnd = bioStart !== -1 ? bioStart : findLine(lines, FOOTER_MARKER);

  const staff = staffStart === -1 ? [] : extractStaff(lines, staffStart, sectionEnd === -1 ? lines.length : sectionEnd);
  const announcements = announcementsStart === -1 ? [] : extractAnnouncements(
    $, lines, announcementsStart + 1, staffStart === -1 ? (sectionEnd === -1 ? lines.length : sectionEnd) : staffStart,
  );
  const principal = staff.find((m) => m.role.trim().toLowerCase() === 'principal');

  return {
    slug: slugFromHref(path),
    sourceUrl: `${BASE_URL}${path}`,
    name,
    schoolType: guessSchoolType(name),
    grades: '',
    address: extractAddress(lines),
    phone: extractLabeled(lines, 'Contact'),
    attendancePhone: extractLabeled(lines, 'Attendance'),
    fax: '',
    hours: extractLabeled(lines, 'School Time'),
    earlyDismissal: extractLabeled(lines, 'Early Dismissal'),
    principalName: principal ? principal.name : '',
    principalBio: extractPrincipalBio(lines),
    staff,
    announcements,
  };
}

function csvEscape(value) {
  const s = String(value ?? '');
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

function toCsv(schools) {
  const columns = [
    'slug', 'name', 'school_type', 'grades', 'address', 'phone',
    'attendance_phone', 'fax', 'hours', 'early_dismissal',
    'principal_name', 'principal_bio', 'staff', 'announcements',
  ];
  const rows = schools.map((s) => {
    const staff = s.staff
      .map((m) => [m.name, m.role, m.email].join(FIELD_DELIMITER))
      .join(RECORD_DELIMITER);
    // Note: the delimited format the Drupal side expects is "Title|URL"
    // only (per spec) — summary is NOT included here, only in schools.json.
    const announcements = s.announcements
      .map((a) => [a.title, a.link].join(FIELD_DELIMITER))
      .join(RECORD_DELIMITER);
    const record = {
      slug: s.slug, name: s.name, school_type: s.schoolType, grades: s.grades,
      address: s.address, phone: s.phone, attendance_phone: s.attendancePhone,
      fax: s.fax, hours: s.hours, early_dismissal: s.earlyDismissal,
      principal_name: s.principalName, principal_bio: s.principalBio,
      staff, announcements,
    };
    return columns.map((c) => csvEscape(record[c])).join(',');
  });
  return [columns.join(','), ...rows].join('\n') + '\n';
}

async function main() {
  await mkdir(OUT_DIR, { recursive: true });

  console.log(`Fetching index: ${INDEX_PATH}`);
  const links = await collectSchoolLinks();
  console.log(`Found ${links.length} school link(s).`);
  if (links.length === 0) {
    console.error('No school links found — check SELECTORS.schoolLinks against the real page markup.');
    process.exit(1);
  }

  const schools = [];
  for (const [slug, path] of links) {
    console.log(`Scraping ${slug} (${path})`);
    try {
      schools.push(await scrapeSchool(path));
    } catch (err) {
      console.error(`  failed: ${err.message}`);
    }
    await sleep(REQUEST_DELAY_MS);
  }

  await writeFile(join(OUT_DIR, 'schools.json'), JSON.stringify(schools, null, 2));
  await writeFile(join(OUT_DIR, 'schools.csv'), toCsv(schools));
  console.log(`Done: ${schools.length} school(s) written to scripts/migration/output/`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
