Module Reviewed
Phase 1 — Server bootstrap & configuration (bootstrap/, app/Config/, routes/, artisan)
Key Responsibilities
Bootstrap Laravel app/container and bind kernels/exception handler.
Define env-driven app settings (env, debug, keys, limits, locales, providers).
Configure auth guards/providers, mail, storage, database (main + testing).
Declare web and API routes (core UX + exports/imports + admin/settings + API CRUD).
Provide artisan entrypoint.
Key Files and Their Roles
bootstrap/app.php — Creates BookStack\App\Application, binds HTTP/Console kernels and exception handler.
artisan — Standard Laravel CLI bootstrap: loads vendor autoload, app, runs console kernel.
app/Config/app.php — Env, debug, revision/recycle limits, upload size, script allowance, crawler/iframe/SSR hosts, timezones/locales, APP_URL, proxies; registers providers (BookStack providers + Socialite) and aliases; maintenance driver.
app/Config/auth.php — Auth method switch (standard|ldap|saml2|oidc), auto-initiate external, guards (session, ldap-session, async-external-session, api-token), providers (users, external), password reset config.
app/Config/database.php — DB connections: default mysql from env; parsing host:port (IPv4/IPv6). Test connection mysql_testing uses TEST_DATABASE_URL or defaults (bookstack-test, user/pass bookstack-test). Optional Redis via REDIS_SERVERS.
app/Config/filesystems.php — Default disk from STORAGE_TYPE; separate image/attachment disks; disks for local, secure local (attachments/images), s3; storage URL; symlink config.
app/Config/mail.php — Mail driver (MAIL_DRIVER), from address/name, mailers (smtp/sendmail/log/array/failover), TLS requirement logic based on port/encryption.
routes/web.php — Large set of authenticated routes covering shelves/books/chapters/pages (CRUD, exports, permissions, references), search, templates, favourites, watching, imports, recycle bin, audit, users/profile/account, images/attachments, comments, tags, AJAX draft/save, home, status/meta files, API docs. CSRF/session middleware usage implied by kernel (not reviewed here). Guests not shown in excerpt (rest of file).
routes/api.php — REST API under /api: CRUD for pages/chapters/books/shelves; exports; attachments, comments, audit log, recycle bin, roles, users; content permissions; search; system info; imports; image gallery. JSON docs at /api/docs.json.
Data Structures or Models
Configs rely on env vars: APP_ENV, APP_DEBUG, APP_URL, APP_KEY, DB_*, STORAGE_*, MAIL_*, AUTH_METHOD, REVISION_LIMIT, etc.
DB connections: mysql (runtime) and mysql_testing (tests). Redis optional via REDIS_SERVERS.
Execution or Lifecycle Flow
CLI/web entry calls bootstrap/app.php to build Application, binding HTTP/Console kernels and exception handler.
app/Config values inform service providers, guards, DB/storage/mail selection.
Routes: web routes grouped under auth middleware (core UX/features); API routes exposed under /api/* for programmatic access.
Important Edge Cases
Config files warn not to edit directly; use .env. Some options (table prefixes) “semi-supported”.
External auth methods can auto-initiate; guards differ per method.
Storage: only local, secure local, and s3 are supported.
Mail TLS forced if port 465 or encryption tls/ssl.
DB host parsing supports IPv6 with bracket/port syntax.
Observations & Notes
Config directory is app/Config/ (not config/).
Default APP_KEY in config is placeholder; .env overrides.
Web routes are extensive; remainder (guest/auth) not reviewed yet—can be covered in later phases if needed.

Module Reviewed
Phase 2 — Core domain & auth (Access, Users, Permissions, Entities core, Activity)
Key Responsibilities
Auth flows: login (MFA/email confirmation gating), registration (email restrictions, default roles), external/social/LDAP/OIDC/SAML scaffolding.
Permissions: role/entity joint-permission cache, per-entity checks, query restriction for visibility/drafts/deleted relations.
Content domain: Books/Shelves/Chapters/Pages models & repos (create, publish, update, revisions, permissions rebuild).
Activity: logging events to DB, notifications, webhooks, and theme hooks.
Key Files and Their Roles
app/Access/LoginService.php — Central login flow: blocks guest login, enforces email confirmation & MFA, records last attempted login, logs activity, dispatches theme event, logs in across guards for admins; handles reattempt, logout, and guard auto-initiation.
app/Access/RegistrationService.php — Enforces registration allowed + email domain restrictions; registers user (default role, optional social account), triggers activity + theme events, email confirmation if required; supports external auth auto-registration via findOrRegister.
app/Permissions/PermissionApplicator.php — Checks ownable permissions (all/own), integrates entity permissions via EntityPermissionEvaluator, restricts entity queries and page drafts, filters relation queries (including deleted entities), and applies visibility to polymorphic relations.
app/Permissions/JointPermissionBuilder.php — Builds the precomputed joint_permissions table per role/entity. Rebuilds all, per entity, or per role; walks books with children, shelves; deletes stale rows and inserts new via chunked operations.
app/Entities/Models/Page.php — Extends BookChild; visibility scope adds draft restrictions then parent visibility; relations for chapter, revisions (current/all), attachments; URL/permalink helpers; JSON display renders HTML via PageContent.
app/Entities/Repos/PageRepo.php — Page lifecycle: draft creation, publish (set priority, revision count, rebuild permissions, activity log, sort parent), update (content handling, revision storage, activity, parent sort), draft saving, content set, destruction (not shown). Uses PageContent, RevisionRepo, ParentChanger, TrashCan, ReferenceUpdater.
app/Activity/Tools/ActivityLogger.php — Persists activities (type/detail/loggable), sets session notifications, dispatches webhooks, runs notification handlers, theme hook; supports removing entity links on delete; logs failed login via configured channel/message.
app/Activity/Models/Activity.php — Activity model with morph to loggable Entity, user relation, jointPermissions relation, helpers for text, entity-type detection, similarity check.
Data Structures or Models
Permissions rely on joint_permissions cache keyed by entity/role with status/owner. Actions support all/own semantics and entity-level overrides.
Activity records: type, user_id, loggable_type/id, detail, timestamps, IP.
Pages: draft/template flags, editor type, related page data, revisions, attachments.
Execution or Lifecycle Flow
Login: credentials attempt → if success, MFA/email-confirm checks; may store session state and throw StoppedAuthenticationException; on login logs activity + theme event; admin users auto-auth across guards; logout clears session & token.
Registration: ensure allowed; verify email domain; pre-register theme hook; create user with default role; log activity/theme; optionally send confirmation email.
Permissions: on reads, queries restricted via restrictEntityQuery; pages further restrict drafts; relation queries filter deleted entities; joint permissions rebuilt on entity/role changes.
Pages: new draft created with defaults (respecting chapter/book default templates) then saved + permissions built; publish/update manage revisions, rebuild permissions, log activity, and sort parents.
Important Edge Cases
Guest credentials are explicitly blocked in login attempt.
Login throttled by MFA/email confirmation via stored session and reattempt flow.
Permissions: non-entity joint permissions bypass entity checks (restrictions/image/attachment/comment). Draft visibility limited to owner.
Joint-permission rebuild uses chunking; table truncation on full rebuild.
Mail/webhooks/notifications triggered on activity add; failures there could impact perf if many webhooks.

Module Reviewed
Phase 3 — Database layer (migrations, seeders, factories)
Key Responsibilities
Define relational schema for auth, content entities, permissions, activity, search, uploads, imports, notifications.
Provide seeders for demo/large datasets.
Provide factories for all major models to support tests/seeding.
Key Files and Their Roles
database/migrations/… (99 files) — Schema evolution from 2014–2025:
Core auth & users: users, password_resets, external auth fields, MFA tables, user invites.
Roles/permissions: roles, role_permissions, joint_permissions (rebuilt/cache), per-entity access controls, exports/import/import permission flags.
Content: books, chapters, pages, revisions (drafts/templates/markdown/support), shelves, slug history, entity refactor (2025 entities table + relation updates), default templates (books/chapters), descriptions HTML, sort rules.
Media & uploads: images, attachments, cover images, path length increases.
Activity & audit: activities table + simplifications, views table, favourites, watches, comments (with content refs/archived), audit log, webhooks, webhooks timeout fields.
Search & indexing: search index table, indexes on activity/pages/views, search weighting.
Settings & misc: settings, cache/sessions/jobs/failed_jobs, API auth, references table, instance_id setting, editor value fallback, guest secondary roles removal, recycle bin (deletions), soft deletes across entities.
database/seeders/DatabaseSeeder.php — Empty placeholder (no default data).
database/seeders/DummyContentSeeder.php — Creates editor/viewer users, assigns roles; generates shelves/books/chapters/pages (~5 books with children plus a large book with 200 pages & 50 chapters), attaches shelves, creates API token, rebuilds joint permissions, indexes search.
database/seeders/LargeContentSeeder.php — Creates editor and very large content set (large book with 50 chapters × 100 pages), rebuilds permissions for that book and indexes entities.
database/factories/* — Factories for users/roles, entities (book/shelf/chapter/page/revision/slug history/deletion), activity (comment/tag/favourite/watch/webhook), uploads (attachment/image), API token, MFA/social accounts, imports, sort rules.
Data Structures or Models
Joint permissions cache (joint_permissions) precomputes per-role visibility; rebuilt on changes.
Entities now centralized (2025 migrations) with relation updates and slug history.
Search index table used by SearchIndex service.
Execution or Lifecycle Flow
Migrations establish schema; later migrations add soft deletes, indexes, permission refinements, imports, references, sort rules, entity refactor.
Seeders are opt-in: DummyContentSeeder for demo-ish data; LargeContentSeeder for stress/scale.
Factories support tests/seeding; seeders often call JointPermissionBuilder and SearchIndex to refresh derived data.
Important Edge Cases
Table prefixes marked “semi-supported” elsewhere; entity refactor migrations (2025) consolidate entity tables—be cautious with older data.
DummyContentSeeder assigns extra permissions to editor (notifications, comments) and creates an API token with hashed secret.
Seeders rebuild permissions and search to keep caches consistent.
Observations & Notes
Default DatabaseSeeder does nothing; production installs stay clean unless a seeder is explicitly run.
For demo data in dev: php artisan db:seed --class=DummyContentSeeder (Docker: docker-compose exec app ...).
For load/stress data: LargeContentSeeder.

Module Reviewed
Phase 4 — Content & feature services (Exports/Imports, Uploads, Search, Settings, References, Theming)
Key Responsibilities
Import/export of Books/Chapters/Pages from zip exports with validation, storage, and activity logging.
File/media handling (attachments, gallery/drawio images) with permission-aware visibility and editor-friendly embeds.
Full-text search across entities with filters/tags/term weighting and permission-aware scoping.
Settings key-value store with per-user/session handling and typed defaults.
Reference maintenance: updating links when entities move/urls change.
Theming hooks: custom event listeners, theme functions loading, custom Artisan commands, social driver registration.
Key Files and Their Roles
app/Exports/Import.php — Model for stored imports (zip), holds metadata, size, creator; loggable; decodes metadata to zip-export models.
app/Exports/ImportRepo.php — Handles import lifecycle: visibility (self vs settings-manage), store uploaded zip (validate, decode, persist to storage, activity log), run import via ZipImportRunner with transaction & cleanup, delete import and stored file, log activities.
app/Uploads/Attachment.php — Ownable attachment model; permission-aware scopeVisible via PermissionApplicator on related page; URL helpers; editorContent with video embed detection; HTML/Markdown links.
app/Uploads/Image.php — Ownable image model; scopeVisible restricts to visible pages + gallery/drawio types; thumbnail generation via ImageResizer; page relation accessor.
app/Search/SearchRunner.php — Core search implementation: parses SearchOptions (terms, exacts, tags, filters), builds queries scoped to visible entities, scores terms via search_terms table (rarity weighting), joins subquery for scoring, supports book/chapter scoped search; hydrates entities for results.
app/Settings/SettingService.php — KV settings with local cache; user vs app scoping; guest uses session; defaults from config; handles string/array serialization, basic formatting (true/false strings, empty -> default).
app/References/ReferenceUpdater.php — Maintains intra-content links when entities move/urls change; updates pages’ HTML/Markdown and descriptions; writes a revision; supports context-based bulk reference changes; dedupes reference updates.
app/Theming/ThemeService.php — Theme event system (listen/dispatch), register custom Artisan commands, load theme_path('functions.php') with error handling, add social drivers via SocialDriverManager.
Data Structures or Models
Imports table stores zip path, type (book/chapter/page), metadata JSON, size, created_by.
Attachments/Images link to pages and joint_permissions for visibility.
Search uses search_terms table joined to entities for scoring; filters include tags/type/other options.
Settings stored in settings table; user settings prefixed user:<id>.
References tracked via references table; updater uses ReferenceFetcher + RevisionRepo.
Execution or Lifecycle Flow
Import: upload zip → validate → store file + metadata → activity log → run import (transaction) → delete import record/file → activity log.
Upload visibility: queries filtered via permission applicator to only pages user can view; attachments/images derived from page permissions.
Search: build options from string; apply term scoring & filters; restrict to visible entities; hydrate results.
Settings: read with cache & defaults; write updates cache and DB; guests use session.
Reference updates: find inbound references, update HTML/Markdown/permalink links, bump revision, save descriptions; handle bulk context remaps (e.g., moves/duplicates).
Theming: load theme functions, dispatch/handle theme events; custom commands registration.
Important Edge Cases
Import validation can throw ZipValidationException; ZipImportException triggers rollback and stored-file revert.
Attachment external links: if external and non-http path, returned as stored.
Search scoring: relies on search_terms prefix matching; entity visibility enforced via PermissionApplicator.
Settings: string “true”/“false” normalized; empty string becomes default; array settings filtered for empty items.
References: deduping by from_id/type; book updates also scan child pages/chapters; page link updates affect both HTML and Markdown.
Observations & Notes
Imports/exports are BookStack-native (zip with structured metadata), not generic ebook/doc imports.
Visibility consistently enforced via permission applicator across uploads and search.
Theme hooks allow extension without core edits (events/commands/social drivers).

Module Reviewed
Phase 5 — UI layer (views, JS/TS, theming hooks)
Key Responsibilities
Blade layouts and page templates for all UX flows (auth, shelves/books/chapters/pages, search, settings, exports/imports, comments, attachments).
Frontend bootstrap & component registry; event/http/translation services.
Rich editors (Lexical + TinyMCE), markdown editor, image/attachment managers, selectors, sortable lists, forms, search UI.
Theming hooks (custom styles/head/body, dark mode toggle) and versioned assets.
Key Files and Their Roles
resources/views/layouts/base.blade.php — Root layout: sets HTML lang/dir, dark-mode class, meta, icons, manifest, opensearch, links versioned CSS/JS, inserts custom styles/head, header/footer/notifications, back-to-top, loads dist/app.js.
resources/views/** — Blade templates for auth, shelves/books/chapters/pages (CRUD, sort, refs, permissions), exports/imports, search, comments, attachments, settings (admin), errors, home/dashboard, common partials (breadcrumbs, entity lists, tag manager, activity list, confirm dialogs), layouts variants (plain/export/tri).
resources/js/app.ts — Frontend entry: creates global $http, $events, $trans, registers all components via ComponentStore, makes baseUrl/importVersioned global.
resources/js/components/** — Many UI widgets: entity selectors, permissions tables, markdown & WYSIWYG editors, image/attachment managers, dropdowns/search, notifications, sortable lists, tri-layout, tabs, shortcuts, page/shelf/book sorting, imports UI, color pickers, etc.
resources/js/services/** — Utilities (http, events, translations, DOM, animations, clipboard, store, text, keyboard navigation, vdom, util).
resources/js/markdown/** — Markdown editor plumbing (CodeMirror integration, actions, shortcuts, rendering).
resources/js/wysiwyg/** — Lexical-based rich editor (nodes, UI framework, services, utils, tests) plus wrappers.
resources/js/wysiwyg-tinymce/** — TinyMCE configuration/plugins for legacy editor.
resources/js/code/** — Code editor/highlighter setups, languages, simple editor interface.
resources/views/help/readme etc provide editor help; help/tinymce/help/wysiwyg.
Asset build output expected at dist/app.js and dist/styles.css (linked via versioned_asset).
Data Structures or Models
JS component system maps DOM component="..." attrs to classes from components/index.
Global services: $http (XHR/fetch wrapper), $events (pub/sub), $trans (frontend translations).
Editors: Lexical nodes/commands, CodeMirror markdown adapter; TinyMCE plugins for legacy mode.
Execution or Lifecycle Flow
Blade renders server-side HTML; base.blade loads CSS then JS (module) with CSP nonce if present.
On load, app.ts registers components and initializes them across the DOM; components read options from data/option attributes.
Editor flows: markdown editor uses CodeMirror & markdown toolbars; WYSIWYG uses Lexical (or TinyMCE legacy) with plugins for uploads/diagrams; attachments & images use manager components with permission-aware APIs.
Theming: custom styles/head partials included; dark-mode class on html; theme events handled in backend (ThemeService) but hooks surface in layout.
Important Edge Cases
CSP nonce respected for dist/app.js if provided.
Dark mode is per-user setting (dark-mode-enabled) applied to html class & theme-color meta.
Permission-sensitive UI elements rely on backend checks; components often expect API endpoints to enforce visibility.
Both Lexical and TinyMCE exist; ensure correct editor selected by settings/user permissions.
Observations & Notes
Frontend is modular: services + component registry; heavy TypeScript for new editors, older JS for many widgets.
Views cover all major flows; common partials centralize breadcrumbs, activity, lists, and forms.
Build system outputs versioned assets; custom theme inserts allowed via partials.

Module Reviewed
Phase 6 — Tests & tooling
Key Responsibilities
End-to-end, API, auth, permissions, content, export/import, search, uploads, and command coverage via PHPUnit.
Provide fixtures/helpers for entities, roles, permissions, files, OIDC JWTs.
Configure test environment (DB, cache/session array drivers, mail/log stubs) and bootstrap for parallel testing with seeded dummy content.
Offer test data assets (images/attachments) for feature flows.
Centralize Laravel test base with helpers for auth context, settings, HTTP client mocking, env overrides.
Key Files and Their Roles
phpunit.xml — PHPUnit 11.5 config; loads vendor/autoload.php, sets APP_ENV=testing, DB connection mysql_testing, array cache/session, sync queue, disables external services, sets API rate limit, auth/mailer/log defaults; includes app/ for coverage.
tests/TestCase.php — Base test class using DatabaseTransactions; registers TestServiceProvider; helpers for acting as admin/editor/viewer, setting settings, mocking HTTP client, env overrides (with DB transaction reset), permission assertions.
tests/CreatesApplication.php — Boots Laravel app for tests.
tests/Helpers/TestServiceProvider.php — Hooks Laravel ParallelTesting to seed DummyContentSeeder when creating test DBs.
tests/Helpers/* — Providers for entities, files, permissions, roles, OIDC JWT helper; enable quick fixture creation.
tests/test-data/* — Images/encoded fixtures for uploads/export/import tests.
Suites by area (feature-level unless noted):
tests/Api/* — REST API auth/config/listing CRUD for books/chapters/pages/shelves/tags/attachments/imports/exports/search/roles/users/system.
tests/Auth/* — Login/registration/MFA/password reset/SSO (LDAP/OIDC/SAML2/social)/invites/group sync.
tests/Permissions/* (+ Scenarios) — Role/entity permission matrix, ownership changes, export permissions.
tests/Entity/* — Core content flows: drafts, revisions, templates, markdown→HTML, comments, slugs, copying, defaults.
tests/Exports/* — Export formats (HTML/Markdown/PDF/Text/ZIP) and import validator/runner.
tests/Activity/* — Audit logs, comments API, watch lists, webhooks.
tests/Search/* — Indexing, options, sibling search, permission-scoped results.
tests/Uploads/* — Attachments/images/gallery permissions and behaviors.
tests/Commands/* — Console commands: clear/reset/regenerate permissions/search/revisions/activity/views, sort rules, avatars, URLs, DB encoding upgrades, admin creation, user deletion.
Other feature checks: HomepageTest, PublicActionTest, SecurityHeaderTest, ThemeTest, LanguageTest, UrlTest, StatusTest, Meta/* (help/licensing/robots/manifest/OG/opensearch).
Data Structures or Models
Tests use Laravel models but rely on factories/providers rather than defining new structs here. Dummy content seeder populates books/chapters/pages for test DBs.
Execution or Lifecycle Flow
PHPUnit loads vendor/autoload.php → boots app via CreatesApplication → registers TestServiceProvider → sets env from phpunit.xml → uses DatabaseTransactions per test. Parallel testing seeds DB once via DummyContentSeeder hook. Helpers create users/entities/files as needed.
Important Edge Cases
Env overrides via runWithEnv preserve original values and rebootstrap DB for parallel runs.
Permission assertions differentiate JSON 403 vs. HTML responses.
External service use is disabled in testing by env defaults (DISABLE_EXTERNAL_SERVICES=true, ALLOW_UNTRUSTED_SERVER_FETCHING=false).
Reduced bcrypt rounds (4) for speed; sync queue driver to avoid background jobs during tests.
Observations & Notes
Tests are feature-heavy; few pure unit tests (tests/Unit/*). Expect slower suite; parallelization supported.
Test DB connection name: mysql_testing; ensure it’s configured in .env/docker for running suites.
Coverage includes exports/imports, SSO, theming, search, and CLI tooling—good safety net for changes across domains.



