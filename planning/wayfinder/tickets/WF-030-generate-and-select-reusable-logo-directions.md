# Generate and select reusable logo directions

**Labels:** `wayfinder:prototype`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Establish the Fight visual system and logo brief](WF-029-establish-visual-system-and-logo-brief.md)

## Question

Which logo direction best satisfies the approved Fight identity brief and can become a durable family mark with
a Fight Common lockup?

## Must decide

- a reference territory and several genuinely different mark directions;
- selection criteria for distinctiveness, meaning, reproducibility, accessibility, and small-size recognition;
- the chosen family mark and its relationship to the Fight Common product name;
- required editable and exported assets, including SVG, monochrome, light/dark, favicon, social, and README-safe
  variants; and
- minimum clear-space, sizing, color, and misuse guidance needed for implementation.

## Resolution boundary

Use `$aios /taste-design` for reference-led exploration. The selected concept must be convertible into clean,
editable SVG and static README-safe exports; image-generation output is reference material, not the final source
asset. Page layout remains downstream.

## Resolution

Select **Inward Port — No Lower Rail** as the Fight family-mark direction. It gives the best combined result
across visual appeal, immediate recognition as an `F`, architectural meaning, and reduced-size legibility.

The approved construction contains:

- a dark structural `F` that acts as the load-bearing boundary;
- one cool-steel approach rail entering from the left;
- one kiln-orange triangular port centered on the dark middle arm;
- one cool-steel upper ownership rail inside the `F`; and
- open lower counterspace with no lower steel rail.

The approach rail represents deliberate inward dependency rather than a generic arrow. The upper steel rail
preserves the layered architecture idea without the small-size noise introduced by the rejected lower rail.
The final asset must translate these relationships into clean flat geometry; gradients, shadows, bevels, raster
texture, and incidental image-generation artifacts are not part of the mark.

### Explored directions

- **Compression Gate** made opposing pressure visible but felt assembled rather than unified.
- **Load Bridge** emphasized force transfer but read as a tool or `G` before it read as an `F`.
- **Folded Rail** produced the simplest silhouette, but its fold risked becoming a familiar ribbon-logo motif.
- **Inward Port** best connected the identity to ports, boundaries, and dependency direction. Variations tested
  rail alignment, balanced spacing, removal of the lower rail, and removal of both internal rails. Removing only
  the lower rail retained the strongest balance of meaning, character, and recognition.

### Selection criteria

The implementation must preserve the decision against five tests:

1. **Distinctiveness:** the port-and-boundary relationship must not collapse into a generic arrow, ribbon,
   shield, or sports mark.
2. **Meaning:** the `F`, inward route, protected counterspace, and active crossing must remain understandable
   without a slogan.
3. **Reproducibility:** the mark must be constructible from a small set of deterministic SVG paths, with no
   raster effect needed to explain it.
4. **Accessibility:** light, dark, reversed, and one-color forms must retain sufficient contrast, and color must
   not be the only carrier of the boundary crossing.
5. **Recognition:** the standalone mark must remain identifiable at 32px in full color and at 16px in the
   approved one-color favicon treatment.

### Family and product relationship

The standalone mark and `FIGHT` wordmark form the stable family identity. `COMMON` is a replaceable product
descriptor, composed as a subordinate but readable lockup element. The mark must remain independent so it can
serve favicons, avatars, diagrams, and future Fight products without the product descriptor.

### Required source and exports

Implementation must produce:

- editable, optimized SVG sources for the standalone mark, stable Fight family lockup, Fight Common horizontal
  lockup, and Fight Common compact stacked lockup;
- full-color light and dark variants plus one-color dark, one-color light, and reversed variants;
- favicon SVG, 16px and 32px PNG favicon proofs, a multi-size ICO where the renderer requires it, and a 180px
  touch icon;
- README-safe light and dark PNG or SVG lockups with deterministic dimensions and a `<picture>`-compatible
  pairing;
- a 512px standalone avatar export and a 1280×640 social-preview composition; and
- a specimen that compares full color, one color, reversed, 64px, 32px, and 16px output before acceptance.

Generated Taste Design images remain disposable reference evidence and must not be promoted as source assets.

### Usage guidance

- Define `x` as the height of the dark middle arm. Keep at least `1x` clear space around the standalone mark and
  family lockup.
- Do not render the full-color standalone mark below 32px. Use the implementation-reviewed one-color favicon treatment from
  16px through 31px; if the exact geometry does not remain recognizable at 16px, do not publish an unreviewed
  optical-size redraw.
- Do not render the horizontal Fight Common lockup below 120px wide or the compact stacked lockup below 72px
  wide without a legibility review.
- Use only the approved carbon, cool-steel, kiln-orange, white, and dark-theme equivalents from `DESIGN.md`.
- Do not stretch, rotate, skew, outline, animate, add effects, change rail spacing, move the port, rearrange the
  lockup, or substitute semantic status colors.
- Keep slogans, architecture labels, and product claims outside the logo asset and its required clear space.
