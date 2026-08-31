# Design QA — Mitsubishi CRM Premium Refinement

- Source truth: user refinement brief plus the prior verified operations-cockpit implementation
- Browser captures: [`screenshots/dashboard/refinement-dashboard.png`](screenshots/dashboard/refinement-dashboard.png), [`screenshots/inventory/refinement-inventory.png`](screenshots/inventory/refinement-inventory.png), and [`screenshots/customer/refinement-customer.png`](screenshots/customer/refinement-customer.png)
- Desktop viewport: 1440 × 1200 CSS pixels, device scale factor 1
- State: Admin portal, animation settled

## Full-view evidence

The dashboard retains the approved cockpit composition while introducing a correct local Mitsubishi mark and more purposeful `Dealer Network` product header. The inventory browser capture confirms four distinct model-specific photographic assets, consistent dark studio art direction, correct status/chassis/price data, and a showroom-like visual rhythm. The customer capture confirms a strong dark identity header, explicit dealer ownership, model photography, tabs and a horizontally legible journey timeline.

## Fidelity surfaces

- Typography: Inter hierarchy remains readable and enterprise-appropriate; uppercase red micro-labels, large names and tab labels provide clear scanning levels.
- Spacing/layout: 220px sidebar, compact 64px topbar, dark automotive zones and light CRM sections create deliberate visual zoning without excessive cards.
- Colors/tokens: Mitsubishi red is limited to actions, active navigation, charts, statuses and timeline signals. Charcoal/black surfaces break up the warm-gray CRM canvas.
- Image quality: Outlander, Xforce, Triton and Pajero Sport use separate 1536px photographic raster assets in a consistent professional studio treatment. The proper local Mitsubishi vector mark visibly renders in the sidebar.
- Copy/content: all original customer, dealer, chassis, price, booking and role-specific mock data remains intact.

## Interaction evidence

- Count-up KPIs, staggered reveal, chart tooltips, ranked-row hover, search suggestions, notification/profile dropdowns, message send, calendar slot selection, booked-slot feedback and booking success state are implemented in vanilla JavaScript/CSS.
- `prefers-reduced-motion` disables non-essential motion.
- All 22 Admin and Dealer routes return successful responses.
- Headless Edge captures completed without page-render failures.

## Comparison history

1. P1 brand issue: the CSS-built diamond mark did not meet the requested asset fidelity. Replaced it with `public/assets/images/brand/mitsubishi-mark.svg`; browser evidence shows correct proportions and rendering.
2. P1 vehicle imagery: legacy vector vehicle illustrations made inventory and profiles feel like placeholders. Replaced all active references with four model-specific local photographic assets; verified in inventory and customer captures.
3. P2 generic header: topbar title was route-only and visually generic. Reworked dashboard context to `Dealer Network` / `Uttara Operations` with Mitsubishi organization labels.
4. P2 static chrome: search/profile/notification areas lacked useful states. Added contextual search results and polished dropdown content/animation.
5. P2 repetitive product rhythm: dashboard lacked a model overview below data sections. Added a responsive dark inventory-showcase zone to both role dashboards.

## Remaining P3 issues

- Vehicle PNG assets are approximately 2MB each. They are crisp and only loaded on image-heavy screens, but WebP/AVIF derivatives would reduce transfer size for production.
- The vehicle photographs are purpose-generated prototype assets rather than licensed official Mitsubishi campaign photography; production should replace them with approved regional brand assets.
- The sales visualization remains dependency-free CSS rather than a full charting library, appropriate for Phase 1 but less expressive than a production analytics chart.

## Verification

- [x] Proper Mitsubishi logo visible
- [x] Four vehicle images exist and render
- [x] Dashboard and Dealer Dashboard
- [x] Customers and customer profiles
- [x] Conversations, bookings and calendar
- [x] Admin and Dealer inventory
- [x] Reports, integrations and settings
- [x] 4 tests passed, 29 assertions

## Light / Dark theme verification — 25 August 2026

- Dark default verified in a clean browser profile on Admin and Dealer dashboards.
- Light theme verified at 1440 × 1024 with intentional off-white canvas, bright elevated surfaces, restrained borders, persistent dark sidebar, and unchanged automotive hero.
- Persistence verified by reopening `/dashboard` with the same browser profile and no theme query parameter; the saved light preference remained active.
- Theme controls, forms, tables, charts, messaging, calendar states, inventory imagery, dropdowns, modals, tooltips, navigation, badges, and responsive chrome are covered by the shared semantic token layer.
- Browser evidence: [`screenshots/design/design-qa-dashboard-final.png`](screenshots/design/design-qa-dashboard-final.png), [`screenshots/design/design-qa-dealer-final.png`](screenshots/design/design-qa-dealer-final.png), [`screenshots/themes/theme-light-dashboard.png`](screenshots/themes/theme-light-dashboard.png), and [`screenshots/themes/theme-light-persisted.png`](screenshots/themes/theme-light-persisted.png).
- Automated verification: 5 tests passed, 34 assertions; all 22 prototype routes remain available.

final result: passed
