# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`monitor`, "Device Monitoring", version 2.9) targeting Cacti 1.2.15+, requires `thold:1.2.1`
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: 8.2+ (CI matrix tests 8.2-8.4)
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.15+)
- **Database**: MariaDB 10.6+ with InnoDB engine
- **Dependency**: Requires the `thold` plugin (>= 1.2.1) to be installed

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `read_config_option()`, `get_filter_request_var()`)
- `themes/` CSS overlays, `sounds/` audible alert assets, `js/` client-side dashboard code

## Project Structure

```
monitor/                    # Repository root (install to plugins/monitor/ in Cacti)
├── js/                       # Dashboard client-side logic
├── sounds/                      # Alert sound assets
├── themes/                        # CSS theme overlays (monitor.css)
├── tests/                            # Test suite
├── db_functions.php                    # SQL filter/join helpers and status/device query utilities
├── monitor.php                           # Web entrypoint/bootstrap (session/request setup)
├── monitor_controller.php                  # Action flow, filter handling, page orchestration
├── monitor_render.php                        # Dashboard/group rendering and view-specific output
├── poller_functions.php                        # Poller helper logic (uptime checks, notifications, email payloads)
├── poller_monitor.php                            # Background poller entry point (CLI: --help, --version, --debug)
├── INFO                                            # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                         # Plugin install/uninstall/upgrade hooks, hook registration, config
```

## Naming Conventions

### Function Names
- Procedural functions use **lowerCamelCase**, the `monitor_` prefix, or the required `plugin_monitor_` lifecycle prefix, matching current file conventions — keep plugin hook callback names exactly synchronized between their definitions and their `api_plugin_register_hook()` registration strings.
- Keep top-level entrypoints lightweight; place reusable logic in the appropriate helper file (`db_functions.php`, `poller_functions.php`).

### Database Tables
Plugin tables are prefixed `plugin_monitor_`. Reuse existing table names and avoid introducing parallel schema variants; keep schema evolution inside the existing setup/upgrade lifecycle functions in `setup.php`.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Prefer Cacti's DB helpers already used in this plugin — use `db_fetch_row_prepared()`, `db_fetch_cell_prepared()`, and `db_execute_prepared()` when dynamic values are present, and use `db_fetch_assoc()` only for queries without dynamic parameters.

```php
// CORRECT
db_fetch_row_prepared('SELECT * FROM plugin_monitor_dashboards WHERE id = ?', [$id]);

// WRONG
db_fetch_row('SELECT * FROM plugin_monitor_dashboards WHERE id = ' . $id);
```

### Input Validation
Use the existing request-validation patterns before consuming request values: `get_request_var()`, `get_filter_request_var()`, `get_nfilter_request_var()`, `set_request_var()`, `validateRequestVars()`.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### Output Escaping
Escape HTML output with existing helpers (e.g., `html_escape()`).

## Database Operations

Keep schema evolution in the existing setup/upgrade flows (table creation/alter logic in `setup.php`'s install/upgrade lifecycle functions); avoid repeated expensive queries inside loops — precompute lists/maps, then iterate.

## Internationalization

Use gettext calls with the `monitor` domain for user-facing strings: `__('Text', 'monitor')`.

## Plugin Architecture

### File Responsibilities
- `setup.php`: plugin lifecycle, hook registration, config arrays/settings, install/upgrade table management.
- `monitor.php`: web entrypoint/bootstrap, includes, session/request setup.
- `monitor_controller.php`: action flow, filter handling, page orchestration.
- `monitor_render.php`: dashboard/group rendering and view-specific output.
- `db_functions.php`: SQL filter/join helpers and status/device query utilities.
- `poller_monitor.php`: CLI poller entrypoint.
- `poller_functions.php`: poller helper logic (uptime checks, notifications, email payload building).

Avoid OO/framework refactors unless explicitly requested; this plugin is intentionally procedural.

### Plugin Hooks
Register hooks in `setup.php`, including `top_header_tabs`, `config_arrays`, `config_settings`, `poller_bottom`, `api_device_save`, `device_action_array/execute/prepare`, `device_remove`, `device_filters`, `device_sql_where`, `device_table_bottom`; keep hook callback names synchronized with their registration strings.

## Best Practices

1. Keep changes localized to the appropriate responsibility file; favor small helper extractions for complex branches (pattern used in `poller_functions.php`).
2. Do not rename public/plugin callback functions unless all call sites and hook strings are updated.
3. Avoid repeated expensive queries inside loops; preserve current poller stat logging behavior and timing model.
4. Run `php -l` on modified plugin files and ensure compatibility with the CI lint/style checks (`lint`, `phpcsfixer` scripts).

## Common Pitfalls to Avoid

```php
// WRONG - direct request superglobal access
$id = $_REQUEST['id'];

// CORRECT
$id = get_filter_request_var('id');
```

## Version Control

Document user-visible or release-impacting changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allow_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **Plugin table-creation API.** Use `api_plugin_db_table_create()`/`api_plugin_db_add_column()`
  (from Cacti core's `lib/plugins.php`) instead of raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN`.
  Both are idempotent (safe no-ops when already applied), so the same call can run unconditionally
  from both the install AND upgrade paths.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.
