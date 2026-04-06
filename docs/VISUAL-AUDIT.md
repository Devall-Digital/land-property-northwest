# Visual audit (deep dive)

**Purpose:** Single place for **live UI/UX and artistic direction** findings from browser-based review (logged-in test user). Complements `docs/DISCOVERY-BACKLOG.md` (code and product correctness). Update this file after each visual pass.

**Last pass:** 2 April 2026 — **three** parallel **computer-use** VM browser audits (test user via `lpnw-login-as` with **`&key=`** matching **`LPNW_LOGIN_AS_SECRET`** or dev fallback; see `docs/DEPLOYMENT.md`) plus **HTTP smoke** on all major paths (all **200** at time of check: `/about/`, `/contact/`, `/pricing/`, `/properties/`, `/map/`, `/shop/`, `/cart/`, `/checkout/`, `/my-account/`, `/saved/`). A fourth commerce-focused VM run **failed** (host image/document cap); treat WooCommerce as **not re-verified** this round. Intermittent **503** still possible on 20i; one agent saw it recover.

**Note on nav while logged in:** With a **subscriber** cookie, “About” may route to **subscriber home** (`/dashboard/`) by design; for marketing pages audit **open `/about/?nocache=1` in the address bar** or use a **logged-out** window.

**Shipped (repo, April 2026):** Plugin **1.0.14+** — `get_card_context()` **deep image discovery**; **empty states** (alerts, saved); **preferences** area bulk as outline buttons; **browse** pagination hint; **dashboard** PRO badge + unified action-card accent + stat icons + coverage % alignment; **home hero** (page content + template) **primary + ghost** CTAs, title class for gradient. Theme **6.5.3** — pricing **Pro** border motion disabled (static gradient border); dark overrides for new plugin blocks. **Marketing DB sync:** Plugin **1.0.17+** includes `LPNW_Page_Content_Sync` — visit `/?nocache=1&lpnw_update=pages` while **logged in as admin**, or add `&key=...` (see `docs/DEPLOYMENT.md`). Legacy **`mu-plugins/lpnw-update-pages.php`** still self-deletes after one run if used.

**Hero focus session (April 2026):** Plugin **1.0.16** — `LPNW_Hero_Svg` **v5**: deeper afternoon sky, sun corona, distant hills, **SMIL-drifting** cloud banks, birds, glass tower accent, crosswalk; **unique SVG id prefix** per request. **Logged-in** hero CTAs via `the_content` filter + `body_class` `lpnw-hero--logged-in`. Theme **6.6.0** — hero **glass content** panel, **title shimmer** (motion-safe), stronger **layer parallax** + **mouse offset** (`--lpnw-hero-mx/my`), taller parallax stack, **primary CTA** scale; template hero matches **logged-in** buttons. Run **page sync** (above) so DB home picks up `<em>anyone else</em>` line + CTAs.

**Design evaluation (April 2026, agent VM):** After the page-content snag fix, **curl** to the sync URL without auth still returns full HTML when the old mu-plugin is gone; with **1.0.17** deployed, plain-text **Updated: Home** responses confirm DB refresh. **Parallax:** layered SVG + scroll + mouse offset reads clearly on desktop; **motion-safe** title shimmer respects `prefers-reduced-motion`. **Remaining gaps** from prior audit still apply: dashboard preview images vs browse, preferences label contrast, pricing duplicate title on some builds, commerce/checkout pass not yet re-recorded. Next full **screen-recorded** pass should cover logged-in **test** user + **/?nocache** after each deploy.

**Art direction goal (owner):** The site should feel **artistically impressive** and **special**: cohesive motion, typography, tactile UI, premium colour discipline, and trust without clutter.

---

## How we re-run this

1. Use **`https://land-property-northwest.co.uk/?nocache=1`** and append **`&lpnw_login_as=test&key=`** with **`LPNW_LOGIN_AS_SECRET`** (or dev **`lpnw2026setup`** if unset) so **20i CDN** does not serve stale HTML/CSS.
2. Optional: **Screen record** on the agent VM during review; **delete the `.mp4` after** synthesis to save disk space.
3. Split work across **parallel agents**: subscriber surfaces, commerce/pricing, marketing homepage, mobile viewport.
4. Merge results here; push **actionable tickets** into `DISCOVERY-BACKLOG.md` or the runbook **Open work** when they are product-sized tasks.

**Security note:** `lpnw-login-as.php` is a **temporary efficiency tool** for pre-launch QA. During development it uses a **default key** in the repo; before launch use **`LPNW_LOGIN_AS_SECRET`** and/or **remove** the mu-plugin from production.

