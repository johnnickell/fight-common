#!/usr/bin/env python3

"""Validate the generated public documentation artifact."""

from __future__ import annotations

import json
import sys
import xml.etree.ElementTree as element_tree
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit


PRODUCTION_ROOT = "https://johnnickell.github.io/fight-common/"
REQUIRED_FILES = (
    "index.html",
    "404.html",
    "search/search_index.json",
    "sitemap.xml",
)


class DocumentParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.canonicals: list[str] = []
        self.root_relative_urls: list[str] = []
        self.static_urls: list[str] = []
        self.return_links: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)
        href = attributes.get("href")
        src = attributes.get("src")

        if tag == "link" and href is not None:
            rel = set((attributes.get("rel") or "").split())
            if "canonical" in rel:
                self.canonicals.append(href)
            elif rel.intersection({"stylesheet", "icon", "preload", "manifest"}):
                self.static_urls.append(href)

        if tag == "a" and href is not None:
            self.return_links.append(href)

        if src is not None:
            self.static_urls.append(src)

        for url in (href, src):
            if url is not None and url.startswith("/") and not url.startswith("//"):
                self.root_relative_urls.append(url)


def fail(message: str) -> None:
    raise ValueError(message)


def parse_html(path: Path) -> DocumentParser:
    parser = DocumentParser()
    parser.feed(path.read_text(encoding="utf-8"))
    parser.close()

    return parser


def published_html_routes(site_directory: Path, documents: list[Path]) -> dict[Path, str]:
    routes: dict[Path, str] = {}
    for document in documents:
        relative = document.relative_to(site_directory)
        if relative.name == "index.html":
            route = relative.parent.as_posix()
            route = "" if route == "." else f"{route}/"
        else:
            route = relative.as_posix()
        routes[document] = f"{PRODUCTION_ROOT}{route}"

    return routes


def published_document_for_location(
    location: str,
    routes: dict[Path, str],
) -> Path | None:
    parsed = urlsplit(location)
    if parsed.scheme or parsed.netloc:
        page = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
    elif parsed.path.startswith("/"):
        page = f"https://johnnickell.github.io{parsed.path}"
    else:
        page = f"{PRODUCTION_ROOT}{parsed.path}"

    for document, route in routes.items():
        if page == route:
            return document

    return None


def local_static_path(site_directory: Path, document: Path, value: str) -> Path | None:
    parsed = urlsplit(value)
    if parsed.scheme or parsed.netloc or value.startswith("#"):
        return None

    path = unquote(parsed.path)
    if not path:
        return None

    if path.startswith("/"):
        if not path.startswith("/fight-common/"):
            return None
        candidate = site_directory / path.removeprefix("/fight-common/")
    else:
        candidate = document.parent / path

    resolved = candidate.resolve()
    try:
        resolved.relative_to(site_directory.resolve())
    except ValueError:
        fail(f"Static asset escapes the artifact: {value}")

    return resolved


def validate(site_directory: Path) -> None:
    if not site_directory.is_dir() or site_directory.is_symlink():
        fail(f"Generated site directory is missing or unsafe: {site_directory}")

    for required in REQUIRED_FILES:
        if not (site_directory / required).is_file():
            fail(f"Generated artifact is missing required file: {required}")

    paths = sorted(site_directory.rglob("*"), key=lambda path: path.as_posix())
    for path in paths:
        if path.is_symlink():
            fail(f"Generated artifact contains symbolic link: {path.relative_to(site_directory)}")
        if "overrides" in path.relative_to(site_directory).parts:
            fail(f"Generated artifact contains source-only overrides path: {path.relative_to(site_directory)}")

    try:
        search_data = json.loads((site_directory / "search/search_index.json").read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        fail(f"Generated search index is malformed: {error}")
    if not isinstance(search_data, dict) or not isinstance(search_data.get("docs"), list) or not search_data["docs"]:
        fail("Generated search index is empty or malformed")

    documents = sorted(site_directory.rglob("*.html"), key=lambda path: path.as_posix())
    routes = published_html_routes(site_directory, documents)

    try:
        sitemap = element_tree.parse(site_directory / "sitemap.xml")
    except element_tree.ParseError as error:
        fail(f"Generated sitemap is malformed: {error}")
    locations = [location.text or "" for location in sitemap.findall(".//{*}loc")]
    if not locations:
        fail("Generated sitemap is empty")
    for location in locations:
        if not location.startswith(PRODUCTION_ROOT):
            fail(f"Generated sitemap location is outside production docs root: {location}")
        if published_document_for_location(location, routes) is None:
            fail(f"Generated sitemap location does not resolve to output: {location}")
    if PRODUCTION_ROOT not in locations:
        fail("Generated sitemap is missing the production docs root")

    for entry in search_data["docs"]:
        if not isinstance(entry, dict) or not isinstance(entry.get("location"), str):
            fail("Generated search index is empty or malformed")
        location = entry["location"]
        if published_document_for_location(location, routes) is None:
            fail(f"Generated search location does not resolve to output: {location}")

    for document in documents:
        parser = parse_html(document)
        if document.name != "404.html" and parser.canonicals != [routes[document]]:
            fail(
                "Generated document must have exactly one route-derived production canonical: "
                f"{document.relative_to(site_directory)}",
            )
        for url in parser.root_relative_urls:
            if not url.startswith("/fight-common/"):
                fail(f"Generated document has root-relative URL outside docs base: {document.relative_to(site_directory)}: {url}")
        for url in parser.static_urls:
            asset = local_static_path(site_directory, document, url)
            if asset is not None and not asset.is_file():
                fail(f"Generated document references missing local static asset: {document.relative_to(site_directory)}: {url}")

    error_parser = parse_html(site_directory / "404.html")
    if PRODUCTION_ROOT not in error_parser.return_links:
        fail("Generated 404 page is missing a return link to the production docs root")


def main(arguments: list[str]) -> int:
    if len(arguments) != 1:
        print("Usage: validate_docs_artifact.py SITE_DIRECTORY", file=sys.stderr)
        return 2

    try:
        validate(Path(arguments[0]))
    except ValueError as error:
        print(f"Documentation artifact validation failed: {error}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
