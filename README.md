# Thunderbird Learning Hub

A PHP 8+/MySQL knowledge base and training portal that blends a hierarchical post archive with onboarding workflows, quizzes, and real-time progress tracking. The application powers public knowledge sharing, role-based restricted content, and structured employee enablement from a single codebase.

## 🧰 Tech stack & runtime requirements

- **Language/runtime:** PHP 8.1+ with PDO/MySQLi enabled, HTTPS recommended.
- **Database:** MySQL 5.7+ (tested on Aurora MySQL and vanilla community builds).
- **Extensions/services:** GD or Imagick for thumbnails, Zip for exports, and cron/queue support if you run background training reminders.
- **3rd-party bundles:** TinyMCE and TCPDF live inside `vendor/` so no Composer step is required out of the box.
- **Recommended dev tools:** `devtools/` utilities plus the in-app bug reporter keep parity between environments.

## 🌟 Feature Highlights

### Knowledge base & collaboration
- Categories → subcategories → posts → replies with TinyMCE rich text editing and attachment support up to 20 MB per file (`posts/add_post.php`, `posts/add_reply.php`).
- Latest Updates/roadmap widget rendered globally from the footer to broadcast release notes and maintenance notices (`includes/footer.php`).
- File preview/download separation plus PDF export via TCPDF for any post (`vendor/tcpdf`, `posts/post.php`).
- Built-in bug reporting mini-app under `/bugs` for lightweight internal QA loops.

### Training automation
- Dedicated dashboard, quiz runner, and results pages (`training/training_dashboard.php`, `training/take_quiz.php`, `training/quiz_results.php`).
- `includes/training_helpers.php` centralizes role enforcement, assignment healing, training history, and quiz-to-content validation.
- Auto role transitions (training ⇄ user) based on open assignments, while preserving admin and super_admin privileges.
- Inline training banners on knowledge base posts encourage trainees to complete associated quizzes without leaving context.

### Access control & personalization
- Session-based PIN login that supports hashed or legacy plaintext pins, brute-force throttling, and user color badges (`login.php`, `includes/user_helpers.php`).
- Visibility flags (public/hidden/restricted/it_only) on categories & subcategories plus per-post privacy (public/private/shared) enforced in SQL queries (`index.php`, `posts/add_post.php`).
- User-specific pinned categories, restricted content filtering for training users, and assignment-scoped search counts.

### Search & discovery
- `includes/search_widget.php` injects the global search box, while `/search/search_working.php` and `/search/search_autocomplete.php` power highlighted results and instant suggestions.
- Debounced autocomplete JSON responses return lean payloads for responsive UX.

### Developer tooling
- `devtools/` contains diagnostics for pinning, assignment integrity, search tuning, TinyMCE sandboxing, and database/file inspection.
- `system/view_debug_log.php` surfaces the unified training/role logs produced by `log_debug()` so administrators can audit state changes quickly.

## 🔐 Role management & training lifecycle details

The application enforces a strict role progression so trainees only see what they are assigned:

- Supported roles live in `users.user_role` (`training`, `user`, `admin`, `super_admin`). Admins retain elevated access indefinitely.
- `includes/training_helpers.php::auto_manage_user_roles()` executes on every authenticated request. If a non-admin has active assignments they are pinned to the `training` role; when every assigned post is complete they graduate back to `user`. Session data mirrors DB updates immediately.
- Completion logic is derived exclusively from post-level rows in `training_course_content`. When a quiz attempt succeeds or a content item is marked finished, `training_progress` is updated and the helper reevaluates the parent course via `promote_user_if_training_complete()`.
- Visibility gates ensure training users only browse assigned categories/subcategories/posts. SQL queries use `COUNT(DISTINCT …)` and `allowed_users LIKE '%"{$user_id}"%'` filters to avoid leakage from shared joins.
- Quiz endpoints (`training/take_quiz.php`, `training/quiz_results.php`) validate `quiz_id` + `content_id/content_type`, block unassigned access, persist answers idempotently, and log attempts through `log_debug()` so support can trace activity.

## 🗂️ Repository layout

| Path | Purpose |
| --- | --- |
| `admin/` | Admin dashboards for training courses, analytics, and user management. |
| `assets/` | Core styling (`css/`) and shared imagery. |
| `bugs/` | Lightweight bug intake and triage UI. |
| `categories/` | Category/subcategory CRUD plus pinning endpoints. |
| `devtools/` | Developer-only diagnostics, debugging utilities, and templates. |
| `docs/` | Legacy README, latest update guide, and other reference docs. |
| `includes/` | Authentication, DB bootstrap, headers/footers, helpers, and widgets. |
| `posts/` | Post CRUD, replies, exports, and upload handlers. |
| `search/` | Full-text search endpoints and autocomplete APIs. |
| `system/` | Bootstrap + configuration loader + debug log viewer. |
| `training/` | Learner-facing dashboard, quiz runner, and result summary pages. |
| `vendor/` | Bundled third-party assets (TinyMCE and TCPDF). |
| `uploads/` | Runtime storage for images/files referenced by posts and replies. |

## 🧱 Data model overview

Thunderbird Learning Hub is fully database-driven. The most commonly touched tables include:

- `users`: profile, role, PIN, and status metadata for everyone who can log in.
- `categories`, `subcategories`: hierarchical taxonomy with per-record visibility and pinning metadata.
- `posts`, `replies`, `files`: main knowledge base content plus attachment metadata and ownership info.
- `training_courses`, `training_course_content`, `user_training_assignments`: define course shells, map course → post content, and track which users must complete which curricula.
- `training_progress`, `training_history`, `training_quizzes`, `user_quiz_attempts`: power quiz delivery, attempt storage, remediation, and auto-promotion.
- `user_pinned_categories`, `bugs`, and other auxiliary tables that support personalization and feedback loops.

