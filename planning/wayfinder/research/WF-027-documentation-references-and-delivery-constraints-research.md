# WF-027: Documentation References and Delivery Constraints Research

- Date: 2026-09-03
- Question: What do the current Fight Common site, strong PHP documentation references, GitHub profile
  rendering, GitHub Pages, MkDocs customization, and credible alternative generators imply for the presentation
  program?
- Evidence boundary: the live Fight Common site; current Fight Common source, Markdown, MkDocs configuration,
  stylesheet, 404 override, and Pages workflow at `47f0141`; John's public profile README; and official
  documentation for GitHub, MkDocs, Material for MkDocs, Symfony, Laravel, Docusaurus, Astro Starlight, and
  VitePress. Visual observations are reference input, not accepted design decisions.

## Result

Retain the current Markdown corpus and begin with a bespoke MkDocs Material presentation. The repository already
has the behavioral foundation the destination needs: a `/fight-common/` site URL, explicit navigation, browser
search, code copy and annotation, snippets, light and dark palettes, custom CSS, and a custom 404. Material and
MkDocs explicitly support adding CSS and assets and extending selected theme templates through `custom_dir`
without forking the parent theme. That creates ample room to test a distinctive homepage and article shell before
accepting the migration cost of a new generator.

The presentation problem is primarily audience orientation, information architecture, brand identity, and page
composition. The current documentation home begins as an installation and Symfony-wiring manual, while the
repository README separately acts as the package overview. The new route should define their distinct jobs and
make the broad component catalog discoverable without hiding Fight Common's Domain, Application, and Adapter
boundaries.

Symfony and Laravel are useful information-architecture references rather than visual templates. Symfony's
documentation home gives newcomers multiple learning routes and then exposes task families and a separate
component catalog. Laravel uses progressively organized conceptual and task reference material. Fight Common can
borrow that layered orientation while remaining a smaller, component-oriented library with its own identity.

John's current public profile README is concise and text-led. It presents his software-architecture background,
AI-assisted engineering question, The Thinking Engineer, and social links, but it does not currently surface the
Fight open-source family. The adaptation therefore has a clear job: add a compact Fight portfolio signal and
durable paths to the work without turning the profile into a duplicate documentation homepage.

## Current Fight Common Baseline

At commit `47f0141`:

- `mkdocs.yml` identifies the site as `fight-common`, sets
  `https://johnnickell.github.io/fight-common/` as `site_url`, and uses Material for MkDocs.
- The navigation leads with Home, Quick Start, Framework Support, a Components group, and Maintenance.
- Material features include tabs, sections, expanded navigation, back-to-top, search highlighting, code copy,
  and code annotations.
- Source Sans 3 and JetBrains Mono provide the text and code roles. Blue-grey/cyan light and slate/light-blue
  dark palettes are the only current identity layer.
- `docs/stylesheets/extra.css` makes narrow typographic adjustments and widens the content grid; it is not yet a
  complete visual system.
- `docs/overrides/404.html` extends Material's main template and links back through `config.site_url`.
- The live generated homepage reports MkDocs 1.6.1 and Material 9.7.7, loads assets relative to the
  `/fight-common/` base, exposes search and theme controls, and uses Material's default book mark.
- `.github/workflows/docs.yml` runs on pushes to `main`, installs `mkdocs-material==9.7.7`, and invokes
  `mkdocs gh-deploy --force` with `contents: write`. This is a branch-writing deployment, not GitHub's newer
  artifact-and-environment Pages flow.
- The root README is a broad package overview with status badges, install command, architecture summary, and
  component inventory. `docs/README.md` is also the site homepage, but leads into installation and Symfony
  wiring before the complete catalog. WF-028 must establish a deliberate division of labor between them.

## Reference Lessons

### Symfony

