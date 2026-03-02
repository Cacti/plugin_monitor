# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility First**: Honor plugin and CI version constraints before all style preferences.
2. **Repository Context Files**: Prioritize `.github/copilot/*` docs if they are added later.
3. **Agent Profiles as Secondary Context**: Reuse conventions from `.github/agents/*.md` when applicable.
4. **Pattern Matching Over Reinvention**: Mirror existing plugin patterns in the same file/flow.
5. **Architectural Consistency**: Keep the plugin procedural and Cacti-native.

## Verified Runtime and Compatibility

Use only capabilities compatible with observed project metadata:

- **Plugin metadata (`INFO`)**
  - `name = monitor`
  - `version = 2.8`
  - `compat = 1.2.15`
  - `requires = thold:1.2.1`
- **CI matrix (`.github/workflows/plugin-ci-workflow.yml`)**
  - PHP: `8.1`, `8.2`, `8.3`, `8.4`
  - OS: `ubuntu-latest`
  - MariaDB service: `10.6`
- **Observed technologies**
  - Procedural PHP plugin files
  - CSS theme overlays in `themes/*/monitor.css`
  - gettext localization (`locales/po/*.po`, `locales/LC_MESSAGES/*.mo`)
  - GitHub Actions integration checks

Do not introduce syntax or APIs that could fail under these versions.

## Architecture and File Responsibilities

This is a single Cacti plugin with procedural flows split by responsibility:

- `setup.php`: plugin lifecycle, hook registration, config arrays/settings, install/upgrade table management.
- `monitor.php`: web entrypoint/bootstrap, includes, session/request setup.
- `monitor_controller.php`: action flow, filter handling, page orchestration.
- `monitor_render.php`: dashboard/group rendering and view-specific output.
- `db_functions.php`: SQL filter/join helpers and status/device query utilities.
- `poller_monitor.php`: CLI poller entrypoint (`--help`, `--version`, `--debug`).
- `poller_functions.php`: poller helper logic (uptime checks, notifications, email payload building).

Avoid OO/framework refactors unless explicitly requested.

## Coding Patterns to Preserve

### Naming and Structure

- Use procedural functions with **lowerCamelCase** naming, matching current core files.
- Keep plugin hook callback names exactly synchronized between definitions and `api_plugin_register_hook()` registration strings.
- Keep top-level entrypoints lightweight; place reusable logic in helper files.

### Cacti Integration

- Prefer Cacti APIs already used in this plugin:
  - Config/state: `read_config_option()`, `set_config_option()`, `read_user_setting()`, `set_user_setting()`
  - Request helpers: `get_request_var()`, `get_filter_request_var()`, `get_nfilter_request_var()`, `set_request_var()`, `validateRequestVars()`
  - DB helpers: `db_fetch_assoc()`, `db_fetch_row_prepared()`, `db_fetch_cell_prepared()`, `db_execute_prepared()`
  - Plugin hooks/realms: `api_plugin_register_hook()`, `api_plugin_register_realm()`
- Use gettext calls with the `monitor` domain for user-facing strings: `__('Text', 'monitor')`.

### Data and Schema Safety

- Keep schema evolution in existing setup/upgrade flows (table creation/alter logic in setup lifecycle functions).
- Reuse existing table names and avoid introducing parallel schema variants.
- Prefer prepared DB calls when dynamic values are present.

## Quality Expectations

### Maintainability

- Keep changes localized to the appropriate responsibility file.
- Favor small helper extractions for complex branches (pattern used in `poller_functions.php`).
- Do not rename public/plugin callback functions unless all call sites and hook strings are updated.

### Security

- Continue current request-validation patterns before consuming request values.
- Escape HTML output with existing helpers (e.g., `html_escape()`).
- Avoid direct string interpolation for dynamic SQL parameters when prepared variants exist.

### Performance

- Avoid repeated expensive queries inside loops.
- Follow current query-shaping patterns (precompute lists/maps, then iterate).
- Preserve current poller stat logging behavior and timing model.

### Documentation

- Keep function docblocks descriptive where present, especially in `poller_functions.php`.
- Keep inline comments concise and only where intent is non-obvious.
- Do not add boilerplate comments for trivial statements.

## Validation and CI Alignment

Before finalizing substantial PHP changes:

1. Run PHP syntax checks (`php -l`) on modified plugin files.
2. Ensure compatibility with Cacti-driven lint/style checks used in CI (`lint`, `phpcsfixer` scripts run from Cacti workspace).
3. Preserve integration behavior expected by CI:
   - Plugin install/enable via Cacti CLI
   - Poller execution path and monitor stats logging

Do not add a new local unit-test framework unless requested.

## Scope Rules for Future Changes

- Prefer minimal, surgical updates over broad rewrites.
- Keep CSS/theme updates limited to existing theme file layout.
- Keep localization changes aligned to existing gettext workflow/files.
- If guidance conflicts, prefer behavior already validated in runtime entrypoints and CI workflow.
