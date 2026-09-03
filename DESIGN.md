# Design System — Fight

## Scope

This document is the visual-system source of truth for the Fight family. Fight Common documentation and its
repository README are the first application. The system must also produce durable static assets suitable for a
GitHub profile README. It records the approved mark and page-shell directions but does not contain their final
production implementation.

## Visual Identity

Fight expresses **tempered precision**: calm, rigorous, durable, and quietly forceful. The name does not mean
literal combat. It describes software boundaries that remain intact under real pressure. The visual language
therefore uses disciplined structures, visible seams, and explicit connection points instead of aggressive
imagery.

The signature idea is a **boundary under load**. Two controlled forms hold a protected center. Kiln orange marks
the places where navigation, dependencies, or actions cross or activate a boundary; it is not a decorative wash.

Reject boxing gloves, fists, weapons, shields, mascots, flames, military insignia, hacker neon, generic code
brackets, literal hexagons, gradient-dependent identity, and comic-book aggression. Also reject the default
documentation treatment of interchangeable blue-on-white surfaces, generic rounded cards, and a landing page
whose entire structure is a hero followed by three feature cards.

## Color

The core palette combines cool mineral surfaces and carbon text with a concentrated kiln-orange signal. Light
and dark themes are peers rather than an inverted afterthought.

| Token | Light | Dark | Usage |
|---|---:|---:|---|
| Canvas | `#F4F6F7` | `#101619` | Page background |
| Surface | `#FFFFFF` | `#172025` | Navigation, prose, and primary panels |
| Raised surface | `#F8FAFA` | `#1C272C` | Interactive cards and menus |
| Text | `#182126` | `#EEF2F3` | Primary copy |
| Muted text | `#53636B` | `#AAB7BD` | Secondary copy and metadata |
| Strong boundary | `#7E8B91` | `#667780` | Essential component and focus-adjacent boundaries |
| Quiet boundary | `#CBD3D7` | `#344249` | Nonessential dividers |
| Kiln | `#C2410C` | `#FF7A45` | Active seams, links, primary actions, and ports |
| Kiln strong | `#9A3412` | `#FF9A72` | Hover, pressed, and high-emphasis states |

Semantic colors use paired theme values:

| Meaning | Light | Dark | Required treatment |
|---|---:|---:|---|
| Success | `#18794E` | `#66D19E` | Label or icon plus color |
| Warning | `#935F00` | `#F3B64E` | Label or icon plus color |
| Error | `#B42335` | `#FF7A8A` | Label or icon plus color |
| Info | `#286EA6` | `#73B7EA` | Label or icon plus color |

Normal text and interactive controls target WCAG AA. Primary and muted text, kiln actions, semantic text, and
essential boundaries meet their applicable contrast thresholds against their intended surfaces. Color never
carries architectural ownership, status, or interaction state by itself.

Gradients are limited to a two-to-four-percent tonal shift between adjacent neutral surfaces. They may give a
button or raised card controlled depth, but never become a multicolor field, glow, or identity dependency.

## Typography

Self-host release-pinned WOFF2 files and provide native system fallbacks. Load only the weights used by the
interface. Documentation must remain readable while fonts load or when they are unavailable.

| Role | Family | Weight | Size / line height | Usage |
|---|---|---:|---:|---|
| XS | Source Sans 3 | 400–600 | 12 / 16px | Dense metadata and captions |
| Small | Source Sans 3 | 400–600 | 14 / 20px | Navigation, labels, and supporting copy |
| Body | Source Sans 3 | 400 | 16 / 26px | Long-form documentation |
| Lead | Source Sans 3 | 400–600 | 18 / 28px | Introductions and callouts |
| H4 | Open Sans | 600 | 22 / 28px | Card and subsection headings |
| H3 / H2 | Open Sans | 600 | 30 / 38px | Article hierarchy |
| H1 | Open Sans | 600 | 42 / 48px | Page thesis |
| Code | Fira Code | 400–500 | 14 / 22px | Code, commands, filenames, and technical metadata |

Fira Code programming ligatures are enabled through contextual alternates. Copied text retains its underlying
characters. Disable ligatures only where the documentation is teaching or comparing the exact character sequence.
Open Sans SemiBold gives headings a clean, stable silhouette without compressing long technical phrases. Open
Sans 700 with restrained tracking is reserved for the family wordmark when additional weight is required; body
copy remains sentence case.

## Spacing

Use a 4px base grid.

| Token | Value | Typical use |
|---|---:|---|
| `space-1` | 4px | Inline separation |
| `space-2` | 8px | Tight control spacing |
| `space-3` | 12px | Label groups |
| `space-4` | 16px | Default component inset |
| `space-6` | 24px | Card and prose grouping |
| `space-8` | 32px | Related sections |
| `space-12` | 48px | Major content breaks |
| `space-16` | 64px | Page sections |
| `space-24` | 96px | Large identity moments |

