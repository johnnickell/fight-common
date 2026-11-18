#!/usr/bin/env python3

"""Validate and render the GitHub-safe Fight Common repository README subset."""

from __future__ import annotations

import argparse
import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlparse

import markdown


PRODUCTION_ROOT = "https://johnnickell.github.io/fight-common/"
REQUIRED_ROUTES = {
    "Quick Start": f"{PRODUCTION_ROOT}quick-start/",
    "Architecture": f"{PRODUCTION_ROOT}architecture/",
    "Components": f"{PRODUCTION_ROOT}#component-atlas",
    "Frameworks": f"{PRODUCTION_ROOT}frameworks/framework-support/",
    "JSend compatibility": f"{PRODUCTION_ROOT}components/validation/",
    "MCP foundation migration": "docs/maintenance/mcp-foundation-namespaces.md",
    "MCP aggregate migration": "docs/mcp-namespaces.md#approved-aggregate-alignment-target",
    "Contributing": f"{PRODUCTION_ROOT}maintenance/contributing/",
    "Repository": "https://github.com/johnnickell/fight-common",
    "Source": "https://github.com/johnnickell/fight-common/tree/main/src",
    "Changelog": "CHANGELOG.md",
    "License": "LICENSE",
}
ALLOWED_RAW_TAGS = frozenset({"picture", "source", "img"})
TEST_WORKFLOW = "https://github.com/johnnickell/fight-common/actions/workflows/tests.yml"
LATEST_RELEASE_NOTES = "https://github.com/johnnickell/fight-common/releases/latest"
# Read GitHub’s selected stable release, rather than sorting tags or releases separately.
STABLE_RELEASE_IMAGE = (
    "https://img.shields.io/badge/dynamic/json?"
    "url=https%3A%2F%2Fapi.github.com%2Frepos%2Fjohnnickell%2Ffight-common%2Freleases%2Flatest"
    "&query=%24.tag_name&label=Stable%20release"
)
EXPECTED_BADGES = (
    ("Tests", f"{TEST_WORKFLOW}/badge.svg?branch=main", TEST_WORKFLOW),
    ("PHP 8.5+", "https://img.shields.io/badge/php-8.5%2B-8892BF.svg?logo=php&logoColor=white", "https://www.php.net/"),
    ("License: MIT", "https://img.shields.io/badge/license-MIT-blue.svg", "LICENSE"),
    ("Stable release", STABLE_RELEASE_IMAGE, LATEST_RELEASE_NOTES),
)


class ReadmeParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[tuple[str, str]] = []
        self.images: list[dict[str, str]] = []
        self.image_links: list[tuple[str, str, str | None]] = []
        self.sources: list[dict[str, str]] = []
        self.heading_ids: set[str] = set()
        self._link_href: str | None = None
        self._link_text: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = {name: value or "" for name, value in attrs}
        if tag == "a":
            self._link_href = attributes.get("href", "")
            self._link_text = []
        elif tag == "img":
            self.images.append(attributes)
            self.image_links.append((attributes.get("alt", ""), attributes.get("src", ""), self._link_href))
        elif tag == "source":
            self.sources.append(attributes)
        elif re.fullmatch(r"h[1-6]", tag) and attributes.get("id"):
            self.heading_ids.add(attributes["id"])

    def handle_endtag(self, tag: str) -> None:
        if tag == "a" and self._link_href is not None:
            self.links.append(("".join(self._link_text).strip(), self._link_href))
            self._link_href = None
            self._link_text = []

    def handle_data(self, data: str) -> None:
        if self._link_href is not None:
            self._link_text.append(data)


def fail(message: str) -> None:
    raise ValueError(message)


def markdown_html(source: str) -> str:
    return markdown.markdown(source, extensions=["fenced_code", "tables", "toc"])


def parse_rendered(source: str) -> tuple[str, ReadmeParser]:
    rendered = markdown_html(source)
    parser = ReadmeParser()
    parser.feed(rendered)
    return rendered, parser


def validate_raw_html(source: str) -> None:
    tags = re.findall(r"<\s*/?\s*([A-Za-z][A-Za-z0-9-]*)\b[^>]*>", source)
    unsupported = sorted({tag.lower() for tag in tags if tag.lower() not in ALLOWED_RAW_TAGS})
    if unsupported:
        fail("README raw HTML is limited to picture, source, and img; found: " + ", ".join(unsupported))
    if len(re.findall(r"<\s*picture\b", source, flags=re.IGNORECASE)) != 1:
        fail("README must contain exactly one themed identity picture")
    if len(re.findall(r"<\s*source\b", source, flags=re.IGNORECASE)) != 1 or len(
        re.findall(r"<\s*img\b", source, flags=re.IGNORECASE)
    ) != 1:
        fail("README themed identity picture must contain one source and one fallback image")
    picture_match = re.search(r"<picture([^>]*)>", source, flags=re.IGNORECASE)
    if picture_match is None or picture_match.group(1).strip():
        fail("README identity picture does not allow presentation attributes")


