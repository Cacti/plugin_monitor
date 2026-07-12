# ADR-0001: Migrate Monitor toward a hexagonal architecture

**Status:** Proposed

## Context

Monitor currently combines Cacti hook registration, HTTP request handling,
session state, dashboard rendering, raw SQL construction, poller execution,
notification policy, email delivery, and persistence in a small number of
procedural files. It also stores monitoring configuration in Cacti's shared
`host` table and directly reads optional plugin tables such as Threshold and
Syslog.

This makes it difficult to test monitoring behavior independently, evolve
state transitions safely, prevent duplicate background work, or maintain a
stable extension boundary while keeping compatibility with Cacti 1.2.

## Decision

Monitor will be migrated incrementally to a hexagonal architecture while
preserving the current Cacti 1.2 hooks, UI routes, device fields, and
notification behavior during the compatibility period.

The target contains the following responsibilities:

- **Domain:** monitoring profiles, observations, incidents, acknowledgements,
  notification attempts, and saved dashboard definitions.
- **Application services:** evaluate a monitoring cycle, reconcile uptime and
  reboots, dispatch notifications, query a dashboard, and acknowledge or mute
  an incident.
- **Ports:** device-status source, threshold-status source, monitor
  repository, dashboard repository, notification gateway, authorization
  policy, clock, and worker lease.
- **Adapters:** Cacti host/Threshold/Syslog APIs, MySQL, Cacti mailer, Cacti
  poller hook, and the existing Monitor web UI.

Domain code must not access request variables, sessions, globals, SQL, the
filesystem, or Cacti helper functions. New code must remain PHP 7.4-compatible
until Cacti changes its supported baseline.

## State and delivery rules

Availability, latency breach, Threshold trigger, acknowledgement, and user UI
mute state are separate concepts. A monitoring incident has an explicit
lifecycle; a notification attempt and confirmed delivery are distinct records.
Only a lease-owning worker may evaluate a cycle or dispatch its notifications.

## Migration plan

1. Add pure value objects and policy functions with characterization tests.
2. Put Cacti host, Threshold, notification, and authorization access behind
   adapters used by new application services.
3. Introduce plugin-owned profile, incident, and notification-attempt storage;
   mirror existing `host.monitor_*` fields through a compatibility adapter.
4. Move the poller script to a lease-aware evaluation and delivery workflow.
5. Move saved dashboard URL state to a versioned dashboard definition, then
   deprecate legacy direct SQL and request/session coupling.

## Consequences

- Existing hooks and database fields remain supported during migration rather
  than requiring a flag-day rewrite.
- New behavior gains unit, integration, and end-to-end test seams.
- Cacti and optional plugin dependencies become replaceable adapters rather
  than hidden global dependencies.
- The migration requires explicit schema/version compatibility and a published
  deprecation timeline before legacy paths are removed.
