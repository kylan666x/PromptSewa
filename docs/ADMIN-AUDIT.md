# PromptSewa — Admin Surface Audit (v1.4.2, A3)

Every admin-reachable route, its controller action, gate, verb, and the test
that locks it. The `staff` middleware (`EnsureUserIsStaff`) guards the whole
`/admin` prefix: guests → login redirect, members → 403, moderators and
admins → in. Route-level `abort_unless(...->isAdmin())` gates elevate to
admin-only inside that perimeter.

Update this file whenever an admin route is added or regated.

## Access ladder

| Viewer | `/admin/*` | Admin-only actions (roles, verify, orders, payments) |
|---|---|---|
| Guest | 302 → login | 302 → login |
| Member | 403 | 403 |
| Moderator | 200 | 403 (except prompt status/preview/reports which are moderator-tier) |
| Admin | 200 | 200 |

Locked by: `CheckoutFlowTest::guests are redirected from the admin panel`,
`members cannot open the admin panel`, `moderators can open the admin panel`,
`AdminFlowsTest::moderators cannot issue verified badges`.

## Route inventory

| Route | Verb | Controller | Gate | Locking test(s) |
|---|---|---|---|---|
| `/admin` | GET | AdminDashboardController@index | staff | `StaffViewStandaloneRenderTest::every admin page renders clean for staff` |
| `/admin/prompts` | GET | PromptAdminController@index | staff | `StaffViewStandaloneRenderTest` |
| `/admin/prompts/{prompt}/preview` | GET | Admin\PromptPreviewController@show | staff | `AdminPreviewTest` (mod 200 full body, member 403, guest redirect, zero grants) |
| `/admin/prompts/{prompt}/status` | PATCH | PromptAdminController@updateStatus | staff | `AdminReviewTest` (approve, reject, 403, 405 lock) |
| `/admin/users` | GET | UserAdminController@index | staff | `StaffViewStandaloneRenderTest` |
| `/admin/users/{user:id}/role` | PATCH | UserAdminController@updateRole | staff + isAdmin + self-demotion guard | `CheckoutFlowTest::only admins can change user roles`, `an admin cannot change their own role` |
| `/admin/users/{user:id}/verified` | PATCH | UserAdminController@toggleVerified | staff + isAdmin | `AdminFlowsTest::admin can toggle the verified badge on and off`, `moderators cannot issue verified badges` |
| `/admin/reports` | GET | PromptReportAdminController@index | staff | `StaffViewStandaloneRenderTest` |
| `/admin/reports/{report}/status` | PATCH | PromptReportAdminController@updateStatus | staff | `AdminFlowsTest::staff can resolve and dismiss reports with attribution` |
| `/admin/payments` | GET | PaymentMethodAdminController@edit | staff | `StaffViewStandaloneRenderTest`, `CheckoutFlowTest::payment secrets are encrypted at rest` |
| `/admin/payments` | PUT | PaymentMethodAdminController@update | staff | `CheckoutFlowTest` |
| `/admin/packs` | GET | PackAdminController@index | staff | `StaffViewStandaloneRenderTest` |
| `/admin/packs/create` | GET | PackAdminController@create | staff | `StaffViewStandaloneRenderTest` |
| `/admin/packs` | POST | PackAdminController@store | staff | `AdminFlowsTest::admin can create, update and delete a pack` |
| `/admin/packs/{pack}/edit` | GET | PackAdminController@edit | staff | `StaffViewStandaloneRenderTest` |
| `/admin/packs/{pack}` | PUT | PackAdminController@update | staff | `AdminFlowsTest` (update half) |
| `/admin/packs/{pack}` | DELETE | PackAdminController@destroy | staff | `AdminFlowsTest` (delete half) |
| `/admin/tool-logos` | GET | ToolLogoAdminController@index | staff | `AdminFlowsTest::tool logos page is admin/staff only` |
| `/admin/tool-logos` | POST | ToolLogoAdminController@store | staff | `AdminFlowsTest::admin can add, deactivate and remove an AI tool` |
| `/admin/tool-logos/{toolLogo}` | PATCH | ToolLogoAdminController@update | staff | `AdminFlowsTest` (deactivate half) |
| `/admin/tool-logos/{toolLogo}` | DELETE | ToolLogoAdminController@destroy | staff | `AdminFlowsTest` (remove half) |
| `/admin/brand` | GET | BrandSettingsAdminController@edit | staff | `BrandSettingsTest::guests cannot open…`, `non-admin members are forbidden…` |
| `/admin/brand` | PUT | BrandSettingsAdminController@update | staff | `BrandSettingsTest::admins can update site name…`, `BrandLogoTest` |
| `/admin/orders` | GET | OrderAdminController@index | staff | `StaffViewStandaloneRenderTest` |
| `/admin/orders/{order}/approve` | PATCH | OrderAdminController@approve | staff + isAdmin | `CheckoutFlowTest::approving a manual pack order…` |
| `/admin/orders/{order}/reject` | PATCH | OrderAdminController@reject | staff + isAdmin | `AdminFlowsTest::rejecting a manual payment…`, `rejecting an already-paid order is refused` |
| `/admin/update` | GET | Dashboard\ReleaseUpdateController@form | staff | `AdminFlowsTest::admin update page loads for staff and forbids members`, `StaffViewStandaloneRenderTest` (standalone render) |
| `/admin/update` | POST | Dashboard\ReleaseUpdateController@update | staff | `AdminFlowsTest`, zip POST graceful-fail verified under prod parity |

## Verb integrity

Every Blade form targeting an admin route is verified against this table by
`Arch\BladeFormVerbTest` (rule a: spoof fields; rule b: no POST→GET-only;
A2: no hardcoded actions). The 405 regression lock lives at
`AdminReviewTest::posting without the method spoof is rejected (405 regression lock)`.

## User binding note

Admin user routes pin `{user:id}` because `User::getRouteKey()` now returns
the public handle for creator URLs — see handoff §6 watch-outs.
