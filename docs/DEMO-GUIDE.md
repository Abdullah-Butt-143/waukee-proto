# Waukee Drupal Prototype: Demo Guide

Everything below is **sample content**. No real District, staff or student data.
Site: `https://waukee-proto.ddev.site` (runs on the presenter's laptop via DDEV; show it by sharing the screen).

> **Status of this guide (updated 23 Oct 2026):** every URL and every role permission in it was checked by script against the running site (public pages return 200, intranet pages return Access denied to the public, role links and permissions match). The visual look and the click-by-click form steps were **not** all clicked through in a browser, so **do one full rehearsal** before the demo (section 3 is written to be rehearsed). The editor, publisher and alert flows (Steps 4, 5 and 7) were run end to end in a real browser on 23 Oct 2026; the problems that run found are fixed by `scripts/phase25_workflow_fixes.php` (see section 0).

---

## 0. Before the demo (5 minutes)

1. Start the project (in WSL): `cd ~/waukee-proto && ddev start`
2. Open **two browser windows**: a normal one for logged-in roles and a **private/incognito** one for the public visitor, so you can show both side by side. (Only one login works per window; use a third window or log out to switch roles.)
3. Open Mailpit for the form emails: run `ddev mailpit`, or open `https://waukee-proto.ddev.site:8026`. (Port is **8026** on this machine; check `ddev describe` if it differs.)
4. Check the emergency alert is **off** (no lime banner at the top of `/`).
5. Hard-refresh (Ctrl+F5) the home page once so the newest CSS loads.
6. Optional full reset of sample content (safe to re-run, nothing is duplicated): `ddev drush php:script scripts/phase13a_pages.php` then `scripts/phase13b_content.php`.

> Dates: lunch menus run from 23 Oct to 29 Oct 2026 and events/news are dated relative to 22 Oct 2026. If you demo later, re-run the scripts above, or the lunch-menu and "upcoming events" lists will look empty.

### One-time fixes you must have applied (run once, then `ddev drush cr`)

`ddev drush php:script scripts/phase25_workflow_fixes.php` does two things the editor, publisher and alert flows depend on:
- **Reviewers can see drafts.** Without it, unpublished pages and news are missing from the **Content** and **Moderated content** lists for everyone except admins (the publisher can only open them by a direct link, and an editor cannot see their own draft in the list).
- **Publishers can edit the alert.** Without it, the **Site emergency alert** row has no **Edit** button for `comms.publisher`.

`phase23_about_hero.php` and `phase24_about_cards.php` put student photos on the About the District hero and its six cards.

### Rebuilding on a fresh clone

Pages, menu links and page-builder blocks live in the **database**, not in git. On a fresh clone run `ddev drush cim -y`, then these scripts in order (all re-runnable): `phase1` … `phase13b`, `phase14_design`, `phase14_images`, `phase15_schools`, `phase15b_menu`, `phase16_homepage`, `phase16_about`, `phase17_news`, **`phase17_schools_data`** (creates the school fields and the 22 schools; **needed or `/schools` crashes**), `phase20_form_links`, `phase21_school_pages`, `phase22_enroll`, `phase23_about_hero`, `phase24_about_cards`, **`phase25_workflow_fixes`** (all in `scripts/`, run with `ddev drush php:script scripts/<name>.php`). If page-builder pages ever render **empty**, run `scripts/phase19_repair_layouts.php` (rebuilds deleted blocks) and `scripts/phase16_about.php`.

---

## 1. The site at a glance (what visitors see)

**Header (every page, identical):** a purple top bar with the district name, audience links (**Students, Parents, Community, Staff**) and a search box; under it the main bar with the logo, navigation **About ▾ · Schools · Academics · Enroll · Resources · Extracurriculars**, a gold **Enroll Now** button and an account icon. It stays at the top while you scroll. When someone is logged in, their name and **Log out** also appear in the top bar.

**About menu:** hover or keyboard-focus **About** to open a two-column menu. Left, **About**: About the District, Mission and vision, Superintendent, Strategic plan, District facts, Board of Education. Right, **Quick links**: Calendar, Lunch menus, News, Enrollment, Jobs, Transportation, School closings, **Facility rental**, **Volunteer hours**, Search. (This replaces the old row of quick-link chips.)

**Mobile (below 1280 px wide):** the navigation collapses into a **Menu** button. Its panel holds Enroll Now, search, the main links, the About links and the Quick links.

**Footer (every page, identical):** Waukee CSD address and phone, Quick Links (Contact Us, Careers, Schools), District (School Board, Staff Directory, Staff Intranet), Connect With Us icons, and the legal row.

**Editor buttons (only when logged in).** Editors and publishers find **Add page**, **Add news** and **Content** here, since there is no admin toolbar on the public theme:
- On the designed pages (home, News, Students, Parents, Schools, Academics) they appear in the **purple strip at the very top**, in the row with Students · Parents · Community · Staff, just before your name and **Log out**.
- On all other pages a thin **dark strip** under the header says "Signed in as <name>" and lists the same buttons plus **Scheduled** and **Admin** where allowed (see section 2).

**Logging out / switching role:** click **Log out** in the purple strip at the top (or at the right end of the dark strip). Fallback: type `/user/logout` in the address bar. You can also use a private window per role.

**Emergency alert:** a lime banner that shows at the top of **every page including the home page, News, Schools and Academics** when switched on (Step 7).

---

## 2. Who to log in as

All demo passwords: **`Demo#2026`**. Log in at `/user/login` (or the account icon in the header).

| Login | Represents | Account bar buttons | What to show |
|---|---|---|---|
| *(not logged in)* | Public: **parent, student, prospective family, job seeker, community member** | none | Public site, forms, search, news, calendar. Intranet is hidden. |
| `ddev drush uli` link (user **admin**) | Technical site administrator | Add page · Add news · Content · Scheduled · Admin · intranet links | Full admin: structure, groups, workflow, views, modules. |
| `admin.demo` | District administrator | Add page · Add news · Content · Scheduled · Admin · intranet links | Content, users, the emergency alert. *Cannot open `/admin/group`; use the admin link for that.* |
| `comms.publisher` | District publisher | Add page · Add news · Content · **Scheduled** · Admin · Announcements · Documents · Staff directory · My account | Review, approve, schedule, publish; compare and restore revisions; form submissions. |
| `school.editor` | Editor for **Sample Elementary only** | **Add page · Add news** (both create inside Sample Elementary) · Content · Admin · intranet links · My account | Build a page, accessibility check, submit for review. Cannot touch the High School. |
| `teacher.staff` | Staff member | Announcements · Documents · Staff directory · My account | Intranet and permission-aware search. |

About a literal "student" login: there is none. Students and families use the public site without logging in. Adding a plain student account is a one-minute job if needed.

---

## 3. The demo, step by step (about 20 minutes)

Do these in order. Each step says **who is logged in** and **what to click**.

### Step 1: Public tour (3 min, logged out / private window)
1. Open `/`. Point out the header, hero, stat strip, "Why choose" section, school/program cards.
2. Hover **About** in the header to show the About menu and quick links. Click **Enroll Now** (or **Enroll**): `/enrollment` is a designed page with a four-step band, "what you will need", an FAQ accordion, helpful pages and a help box. Shrink the window below 1280 px wide: the **Menu** button appears. Click it.
3. Click **Schools** in the header → `/schools`: the district directory of **22 schools** grouped by level (high schools, 9th-grade campuses, middle, elementary, specialized centers) with a filter. Click **Explore** on **Waukee High School** → `/schools/waukee-high-school`: a full profile (principal's message, leadership team, bell schedule, news, family resources). Other schools (for example `/schools/brookview-elementary`) show a shorter profile built from the same data.
   Then open `/schools/sample-elementary`: the **school site** used for the editor demo (the school's own news **plus District items**, events, and a **Pages from Sample Elementary** list). It is reached by address or from the editor flow; it is not one of the 22 directory cards.
4. Click **About** → `/district`: hero, stat strip, "Our story", photo cards (Mission and vision, Superintendent, Strategic plan, District facts, Board of Education, Our schools), "What We Value".
5. Open `/news`: card grid of all news, with a **School** filter (try Sample High School → Apply). Click a story.
6. Open `/calendar` (pick a school in the filter), `/events`, `/lunch-menus`.

### Step 2: Search (1 min, logged out)
Open `/search`, search **handbook**: only the **2 public** documents. Keep this tab for step 8.

### Step 3: The two accessible forms (3 min, logged out)
Reach them from the header: hover **About** → **Quick links** → **Facility rental** / **Volunteer hours** (or open the **Menu** button on a phone), or from `/community` and `/students`.
1. **Facility rental** (`/community/facility-rental`): open **Room or facility** and change it. Extra questions appear: Auditorium → stage/lighting + seating (required); Cafeteria → kitchen + tables; Gymnasium → equipment; Classroom → projector; Athletic field → field lights.
2. Pick Auditorium, leave seating empty, fill the other required fields, submit: an error asks for seating. Pick Gymnasium instead: no seating question, submits fine.
3. Click Submit on an empty form to show the error list. Tab through the form with the keyboard to show focus and labels.
4. Submit a valid request. Open **Mailpit**: two emails (one to the facilities office, one confirmation to the submitter).
5. **Volunteer hours** (`/students/volunteer-hours`): choose activity **Other** → a required "describe the activity" box appears. Hours must be 0.25 to 24 (try 99).

### Step 4: Editor builds a page and sends it for review (5 min, log in as `school.editor`)

**Log in.** Click the small **person icon** at the right end of the main header (the white bar), type **school.editor** and the password **Demo#2026**, click **Log in**.

**Find the editing buttons.** Look at the **purple strip at the very top** of the page. After Students · Parents · Community · Staff you now see three new words: **Add page**, **Add news**, **Content**, then your name and **Log out**. (On some pages they are in a dark strip under the header instead.) A school editor only works inside their own school, Sample Elementary.

**Add a page**
1. Click **Add page** in the purple strip. A form titled "Add Group node (Basic page)" opens. It has a big white card on the left and a narrower white panel on the right.
2. In the left card: click the **Title** box and type a title such as "Field day information". **Page owner**, **Review date** and **Image** are optional. Click inside the large **Body** box and type a few sentences.
3. In the right panel you see **Last saved**, **Author**, a **Revision log message** box and a **Save as** drop-down. For an editor the drop-down only lists **Draft** and **In review**. Choose **In review** (this is "send for approval"). Choose **Draft** instead if you only want to keep working on it.
4. Click the purple **Save** button. It floats at the bottom of the screen, just to the left of the small round button, and stays there as you scroll. A green message says the page was created.
5. You now see your new page. To build it with the page builder, click the **Layout** tab (above the page title, next to View and Edit), then **Add section**, then **Add block**. The list offers only approved building blocks: Hero, Card grid, Audience pathways, Quick links, Accordion, Callout, Image + text, Video embed, the News/Events feeds and School pages list. Add a **Callout** and a **Hero**, fill them in and click **Save layout**.
6. **Accessibility check:** the round **Editoria11y** button at the bottom right of the page counts problems. Make a heading jump from H2 to H4, or use link text such as "click here": it shows a warning. Fix it and the warning disappears.
7. To send the page for review after building it: click the **Edit** tab, set **Save as** to **In review**, click the purple **Save**.

**Add news**
1. Click **Add news** in the purple strip.
2. Type a **Title**, a short **Summary** and the **Body**. Tick one box under **School** (required: choose Sample Elementary, or District (all schools) for district-wide news). Tick an **Audience** box if you like.
3. In the right panel set **Save as** to **In review**, then click the purple **Save**.

**Show the limits.** A school editor cannot reach another school's pages. Open the High School's form by typing `/group/3/content/create/group_node:page` in the address bar: the answer is **Access denied**. Also note the **Save as** list has no **Published** choice: editors can never publish.

### Step 5: Publisher approves, schedules and restores (4 min, log in as `comms.publisher`)

Use a second browser window, or click **Log out** in the purple strip first. Log in with **comms.publisher** / **Demo#2026**.

**Find what is waiting**
1. In the purple strip click **Content**. You land on the list of all content. Under the page title there are tabs: **Overview**, **Moderated content**, **Scheduled content**.
2. Click the **Moderated content** tab. It lists only items that are not published yet. The state column says **In review** next to the editor's page and news. (If the list says nothing is waiting but the editor did save something, the one-time fix in section 0 has not been run.)

**Review it**
3. Click the page's **title** to see it as the public would, or click **Edit** at the end of its row to open the form.

**Choose one of three actions** (all use the **Save as** drop-down in the right panel, then the purple **Save**):
- **Approve now:** set **Save as** to **Published** and click **Save**. It is public at once.
- **Send it back:** set **Save as** to **Draft** and click **Save**. Write the reason in the **Revision log message** box first.
- **Approve for later (schedule):** in the right panel click **Scheduling options** to open it, click **Publish on**, pick a date and time a few minutes ahead, and make sure the state next to it is **Published**. Leave **Save as** on **In review** and click **Save**. The **Scheduled content** tab (under Content) lists it. It goes live on the next cron run (`ddev drush cron` forces it).

**See the result**
4. In a private window open the page's address (shown under the page title after saving), or click **News** in the header. A published page opens without logging in. A published news story is the first card on the News page and appears in the Sample Elementary school feed. A published page is **not** added to any menu automatically.

**Revision history and restore** (on any page)
5. Click **Edit**, change a sentence, click **Save**. Click the **Revisions** tab (above the title). Tick two revisions in the list and click **Compare selected revisions** to see what changed. Then click **Revert** next to an older revision and confirm. The old text is live again, and the history gets a new line instead of losing anything.

### Step 6: Staff and the permission-aware search (3 min, `teacher.staff`)
1. Log in. The purple strip now shows **Announcements · Documents · Staff directory** before your name, and **Staff** among the audience links opens the intranet.
2. Click **Announcements** (use the **School** drop-down filter), then **Documents** (**Category** filter), then **Staff directory** (type "jordan" in the search box). For the app launcher open `/intranet/hr-resources` (the launcher is in the right sidebar).
3. Click the **search box** in the purple strip, type **handbook**, press Enter: now **4 results** (the public 2 plus the Staff Handbook and Employee Handbook) versus 2 for the public. This is the proof that search respects permissions.
4. `/documents/salary-schedule-sample` is **Access denied even for staff** (publishers and admins only).

### Step 7: Emergency alert (1 min, log in as `comms.publisher` or `admin.demo`)

The alert is one saved message called **Site emergency alert**. It is not a page, so it has no review step: switching it on shows it everywhere at once.

1. Log in. In the purple strip click **Content**, then click the **Blocks** tab under the page title.
2. Find the row **Site emergency alert** (type Emergency alert) and click **Edit** at the end of that row. (No Edit button for the publisher means the one-time fix in section 0 has not been run.)
3. Change the message text if you like. Tick the **Active** check box and click **Save**.
4. In a private window open the home page, **News**, **Schools** and any other page. A lime **Alert** banner is at the very top of every one.
5. To switch it off: repeat steps 1 and 2, untick **Active**, click **Save**. The banner disappears everywhere (reload with Ctrl+F5 if it lingers).

### Close
Tie each feature to the RFP exhibits: one platform, open source, no licence fee, permission-aware content, accessible forms, editorial workflow.

---

## 4. Where things appear (so editors know what to expect)

| Created with | Where it shows up | Notes |
|---|---|---|
| **Add page** by a school editor (group route) | Its own address (made from the title, e.g. `/field-day-information`) and the **Pages from <school>** list on that school's landing page | Owned by the school: only that school's editors can edit it. Not added to any menu. |
| **Add page** by a publisher/admin (`/node/add/page`) | Its own address only | Not owned by a school; does **not** appear in a school's list. Link to it from a Card grid or Callout. |
| **News** (any route) | `/news`, and the school's news feed according to its **School** field (**District (all schools)** appears on every school) | School is required, so nothing is left out of every school's feed. |
| **Event** | `/events`, `/calendar` and the school feeds, by School field | Times show in America/Chicago. |
| **Announcement / Document / Staff profile** | Intranet lists, the public lists if marked public, and search (permission-aware) | Audience term decides who sees it. |

Nothing is public until a publisher chooses **Published** in the **Save as** drop-down (editors can only choose **Draft** and **In review**).

---

## 5. Public page reference (no login)

| URL | What it is |
|---|---|
| `/` | Home (hand-built design, see limits). |
| `/schools` | School directory: 22 schools by level, with a filter. Data comes from the school groups, editable in Drupal. |
| `/schools/{slug}` (e.g. `/schools/waukee-high-school`, `/schools/northwest-high-school`, `/schools/brookview-elementary`) | One school's page, built from its group; unknown slugs give 404. |
| `/academics` | Academics page (hand-built design). |
| `/schools/sample-elementary` · `/schools/sample-high-school` | The two sample school "sites" used for the editor/group demo: own news/events + District items + pages list. Not in the 22-school directory. |
| `/district` | About the District (student photo in the purple hero and on the six "Explore the District" cards). Sub-pages: `/district/mission-vision`, `/district/superintendent`, `/district/strategic-plan`, `/district/facts`. |
| `/news` | News page in the homepage design: purple hero, a row of **School** buttons (All, District, Sample Elementary, Sample High School) to filter, three-column cards with image, date, school and summary, and **Previous / Next** buttons (9 per page). A card opens the article page (same design, with a **Back to all news** button). |
| `/enrollment` | Designed enrollment page (hero, 4 steps, documents needed, FAQ, helpful pages, help box). |
| `/user/login` · `/user/password` | Two-panel login and password-reset pages with the shared header and footer. |
| `/families` (Parents) · `/prospective-families` · `/students` · `/careers` · `/community` | Audience pages in the homepage design: purple hero with the page's intro text, an icon card for each sub-page (Enrollment, Transportation, ...), and a Calendar / News / Our Schools strip. Reached from **Students**, **Parents** and **Community** in the purple strip. |
| `/careers/jobs` | Job listings in the homepage design: purple hero with a **Benefits** button, one row per open position (title, location, closing date, short text, **View details** and **Apply** buttons) and a "Recently closed" list. Each job opens its own page (same design) with an **Apply now** box. Jobs come from the **Job posting** content type, so a new posting appears here by itself. |
| `/transportation` · `/families/nutrition` · `/families/health-services` · `/closings` | Family pages. |
| `/prospective-families/why-us` · `/prospective-families/schools` · `/prospective-families/enroll` | Prospective families. |
| `/students/activities` · `/students/resources` · **`/students/volunteer-hours`** | Students (volunteer form). |
| `/careers/benefits` | Careers: benefits (plain page). |
| **`/community/facility-rental`** · `/community/board-of-education` · `/community/news` | Community (facility rental form). |
| `/form/facility-rental` · `/form/volunteer-hours` | The same forms on their own page. |
| `/calendar` · `/events` · `/lunch-menus` | Calendar, event list, lunch menus (School filter on each). |
| `/search` | Search with Content type and School filters. Try `handbook`, `enrollment`, `transportation`. |
| `/staff-directory` | Public staff directory (public profiles only). |
| `/sitemap.xml` | About 57 public URLs; intranet content excluded (count grows with content). |

**News (10):** `/news/welcome-back-new-school-year` · `/news/sample-elementary-reading-night` · `/news/sample-high-school-robotics-team-advances` · `/news/board-education-approves-calendar` · `/news/lunch-program-adds-fresh-salad-bars` · `/news/sample-elementary-welcomes-new-reading-specialist` · `/news/sample-high-school-band-earns-top-ratings` · `/news/bus-route-changes-start-monday` · `/news/prospective-family-open-house-announced` · `/news/career-fair-connects-students-local-employers`

**Events (10):** `/events/back-school-night` · `/events/fall-concert` · `/events/elementary-science-fair` · `/events/board-education-meeting` · `/events/parent-teacher-conferences` · `/events/kindergarten-round` · `/events/homecoming-parade` · `/events/winter-choir-concert` · `/events/open-house-prospective-families` · `/events/spirit-week-kickoff`

**Job postings (3):** `/careers/jobs/teacher-sample-elementary` · `/careers/jobs/bus-driver` · `/careers/jobs/food-service-assistant`

**Public documents:** `/documents/student-and-family-handbook-sample` · `/documents/board-policy-manual-sample` · `/documents/enrollment-form-sample` · `/documents/school-calendar-sample` · `/documents/board-meeting-agenda-sample` · `/documents/family-handbook-sample`

**Public staff profiles:** `/staff/taylor-teacher` · `/staff/jordan-ellis` · `/staff/casey-morgan` · `/staff/riley-chen` · `/staff/sam-okafor` · `/staff/morgan-patel` · `/staff/avery-brooks` · `/staff/quinn-rivera` · `/staff/drew-hamilton` · `/staff/jamie-nguyen` · `/staff/cameron-lopez` · `/staff/skyler-bennett`

**SEO to mention** (view page source): each page has title, meta description, canonical link, Open Graph/Twitter tags; JSON-LD **Organization** on the home page, **Event** on events, **NewsArticle** on news.

**Things the public cannot see** (show in the private window; each gives **Access denied**): `/intranet/announcements` · `/intranet/documents` · `/intranet/staff-directory` · `/intranet/hr-resources` · `/documents/employee-handbook-sample` · `/documents/salary-schedule-sample` · `/staff/reese-carter`

---

## 6. Admin reference (`admin` via `ddev drush uli`; `admin.demo` for day-to-day)

| URL | Show | Who |
|---|---|---|
| `/admin/content` | All content, filter by type and status. | admin, admin.demo, publisher, editor |
| `/admin/content/moderated` | Content by workflow state (find "In review"). | publisher, admin |
| `/admin/content/scheduled` | Scheduled publishing queue. | publisher, admin |
| `/admin/content/block` | Reusable blocks, incl. **Site emergency alert**. | admin.demo, admin |
| `/admin/structure/types` | The 8 content types and fields. | admin |
| `/admin/structure/taxonomy` | School, Audience, Department, Document category. | admin |
| `/admin/people` · `/admin/people/roles` | Users and roles. | admin, admin.demo |
| `/admin/group` | Groups: **Sample Elementary**, **Sample High School**, **Staff Intranet** (Members tab). | **admin only** (admin.demo gets Access denied) |
| `/admin/config/workflow/workflows` → Editorial | Draft → In review → Published. | admin |
| `/admin/structure/block-content/types` | Page-builder components (Hero, Card grid, Stat strip, Photo card grid, Competency grid, Callout, Accordion, …). | admin |
| `/admin/structure/types/manage/page/display` | Layout Builder Restrictions: editors may use **only** the approved components. | admin |
| `/admin/structure/views` | News feed, Events/Calendar, Announcements, Document library, Staff directory, Lunch menus, Search. | admin |
| `/admin/structure/webform` → form → **Results** | Form submissions. | publisher, admin |
| `/admin/config/search/search-api` | Search index with the **Content access** processor (why staff results are hidden from the public). | admin |
| `/admin/structure/taxonomy/manage/audience/overview` | Permissions by Term (who may see staff-only / restricted content). | admin |
| `/admin/config/search/simple-sitemap` | Sitemap settings. | admin |
| `/admin/appearance` | Waukee Theme (default), Gin (admin theme). | admin |

---

## 7. Requirement checklist (what to claim)

| Requirement | Status | Where to show it |
|---|---|---|
| Editor builds a page with the page builder, sends for approval; publisher approves and schedules | **Done** | Steps 4 and 5 |
| Revision history and one-click restore (with compare) | **Done** | Step 5, point 4 |
| Search: staff see intranet results, public users do not | **Done** | Steps 2 and 6 |
| Accessible forms with conditional logic (facility rental, volunteer hours) | **Done** (markup checked; no screen-reader test yet) | Step 3 |
| Emergency alert on every page (including the designed pages) | **Done** | Step 7 |
| Audience pages for students, parents, community and careers/jobs in the homepage design | **Done** | Step 1; **Students**, **Parents**, **Community** in the purple strip; **Careers** under the About menu quick links (**Jobs**) |
| School sites with own news/events + district items | **Done** | Step 1, point 3 |
| AI assistant answering from site content | **Not built** (design agreed: reuse the permission-aware search + Claude; needs an API key) | Say "planned" |
| Google Workspace sign-in | **Not built** (local role-based demo users used instead) | Say "planned" |

---

## 8. Be upfront about these (known limits)

- **The home page is a hand-built template** (`page--front.html.twig`). Editing "Home" in the page builder does **not** change what visitors see on `/`, and its news and events sections are **fixed sample text**, not live feeds: a news item you publish shows on `/news` and the school pages, **not** on the home page. Home also shows placeholder numbers (13,000 learners, 19 facilities) that do not match the two sample schools. Say it is a design mock to be wired to live content.
- **Header and footer are shared files** (`templates/includes/tw-header.html.twig` and `tw-footer.html.twig`) used by every page, so a link or label change is made once. Home, Schools and Academics style them with the Tailwind web script; all other pages use a compiled stylesheet, `css/tw-chrome.css`. **After adding new Tailwind classes to those two files, rebuild it** (command in `web/themes/custom/waukee_theme/tailwind/README.md`), or the new classes will look unstyled on inner pages.
- The header navigation links (Academics, Resources, Extracurriculars) point at existing sample pages (Student resources, Families, Activities); they are not separate sections yet.
- The **22-school directory** shows only schools that have a Tier set. The two sample schools (Sample Elementary / Sample High School) have none, so they are not in the directory; the editor/group demo and the **Pages from <school>** list use those two. Principal names and numbers for the 22 schools come from the design files (one known conflict: Waukee High School's principal differs between the directory and the profile in the source designs).
- `/`, `/schools`, `/schools/{slug}` and `/academics` are hand-built templates with their own header and footer; the shared header on other pages is a separate copy, so labels and links can differ slightly.
- A page created with `/node/add/page` (not the school route) belongs to no school and is not listed on a school page.
- New pages are not added to any menu; use the **Pages from <school>** list, a Card grid, or an admin menu link.
- **Search filters** are drop-downs (Content type, School), not "facets" with counts (Facets module not installed).
- Sample documents are small text files, not real PDFs.
- The page-builder **Image + text** block stores a left/right setting; on the new design the home-style split section applies it, older layouts may not.
- **Staff editing their own profile** is not demo-ready: the approval workflow gives plain staff no save transition.
- Not hosted on public HTTPS (the plan calls for AWS + Cloudflare); runs on the presenter's laptop.
- **Accessibility:** contrast measured (text passes AA), focus outlines and keyboard-friendly forms built in, Editoria11y checker on for editors. **No** automated scan (axe/WAVE) and **no** screen-reader pass has been done yet.
- Mobile layouts and the logged-in account bar on narrow screens have had little visual checking; rehearse them.

---

## 9. If something looks wrong during the demo

| Problem | Fix |
|---|---|
| Page shows old content or errors | `ddev drush cr`, then Ctrl+F5 |
| Header or footer looks unstyled on inner pages | `css/tw-chrome.css` is stale: rebuild it (see `web/themes/custom/waukee_theme/tailwind/README.md`), then `ddev drush cr` |
| A page-builder page is **empty** (title but no content) | `ddev drush php:script scripts/phase19_repair_layouts.php`, then `scripts/phase16_about.php` |
| Can't log in | `ddev drush uli` (admin) or `ddev drush upwd teacher.staff 'Demo#2026'` |
| No account bar after login | Hard refresh (Ctrl+F5) and `ddev drush cr` |
| Lunch menu or "upcoming events" empty | Dates have passed: re-run `phase13a_pages.php`, `phase13b_content.php`, `phase10_calendar_feeds.php` |
| Editor's draft is missing from **Content**, or the publisher sees nothing under **Moderated content** | Run `ddev drush php:script scripts/phase25_workflow_fixes.php` once, then `ddev drush cr` |
| Publisher has no **Edit** button on the alert | Same fix (`phase25_workflow_fixes.php`) |
| No purple **Save** button on an Add/Edit form | `ddev drush cr`, then Ctrl+F5; the button floats at the bottom of the screen |
| Alert stuck on | `/admin/content/block` → **Site emergency alert** → untick Active |
| Scheduled page did not publish | `ddev drush cron` |
| Form emails missing | Mailpit is at `https://waukee-proto.ddev.site:8026` (`ddev mailpit`) |
| `/schools` shows "field_tier not found" | The school fields are missing: `ddev drush php:script scripts/phase17_schools_data.php`, then `ddev drush cr` |
| School page redirects to the home page | `ddev drush cget system.site page.front` should be `/home`; fix with `ddev drush cset system.site page.front /home -y` |
| Backup plan | Record a screen video of section 3 beforehand. |
