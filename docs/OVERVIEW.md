# Waukee Prototype: Simple Overview

The site is a **prototype** for Waukee Community School District. All content is sample content. It is built with Drupal 11 and runs on a laptop with DDEV at `https://waukee-proto.ddev.site`.

---

## 1. Features

### What visitors can do
- **Home page:** a big welcome section, key numbers about the district, and cards that lead to schools and programs.
- **Schools:** a list of 22 schools grouped by level (high, 9th-grade, middle, elementary, special centers). Each school has its own page with principal, address, schedule and news.
- **Schools menu:** the "Schools" item in the header opens a big menu with search and filters. It updates by itself when schools are added or edited.
- **News:** news cards that can be filtered by school.
- **Calendar, events and lunch menus:** see what is happening and what is for lunch.
- **Enrollment:** a page that explains the steps to enroll and answers common questions.
- **About the District:** the story, mission, superintendent, strategic plan, facts and school board.
- **Search:** finds public pages and documents. Private documents never show up for the public.
- **Forms:** facility rental and student volunteer hours. They can be used with a keyboard and a screen reader. Each form sends emails.
- **Emergency alert:** a bright banner shown at the top of every page when switched on.
- **Works on phones:** on small screens the menu turns into a single **Menu** button.

### What staff can do
- **Staff intranet:** announcements, documents and a staff directory. Only logged-in staff can see these.
- **Add pages and news:** with simple forms.
- **Page builder (Layout Builder):** arrange blocks on a page by dragging them.
- **Review before publishing:** nothing goes live until someone with permission approves it.
- **Schedule:** choose a date for content to go live.
- **Revisions:** compare older versions of a page and bring one back.

---

## 2. Users

Log in at `/user/login`, or click the person icon at the right end of the header.

| Role | Username | Password | Buttons in the purple/dark strip | What they can do |
|---|---|---|---|---|
| Visitor (parent, student, prospective family, job seeker, community member) | none | none | none | View the public site, use the forms, search. The intranet is hidden. |
| Site admin (technical administrator; the only one who can use the page builder and `/admin/group`) | `admin` | `TempAdmin#2026` | Add page · Add news · Content · Scheduled · Admin · intranet links | Everything: structure, groups, workflow, views, modules, Layout Builder. If the password does not work, run `ddev drush uli` for a one-time login link. |
| District administrator | `admin.demo` | `Demo#2026` | Add page · Add news · Content · Scheduled · Admin · intranet links | Manage content and users, edit the emergency alert. Cannot open `/admin/group`. |
| District publisher | `comms.publisher` | `Demo#2026` | Add page · Add news · Content · Scheduled · Admin · Announcements · Documents · Staff directory · My account | Review, approve, schedule and publish; compare and restore revisions; read form submissions; edit the emergency alert. |
| School editor (Sample Elementary only) | `school.editor` | `Demo#2026` | Add page · Add news (both create inside Sample Elementary) · Content · intranet links · My account (no **Admin** button) | Write pages and news for their own school only. Set **Save as** to Draft or In review. Cannot publish, cannot open the Admin page and cannot touch the High School. |
| Staff member | `teacher.staff` | `Demo#2026` | Announcements · Documents · Staff directory · My account | Use the staff intranet and the permission-aware search (4 `handbook` results versus 2 for the public). Cannot open the salary schedule. |

There is no student login. Students and families use the public site without logging in.

These are demo credentials for the prototype only. Change them before any real use.

---

## 3. Flows

### Publishing flow (the main one)
1. A **school editor** logs in and clicks **Add page** or **Add news**.
2. They write the content and set **Save as** to **In review**. They cannot choose Published.
3. A **publisher** logs in and opens **Content**, then **Moderated content**.
4. The publisher reads it and either publishes it now or schedules a date.
5. The page goes live for everyone. If something is wrong, the publisher can restore an older version.

### Visitor flow
1. Open the home page.
2. Pick a path: **Schools**, **News**, **Enroll**, **Calendar** or **Search**.
3. Open a school to see its profile, news and schedules.
4. Fill in a form if needed. The site sends a confirmation email.

### Emergency alert flow
The alert is one saved message called **Site emergency alert**. It is a reusable block, not a page, so it has no review step. It is not in the toolbar, and it is not the yellow "accessibility alert" box at the bottom right of the screen (that box is the Editoria11y accessibility checker).

1. Log in as `comms.publisher` or `admin.demo`.
2. In the purple strip at the top, click **Content**. Under the page title, click the **Blocks** tab. (Shortcut: type `/admin/content/block` in the address bar.)
3. Find the row **Site emergency alert** (type *Emergency alert*) and click **Edit** at the end of that row.
4. Write or change the message, tick **Active**, and click **Save**.
5. The lime **Alert** banner shows at the top of every page right away. Check it in a private window.
6. To turn it off, repeat steps 2 and 3, untick **Active** and click **Save**. Press Ctrl+F5 if the banner lingers.
### School data flow
1. Each school is stored once in Drupal (name, level, address, principal, photo and more).
2. The home page, the Schools list, each school page and the header menu all read from that same data.
3. Edit a school once and every place updates.

---

## 4. Screen by screen: where to go and what to do

Log in at `/user/login`, or click the small **person icon** at the right end of the white header bar. Demo password: `Demo#2026`.

After login, editor buttons appear in the **purple strip at the very top** (next to Students · Parents · Community · Staff). On pages without that strip, a **dark strip** under the header shows "Signed in as ..." with the same buttons. Log out with **Log out** in either strip, or go to `/user/logout`.

### Public screens (no login)

