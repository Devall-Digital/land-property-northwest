# Visual audit (deep dive)

**Purpose:** Single place for **live UI/UX and artistic direction** findings from browser-based review (logged-in test user). Complements `docs/DISCOVERY-BACKLOG.md` (code and product correctness). Update this file after each visual pass.

**Last pass:** April 2026 — four parallel **computer-use** browser sessions on the VM (`/?nocache&lpnw_login_as=test&key=lpnw2026setup`). Some pages hit **503** or incomplete mobile tooling; findings below are merged and de-duplicated; items marked *verify* need a quick human check on a real phone.

**Shipped (repo, April 2026):** Plugin **1.0.14** — `LPNW_Property::get_card_context()` **deep image discovery** in `raw_data` (Rightmove shape drift). Theme **6.5.2** — hide duplicate page title on **pricing / properties / contact**; **Compare plans** heading **white + underline bar** (no low-contrast gradient text); pricing table **SVG ticks**, **row hover**, **sticky thead** (desktop), **scroll fade** hint on narrow screens; **VIP card** styling + **Premium** ribbon; **trust line** under table; **dark-mode** checkbox + feature-tag + reset link contrast; **NEW** badge inset. Re-verify on live with `/?nocache`.

**Art direction goal (owner):** The site should feel **artistically impressive** and **special**: cohesive motion, typography, tactile UI, premium colour discipline, and trust without clutter.

---

## How we re-run this

1. Use **`https://land-property-northwest.co.uk/?nocache`** (and append `&lpnw_login_as=test&key=...` for test user) so **20i CDN** does not serve stale HTML/CSS.
2. Optional: **Screen record** on the agent VM during review; **delete the `.mp4` after** synthesis to save disk space.
3. Split work across **parallel agents**: subscriber surfaces, commerce/pricing, marketing homepage, mobile viewport.
4. Merge results here; push **actionable tickets** into `DISCOVERY-BACKLOG.md` or the runbook **Open work** when they are product-sized tasks.

**Security note:** `lpnw-login-as.php` is a **temporary efficiency tool** for pre-launch QA. **Remove from production** and **rotate the key** before full public launch.

---

## Executive summary