Compact screens reduce outer spacing before reducing readable internal spacing. Code, tables, and diagrams may
scroll horizontally when reflow would alter their meaning.

## Shape, Borders, and Elevation

The system uses a **structural frame** rather than a field of floating rounded cards.

- Use square geometry for diagrams, code, prose regions, and architectural frames.
- Use a 2px radius for compact controls and a 4–6px radius for buttons, search, and cards where touch or focus
  benefits. Fully rounded pills are limited to short status labels.
- Essential boundaries use the strong-boundary token and at least 3:1 non-text contrast. Quiet dividers may use
  the quiet-boundary token when they do not carry meaning.
- A heavier edge may identify a load-bearing seam, current route, or architectural ownership rail.
- Low elevation: `0 1px 2px rgba(16, 22, 25, 0.08)` for resting interactive surfaces.
- Medium elevation: `0 6px 18px rgba(16, 22, 25, 0.10)` for open menus and elevated previews.
- Primary buttons may combine low elevation with a subtle neutral gradient. Code blocks and navigation rely on
  boundaries and surface contrast rather than shadow.
- Dark mode favors controlled edge highlights; avoid broad black shadows that turn surfaces muddy.

A faint drafting grid or registration detail may appear behind a logo specimen, homepage identity moment, or
architecture overview. It must never sit behind prose, code, tables, or navigation.

## Component Vocabulary

The documentation system needs:

- global navigation, repository/version link, search, theme control, and mobile navigation;
- thesis and route-entry blocks for Architecture, Quick Start, and Explore Components;
- problem-grouped component atlas sections and directly linked component cards;
- labeled Domain, Application, and Adapter ownership rails;
- breadcrumbs, local table of contents, article navigation, and stable heading anchors;
- code blocks with language, filename, copy, annotations, and optional exact-character mode;
- notes, warnings, compatibility callouts, configuration-format tabs, tables, dependency lists, and
  framework-composition panels;
- architecture diagrams, dependency paths, port/adapter diagrams, and figure captions;
- contribution/edit links, previous/next links, footer, empty search, and custom 404 guidance.

Every interactive component defines default, hover, active, disabled, focus-visible, loading where applicable,
and error states. Focus indicators use a high-contrast outline and offset; shadow alone is not a focus indicator.

## Selected Documentation Direction

**Atlas Deck** is the approved Fight Common homepage and article-shell direction. The homepage gives the promise,
Composer installation, and compact architecture proof first; presents Architecture, Quick Start, and Explore
Components as prominent parallel routes; and then exposes the complete problem-grouped component atlas. Category
cards reveal their component links without an intermediate page and use ownership rails to show Domain,
Application, and Adapter placement.

The matching article shell uses component navigation, a focused prose-and-code column, and a local table of
contents. Compact layouts convert component navigation to a horizontal rail and preserve breadcrumbs, ownership,
dependencies, code, notes, diagrams, and next steps in the primary reading flow. Both homepage and article shells
end with a restrained footer containing `© 2026 John Nickell` plus GitHub, contribution, and MIT-license links.
Prefer a year sourced from site configuration or the build when practical.

## Reviewed Prototype Fixed Point

The approved responsive prototype preserves Atlas Deck as a documentation experience rather than a static
composition. Its fixed point includes:

- Open Sans SemiBold headings, Source Sans 3 prose, and ligature-enabled Fira Code;
- the deterministic Inward Port SVG mark and Fight Common lockup;
- a full-width flex dependency chain that gives Adapter, Application, and Domain equal space, stacking only on
  truly narrow screens;
- a complete problem-grouped homepage atlas with Mail directly visible under Connect Systems;
- a three-column desktop article shell that becomes a single reading column with horizontal component navigation
  on compact screens;
- keyboard-accessible search, theme, navigation, copy, local-anchor, and configuration-tab interactions;
- equivalent-format tabs only where the underlying framework or tool genuinely supports each format, with every
  panel retaining its filename, copy action, and stable semantics; and
- kiln-orange warning callouts reserved for consequential behavior, using an icon, label, and explanatory copy so
  the warning never depends on color alone.

The prototype repairs oversized and detached copy controls, brittle architecture diagrams, uneven dependency
cells, undersized mobile controls, and article anchors that escaped to the homepage. Production implementation
must retain the resulting reflow behavior from 375px through 1440px, 44px compact touch targets, visible keyboard
focus, reduced-motion support, and WCAG AA contrast.

## Diagram and Illustration Language

Architecture diagrams are the illustration system. Use clear orthogonal or deliberately curved paths, arrowheads
that state dependency direction, explicit layer names, short captions, and enough whitespace to read the model.
Domain, Application, and Adapter remain distinguishable through position, labels, edge styles, and only then
color. A diagram may highlight one dependency path interactively, but its static state must remain complete.