| Screen | Go to | What to do |
|---|---|---|
| Home | `/` | Scroll the hero, stat strip and school/program cards. Click **Enroll Now** in the header to start enrollment. |
| Schools directory | Header **Schools**, or `/schools` | Use the filter to narrow by level. Click **Explore** on a card to open that school. |
| School page | `/schools/waukee-high-school` | Read the principal, address, bell schedule and the school's latest news. |
| Schools menu | Hover **Schools** in the header | Type in the search box or click a level chip to filter. Click a school to open it. |
| News | Header, or `/news` | Click a **School** button to filter. Click a card to read it, then **Back to all news**. Use **Previous / Next** for more. |
| Calendar, events, lunch | `/calendar`, `/events`, `/lunch-menus` | Pick a school in the filter to see its items. |
| Enrollment | `/enrollment` | Follow the four steps, check "what you will need", open the FAQ accordion. |
| About the District | Hover **About**, or `/district` | Click a photo card (Mission and vision, Superintendent, Strategic plan, District facts, Board). |
| Students / Parents / Community | Purple strip links | Click an icon card to open a sub-page. Use **In this section** on the right to move between its pages. |
| Careers | **About** menu, then **Jobs**, or `/careers/jobs` | Click **View details** or **Apply** on a posting. |
| Search | Search box in the purple strip, or `/search` | Type a word such as `handbook`, press Enter, narrow with the **Content type** and **School** filters. The public sees only public results. |
| Facility rental form | **About** menu, **Quick links**, **Facility rental** (`/community/facility-rental`) | Pick a room. Extra questions appear (an auditorium asks for seating). Fill in the required fields and click **Submit**. Two emails go out. |
| Volunteer hours form | `/students/volunteer-hours` | Choose an activity (**Other** asks you to describe it). Hours must be 0.25 to 24. Click **Submit**. |
| Phone view | Make the window narrower than 1280 px | The menu becomes one **Menu** button. Click it to open links and search. |

### School editor screens (`school.editor`, Sample Elementary only)

| Screen | Go to | What to do |
|---|---|---|
| Add a page | Purple strip, **Add page** | Type a **Title** and **Body**. In the right panel set **Save as** to **In review** (editors cannot pick Published). Click the purple **Save** at the bottom. |
| Build the page | After saving, click the **Layout** tab | Click **Add section**, then **Add block**, and choose from the approved blocks (Hero, Callout, Accordion and so on). Click **Save layout**. |
| Accessibility check | Round button at the bottom right | Click it to see problems such as skipped headings or "click here" links. Fix them and the count drops. |
| Add news | Purple strip, **Add news** | Fill **Title**, **Summary**, **Body**. Tick one **School** box (required). Set **Save as** to **In review** and click **Save**. |
| Send an existing page back for review | Open the page, click the **Edit** tab | Set **Save as** to **In review** and click **Save**. |

### Publisher screens (`comms.publisher`)

| Screen | Go to | What to do |
|---|---|---|
| Review queue | Purple strip, **Content**, then **Moderated content** tab | Items marked **In review** are waiting. Click a title to preview, or **Edit** to act. |
| Approve now | **Edit** on the item | Set **Save as** to **Published** and click **Save**. |
| Send back | **Edit** on the item | Type a reason in **Revision log message**, set **Save as** to **Draft**, click **Save**. |
| Schedule | **Edit** on the item | Open **Scheduling options**, set **Publish on** to a future time, leave **Save as** on **In review**, click **Save**. See it under the **Scheduled content** tab. It goes live on the next cron run (`ddev drush cron` forces it). |
| Revisions and restore | Open a page, click the **Revisions** tab | Tick two revisions and click **Compare selected revisions**. Click **Revert** beside an old one and confirm. |
| Emergency alert | See the **Emergency alert flow** above | **Content**, **Blocks** tab, **Site emergency alert**, **Edit**, tick **Active**, **Save**. |
| Form submissions | `/admin/structure/webform`, choose the form, click **Results** | Read what visitors submitted. |

### Staff screens (`teacher.staff`)

| Screen | Go to | What to do |
|---|---|---|
| Announcements | Purple strip, **Announcements** | Use the **School** drop-down to filter. |
| Documents | Purple strip, **Documents** | Use the **Category** filter. |
| Staff directory | Purple strip, **Staff directory** | Type a name such as `jordan` in the search box. |
| Search as staff | Search box in the purple strip | Search `handbook`. Staff see 4 results and the public sees 2. |

### Admin screens (`admin` via `ddev drush uli`, or `admin.demo`)

| Screen | Go to | What to do |
|---|---|---|
| All content | `/admin/content` | Filter by type and status. |
| Reusable blocks | `/admin/content/block` | Edit the **Site emergency alert**. |
| Users and roles | `/admin/people` | Add users or change roles. |
| Groups (schools, intranet) | `/admin/group` (`admin` only) | Open a group, then its **Members** tab. |
| Workflow | `/admin/config/workflow/workflows`, **Editorial** | See Draft, In review, Published and who may move between them. |
| Page builder components | `/admin/structure/block-content/types` | See the building blocks editors can use. |
| Views (lists and feeds) | `/admin/structure/views` | Edit News, Events, Announcements, Documents, Staff directory, Lunch menus, Search. |
| Search index | `/admin/config/search/search-api` | Check the **Content access** processor that hides private content from the public. |

For the full 20-minute click path, see [DEMO-GUIDE.md](DEMO-GUIDE.md).

---

## 5. How it is built (short version)

- **Drupal 11** runs the site.
- The **theme** is `web/themes/custom/waukee_theme`. It uses Tailwind designs.
- The custom module `web/modules/custom/waukee_core` holds the school logic.
- Content and settings are set up by scripts in `scripts/`, run with `ddev drush php:script scripts/<file>.php`.
- Mail from the forms can be read in **Mailpit** (`ddev mailpit`).
