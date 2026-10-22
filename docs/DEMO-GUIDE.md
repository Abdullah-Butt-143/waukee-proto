# Waukee Drupal Prototype: Demo Guide

Everything below is **sample content**. No real District, staff or student data.
Site: `https://waukee-proto.ddev.site` (runs on the presenter's laptop via DDEV; show it by sharing the screen).

---

## 0. Before the demo (5 minutes)

1. Start the project (in WSL): `cd ~/waukee-proto && ddev start`
2. Get a full-admin login link: `ddev drush uli` (open the printed link; it lands on "Edit account", just ignore that page).
3. Open a **private/incognito window** for the "public visitor" view, so you can show public vs logged-in side by side.
4. Open Mailpit in another tab to show form emails: `https://waukee-proto.ddev.site:8025`
5. Check the emergency alert is **off** (it should be). Check a page loads: `/`.
6. Optional reset of sample content: `ddev drush php:script scripts/phase13a_pages.php` and `scripts/phase13b_content.php` (safe to re-run; nothing is duplicated).

> **Look and feel (updated):** the theme was redesigned (sticky header with audience links and a "Staff login" button, full-width hero with school illustration, icon pathway cards, news cards with images, event date badges, footer with contact and links, mobile menu). All images are generated sample artwork. The site timezone is America/Chicago, so event times show as the District would see them (e.g. Back-to-school night: Sun Oct 25, 7:00 PM).

> Dates: lunch menus run from 23 Oct to 29 Oct 2026 and events/news are dated relative to 22 Oct 2026. If you demo later, re-run the two scripts above, or the lunch-menu and "upcoming events" lists will look empty.

---

## 1. Who to log in as

All demo passwords: **`Demo#2026`**. Log in at `/user/login`.

| Login | What they represent | What you will show with them |
|---|---|---|
| *(not logged in)* | Public visitor: **parent, student, prospective family, job seeker, community member** | Homepage, schools, calendar, news, lunch menus, forms, search. Intranet is hidden. |
| `ddev drush uli` link (user **admin**) | Technical site administrator | Full admin: structure, content types, roles, workflow, views, modules. |
| `admin.demo` | District administrator | Manage content, users, the emergency alert, all schools. |
| `comms.publisher` | District publisher | Review, approve, schedule, publish; compare and restore revisions; read form submissions. |
| `school.editor` | Editor for **Sample Elementary only** | Build a page from components, run the accessibility checker, submit for review. Cannot edit the High School. |
| `teacher.staff` | Staff member | Intranet: announcements, documents, staff directory, app launcher; search shows staff results. |

**About "student" login:** there is no separate student account in this prototype. Students, parents and the community use the **public site without logging in** (section 3). If your lead wants a literal student login, it is a one-minute add (a plain account with no intranet access): ask me.

---

## 2. Suggested 15-minute demo flow (matches the team build plan)

| # | Minutes | Do this | Logged in as |
|---|---|---|---|
| 1 | 2 | Homepage: hero, "I am a..." audience pathways, news + events, quick links bar. Shrink the window to show the mobile **Menu** button. | public |
| 2 | 2 | `/schools/sample-elementary` then `/schools/sample-high-school`: same design, own news/events **plus district items**. Open `/calendar` and pick a school. | public |
| 3 | 1 | `/search` search **handbook**: only public documents. | public |
| 4 | 4 | Editor flow: build a page from components, run the accessibility checker, submit for review. Show the High School page is off limits. | `school.editor` |
| 5 | 2 | Approval: review, compare revisions, publish or schedule; restore an old revision. | `comms.publisher` |
| 6 | 2 | Intranet: announcements, app launcher, staff directory, document library; repeat **handbook** search and see staff documents. | `teacher.staff` |
| 7 | 1 | Facility rental form: fields change with the room. Show the email in Mailpit. | public |
| 8 | 1 | Turn on the snow-day alert; show it on every page; turn it off. | `admin.demo` |
| - | - | Close: tie each feature to the RFP exhibits (one platform, open source, no licence fee). | |

---

## 3. PUBLIC pages (no login)

### Homepage and sections
| URL | What to point out |
|---|---|
| `/` | Hero + "Enroll today", audience pathways (Current family, Prospective family, Student, Community member, Job seeker, Staff member), news feed, events feed, quick links. Purple/lime theme, alert bar area at the very top. |
| `/families` | Section page: callout + card grid linking to sub-pages. |
| `/prospective-families` | Same pattern. |
| `/students` | Same pattern (this is the **student** view). |
| `/careers` | Same pattern. |
| `/community` | Same pattern. |

### Family pages
`/enrollment` · `/transportation` · `/families/nutrition` · `/families/health-services` · `/closings`

### Prospective families
`/prospective-families/why-us` · `/prospective-families/schools` · `/prospective-families/enroll`

### Students
`/students/activities` · `/students/resources` · **`/students/volunteer-hours`** (live accessible form)

### Careers
`/careers/benefits` · `/careers/jobs` (text page; the 3 job postings exist as content but are not yet listed on it)

### Community
**`/community/facility-rental`** (live accessible form, conditional fields) · `/community/board-of-education` · `/community/news` (news feed)

### Schools (one install, two "sites")
`/schools/sample-elementary` · `/schools/sample-high-school`
Each shows its **own** news and events plus **District** items.

### Calendar, events, menus, news
| URL | What it is |
|---|---|
| `/calendar` | Month calendar (FullCalendar) with a **School** filter. District events appear for every school. |
| `/events` | Events list with a **School** filter. |
| `/lunch-menus` | Lunch menus table with a School filter. |
| `/community/news` | Latest news. |
| `/search` | Site search: keyword box + **Content type** and **School** filters. Try `handbook`, `enrollment`, `transportation`. |
| `/staff-directory` | Public staff directory (12 public profiles; staff-only profiles are hidden). Search by name or job title; filter by department and school. |

### News articles (10)
`/news/welcome-back-new-school-year` · `/news/sample-elementary-reading-night` · `/news/sample-high-school-robotics-team-advances` · `/news/board-education-approves-calendar` · `/news/lunch-program-adds-fresh-salad-bars` · `/news/sample-elementary-welcomes-new-reading-specialist` · `/news/sample-high-school-band-earns-top-ratings` · `/news/bus-route-changes-start-monday` · `/news/prospective-family-open-house-announced` · `/news/career-fair-connects-students-local-employers`

### Events (10)
`/events/back-school-night` · `/events/fall-concert` · `/events/elementary-science-fair` · `/events/board-education-meeting` · `/events/parent-teacher-conferences` · `/events/kindergarten-round` · `/events/homecoming-parade` · `/events/winter-choir-concert` · `/events/open-house-prospective-families` · `/events/spirit-week-kickoff`

### Job postings (3)
`/careers/jobs/teacher-sample-elementary` · `/careers/jobs/bus-driver` · `/careers/jobs/food-service-assistant`

### Public documents (5 + 2 older samples)
`/documents/student-and-family-handbook-sample` · `/documents/board-policy-manual-sample` · `/documents/enrollment-form-sample` · `/documents/school-calendar-sample` · `/documents/board-meeting-agenda-sample` · `/documents/family-handbook-sample`

### Staff profiles (public ones)
`/staff/taylor-teacher` · `/staff/jordan-ellis` · `/staff/casey-morgan` · `/staff/riley-chen` · `/staff/sam-okafor` · `/staff/morgan-patel` · `/staff/avery-brooks` · `/staff/quinn-rivera` · `/staff/drew-hamilton` · `/staff/jamie-nguyen` · `/staff/cameron-lopez` · `/staff/skyler-bennett`

### SEO items to mention (view page source or `/sitemap.xml`)
- `/sitemap.xml`: 41 public URLs; intranet content is excluded.
- Each page has a title, meta description, canonical link, Open Graph/Twitter tags.
- Structured data (JSON-LD): **Organization** on the homepage, **Event** on event pages, **NewsArticle** on news pages.

### Things the public **cannot** see (good to show in the private window)
Open these while not logged in; each gives **Access denied**:
`/intranet/announcements` · `/intranet/documents` · `/intranet/staff-directory` · `/intranet/hr-resources` · `/documents/employee-handbook-sample` · `/documents/salary-schedule-sample` · `/staff/reese-carter`

---

## 4. ADMIN (`admin` via `ddev drush uli`, and `admin.demo`)

Use the **admin** link for the technical tour; use **admin.demo** to show a district administrator's day-to-day view.

| URL | Show |
|---|---|
| `/admin/content` | All content: pages, news, events, staff, documents, announcements, jobs, lunch menus; filter by type and status. |
| `/admin/structure/types` | The 8 content types and their fields (e.g. Event → Manage fields: smart date, location, school, audience). |
| `/admin/structure/taxonomy` | School, Audience, Department, Document category vocabularies. |
| `/admin/people` and `/admin/people/roles` | The users and roles (district admin, publisher, school editor, staff). |
| `/admin/group` | Groups: **Sample Elementary**, **Sample High School**, **Staff Intranet** (Members tab shows who belongs). |
| `/admin/config/workflow/workflows` → Editorial | Draft → In review → Published. |
| `/admin/structure/block-content/types` | The page-builder components: Hero, Card grid, Audience pathways, Quick links, Accordion, Callout, Image + text, Video embed, Emergency alert, App launcher. |
| `/admin/structure/types/manage/page/display` | Layout Builder Restrictions: editors may use **only** the approved components. |
| `/admin/structure/views` | News feed, Events/Calendar, Announcements, Document library, Staff directory, Lunch menus, Search. |
| `/admin/structure/webform` | The two forms; **Results** tab shows submissions. |
| `/admin/config/search/search-api` | Search index with the **Content access** processor (why staff results are hidden from the public). |
| `/admin/structure/taxonomy/manage/audience/overview` → edit **Staff** / **Restricted** | Permissions by Term (who may see staff-only / restricted content). |
| `/admin/config/search/simple-sitemap` | Sitemap settings. |
| `/admin/appearance` | Waukee Theme (default) and Gin (admin theme). |

### Emergency alert (show live)
1. Log in as `admin.demo` (or admin).
2. Go to `/admin/content/block` and edit **Site emergency alert**.
3. Tick **Active**, save.
4. Open `/`, `/families`, `/schools/sample-elementary`, `/calendar` in the other window: the lime banner "Alert: Snow day..." shows **at the top of every page**.
5. Untick **Active**, save: it disappears everywhere.

---

## 5. PUBLISHER (`comms.publisher`)

| Do this | How |
|---|---|
| See everything awaiting approval | `/admin/content` (filter Unpublished / look for "In review"). |
| Approve and publish | Open a draft, use the **Change to** / moderation control at the bottom of the edit form, choose **Publish**. |
| Schedule | On the edit form, open the **Scheduling options** area, set a publish date/time. |
| Compare revisions and restore | Open a page → **Revisions** tab → **Compare** two revisions; use **Revert** on an old one. (Needs 2+ saved revisions: save a page twice first.) |
| See form submissions | `/admin/structure/webform` → Facility rental request → **Results**. |

---

## 6. SCHOOL EDITOR (`school.editor`): Sample Elementary only

1. Log in. Open `/node/add/page` (or edit `/schools/sample-elementary`).
2. Add a page, save, then open the **Layout** tab: **Add block** shows only the approved components (Hero, Card grid, Audience pathways, Quick links, Accordion, Callout, Image + text, Video embed) and the News/Events feeds, **nothing else**.
3. **Accessibility checker:** the Editoria11y checker button appears at the bottom of the page. In a Callout, enter an H2 then jump to an H4 (skipped heading level), or use a vague link like "click here". The checker flags it. Fix it, and it clears.
4. In the save controls, the editor sees **Save as draft** and **Submit for review**, **no Publish**.
5. Try to edit the High School page (`/schools/sample-high-school`): **not allowed**.

---

## 7. STAFF (`teacher.staff`): the intranet

The **Announcements / Document library / Staff directory** links appear in the header only for staff.

| URL | Show |
|---|---|
| `/intranet/announcements` | 5 current announcements (a 6th, expired one is hidden). **School** filter. |
| `/intranet/documents` | Document library with **Category** filter: public **and staff** documents (restricted ones are hidden from staff). |
| `/intranet/staff-directory` | All 15 profiles including staff-only. Search "jordan"; filter by department/school. |
| `/intranet/hr-resources` and `/intranet/it-resources` | Intranet pages (members only). **App launcher** block in the sidebar. |
| `/search` → `handbook` | Now returns **4 results** (public 2 + staff handbooks 2) vs **2** for the public. This is the permission-aware search proof point. |
| `/staff/taylor-teacher` | Taylor Teacher's profile (owned by `teacher.staff`). *Do not demo editing it; see limits in section 10.* |
| `/documents/employee-handbook-sample` | Opens for staff, **Access denied** for the public. |
| `/documents/salary-schedule-sample` | Restricted: **Access denied** even for staff; only publishers/admins can open it. |

---

## 8. Forms

Both forms are public. Check them with the keyboard (Tab/Shift+Tab/Space/Enter) as well.

**Facility rental: `/community/facility-rental`**
Choose a **Room**. Extra questions appear: Auditorium → stage/lighting and seating (required); Cafeteria → kitchen and tables; Gym → equipment; Classroom → projector; Athletic field → lights. Submit: a confirmation message appears and **two emails** appear in Mailpit (one to the facilities office, one to the submitter).

**Volunteer hours: `/students/volunteer-hours`**
Choose activity **Other**: a "describe the activity" box appears and becomes required. Hours must be between 0.25 and 24.

---

## 9. Page inventory (complete list of what was built)

**26 Basic pages:** Home, Families, Prospective Families, Students, Careers, Community, Enrollment, Transportation, Nutrition, Health Services, School closings, Why Sample Community Schools, Our schools, Enroll in our district, Activities, Volunteer hours (form), Student resources, Benefits, Job listings, Facility rentals (form), Board of Education, Community news, Sample Elementary, Sample High School, HR resources (intranet), IT resources (intranet).
**Plus:** 10 news, 10 events, 15 staff profiles, 12 documents, 6 announcements, 10 lunch menus, 3 job postings.
**Listing/tool pages:** `/calendar`, `/events`, `/lunch-menus`, `/search`, `/staff-directory`, `/intranet/announcements`, `/intranet/documents`, `/intranet/staff-directory`, `/sitemap.xml`.
**Forms:** Facility rental request, Volunteer hours.

---

## 10. Be upfront about these (known limits)

- **Not built:** Google Workspace sign-in (planned as stretch; local role-based demo users are used instead) and the AI assistant.
- **Search filters** are drop-downs (Content type, School), not "facets" with counts. The Facets module is not installed.
- `/careers/jobs` is a text page; the three job postings exist but are not yet shown in a list there.
- Sample documents are small text files, not real PDFs.
- The page-builder **Image + text** block stores a left/right setting that the layout does not yet apply.
- **Staff editing their own profile** (a line in the team plan) is not demo-ready: the approval workflow gives plain staff no save transition. It needs a small decision (for example a draft-free "own profile" workflow) before it can be shown.
- This is a prototype with sample content; it is not yet hosted on a public HTTPS server (the team plan calls for AWS + Cloudflare).
- Accessibility: contrast ratios were measured (all text passes AA), focus outlines and keyboard-friendly forms are built in, and the Editoria11y checker is on for editors. A full automated scan (axe/WAVE) and a manual screen-reader pass have **not** been done yet.

---

## 11. If something looks wrong during the demo

| Problem | Fix |
|---|---|
| Page shows old content or errors | `ddev drush cr` |
| Can't log in | `ddev drush uli` (admin) or reset with `ddev drush upwd teacher.staff 'Demo#2026'` |
| Lunch menu or "upcoming events" empty | Dates have passed: re-run `ddev drush php:script scripts/phase13a_pages.php` and `scripts/phase13b_content.php`, plus `scripts/phase10_calendar_feeds.php` for lunch menus |
| Alert stuck on | `/admin/content/block` → edit **Site emergency alert** → untick Active |
| Backup plan | Record a screen video of the flow in section 2 beforehand (as the team plan recommends). |
