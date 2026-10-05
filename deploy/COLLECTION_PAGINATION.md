# Collection pagination rollout

Coordinate the API and Flutter rollout. Existing JSON list/data keys remain;
top-level `pagination` adds page, limit, total, total_pages and has_more. Wheel
detail adds `data.results_pagination`. Defaults are page1/limit50, except users
and wheel results retain limit100. Integer page1..10000 and limit1..100 only;
invalid/array/zero/negative values return422. No unlimited mode exists.

Covered API collections: events, cash transactions, announcements, voting,
inventories, loans, attendance history/attendees, participants, chat rooms,
wheel sessions/results and users. Counts use the same tenant/permission/role
filters as the fetched rows; cash balance continues to cover the whole ledger.
Existing primary ordering gains an ID tie-breaker. Wheel results select newest
first then reverse each page for ascending display; items retain the existing
1000-item creation cap because all candidates participate in a spin.

Flutter services preserve old result keys and expose metadata with optional
page/limit arguments. Main lists provide previous/next navigation, replacing
the current page rather than accumulating unlimited rows. Cash month changes
reset to page1. Existing local search/status/RT filters apply to the current
page. This needs product/device QA; do not advertise those filters as a global
search. Older clients can parse the unchanged data shape but show only the
default page; publish the coordinated client before rollout to large tenants.

Browser organization and tenant member/event/announcement/cash lists include
bounded pages and navigation. Geography dropdowns, aggregate reports and legacy
reference selectors still need separate bounded search/export design, so the
whole PERF-02 finding remains partial. Offset pages are not a snapshot: inserts
or deletions between requests can shift rows. Stable tie ordering avoids duplicate
ties in an unchanged dataset; it does not promise cross-request snapshot isolation.
MySQL query plans, indexes and realistic local load are separate verification.
