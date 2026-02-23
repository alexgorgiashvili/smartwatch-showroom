# Product Listing Page — Discovery & Design Plan

**Date:** 2026-02-20  
**Goal:** Transform the current product grid into a premium, high-conversion **Tech-Showcase**.

---

## 1) Discovery Findings

### 1.1 Product data structure (available fields)
Source of truth is the `Product` model and product migrations.

**Core content fields**
- `name_en`, `name_ka`
- `short_description_en`, `short_description_ka`
- `description_en`, `description_ka`
- `slug`

**Commerce fields**
- `price`
- `sale_price`
- `currency`
- `featured`
- `is_active`

**Feature/spec fields usable as tags/badges**
- `sim_support` (boolean)
- `gps_features` (boolean)
- `water_resistant` (string, e.g. IP67)
- `battery_life_hours` (integer)
- `warranty_months` (integer)

**Media**
- One-to-many product images via `images`
- `primaryImage` relation for hero card image
- `ProductImage` provides normalized `url` accessor and localized `alt`

**Current listing badge usage**
- Discount percent badge
- Featured star badge
- Small feature row with SIM/GPS/Warranty indicators

---

### 1.2 Grid component location and layout type
**Primary listing page:** `resources/views/products/index.blade.php`

Current grid is **fixed breakpoint columns** (not fluid masonry):
- mobile: 1 column
- small: 2 columns
- large: 3 columns
- xl: 4 columns

Grid declaration currently uses static Tailwind breakpoints and a fixed max-width container.

---

### 1.3 Existing filtering/sorting logic
**Controller:** `app/Http/Controllers/ProductController.php`

Existing functionality:
- **Search** across localized name and short descriptions.
- **Category filter** via query string:
  - `sim` → `sim_support = true`
  - `gps` → `gps_features = true`
  - `new` → created in last 30 days
- **Sorting** via query string:
  - `featured` (default)
  - `price_low`
  - `price_high`
  - `newest`

**UI controls on listing page**
- Search form
- Category pills
- Sort dropdown

No dedicated JS filtering engine was found for products; listing is server-rendered via request parameters.

---

## 2) Design Requirements (Approved for implementation planning)

### 2.1 Bento-style cards (floating premium look)
- Replace flat card shell with premium card surface:
  - soft border (`border-white/10` style equivalent)
  - mild translucent or elevated panel look
  - subtle lift + refined shadow on hover
- Keep interaction lightweight and consistent with existing performance.
- Preserve click target as full-card link to product detail.

### 2.2 Dynamic badges (minimalist, non-cluttered)
Define a badge priority system:
1. `sale_price` discount badge (highest urgency)
2. `featured` / `new` badge
3. 1–2 feature badges only (SIM, GPS, Waterproof)

Rules:
- Limit visible badges per card (max 3 total at once, max 2 feature badges).
- Use short labels: “SIM”, “GPS”, “IP67”, “NEW”.
- Use low-noise monochrome/soft accent palette, not saturated multicolor blocks.

### 2.3 Quick preview behavior
Phase 1 (no modal):
- Implement elegant image interaction:
  - subtle zoom on hover
  - if secondary image exists, crossfade/swap to secondary image on hover

Phase 2 (optional):
- Introduce lightweight Quick View drawer/modal with key specs + CTA.

### 2.4 Micro-copy & typography
- Product model name:
  - premium sans-serif stack (Space Grotesk already present in project)
  - stronger letter spacing/weight hierarchy
- Price treatment:
  - primary visual anchor (larger, bold, high contrast)
  - sale and original price pairing remains clear
- Feature text:
  - concise and de-noised (avoid long rows and icon overload)

### 2.5 Mobile optimization (2-column spacious grid)
- Change mobile layout to **2 columns** from small viewports upward where feasible.
- Increase perceived breathing room:
  - balanced card padding
  - slightly reduced image height on small screens
  - consistent gap sizing (avoid cramped text stacks)
- Ensure badges and price do not collide at narrow widths.

---

## 3) Proposed UX/Visual System for the Tech-Showcase

### Card anatomy (listing)
1. Media zone (image + top badges)
2. Product identity (model name + short line)
3. Feature chips (max 2)
4. Price block (dominant)
5. Secondary hint CTA (e.g., “View details” affordance)

### Motion principles
- 150–250ms transitions
- No aggressive transforms
- Hover effects disabled/reduced for touch devices
- Respect reduced-motion preferences

---

## 4) Implementation Plan (next phase)

### Step A — Structure & componentization
- Extract product card into a dedicated showcase partial/component.
- Keep server-rendered filters/sorting as-is.

### Step B — Visual upgrade
- Introduce premium card styles (floating shell, subtle border, shadow tuning).
- Rework typography hierarchy for model + price.
- Add badge priority renderer.

### Step C — Quick preview interaction
- Add dual-image hover swap when extra image is available.
- Add graceful fallback to single-image zoom.

### Step D — Mobile-first tuning
- Shift to 2-column mobile grid with spacious rhythm.
- Tune truncation/line-clamp and chip wrapping for narrow widths.

### Step E — QA checklist
- Verify search/filter/sort query state persistence.
- Verify Georgian/English text lengths.
- Verify card consistency across all discount/feature combinations.
- Verify no CLS jumps from badge/image transitions.

---

## 5) Risks and constraints

- Current route/controller architecture is server-rendered; advanced client-side filtering is out-of-scope unless requested.
- Over-badging can reduce clarity; strict badge priority rules are required.
- Quick View modal introduces complexity and should be optional after hover-preview baseline is complete.

---

## 6) Reference files audited

- `app/Models/Product.php`
- `app/Models/ProductImage.php`
- `database/migrations/2026_02_13_000000_create_products_table.php`
- `database/migrations/2026_02_14_000003_add_sale_price_to_products_table.php`
- `app/Http/Controllers/ProductController.php`
- `resources/views/products/index.blade.php`
- `resources/views/products/show.blade.php`
- `routes/web.php`