def validate_identity(root: Path, parser: ReadmeParser) -> None:
    dark = "docs/assets/identity/fight-common-readme-dark.svg"
    light = "docs/assets/identity/fight-common-readme-light.svg"
    theme_sources = [source for source in parser.sources if source.get("media") == "(prefers-color-scheme: dark)"]
    if len(theme_sources) != 1 or theme_sources[0].get("srcset") != dark:
        fail(f"README dark-theme identity source must be {dark}")
    if set(theme_sources[0]) != {"media", "srcset"}:
        fail("README dark-theme identity source has unsupported attributes")
    fallback = [image for image in parser.images if image.get("src") in {light, dark}]
    if len(fallback) != 1 or fallback[0].get("src") != light:
        fail(f"README fallback identity image must be {light}")
    if len(fallback[0].get("alt", "").strip()) < 20 or "Fight Common" not in fallback[0]["alt"]:
        fail("README identity fallback needs meaningful alternative text")
    if fallback[0].get("width") != "720":
        fail("README identity fallback must preserve the canonical 720px intrinsic width")
    if set(fallback[0]) != {"src", "alt", "width"}:
        fail("README identity fallback has unsupported attributes")
    for asset in (dark, light):
        if not (root / asset).is_file():
            fail(f"README identity asset does not exist: {asset}")


def validate_badges(source: str, parser: ReadmeParser) -> None:
    for alt, image, target in EXPECTED_BADGES:
        markup = f"[![{alt}]({image})]({target})"
        if source.count(markup) != 1 or parser.image_links.count((alt, image, target)) != 1:
            fail(f"README must contain exactly one correctly linked {alt} badge")
    if len(parser.images) != len(EXPECTED_BADGES) + 1 or source.count("![") != len(EXPECTED_BADGES):
        fail("README must contain only the Tests, PHP 8.5+, MIT, and Stable release badges plus its identity image")


def document_fragments(path: Path) -> set[str]:
    if path.suffix.lower() not in {".md", ".markdown"}:
        return set()
    _, parser = parse_rendered(path.read_text(encoding="utf-8"))
    return parser.heading_ids


def validate_targets(root: Path, parser: ReadmeParser) -> None:
    for _text, target in parser.links:
        parsed = urlparse(target)
        if parsed.scheme:
            if parsed.scheme != "https":
                fail("README links must use HTTPS or repository-relative targets")
            continue
        if target.startswith("//") or target.startswith("/"):
            fail("README links must use HTTPS or repository-relative targets")
        path_text = unquote(parsed.path)
        if not path_text:
            if parsed.fragment and parsed.fragment not in parser.heading_ids:
                fail(f"README link fragment does not exist: {target}")
            continue
        target_path = (root / path_text).resolve()
        try:
            target_path.relative_to(root.resolve())
        except ValueError:
            fail(f"README link escapes the repository: {target}")
        if not target_path.exists():
            fail(f"README link target does not exist: {path_text}")
        if parsed.fragment and parsed.fragment not in document_fragments(target_path):
            fail(f"README link fragment does not exist: {target}")
    for image in parser.images:
        source = image.get("src", "")
        parsed = urlparse(source)
        if parsed.scheme:
            if parsed.scheme != "https":
                fail("README images must use HTTPS or repository-relative targets")
            continue
        if not source or not (root / unquote(parsed.path)).is_file():
            fail(f"README image target does not exist: {source}")