---

## Full-site VM pass (2 April 2026)

Merged from parallel VM sessions. *Verify* items need a second look (agent misread or plugin-specific UI).

### Homepage and hero (guest + logged-in)

- **Sticky bottom bar** (`.lpnw-sticky-cta-bar`) plus **multiple “Start free” CTAs** on the home funnel can feel **repetitive**; consider **not showing** the sticky bar when another prominent conversion block is in view, or delay it (*verify* overlap with any **guest** overlay).
- One agent reported a **full-screen modal** blocking the hero; our theme code shows **hero + sticky bar + in-content CTAs**, not a dedicated modal. ***Verify on a logged-out session*:** if a **third-party** or **Jetpack** overlay appears, document and remove or make dismissible.
- **Hero art:** confirm **sun / clouds / parallax** read clearly **behind** the glass content panel at 1280px; agent saw strong **city silhouette** but questioned **sky depth** (*subjective*).
- **Mobile (~390px):** *Predicted* — sticky bar + **cookie notice** may **stack** and eat vertical space; **modal** claim would be *critical* on mobile if real — **verify on device**.
- **Latest activity / property strip:** **badge inconsistency** (NEW vs type vs source); **“Latest activity”** subheading **contrast**; **small** WhatsApp/Email links; **grid gap** uniformity.

### Marketing (`/about/`, `/contact/`, `/pricing/`)

- **`/pricing/`:** **Duplicate “Pricing”** (theme entry title + hero) — still **High**.
- **“Compare plans”** gold-on-navy — **contrast** risk — still **High**.
- **Pricing table on narrow viewports:** horizontal scroll behaviour needs **real 390px check** (agent could not complete).
- **`/about/` and `/contact/`:** **HTTP 200** from this environment; if VM session **redirected to dashboard**, retest **logged out** or **direct URL**.

### Subscriber (`/dashboard/`, `/map/`, *preferences/saved*)

- **Dashboard property cards:** strong report of **blue tint / overlay on photos** making images look **washed** — treat as **High** (CSS: gradient overlay on `.lpnw-property-card` image or similar). *May coexist* with older “placeholder only” bug; **check both**.
- **NEW** badge on tinted image — **contrast**.
- **Agent / WhatsApp / Email** links and **timestamps** — **low contrast** on dark cards.
- **Feature tags** — small text, weak contrast (matches prior audit).
- **Alert coverage** widget — **%** alignment / row spacing (matches P2).
- **Map (`/map/`):** legend **dot** size; **cluster** visibility on light tiles; **source** filter styling vs dashboard; attribution **font size**.

### Commerce (`/shop/`, `/cart/`, `/checkout/`, `/my-account/`)

- **Not completed** this pass (VM sub-agent hit **image/document limit**). **Next:** one session with **screenshot budget** or plain **checklist** without captures.

### Infrastructure

- **503 Service Unavailable** observed mid-session (**PHP worker** message) — **ops priority**; blocks trust and audits.

---

## Executive summary

| Theme | Severity | Notes |
|-------|----------|--------|
| **Dashboard alert preview vs /properties/** | Critical | Cards in “preview alerts” often show **image placeholders** while browse shows **photos** for similar listings — investigate `raw_data` / `LPNW_Property::get_card_context()` and historical queue rows. |
| **Dashboard card image overlay** | High (VM) | Live VM pass: **heavy blue tint** over property photos on dashboard cards — inspect image-area **linear-gradient overlay** / blend; may be separate from missing-URL placeholders. |
| **503 / PHP worker** | High | VM + prior passes: intermittent **503** — hosting / PHP pool stability. |
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

- [ ] WooCommerce **shop → cart → checkout → thank you** (test mode or small purchase) — **blocked Apr 2026** by VM capture limits; retry with **no/minimal** screenshots.  
- [ ] **My account** orders / subscriptions UI.  
- [ ] **Two area landing pages** + **one blog post** template.  
- [ ] **Property map** on phone (Leaflet touch, cookie bar).  
- [ ] **Contact form** success/error states.  
- [ ] **404** and **search** if enabled.  
- [ ] **Logged-out** homepage: confirm whether any **modal** is real (third-party) or misidentified UI.  
- [ ] **`/preferences/`** and **`/saved/`** dedicated VM pass (this round: partial / navigation drift).

---

## Source

Parallel VM browser audits (April 2026), logged in via `mu-plugins/lpnw-login-as.php` test user. Merged and edited for consistency; contradictory items (e.g. trust bar “missing” vs page content) resolved in favour of **code + second look**.