| Theme | Severity | Notes |
|-------|----------|--------|
| **Dashboard alert preview vs /properties/** | Critical | Cards in “preview alerts” often show **image placeholders** while browse shows **photos** for similar listings — investigate `raw_data` / `LPNW_Property::get_card_context()` and historical queue rows. |
| **Preferences area labels** | High | Reviewers reported **missing or invisible postcode labels** in the areas grid — likely **contrast/CSS on `body.lpnw-site`** (labels exist in PHP as `<span>` after checkbox). **Verify** and fix token colours. |
| **Contrast (feature chips, gold headings, help text)** | High | Feature tags on cards, “Compare plans” gold on navy, light grey help copy — **WCAG AA** risk. |
| **Pricing page structure** | High | **Duplicate “Pricing”** (GeneratePress title + hero); comparison table **UX** (sticky header, mobile scroll, SVG checkmarks); **VIP** lacks premium visual differentiation. |
| **Artistic / premium gap** | Cross-cutting | Weak **micro-interactions**, **flat cards**, **inconsistent button scale**, **gold reads “budget”** to some testers — needs a **design pass**: motion discipline, champagne/white hierarchy, hover depth, optional trust strip. |
| **Mobile** | Mixed | **Real-device** pass still needed; automated mobile session was **partial** (ignore absurd cases like **&lt;100px** viewport letter-stacking as DevTools artefact). **Verify:** pricing table scroll, cookie bar vs sticky CTA, property grid column count at 375px. |
| **Not covered this pass** | — | **/shop/**, **cart**, **checkout**, **my-account**, **area landing pages** in depth, **map** touch UX — schedule next pass. |

---

## P0 — Fix first (revenue / trust / “broken”)

1. **Dashboard preview images**  
   - **Where:** `/dashboard/` → recent / preview alert property cards.  
   - **Symptom:** Placeholder blocks (“Detached house” etc.) while `/properties/` shows images.  
   - **Hypothesis:** Empty or legacy `image_url` paths in `raw_data` for those rows, or different code path; confirm with one `property_id` in DB.  
   - **Premium:** After fix, optional **skeleton shimmer** while images load.

2. **Preferences — area checkboxes readable**  
   - **Where:** `/preferences/` → Areas → `#lpnw-areas-checkboxes`.  
   - **Symptom:** Checkboxes visible but **labels appear blank** in audit screenshots.  
   - **Action:** Audit **`body.lpnw-site .lpnw-checkbox-group__item span`** (and light accordion backgrounds) for **same-colour text**; ensure **code + region name** (e.g. `M — Greater Manchester`) if hierarchy needs clarity.

3. **503 / availability**  
   - **Note:** One marketing audit hit **503** mid-session. Track with hosting; unrelated to CSS but blocks review.

---

## P1 — High impact UX and accessibility

- **Feature tags on property cards** (dashboard + browse): grey on dark — raise text/background contrast; consider **semantic tint** (muted teal outline) rather than low-contrast grey blobs.  
- **“Compare plans”** (`/pricing/`): gold/yellow on navy — **lighten heading** or use **white + gold underline**.  
- **Duplicate “Pricing” heading:** hide theme **entry title** on that template or consolidate to **one** hero line.  
- **“Your plan” / tier card** (`/dashboard/`): PRO badge blends into background — **stronger tier treatment** (border glow, pill, or small gradient) especially for **VIP** later.  
- **Pricing table:** replace raw **✓ / —** with **SVG icons**; add **row hover**; **sticky thead** on scroll; **mobile:** wrapped table or horizontal scroll with **visible scroll hint**.  
- **Pricing conversion:** short **trust line** (cancel anytime, secure payment) and optional **testimonial strip** below cards; **VIP card** needs **clear premium styling** (not identical to Free).  
- **Preferences:** **Select all / Deselect all** as buttons or ghost links consistent with design system; **Reset to defaults** higher contrast; accordion **open/closed** state clearer (motion + background).  
- **CTA hierarchy (homepage hero):** primary **Start free** must dominate; secondary **See pricing** clearly secondary (outline / lower weight).

---

## P2 — Medium polish

- **NEW badge:** `8–10px` inset from card edge on image overlay.  
- **Dashboard “alert coverage”:** align **%** column with labels; consistent row spacing.  
- **Stat cards:** slightly larger **icons** or **brand-colour** icons; optional **count-up** on first view (respect `prefers-reduced-motion`).  
- **Action cards on dashboard:** unify **left accent** rule (which cards get gold bar vs not).  
- **Pricing “MOST POPULAR” decoration:** green/yellow swoosh — **intentional motion** or **remove**.  
- **Browse:** ensure **pagination / load more** is obvious at end of grid.  
- **Empty states:** saved properties / no alerts — **illustration + one CTA** (not plain text only).

---

## P3 — Low / nice-to-have

- Welcome line typography (`font-weight`, optional dynamic “you have X new matches”).  
- **“1,649 matches this week”** panel: palette feels dated — align with **navy + amber/teal** system.  
- **Footer** on pricing scroll: confirm **full footer** visible and link density.  
- **Favicon / wordmark:** optional **small mark** (bell + NW) for tab recognition.

---

## Artistic / “special” direction (brief for design implementation)

These are **intentional** targets, not one-off bugs:

1. **One hero accent rule:** Either **white headline + single accent word** in teal/amber, or a **controlled gradient** — avoid competing gold-and-teal in the same line without a system.  
2. **Motion discipline:** **Parallax + section reveals** already exist; add **short, ease-out hovers** on cards/buttons (150–200ms), **no** heavy always-on loops (stability first).  
3. **Depth:** Consistent **card shadow language** (dashboard widgets vs property cards vs pricing cards).  
4. **Trust without noise:** One **tight trust strip** (stats or logos) under hero or above pricing — **animated counters** optional if subtle.  
5. **Imagery:** Property cards should **always** prefer real imagery when URL exists; **placeholder** as branded silhouette, not flat rectangle.  
6. **Premium tier story:** VIP should **look** 4× the price (border, micro-copy, “concierge” cues).  
7. **Map as premium feature:** Coverage preview could evolve to **mini-map** or static NW outline (later; aligns with “special” positioning).

---

## Coverage gaps (next visual pass)

- [ ] WooCommerce **shop → cart → checkout → thank you** (test mode or small purchase).  
- [ ] **My account** orders / subscriptions UI.  
- [ ] **Two area landing pages** + **one blog post** template.  
- [ ] **Property map** on phone (Leaflet touch, cookie bar).  
- [ ] **Contact form** success/error states.  
- [ ] **404** and **search** if enabled.

---

## Source

Parallel VM browser audits (April 2026), logged in via `mu-plugins/lpnw-login-as.php` test user. Merged and edited for consistency; contradictory items (e.g. trust bar “missing” vs page content) resolved in favour of **code + second look**.