def validate_content(root: Path, source: str, parser: ReadmeParser) -> None:
    composer = json.loads((root / "composer.json").read_text(encoding="utf-8"))
    mkdocs = (root / "mkdocs.yml").read_text(encoding="utf-8")
    if composer.get("name") != "johnnickell/fight-common" or composer.get("require", {}).get("php") != ">=8.5":
        fail("README package identity must agree with composer.json")
    if f"site_url: {PRODUCTION_ROOT}" not in mkdocs:
        fail("README production routes must agree with mkdocs.yml")
    if not source.startswith("<picture>") or not 0 <= source.find("# Fight Common") <= 500:
        fail("README must identify Fight Common immediately")
    if source.count("composer require johnnickell/fight-common") != 1:
        fail("README must contain the exact shortest Composer installation command once")
    if "Adapter → Application → Domain" not in source:
        fail("README must explain the inward Adapter → Application → Domain dependency direction")
    links = {(text, target) for text, target in parser.links}
    for label, route in REQUIRED_ROUTES.items():
        if (label, route) not in links and not any(target == route for _text, target in links):
            fail(f"README is missing the canonical {label} route")
    if "[MIT License](LICENSE)" not in source:
        fail("README is missing the canonical License route")
    route_sources = {
        f"{PRODUCTION_ROOT}quick-start/": root / "docs/quick-start/index.md",
        f"{PRODUCTION_ROOT}architecture/": root / "docs/architecture/index.md",
        f"{PRODUCTION_ROOT}#component-atlas": root / "docs/README.md",
        f"{PRODUCTION_ROOT}frameworks/framework-support/": root / "docs/frameworks/framework-support/index.md",
        f"{PRODUCTION_ROOT}maintenance/contributing/": root / "docs/maintenance/contributing/index.md",
        f"{PRODUCTION_ROOT}components/validation/": root / "docs/components/validation/index.md",
    }
    for route, path in route_sources.items():
        if not path.is_file():
            fail(f"README production route has no canonical documentation source: {route}")
    if "component-atlas" not in document_fragments(root / "docs/README.md"):
        fail("README Component Atlas route has no canonical documentation fragment")
    if "DoctrineTransactionalUnitOfWork" in source or "deprecated 1.x compatibility" in source:
        fail("README duplicates detailed repository-guide semantics")
    if "Copyright © 2026 John Nickell" not in source:
        fail("README is missing its copyright signal")


def render_document(rendered: str, theme: str) -> str:
    if theme == "dark":
        rendered = rendered.replace('media="(prefers-color-scheme: dark)"', 'media="all"')
    elif theme == "light":
        rendered = rendered.replace('media="(prefers-color-scheme: dark)"', 'media="not all"')
    theme_attribute = "" if theme == "auto" else f' data-theme="{theme}"'
    head = """<!doctype html>
""" + f'<html lang="en"{theme_attribute}><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">\n'
    body = """<title>Fight Common README preview</title><style>
:root{color-scheme:light dark;--canvas:#fff;--text:#1f2328;--border:#d0d7de;--link:#0969da;--code:#f6f8fa}
:root[data-theme=dark]{--canvas:#0d1117;--text:#e6edf3;--border:#30363d;--link:#58a6ff;--code:#161b22}
@media(prefers-color-scheme:dark){:root:not([data-theme]){--canvas:#0d1117;--text:#e6edf3;--border:#30363d;--link:#58a6ff;--code:#161b22}}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--text);font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
main{max-width:1012px;margin:auto;padding:32px}picture img{display:block;max-width:100%;height:auto}a{color:var(--link)}pre{overflow:auto;padding:16px;background:var(--code);border-radius:6px}
table{display:block;width:max-content;max-width:100%;overflow:auto;border-spacing:0;border-collapse:collapse}th,td{padding:6px 13px;border:1px solid var(--border)}h1,h2{padding-bottom:.3em;border-bottom:1px solid var(--border)}
@media(max-width:480px){main{padding:16px}}
</style></head><body><main>"""
    return head + '<base href="/">' + body + rendered + "</main></body></html>\n"


def validate(root: Path, output: Path | None = None, theme: str = "auto") -> None:
    root = root.resolve()
    readme = root / "README.md"
    if not readme.is_file():
        fail("Repository is missing README.md")
    source = readme.read_text(encoding="utf-8")
    validate_raw_html(source)
    rendered, parser = parse_rendered(source)
    validate_identity(root, parser)
    validate_badges(source, parser)
    validate_targets(root, parser)
    validate_content(root, source, parser)
    if output is not None:
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(render_document(rendered, theme), encoding="utf-8")


def main() -> int:
    argument_parser = argparse.ArgumentParser(description=__doc__)
    argument_parser.add_argument("root", type=Path)
    argument_parser.add_argument("--output", type=Path)
    argument_parser.add_argument("--theme", choices=("auto", "light", "dark"), default="auto")
    arguments = argument_parser.parse_args()
    try:
        validate(arguments.root, arguments.output, arguments.theme)
    except (OSError, ValueError, json.JSONDecodeError) as error:
        print(str(error), file=sys.stderr)
        return 1
    print("README validation and GitHub-safe render passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
