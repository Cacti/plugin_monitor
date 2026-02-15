# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: Respect versions and compatibility declared by this plugin and CI.
2. **Context Files**: If `.github/copilot/*` files are added later, prioritize them first.
3. **Codebase Patterns**: When no explicit guidance exists, follow established patterns in this repository.
4. **Architectural Consistency**: Preserve the current monolithic, procedural Cacti plugin architecture.
5. **Code Quality**: Prioritize maintainability, security, performance, and testability in ways already present in this codebase.

## Technology Version Detection

Before generating code, detect and honor exact versions from repository metadata:

- **Plugin metadata**: `INFO`
  - `version = 2.8`
  - `compat = 1.2.15` (Cacti compatibility)
  - `requires = thold:1.2.1`
- **CI runtime matrix**: `.github/workflows/plugin-ci-workflow.yml`
  - PHP: `8.1`, `8.2`, `8.3`, `8.4`
  - MariaDB service: `10.6`
- **Language/frameworks observed**:
  - PHP plugin code (`setup.php`, `monitor.php`, `poller_monitor.php`)
  - CSS themes (`monitor.css`, `themes/*/monitor.css`)
  - GitHub Actions workflow YAML
  - gettext localization (`locales/po/*.po`, `locales/LC_MESSAGES/*.mo`)

Do not introduce APIs or syntax incompatible with the supported Cacti/plugin environment and CI matrix.

## Context Files

If present in future, prioritize `.github/copilot` files in this order:

- `architecture.md`
- `tech-stack.md`
- `coding-standards.md`
- `folder-structure.md`
- `exemplars.md`

If these files do not exist, use repository patterns directly.

## Architecture and Boundaries (Observed)

This repository is a **single Cacti plugin** with procedural PHP entrypoints and Cacti hook integration.

- **Plugin lifecycle and hooks**: `setup.php`
  - Registration via `api_plugin_register_hook()` and `api_plugin_register_realm()`.
  - Upgrade/install logic via `plugin_monitor_install()`, `plugin_monitor_upgrade()`, `monitor_check_upgrade()`.
- **Web UI controller/rendering**: `monitor.php`
  - Request routing by `action` switch.
  - Rendering and UI state via Cacti helper functions.
- **CLI/poller processing**: `poller_monitor.php`
  - Argument parsing (`--help`, `--version`, `--force`, `--debug`).
  - Notification and uptime/reboot processing.
- **Presentation assets**: `monitor.css`, `themes/*/monitor.css`, `sounds/`, `images/`.
- **Localization assets**: gettext `.po/.mo` files under `locales/`.

Do not refactor this plugin into OO/framework patterns unless the existing code in this repository does so first.

## Codebase Scanning Instructions

For any new change:

1. Find similar logic in the same entrypoint type (`setup.php` for hooks/config, `monitor.php` for UI routing/rendering, `poller_monitor.php` for CLI/poller flows).
2. Match these patterns exactly:
   - Function naming: `monitor_*`, `plugin_monitor_*`
   - Procedural flow with top-level includes and switch routing
   - Cacti helper/database APIs (`db_fetch_*`, `db_execute*`, `read_config_option`, `set_config_option`)
   - Localization calls: `__('Text', 'monitor')`
3. Reuse existing request handling and sanitization helpers before adding any new input handling.
4. Prefer existing table names and schema migration style in `monitor_check_upgrade()` and related setup functions.
5. Avoid introducing new architectural abstractions not currently used.

## Code Quality Standards (Evidence-Based)

### Maintainability

- Keep procedural style and naming consistent with existing files.
- Keep related behavior grouped by responsibility (install/upgrade/hooks in `setup.php`; UI rendering in `monitor.php`; poller logic in `poller_monitor.php`).
- Prefer small helper functions as seen throughout the codebase.

### Security

- Follow current input-handling patterns:
  - `get_request_var()`, `get_nfilter_request_var()`, `get_filter_request_var()`, `set_request_var()`
  - `validate_request_vars()` where appropriate
- Escape output using existing helpers such as `html_escape()` for HTML contexts.
- Use prepared database calls where parameters are dynamic (`db_fetch_*_prepared`, `db_execute_prepared`) following existing usage.

### Performance

- Match existing data-access style: targeted SQL queries and batched operations.
- Preserve existing poller timing/stat collection behavior in `poller_monitor.php`.
- Avoid adding expensive repeated queries inside loops when existing code already provides reusable query patterns.

### Testability

- Keep logic in discrete functions so behavior can be linted, statically analyzed, and integration-tested as in CI.
- Preserve CLI flags and deterministic output patterns used by workflow checks.

## Documentation Requirements

Documentation level in this repository is **Standard**:

- File-level header blocks are consistently present in PHP and workflow files.
- Inline comments are concise and purpose-driven.
- Follow existing style: do not over-document trivial lines.
- Update `CHANGELOG.md` style only when project maintainers require release note updates.

## Testing Approach (Observed)

This repository relies on **integration + static checks** via GitHub Actions:

- PHP syntax lint (`php -l` over plugin files)
- Composer-based lint/style checks from Cacti workspace (`lint`, `phpcsfixer` scripts)
- Runtime integration checks by installing Cacti + plugin and running poller
- No repository-local unit test suite is currently present

When generating code:

- Ensure code is syntactically valid PHP.
- Keep style and lint compatibility with current Cacti-driven CI steps.
- Do not invent a new test framework in this plugin unless requested.

## PHP-Specific Guidelines

- Maintain procedural PHP structure and Cacti plugin API usage.
- Keep includes, globals, and helper calls consistent with existing patterns.
- Match array formatting and control-flow style used in current files.
- Continue using gettext domain `'monitor'` for user-facing strings.
- Preserve compatibility with CI-validated PHP versions and Cacti/plugin constraints.

## Versioning and Change Tracking

- Follow existing changelog conventions in `CHANGELOG.md` (sectioned by release, `issue#` / `feature#` bullets).
- Treat plugin version metadata in `INFO` as the authoritative plugin version declaration.

## General Best Practices for This Repository

- Prioritize consistency with existing code over introducing newer external patterns.
- Reuse existing Cacti APIs and plugin hooks instead of custom abstractions.
- Keep CSS/theme changes aligned with current theme folder structure.
- Keep localization updates aligned with existing gettext files and domain usage.
- If uncertain, mirror nearby code patterns in the same file first.

## Project-Specific Guidance

- Scan relevant files before generating code; do not assume patterns from unrelated projects.
- Respect current architectural boundaries:
  - Hook/config lifecycle in `setup.php`
  - UI/rendering workflow in `monitor.php`
  - Poller/notification workflow in `poller_monitor.php`
- When conflicts arise, prefer patterns that are currently active in top-level runtime paths and CI-validated flows.
- Always prioritize compatibility and consistency with this repository over external “best practice” rewrites.