> 📌 A full schema export lives in your production database; import it into your local MySQL instance before first run.

## 🚀 Getting started

1. **Clone & install dependencies**
   ```bash
   git clone https://github.com/<org>/Thunderbird-Learning-Hub.git
   cd Thunderbird-Learning-Hub
   ```
   Third-party libraries (TinyMCE + TCPDF) are already committed under `vendor/`. If you prefer Composer, install the same packages manually inside that folder.

2. **Create `config.php`**
   Copy `system/config.php` to `config.php` (ignored from git) or craft your own file that defines `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SESSION_TIMEOUT`, `MAX_FILE_SIZE`, `UPLOAD_PATH_*`, `IMAGE_EXTENSIONS`, and `SITE_NAME`. Production installs often keep credentials outside version control—just ensure `includes/db_connect.php` can require your `config.php` successfully.

3. **Provision the database**
   - Create a MySQL 5.7+ database.
   - Import your schema dump (see production backup) so every `training_*`, `posts`, `users`, etc. table exists.
   - Insert at least one admin or super_admin user with a numeric PIN (bcrypt hashed pins work too).

4. **Configure file permissions**
   Allow PHP to write uploaded assets:
   ```bash
   chmod 755 uploads uploads/images uploads/files
   ```

5. **Serve the app**
   - Apache/Nginx + PHP-FPM: point the virtual host to the repo root.
   - For local testing, `php -S localhost:8000` from the repo root works as well (ensure `.htaccess` rewrites are ported if needed).

6. **Log in**
   Visit `/login.php`, enter the PIN associated with your seeded admin user, and you’ll land on `index.php`.

## 🎓 Training workflow

1. **Assignment** – Admins assign `training_courses` to users via the admin UI (`admin/manage_training_courses.php`). Each course references one or more posts via `training_course_content`.
2. **Auto role management** – `includes/training_helpers.php::auto_manage_user_roles()` runs on every authenticated page load, ensuring users with active assignments hold the `training` role and graduates return to the `user` role. Admins/super_admins are never auto-demoted.
3. **Learning experience** – Training users see the hero progress bar injected from `includes/header.php`, plus the detailed dashboard at `/training/training_dashboard.php` which lists per-course content, estimated time, and quiz shortcuts.
4. **Quizzes & progress** – `/training/take_quiz.php` validates that the learner is assigned to the content, records answers idempotently, writes to `training_progress`, and logs attempts inside `user_quiz_attempts`.
5. **Completion & promotion** – When every post in a course is complete, `promote_user_if_training_complete()` updates `user_training_assignments` and flips the role to `user`, mirroring the change in `$_SESSION['user_role']` so navigation updates instantly.

## 🔎 Search & personalization details

- Search endpoints use `COUNT(DISTINCT …)` and guard against row duplication, mirroring the same SQL hardening used throughout the categories and training queries.
- Training users only see content explicitly assigned to them. The helper `filter_content_for_training_user()` trims subcategory/post listings, while search joins restrict counts to assigned posts.
- Pinned categories are stored per user in `user_pinned_categories`; super admins and admins retain pinning even when training filters are active, whereas trainees see a simplified layout.

## 🧰 Development workflow & debugging

- Enable verbose PHP logging during development—`system/config.php` already calls `error_reporting(E_ALL)`.
- Inspect role flips, quiz submissions, and assignment healing through `system/view_debug_log.php`, which reads `includes/training_helpers.php`’s `assignment_debug.log` output.
- Use `devtools/debug_assignment.php`, `devtools/debug_search.php`, and related helpers when reconciling database state with UI expectations.
- The footer’s Latest Updates widget pulls structured content from `docs/LATEST_UPDATES_GUIDE.md`; edit that guide before shipping new release notes.

## 🧾 Operational tips

- **Database hygiene:** run the schema diff from production or `devtools/inspect_files_and_db.php` to confirm new tables/columns exist before deploying feature branches.
- **Assignments & duplication control:** rely on helper methods rather than reimplementing SQL—`includes/training_helpers.php` centralizes the `COUNT(DISTINCT …)` protections so JOIN-heavy widgets do not inflate totals.
- **Error handling:** shared helpers include guardrails for missing params; mimic their redirect/error block behavior when adding new pages so UX stays consistent.
- **Session safety:** if you add AJAX handlers, call `session_start()` defensively and update `$_SESSION['user_role']` anytime you change the database role for the active user.

## 🧪 Testing & QA tips

While the project currently relies on manual testing, the following smoke tests are recommended before each deploy:

1. **Authentication** – Exercise PIN login/logout plus session timeout via `logout.php`.
2. **Content CRUD** – Create, edit, and delete a post with both image and document uploads, ensuring file records appear in the `files` table.
3. **Search** – Query for recent posts via `/search/search_working.php` and confirm highlight snippets + filters behave for admins and trainees.
4. **Training flow** – Assign a course, complete its posts/quiz, verify dashboard progress, and confirm auto-promotion back to `user`.
5. **Bug reporting** – Submit an issue through `/bugs/bug_report.php` and close it via `/bugs/update_bug_status.php` to ensure the loop still works.

## 📚 Additional documentation

- [`docs/README.md`](docs/README.md) – legacy setup instructions with more screenshots and user-facing explanations.
- [`docs/LATEST_UPDATES_GUIDE.md`](docs/LATEST_UPDATES_GUIDE.md) – template for updating the Latest Updates widget.
- `devtools/template_page.php` – starter page that already pulls in headers, training helpers, and footer scripts for rapid prototyping.

Need something that isn’t covered here? Check `devtools/inspect_files_and_db.php` for a catalog of runtime data, or open an issue via the in-app bug reporter so the admin team can triage it.