Do not introduce decorative stock illustration or character art. Abstract texture is subordinate to technical
meaning. Real PHP, configuration, and architectural relationships are the primary visual material.

## Motion

The system is **responsive, nearly still**.

| Token | Duration | Usage |
|---|---:|---|
| Immediate | 0–80ms | Pressed-state feedback |
| Fast | 120ms | Color and boundary transitions |
| Standard | 180ms | Card elevation, menus, and focused path highlights |

Use an ease-out curve for entering emphasis and ease-in for its removal. Hover and active states may shift an
edge by no more than 2px or adjust a restrained shadow. Do not use ambient animation, scroll reveals, bouncing,
or routine logo motion. `prefers-reduced-motion` removes nonessential transitions, and meaning never depends on
movement.

## Creative Bets

### Load-bearing seam

Kiln orange appears specifically where a route, dependency, or action crosses or activates a boundary. Its
scarcity makes it recognizable and prevents the accent from becoming generic decoration.

### Ownership rail

Component cards and guides expose Domain, Application, or Adapter placement through a consistent labeled edge.
The rail turns architecture into useful navigation instead of a background manifesto.

### Structural F

The family mark will resolve disciplined boundary forms into an F through silhouette or negative space. The
letter should be discoverable without becoming a conventional monogram pasted onto an unrelated badge.

## Logo Brief

The Fight family mark means **a boundary that holds under load**. It should suggest opposing pressure, a protected
center, and deliberate inward dependency while resolving into a structural F.

- Build the mark from a few substantial geometric forms with a stable outer silhouette and controlled negative
  space. Avoid fine internal lines.
- The standalone mark must remain recognizable at 16px and work in one color, reversed, and without gradients or
  shadows.
- Keep the mark independent from the wordmark so it can serve favicons, avatars, and future products.
- Use a family-first modular lockup: the mark plus `FIGHT` is stable; `COMMON` is a replaceable product descriptor.
- Provide horizontal, compact stacked, standalone, monochrome, and README-safe exports during the logo phase.
- Keep slogans and architecture labels out of the logo asset.
- Prohibit fists, gloves, weapons, shields, mascots, flames, military insignia, literal arenas, generic code
  brackets, literal hexagons, and effects required to understand the geometry.

## Selected Mark Direction

**Inward Port — No Lower Rail** is the approved Fight family-mark direction. A dark structural `F` forms the
load-bearing boundary. One cool-steel approach rail enters from the left through a kiln-orange triangular port
centered on the dark middle arm. One upper steel ownership rail remains inside the `F`; the lower counterspace
stays open.

This construction preserves the architecture story without overloading the small silhouette. The approach rail
must read as an inbound dependency path rather than a generic arrow, and the orange port must remain a scarce
boundary crossing rather than a decorative wedge. The mark stays independent from the stable `FIGHT` family
wordmark; `COMMON` is a replaceable product descriptor.

Taste Design raster studies are reference material only. Reconstruct the mark as flat, deterministic, editable
SVG geometry with consistent angles and spacing. Do not reproduce generated gradients, shadows, bevels, texture,
or other raster artifacts. The final system must prove full-color, light, dark, reversed, one-color, README-safe,
social, avatar, and favicon output, including recognition at 32px and a reviewed one-color treatment at 16px.

Clear space around the standalone mark and lockups is at least the height of the dark middle arm. The full-color
mark has a 32px minimum; the horizontal Fight Common lockup has a 120px minimum width; and the compact stacked
lockup has a 72px minimum width. Use the selected palette only, preserve the approved rail and port relationships,
and do not stretch, rotate, skew, outline, animate, add effects, or rearrange the lockup.

## Reference Products

- **Symfony documentation:** retain its multiple learning routes and visible topic families; do not borrow its
  red identity or framework-scale taxonomy.
- **Laravel documentation:** retain its code-forward restraint and long-form lookup quality; do not imitate its
  brand treatment or collapse Fight's architecture into a flat topic list.
- **Doctrine:** retain the idea of a recognizable family spanning standalone Composer packages; reject its dense,
  reference-first orientation as the Fight Common homepage model.
- **Temporal documentation:** retain its decisive promise and clear first actions; reject generic SaaS-card gloss
  and product-conversion patterns that do not serve an open-source component library.

The creative white space is a documentation identity in which architectural boundaries are both the subject and
the functional visual grammar.

## AI-Slop Check

This system intentionally avoids warm cream plus editorial serif, near-black plus acid accent, broadsheet rules,
generic blue-on-white SaaS styling, gratuitous gradients, ubiquitous rounded cards, decorative numbering, and
animation added merely for presence. Boldness is spent on the structural boundary language; typography, depth,
and motion remain disciplined around it.