The [Symfony documentation home](https://symfony.com/doc/current/index.html) offers different newcomer routes
and then groups material into Getting Started, Architecture, Basics, Advanced Topics, Security, Front-end,
Utilities, and Production. Its [component catalog](https://symfony.com/doc/current/components/index.html) is a
separate exhaustive reference. The useful lesson is progressive disclosure: orientation and common journeys
first, comprehensive component lookup still close at hand.

Fight Common should not imitate Symfony's framework-sized taxonomy. It should test a smaller component atlas
organized around user intent while keeping architectural ownership visible.

### Laravel

Laravel's [documentation](https://laravel.com/docs/12.x) is a strong long-form reading and lookup reference:
stable topic navigation, direct prose, task-centered pages, and code-forward guidance. Its
[directory-structure guide](https://laravel.com/docs/12.x/structure) demonstrates the pattern of orienting a
reader before enumerating details.

Fight Common should test whether each component page can answer, in order: why the capability exists, where it
lives architecturally, the shortest valid use, framework/provider composition, failure behavior, and deeper API
reference. That page contract remains a WF-028 decision, not a research conclusion.

### GitHub Profile README

GitHub displays a profile README only from a public repository matching the username with a non-empty root
`README.md`; see [Managing your profile README](https://docs.github.com/en/account-and-profile/how-tos/profile-customization/managing-your-profile-readme).
The surface is GitHub-rendered Markdown, with headings, links, images, code, tables, details, and a bounded subset
of HTML described in GitHub's
[writing and formatting reference](https://docs.github.com/en/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax).

Consequences:

- profile composition must work in GitHub's fixed content column and both GitHub themes;
- core meaning cannot depend on custom CSS, JavaScript, animation, remote badge uptime, or precise responsive
  control;
- images need meaningful alternative text and durable repository-owned URLs;
- a static light/dark-safe mark or intentionally paired exports are safer than a web-only lockup; and
- publication belongs to `johnnickell/johnnickell` and requires its own approval and verification.

The current profile README is a concise personal introduction plus links to The Thinking Engineer and social
channels. The Fight adaptation should add to that hierarchy rather than overwrite it by default.

## Delivery Constraints

### MkDocs Material

MkDocs supports extra CSS and JavaScript for focused changes and `theme.custom_dir` for template extension; the
[MkDocs customization guide](https://www.mkdocs.org/user-guide/customizing-your-theme/) specifically documents
404 replacement and inherited theme templates. Material's
[customization guide](https://squidfunk.github.io/mkdocs-material/customization/) confirms that selected blocks
and partials can be overridden without forking the theme, while unaffected parent templates continue to update.

Material also supplies a browser-side [built-in search plugin](https://squidfunk.github.io/mkdocs-material/plugins/search/)
that indexes generated page and section content without a server. The existing repository uses the search UI;
the prototype and final gate must prove search rather than assume it survives template changes.

Practical boundary: prefer documented configuration, CSS, assets, and the narrowest block or partial overrides.
Avoid copying whole upstream templates when one block will do, because template copies accumulate upgrade risk.
Pin the complete documentation toolchain reproducibly rather than installing one top-level package into an
otherwise floating Python environment.

### GitHub Pages

The current `mkdocs gh-deploy --force` workflow publishes on `main` pushes by writing generated output to the
Pages branch. GitHub also documents an artifact-based custom workflow using `configure-pages`,
`upload-pages-artifact`, and `deploy-pages`, with explicit `pages: write`, `id-token: write`, and a protected
`github-pages` environment; see [Using custom workflows with GitHub Pages](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages).

WF-033 must choose deliberately between those models. Either path must prove:

- correct `/fight-common/` asset and canonical paths in a production-like build;
- deterministic dependencies and a separate build validation step;
- no deployment from pull-request validation;
- preservation or explicit redirection of published URLs; and
- a generated custom 404 that returns users to the correct project base.

## Generator Options

| Candidate | Strengths for this program | Cost or uncertainty | Research position |
|---|---|---|---|
| MkDocs Material | Existing Markdown and configuration; built-in local search; syntax, snippets, navigation, palettes, CSS, assets, and template extension; already deployed at the required subpath | Deep whole-template overrides can become upgrade-sensitive; bespoke interaction remains bounded by the Jinja/Material architecture | Default prototype and likely delivery path |
| Docusaurus | Mature documentation feature set, React/MDX composition, versioning, i18n, search integrations, and extensive theming | Node/React toolchain; Markdown/extension migration; larger URL, search, and runtime regression surface | Consider only if the accepted interaction genuinely needs React/MDX or versioned product experiences |
| Astro Starlight | Documentation-focused Astro foundation, accessible-by-default positioning, component extensibility, and static output | New authoring and integration model; migration and plugin choices must be proved against current snippets, navigation, search, and URLs | Credible lower-runtime alternative if Material cannot express the accepted design |
| VitePress | Fast content-focused static generation, Vue customization, technical-docs defaults, and built-in local fuzzy search | Vue/Node ownership plus Markdown/config and URL migration; custom theme becomes a new frontend surface to maintain | Credible only when Vue-level composition is a selected requirement |

Official references: [Docusaurus introduction](https://docusaurus.io/docs),
[Astro Starlight](https://starlight.astro.build/),
[VitePress overview](https://vitepress.dev/guide/what-is-vitepress), and
[VitePress local search](https://vitepress.dev/reference/default-theme-search.html).

No alternative wins from features alone. WF-032 must first produce a reviewed fixed point; WF-033 then selects
the smallest architecture that preserves it and all accepted documentation behavior.

## Consequences for the Decision Frontier

1. WF-028 must distinguish evaluation, adoption, reference, framework-composition, and contribution journeys and
   assign each entry surface a job.
2. WF-029 and WF-030 must produce a family identity that works as a full web system and as durable static assets
   under GitHub constraints.
3. WF-031 must compare homepage and article shells together using real content; a dramatic landing page with a
   generic inner-doc shell is not a complete direction.
4. WF-032 starts in MkDocs Material and must exercise search, theme, mobile, keyboard, and reduced-motion behavior
   in the prototype rather than treating them as later polish.
5. WF-033 selects the generator and Pages model only after the reviewed design exposes the true customization
   needs.
6. WF-034 stays compact and profile-native; it links to the primary product rather than reproducing it.
7. WF-035 defines stable-URL, accessibility, performance, SEO, and hosted-state evidence before implementation
   tickets are written.
