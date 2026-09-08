#!/usr/bin/env python3

"""Validate the generated public documentation artifact."""

from __future__ import annotations

import hashlib
import json
import re
import sys
import xml.etree.ElementTree as element_tree
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit


PRODUCTION_ROOT = "https://johnnickell.github.io/fight-common/"
ALLOWED_EXTERNAL_HYPERLINK_SCHEMES = frozenset({"http", "https", "mailto", "tel"})
REQUIRED_FILES = (
    "index.html",
    "404.html",
    "search/search_index.json",
    "sitemap.xml",
)
CANONICAL_ROUTES = (
    "quick-start/",
    "architecture/",
    "components/values/",
    "components/collections/",
    "components/specifications/",
    "components/repositories/",
    "components/event-sourcing/",
    "components/utilities/",
    "components/messaging/",
    "components/validation/",
    "components/serialization/",
    "components/dependency-injection/",
    "components/http-client/",
    "components/auth/",
    "components/cache/",
    "components/files/",
    "components/file-transfer/",
    "components/templating/",
    "components/routing/",
    "components/mail/",
    "components/sms/",
    "components/sockets/",
    "components/observability/",
    "components/process/",
    "components/scheduler/",
    "frameworks/framework-support/",
    "frameworks/codeigniter/",
    "maintenance/contributing/",
    "maintenance/coding-standard/",
)
HOMEPAGE_TEXT = (
    "Adopt focused PHP building blocks without coupling Domain or Application code to a framework.",
    "composer require johnnickell/fight-common",
    "Adapter",
    "Application",
    "Domain",
    "Architecture",
    "Quick Start",
    "Explore Components",
    "Model the Domain",
    "Coordinate Application Behavior",
    "Connect Systems",
    "Operate Workloads",
    "Integrate Frameworks",
)
HOMEPAGE_LINKS = (
    "architecture/",
    "quick-start/",
    "components/values/",
    "components/collections/",
    "components/specifications/",
    "components/repositories/",
    "components/event-sourcing/",
    "components/utilities/",
    "components/messaging/",
    "components/validation/",
    "components/serialization/",
    "components/dependency-injection/",
    "components/http-client/",
    "components/auth/",
    "components/cache/",
    "components/files/",
    "components/file-transfer/",
    "components/templating/",
    "components/routing/",
    "components/mail/",
    "components/sms/",
    "components/sockets/",
    "components/observability/",
    "components/process/",
    "components/scheduler/",
    "frameworks/framework-support/",
    "frameworks/codeigniter/",
)
HOMEPAGE_OWNERSHIP_RAILS = (
    ("components/values/", "Values", "Ownership: Domain · Adapter"),
    ("components/collections/", "Collections", "Ownership: Domain"),
    ("components/specifications/", "Specifications", "Ownership: Domain"),
    (
        "components/repositories/",
        "Repositories",
        "Ownership: Domain · Application · Adapter",
    ),
    (
        "components/event-sourcing/",
        "Event Sourcing",
        "Ownership: Domain · Application · Adapter",
    ),
    ("components/utilities/", "Utilities", "Ownership: Domain"),
    (
        "components/messaging/",
        "Messaging (CQRS)",
        "Ownership: Domain · Application · Adapter",
    ),
    ("components/validation/", "Validation", "Ownership: Application · Adapter"),
    ("components/serialization/", "Serialization", "Ownership: Domain · Application"),
    ("components/dependency-injection/", "Dependency Injection", "Ownership: Application"),
    ("components/http-client/", "HTTP Client", "Ownership: Application · Adapter"),
    ("components/auth/", "Auth", "Ownership: Domain · Application · Adapter"),
    ("components/cache/", "Cache", "Ownership: Application · Adapter"),
    ("components/files/", "Files", "Ownership: Application · Adapter"),
    ("components/file-transfer/", "File Transfer", "Ownership: Application · Adapter"),
    ("components/templating/", "Templating", "Ownership: Application · Adapter"),
    ("components/routing/", "Routing", "Ownership: Application · Adapter"),
    ("components/mail/", "Mail", "Ownership: Application · Adapter"),
    ("components/sms/", "SMS", "Ownership: Application · Adapter"),
    ("components/sockets/", "Sockets", "Ownership: Application · Adapter"),
    (
        "components/observability/",
        "Observability",
        "Ownership: Domain · Application · Adapter",
    ),
    ("components/process/", "Process", "Ownership: Application · Adapter"),
    ("components/scheduler/", "Scheduler", "Ownership: Application · Adapter"),
    ("frameworks/framework-support/", "Framework Support", "Ownership: Adapter"),
    ("frameworks/codeigniter/", "CodeIgniter", "Ownership: Adapter"),
)
MAIL_ARTICLE_TEXT = (
    "Connect Systems",
    "Application and Adapter",
    "MailTransport, MailFactory, Symfony Mailer",
    "Consequential behavior",
    "Recipient overrides replace every original To, Cc, and Bcc recipient.",
    "Next steps",
)
MAIL_ARTICLE_CLASSES = (
    "atlas-component-rail",
    "atlas-breadcrumbs",
    "atlas-article-metadata",
    "atlas-local-contents",
    "atlas-relationship-diagram",
    "atlas-consequential-callout",
    "atlas-next-steps",
)
MAIL_ARTICLE_ANCHORS = (
    "configuration-formats",
    "symfony-configuration",
    "usage-examples",
)
MAIL_ARTICLE_HEADINGS = (
    (1, "mail", "Mail"),
    (2, "configuration-formats", "Configuration formats"),
    (2, "mailmessage", "MailMessage"),
    (2, "mailservice-facade", "MailService (Facade)"),
    (2, "mailtransport", "MailTransport"),
    (2, "mailfactory", "MailFactory"),
    (2, "attachment", "Attachment"),
    (2, "priority", "Priority"),
    (2, "symfony-configuration", "Symfony Configuration"),
    (2, "usage-examples", "Usage Examples"),
)
MAIL_ARTICLE_LOCAL_CONTENTS = tuple((f"#{identifier}", title) for _, identifier, title in MAIL_ARTICLE_HEADINGS[1:])
MAIL_CONFIGURATION_FORMATS = (
    ("yaml", "YAML", "config/services.yaml"),
    ("xml", "XML", "config/services.xml"),
    ("php", "PHP", "config/services.php"),
)
QUICK_START_ARTICLE_TEXT = (
    "Framework-Neutral Quick Start",
    "composer require johnnickell/fight-common",
    "Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.",
    "InMemoryCommandRouter",
    "RoutingCommandBus",
    "SimpleEventDispatcher",
    "TransactionalUnitOfWork",
    "EventDispatchFailed",
    "PaymentNotSuccessful",
    "FulfillmentRequester",
    "Only succeeded requests fulfillment.",
    "Both pending and failed",
    "redelivery or retry of FulfillOrder",
    "Production transaction boundary",
    "no queue, saga, or durable outbox",
)
QUICK_START_ARTICLE_HEADINGS = (
    (1, "framework-neutral-quick-start", "Framework-Neutral Quick Start"),
    (2, "prerequisites", "Prerequisites"),
    (2, "process-an-order", "Process an order"),
    (2, "complete-executable-example", "Complete executable example"),
    (2, "ownership-and-flow", "Ownership and flow"),
    (2, "payment-guard-and-retries", "Payment guard and retries"),
    (2, "continue", "Continue"),
)
QUICK_START_ARTICLE_ANCHORS = (
    "prerequisites",
    "process-an-order",
    "complete-executable-example",
    "ownership-and-flow",
    "payment-guard-and-retries",
    "continue",
)
QUICK_START_EXECUTABLE_REGIONS = (
    "final class OrderProcessingExample",
    "final readonly class CustomerId",
    "final readonly class ProcessOrder implements Command",
    "final readonly class FulfillOrder implements Command",
    "require $_SERVER['FIGHT_AUTOLOAD'] ?? __DIR__.'/vendor/autoload.php';",
    "OrderProcessingExample::process().PHP_EOL",
    "provider-token-for-customer-42",
)
QUICK_START_REQUIRED_PHP_CODE_BLOCKS = 3
QUICK_START_COPY_FEATURE = "content.code.copy"
QUICK_START_NEXT_PATHS = (
    "../architecture/",
    "../components/messaging/",
    "../components/repositories/",
    "../frameworks/framework-support/",
)
REQUIRED_LOCAL_ASSETS = (
    "assets/fonts/open-sans-v44-latin.woff2",
    "assets/fonts/source-sans-3-v19-latin.woff2",
    "assets/fonts/fira-code-v27-latin.woff2",
    "assets/fonts/manifest.json",
    "assets/identity/fight-mark-light.svg",
    "assets/identity/fight-mark-dark.svg",
    "assets/identity/fight-common-social-1280x640.png",
    "javascripts/atlas-format-tabs.js",
    "javascripts/atlas-article-accessibility.js",
)
GENERIC_SITE_DESCRIPTION = "A shared PHP library for Hexagonal Architecture, CQRS, and reusable domain primitives."
SOCIAL_IMAGE = f"{PRODUCTION_ROOT}assets/identity/fight-common-social-1280x640.png"
COPY_CONTROL_SELECTOR = ".atlas-format-tabs__copy"
COPY_CONTROL_DECLARATIONS = {
    "flex": "0 0 44px",
    "inline-size": "44px",
    "min-block-size": "44px",
}
NATIVE_CONTROL_SELECTORS = frozenset(
    {
        ".md-header__button",
        ".md-code__button",
        ".md-clipboard",
        ".md-search__form > .md-search__icon",
        ".md-search__options > .md-search__icon",
    },
)
NATIVE_CONTROL_DECLARATIONS = {
    "flex": "0 0 44px",
    "inline-size": "44px",
    "min-block-size": "44px",
    "min-inline-size": "44px",
}
FOOTER_DEFAULT_TOKEN_SELECTORS = frozenset({":root", '[data-md-color-scheme="default"]'})
FOOTER_SLATE_TOKEN_SELECTORS = frozenset({'[data-md-color-scheme="slate"]'})
FOOTER_TOKEN_NAMES = (
    "--fight-footer-surface",
    "--fight-footer-text",
    "--fight-footer-link",
    "--fight-footer-focus",
)
MATERIAL_FOOTER_LINK_SELECTOR = "html .md-footer-meta.md-typeset a"
FOOTER_LINK_SELECTOR = "html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a"
FOCUSED_FOOTER_LINK_SELECTORS = frozenset(
    {
        "html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a:focus-visible",
        "html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a.focus-visible",
    },
)
FOOTER_RULES = (
    (frozenset({".atlas-footer"}), {
        "background-color": "var(--fight-footer-surface)",
        "color": "var(--fight-footer-text)",
    }),
    (frozenset({
        ".atlas-footer a:focus-visible",
        ".atlas-footer button:focus-visible",
        ".atlas-footer input:focus-visible",
        ".atlas-footer select:focus-visible",
        ".atlas-footer summary:focus-visible",
        ".atlas-footer textarea:focus-visible",
        ".atlas-footer [tabindex]:focus-visible",
    }), {"outline-color": "var(--fight-footer-focus)"}),
)
HEX_COLOR = re.compile(r"#[0-9A-Fa-f]{6}$")
COPY_STATUS_ATTRIBUTES = {
    "data-atlas-copy-status": None,
    "role": "status",
    "aria-live": "polite",
    "aria-atomic": "true",
}
FONT_MANIFEST_FILE = "assets/fonts/manifest.json"
FONT_FILE_NAME = re.compile(r"[a-z0-9]+(?:-[a-z0-9]+)*-v[0-9]+-latin\.woff2")
FONT_LICENSE_FILE_NAME = re.compile(r"licenses/[a-z0-9]+(?:-[a-z0-9]+)*-v[0-9]+-ofl-1\.1\.txt")
FONT_ENTRY_KEYS = frozenset({"family", "version", "weight", "subset", "file", "sha256", "source", "license"})
FONT_LICENSE_KEYS = frozenset({"spdx", "file", "sha256", "source", "copyright", "reserved_font_names"})
EXPECTED_FONT_DISTRIBUTIONS = (
    {
        "family": "Open Sans",
        "version": "44",
        "weight": "600",
        "subset": "latin",
        "file": "open-sans-v44-latin.woff2",
        "sha256": "6d030a368703b1104f229bdb0f73bf653134a95d9d68e06a7c1a52549f594cc3",
        "source": "https://fonts.gstatic.com/s/opensans/v44/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsgH1x4gaVIUwaEQbjA.woff2",
        "license": {
            "spdx": "OFL-1.1",
            "file": "licenses/open-sans-v44-ofl-1.1.txt",
            "sha256": "fbbbcfef55318de350562559b671360de6d597112ecc5c73881b05092db89602",
            "source": "https://raw.githubusercontent.com/google/fonts/main/ofl/opensans/OFL.txt",
            "copyright": "Copyright 2020 The Open Sans Project Authors (https://github.com/googlefonts/opensans)",
            "reserved_font_names": [],
        },
    },
    {
        "family": "Source Sans 3",
        "version": "19",
        "weight": "400 600",
        "subset": "latin",
        "file": "source-sans-3-v19-latin.woff2",
        "sha256": "ac057a5593cbe3df0d2585da5dd5f33b8efa84aa30550c710fe061b37fc5c54b",
        "source": "https://fonts.gstatic.com/s/sourcesans3/v19/nwpStKy2OAdR1K-IwhWudF-R3w8aZejf5Hc.woff2",
        "license": {
            "spdx": "OFL-1.1",
            "file": "licenses/source-sans-3-v19-ofl-1.1.txt",
            "sha256": "09746787287a289323b0ec3cff4d1a4a801331b82b7207c1e186f5d26619a392",
            "source": "https://raw.githubusercontent.com/google/fonts/main/ofl/sourcesans3/OFL.txt",
            "copyright": "Copyright 2010-2020 Adobe (http://www.adobe.com/), with Reserved Font Name 'Source'. All Rights Reserved. Source is a trademark of Adobe in the United States and/or other countries.",
            "reserved_font_names": ["Source"],
        },
    },
    {
        "family": "Fira Code",
        "version": "27",
        "weight": "400 500",
        "subset": "latin",
        "file": "fira-code-v27-latin.woff2",
        "sha256": "d32d5b5a7c9720cf3812f7d1e3ebdb54116f8f656e7471a03a5a93bdd93c98b3",
        "source": "https://fonts.gstatic.com/s/firacode/v27/uU9NCBsR6Z2vfE9aq3bh3dSDqFGedA.woff2",
        "license": {
            "spdx": "OFL-1.1",
            "file": "licenses/fira-code-v27-ofl-1.1.txt",
            "sha256": "926041dac670e6922505e35ac1661a4e8d20f1ffeabbbcb5edb5544370702369",
            "source": "https://raw.githubusercontent.com/google/fonts/main/ofl/firacode/OFL.txt",
            "copyright": "Copyright 2014-2020 The Fira Code Project Authors (https://github.com/tonsky/FiraCode)",
            "reserved_font_names": [],
        },
    },
)


class DocumentParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.canonicals: list[str] = []
        self.root_relative_urls: list[str] = []
        self.static_urls: list[str] = []
        self.hyperlinks: list[str] = []
        self.return_links: list[str] = []
        self.text: list[str] = []
        self.classes: list[str] = []
        self.ids: list[str] = []
        self.elements: list[tuple[str, dict[str, str | None]]] = []
        self.format_panel_elements: dict[str, list[tuple[str, dict[str, str | None]]]] = {}
        self.identity_images: list[tuple[str | None, dict[str, str | None]]] = []
        self.metadata: dict[str, list[str]] = {}
        self.headings: list[tuple[int, str, str]] = []
        self.local_contents_links: list[tuple[str, str]] = []
        self.pre_code_blocks: list[str] = []
        self._current_heading: tuple[int, str, list[str]] | None = None
        self._in_heading_permalink = False
        self._in_local_contents = False
        self._current_local_contents_link: tuple[str, list[str]] | None = None
        self._open_logo_contexts: list[tuple[str, str]] = []
        self._open_section_panels: list[str | None] = []
        self._current_pre_code: list[str] | None = None

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)
        self.elements.append((tag, attributes))
        if tag == "pre":
            self._current_pre_code = []
        if tag == "section":
            self._open_section_panels.append(attributes.get("data-atlas-format-panel"))
        format_panel = next((panel for panel in reversed(self._open_section_panels) if panel is not None), None)
        if format_panel is not None:
            self.format_panel_elements.setdefault(format_panel, []).append((tag, attributes))
        class_names = attributes.get("class")
        classes = set((class_names or "").split())
        if {"md-header__button", "md-logo"}.issubset(classes):
            self._open_logo_contexts.append((tag, "header"))
        elif {"md-nav__button", "md-logo"}.issubset(classes):
            self._open_logo_contexts.append((tag, "drawer"))
        if class_names is not None:
            self.classes.extend(class_names.split())
            if "atlas-local-contents" in class_names.split():
                self._in_local_contents = True
        identifier = attributes.get("id")
        if identifier is not None:
            self.ids.append(identifier)
        href = attributes.get("href")
        src = attributes.get("src")

        if tag == "link" and href is not None:
            rel = set((attributes.get("rel") or "").split())
            if "canonical" in rel:
                self.canonicals.append(href)
            elif rel.intersection({"stylesheet", "icon", "preload", "manifest"}):
                self.static_urls.append(href)

        if tag == "meta":
            key = attributes.get("name") or attributes.get("property")
            content = attributes.get("content")
            if key is not None and content is not None:
                self.metadata.setdefault(key, []).append(content)

        if href is not None:
            if tag in {"a", "area"}:
                self.hyperlinks.append(href)
            if tag == "a":
                self.return_links.append(href)
                if self._in_local_contents:
                    self._current_local_contents_link = (href, [])

        if tag == "a" and self._current_heading is not None and "headerlink" in (class_names or "").split():
            self._in_heading_permalink = True

        if src is not None:
            self.static_urls.append(src)
            if tag == "img":
                context = self._open_logo_contexts[-1][1] if self._open_logo_contexts else None
                self.identity_images.append((context, attributes))

        for url in (href, src):
            if url is not None and url.startswith("/") and not url.startswith("//"):
                self.root_relative_urls.append(url)

        if tag in {"h1", "h2", "h3", "h4", "h5", "h6"}:
            self._current_heading = (int(tag[1]), attributes.get("id") or "", [])

    def handle_data(self, data: str) -> None:
        self.text.append(data)
        if self._current_pre_code is not None:
            self._current_pre_code.append(data)
        if self._current_heading is not None and not self._in_heading_permalink:
            self._current_heading[2].append(data)
        if self._current_local_contents_link is not None:
            self._current_local_contents_link[1].append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag == "pre" and self._current_pre_code is not None:
            self.pre_code_blocks.append("".join(self._current_pre_code))
            self._current_pre_code = None
        if tag == "section" and self._open_section_panels:
            self._open_section_panels.pop()
        if self._open_logo_contexts and self._open_logo_contexts[-1][0] == tag:
            self._open_logo_contexts.pop()
        if tag in {"h1", "h2", "h3", "h4", "h5", "h6"} and self._current_heading is not None:
            level, identifier, text = self._current_heading
            self.headings.append((level, identifier, " ".join("".join(text).split())))
            self._current_heading = None
        if tag == "a" and self._current_local_contents_link is not None:
            href, text = self._current_local_contents_link
            self.local_contents_links.append((href, " ".join("".join(text).split())))
            self._current_local_contents_link = None
        if tag == "a" and self._in_heading_permalink:
            self._in_heading_permalink = False
        if tag == "aside" and self._in_local_contents:
            self._in_local_contents = False


def fail(message: str) -> None:
    raise ValueError(message)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as file:
        for chunk in iter(lambda: file.read(1024 * 1024), b""):
            digest.update(chunk)

    return digest.hexdigest()


def validate_safe_font_path(value: object, pattern: re.Pattern[str], description: str) -> str:
    if not isinstance(value, str) or not pattern.fullmatch(value):
        fail(f"Generated font manifest has an unsafe {description} path: {value}")

    return value


def validate_font_distribution(site_directory: Path) -> None:
    manifest_path = site_directory / FONT_MANIFEST_FILE
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        fail(f"Generated font manifest is malformed: {error}")

    if not isinstance(manifest, dict) or manifest.get("schema_version") != 1:
        fail("Generated font manifest must declare schema version 1")
    if set(manifest) != {"schema_version", "fonts"} or not isinstance(manifest["fonts"], list):
        fail("Generated font manifest must contain only schema_version and fonts")

    fonts = manifest["fonts"]
    if len(fonts) != len(EXPECTED_FONT_DISTRIBUTIONS):
        fail("Generated font manifest does not declare the complete release-pinned font set")

    expected_by_family = {font["family"]: font for font in EXPECTED_FONT_DISTRIBUTIONS}
    seen_families: set[str] = set()
    for font in fonts:
        if not isinstance(font, dict) or set(font) != FONT_ENTRY_KEYS:
            fail("Generated font manifest entry has an invalid schema")

        family = font.get("family")
        if not isinstance(family, str) or family in seen_families:
            fail("Generated font manifest has a missing or duplicate font family")
        seen_families.add(family)

        font_file = validate_safe_font_path(font.get("file"), FONT_FILE_NAME, "file")
        license_data = font.get("license")
        if not isinstance(license_data, dict) or set(license_data) != FONT_LICENSE_KEYS:
            fail(f"Generated font manifest has invalid license metadata for {family}")
        license_file = validate_safe_font_path(license_data.get("file"), FONT_LICENSE_FILE_NAME, "license file")

        expected = expected_by_family.get(family)
        if expected is None or font != expected:
            fail(f"Generated font manifest metadata differs from the release pin: {family}")

        distributed_font = site_directory / "assets/fonts" / font_file
        if not distributed_font.is_file() or distributed_font.is_symlink():
            fail(f"Generated artifact is missing a safe distributed font: {font_file}")
        if sha256(distributed_font) != font["sha256"]:
            fail(f"Generated font digest does not match manifest: {font_file}")

        distributed_license = site_directory / "assets/fonts" / license_file
        if not distributed_license.is_file() or distributed_license.is_symlink():
            fail(f"Generated artifact is missing a safe font license: {license_file}")
        if sha256(distributed_license) != license_data["sha256"]:
            fail(f"Generated font license digest does not match manifest: {license_file}")

    if seen_families != set(expected_by_family):
        fail("Generated font manifest does not declare the complete release-pinned font set")


def parse_html(path: Path, cache: dict[Path, DocumentParser] | None = None) -> DocumentParser:
    path = path.resolve()
    if cache is not None and path in cache:
        return cache[path]

    parser = DocumentParser()
    parser.feed(path.read_text(encoding="utf-8"))
    parser.close()

    if cache is not None:
        cache[path] = parser

    return parser


def css_blocks(source: str) -> list[tuple[str, str]]:
    source = re.sub(r"/\*.*?\*/", "", source, flags=re.DOTALL)
    blocks: list[tuple[str, str]] = []
    position = 0
    while position < len(source):
        opening = source.find("{", position)
        if opening == -1:
            break
        prelude = " ".join(source[position:opening].split())
        depth = 1
        cursor = opening + 1
        quote: str | None = None
        while cursor < len(source) and depth:
            character = source[cursor]
            if quote is not None:
                if character == quote and source[cursor - 1] != "\\":
                    quote = None
            elif character in {'"', "'"}:
                quote = character
            elif character == "{":
                depth += 1
            elif character == "}":
                depth -= 1
            cursor += 1
        if depth:
            fail("Generated stylesheet contains an unterminated rule")
        blocks.append((prelude, source[opening + 1 : cursor - 1]))
        position = cursor

    return blocks


def css_rule_matches(
    prelude: str,
    body: str,
    required_selectors: frozenset[str],
    required_declarations: dict[str, str],
) -> bool:
    selectors = frozenset(selector.strip() for selector in prelude.split(","))
    declarations = css_declarations(body)

    return required_selectors.issubset(selectors) and all(
        declarations.get(name) == value for name, value in required_declarations.items()
    )


def css_declarations(body: str) -> dict[str, str]:
    return {
        name.strip(): value.strip()
        for declaration in body.split(";")
        if ":" in declaration
        for name, value in [declaration.split(":", 1)]
    }

def validate_control_styles(stylesheet: str) -> None:
    blocks = css_blocks(stylesheet)
    if not any(
        css_rule_matches(prelude, body, frozenset({COPY_CONTROL_SELECTOR}), COPY_CONTROL_DECLARATIONS)
        for prelude, body in blocks
        if not prelude.startswith("@")
    ):
        fail("Generated stylesheet is missing approved copy control sizing")

    if not any(
        css_rule_matches(prelude, body, NATIVE_CONTROL_SELECTORS, NATIVE_CONTROL_DECLARATIONS)
        for prelude, body in blocks
        if not prelude.startswith("@")
    ):
        fail("Generated stylesheet is missing 44px native control targets across reviewed viewports")


def relative_luminance(color: str) -> float:
    if HEX_COLOR.fullmatch(color) is None:
        fail(f"Generated stylesheet footer token is not a six-digit color: {color}")

    channels = (int(color[index : index + 2], 16) / 255 for index in (1, 3, 5))
    linear = (
        channel / 12.92 if channel <= 0.04045 else ((channel + 0.055) / 1.055) ** 2.4
        for channel in channels
    )
    red, green, blue = linear

    return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue)


def contrast_ratio(foreground: str, background: str) -> float:
    lighter, darker = sorted((relative_luminance(foreground), relative_luminance(background)), reverse=True)

    return (lighter + 0.05) / (darker + 0.05)


def selector_specificity(selector: str) -> tuple[int, int, int]:
    return (
        len(re.findall(r"#[A-Za-z_][A-Za-z0-9_-]*", selector)),
        len(re.findall(r"\.[A-Za-z_][A-Za-z0-9_-]*|\[[^]]+\]|:(?!:)[A-Za-z_][A-Za-z0-9_-]*", selector)),
        len(re.findall(r"(?<![.#\w-])[A-Za-z][A-Za-z0-9-]*", selector)),
    )


def footer_tokens(blocks: list[tuple[str, str]], selectors: frozenset[str]) -> dict[str, str]:
    for prelude, body in blocks:
        rule_selectors = frozenset(selector.strip() for selector in prelude.split(","))
        declarations = css_declarations(body)
        if selectors.issubset(rule_selectors) and all(name in declarations for name in FOOTER_TOKEN_NAMES):
            return {name: declarations[name] for name in FOOTER_TOKEN_NAMES}

    fail("Generated stylesheet is missing footer-local dark-surface tokens")


def validate_footer_styles(stylesheet: str) -> None:
    blocks = css_blocks(stylesheet)
    for selectors, declarations in FOOTER_RULES:
        if not any(css_rule_matches(prelude, body, selectors, declarations) for prelude, body in blocks):
            fail("Generated stylesheet is missing footer-local dark-surface styles")

    if selector_specificity(FOOTER_LINK_SELECTOR) <= selector_specificity(MATERIAL_FOOTER_LINK_SELECTOR):
        fail("Generated stylesheet footer link selector does not outrank pinned Material selector")
    if not any(
        css_rule_matches(prelude, body, frozenset({FOOTER_LINK_SELECTOR}), {"color": "var(--fight-footer-link)"})
        for prelude, body in blocks
    ):
        fail("Generated stylesheet is missing a footer link rule that outranks Material")
    if not any(
        css_rule_matches(prelude, body, FOCUSED_FOOTER_LINK_SELECTORS, {"outline-color": "var(--fight-footer-focus)"})
        for prelude, body in blocks
    ):
        fail("Generated stylesheet is missing a focused footer link rule that outranks Material")

    for selectors in (FOOTER_DEFAULT_TOKEN_SELECTORS, FOOTER_SLATE_TOKEN_SELECTORS):
        tokens = footer_tokens(blocks, selectors)
        if contrast_ratio(tokens["--fight-footer-link"], tokens["--fight-footer-surface"]) < 4.5:
            fail("Generated stylesheet footer link contrast is below 4.5:1")
        if contrast_ratio(tokens["--fight-footer-focus"], tokens["--fight-footer-surface"]) < 3:
            fail("Generated stylesheet footer focus contrast is below 3:1")


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


def local_hyperlink_target(site_directory: Path, document: Path, value: str) -> tuple[Path, str] | None:
    parsed = urlsplit(value)
    production = urlsplit(PRODUCTION_ROOT)

    if parsed.scheme:
        scheme = parsed.scheme.lower()
        if scheme not in ALLOWED_EXTERNAL_HYPERLINK_SCHEMES:
            fail(
                "Generated document uses an unsupported hyperlink scheme: "
                f"{document.relative_to(site_directory)}: {scheme}",
            )
        if parsed.scheme != production.scheme or parsed.netloc != production.netloc:
            return None
        path = unquote(parsed.path)
        if not path.startswith(production.path):
            fail(f"Generated document links outside production docs base: {document.relative_to(site_directory)}: {value}")
        candidate = site_directory / path.removeprefix(production.path)
    else:
        if parsed.netloc:
            fail(
                "Generated document uses an unsupported hyperlink scheme: "
                f"{document.relative_to(site_directory)}: protocol-relative",
            )
        path = unquote(parsed.path)
        if not path and not parsed.fragment:
            return None
        if path.startswith("/"):
            if not path.startswith("/fight-common/"):
                fail(f"Generated document links outside production docs base: {document.relative_to(site_directory)}: {value}")
            candidate = site_directory / path.removeprefix("/fight-common/")
        else:
            candidate = document.parent / path

    resolved = candidate.resolve()
    try:
        resolved.relative_to(site_directory.resolve())
    except ValueError:
        fail(f"Generated document link escapes the artifact: {document.relative_to(site_directory)}: {value}")

    if resolved.is_dir():
        resolved /= "index.html"

    return resolved, unquote(parsed.fragment)


def has_element(parser: DocumentParser, tag: str, **expected: str | None) -> bool:
    return any(
        element_tag == tag
        and all(
            name in attributes if value is None else attributes.get(name) == value
            for name, value in expected.items()
        )
        for element_tag, attributes in parser.elements
    )


def has_identity_image(
    parser: DocumentParser,
    site_directory: Path,
    document: Path,
    asset: str,
    class_name: str,
    context: str | None = None,
) -> bool:
    expected_asset = site_directory / asset
    return any(
        image_context == context
        and local_static_path(site_directory, document, attributes.get("src") or "") == expected_asset
        and class_name in (attributes.get("class") or "").split()
        for image_context, attributes in parser.identity_images
    )


def metadata_value(parser: DocumentParser, name: str, document: Path, site_directory: Path) -> str:
    values = parser.metadata.get(name, [])
    if len(values) != 1 or not values[0].strip():
        fail(
            "Generated document must have exactly one non-empty metadata value: "
            f"{document.relative_to(site_directory)}: {name}",
        )

    return values[0]


def has_mail_configuration_controls(parser: DocumentParser) -> bool:
    if not has_element(parser, "section", **{"data-atlas-format-tabs": None}):
        return False
    if not has_element(parser, "div", role="tablist", **{"aria-label": "Configuration format"}):
        return False
    if not has_element(parser, "span", **COPY_STATUS_ATTRIBUTES):
        fail("Generated Mail article is missing an accessible copy status announcement")

    for position, (format_name, format_label, filename) in enumerate(MAIL_CONFIGURATION_FORMATS):
        tab_id = f"atlas-mail-config-{format_name}-tab"
        panel_id = f"atlas-mail-config-{format_name}-panel"
        selected = "true" if position == 0 else "false"
        tabindex = "0" if position == 0 else "-1"
        if not has_element(
            parser,
            "button",
            role="tab",
            id=tab_id,
            **{
                "data-atlas-format": format_name,
                "aria-controls": panel_id,
                "aria-selected": selected,
                "tabindex": tabindex,
            },
        ):
            return False
        if not has_element(
            parser,
            "section",
            role="tabpanel",
            id=panel_id,
            **{
                "data-atlas-format-panel": format_name,
                "aria-labelledby": tab_id,
            },
        ):
            return False
        panel_attributes = next(
            attributes
            for tag, attributes in parser.elements
            if tag == "section" and attributes.get("id") == panel_id
        )
        if (position == 0 and "hidden" in panel_attributes) or (position > 0 and "hidden" not in panel_attributes):
            return False
        panel_elements = parser.format_panel_elements.get(format_name, [])
        if not any(
            tag == "code" and attributes.get("data-atlas-filename") == filename
            for tag, attributes in panel_elements
        ):
            return False
        if not any(
            tag == "button"
            and "data-atlas-copy" in attributes
            and attributes.get("aria-label") == f"Copy {format_label} configuration"
            for tag, attributes in panel_elements
        ):
            return False

    return True


def validate_mail_article_semantics(parser: DocumentParser) -> None:
    if not parser.headings or parser.headings[0] != MAIL_ARTICLE_HEADINGS[0]:
        fail("Generated Mail article must begin with one logical H1: Mail")
    if [heading for heading in parser.headings if heading[0] == 1] != [MAIL_ARTICLE_HEADINGS[0]]:
        fail("Generated Mail article must contain exactly one logical H1: Mail")

    previous_level = 0
    for level, _, _ in parser.headings:
        if level > previous_level + 1:
            fail("Generated Mail article has an invalid heading hierarchy")
        previous_level = level

    for heading in MAIL_ARTICLE_HEADINGS[1:]:
        if heading not in parser.headings:
            fail(f"Generated Mail article is missing required semantic heading: {heading[2]}")
    if parser.local_contents_links != list(MAIL_ARTICLE_LOCAL_CONTENTS):
        fail("Generated Mail article local contents must link to its semantic H2 sections")


def validate_mail_article_title_order(parser: DocumentParser) -> None:
    title_position = next(
        (
            position
            for position, (tag, attributes) in enumerate(parser.elements)
            if tag == "h1" and attributes.get("id") == "mail"
        ),
        None,
    )
    if title_position is None:
        fail("Generated Mail article must render its title before supporting shell affordances")

    for tag, class_name in (
        ("nav", "atlas-breadcrumbs"),
        ("dl", "atlas-article-metadata"),
        ("figure", "atlas-relationship-diagram"),
        ("aside", "atlas-consequential-callout"),
    ):
        supporting_position = next(
            (
                position
                for position, (element_tag, attributes) in enumerate(parser.elements)
                if element_tag == tag and class_name in (attributes.get("class") or "").split()
            ),
            None,
        )
        if supporting_position is None or title_position > supporting_position:
            fail("Generated Mail article must render its title before supporting shell affordances")


def validate_mail_article_skip_navigation(parser: DocumentParser, site_directory: Path) -> None:
    article_start = next(
        (
            attributes
            for tag, attributes in parser.elements
            if tag == "h1"
            and attributes.get("id") == "mail"
            and "data-atlas-article-start" in attributes
            and attributes.get("tabindex") == "-1"
        ),
        None,
    )
    accessibility_script = site_directory / "javascripts/atlas-article-accessibility.js"
    if article_start is None or not accessibility_script.is_file():
        fail("Generated Mail skip navigation must target the focusable article start")

    script = accessibility_script.read_text(encoding="utf-8")
    required_behavior = (
        "[data-atlas-article-start]",
        '[data-md-component="skip"] .md-skip',
        "skip.href = `#${articleStart.id}`",
        "articleStart.focus()",
    )
    if any(behavior not in script for behavior in required_behavior):
        fail("Generated Mail skip navigation must target the focusable article start")


def validate_quick_start_article(parser: DocumentParser, search_data: dict[str, object]) -> None:
    quick_start_text = "".join(parser.text)
    for required_text in QUICK_START_ARTICLE_TEXT:
        if required_text not in quick_start_text:
            fail(f"Generated Quick Start article is missing required content: {required_text}")
    for anchor in QUICK_START_ARTICLE_ANCHORS:
        if anchor not in parser.ids:
            fail(f"Generated Quick Start article is missing required article anchor: #{anchor}")
    if tuple(heading for heading in parser.headings if heading[0] <= 2) != QUICK_START_ARTICLE_HEADINGS:
        fail("Generated Quick Start article must preserve its exact H1/H2 hierarchy and order")
    for next_path in QUICK_START_NEXT_PATHS:
        if next_path not in parser.return_links:
            fail(f"Generated Quick Start article is missing required next path: {next_path}")
    if len(parser.pre_code_blocks) < QUICK_START_REQUIRED_PHP_CODE_BLOCKS:
        fail("Generated Quick Start article is missing its required executable PHP code blocks")
    highlighted_code_blocks = sum(
        1
        for tag, attributes in parser.elements
        if tag == "div" and "highlight" in (attributes.get("class") or "").split()
    )
    if highlighted_code_blocks < QUICK_START_REQUIRED_PHP_CODE_BLOCKS:
        fail("Generated Quick Start article is missing syntax highlighting for its executable PHP code blocks")
    for token_class in ("k", "s1"):
        if token_class not in parser.classes:
            fail(f"Generated Quick Start article is missing PHP syntax token class: {token_class}")
    for region in QUICK_START_EXECUTABLE_REGIONS:
        if region not in quick_start_text:
            fail(f"Generated Quick Start article is missing executable region: {region}")
    if QUICK_START_COPY_FEATURE not in quick_start_text:
        fail("Generated Quick Start article must enable Material's runtime code-copy controls")

    entries = search_data.get("docs")
    if not isinstance(entries, list) or not any(
        isinstance(entry, dict) and entry.get("location") == "quick-start/" for entry in entries
    ):
        fail("Generated search index is missing the Quick Start entry")


def validate(site_directory: Path) -> None:
    if not site_directory.is_dir() or site_directory.is_symlink():
        fail(f"Generated site directory is missing or unsafe: {site_directory}")
    site_directory = site_directory.resolve()

    for required in REQUIRED_FILES:
        if not (site_directory / required).is_file():
            fail(f"Generated artifact is missing required file: {required}")

    for asset in REQUIRED_LOCAL_ASSETS:
        if not (site_directory / asset).is_file():
            fail(f"Generated artifact is missing required local asset: {asset}")
    validate_font_distribution(site_directory)
    stylesheet = (site_directory / "stylesheets/extra.css").read_text(encoding="utf-8")
    validate_control_styles(stylesheet)
    validate_footer_styles(stylesheet)

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
    parsed_documents: dict[Path, DocumentParser] = {}

    published_routes = set(routes.values())
    for route in CANONICAL_ROUTES:
        if f"{PRODUCTION_ROOT}{route}" not in published_routes:
            fail(f"Generated artifact is missing canonical route: {route}")

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
        target_document = published_document_for_location(location, routes)
        if target_document is None:
            fail(f"Generated search location does not resolve to output: {location}")
        fragment = unquote(urlsplit(location).fragment)
        if fragment and fragment not in parse_html(target_document, parsed_documents).ids:
            fail(f"Generated search location does not resolve to output fragment: {location}")

    descriptions: set[str] = set()
    for document in documents:
        parser = parse_html(document, parsed_documents)
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
        for hyperlink in parser.hyperlinks:
            target = local_hyperlink_target(site_directory, document, hyperlink)
            if target is None:
                continue
            target_document, fragment = target
            if not target_document.is_file():
                fail(
                    "Generated document links to missing local output: "
                    f"{document.relative_to(site_directory)}: {hyperlink}",
                )
            if fragment and target_document.suffix == ".html" and fragment not in parse_html(target_document, parsed_documents).ids:
                fail(
                    "Generated document links to missing local fragment: "
                    f"{document.relative_to(site_directory)}: {hyperlink}",
                )

        if routes[document] not in {PRODUCTION_ROOT, *(f"{PRODUCTION_ROOT}{route}" for route in CANONICAL_ROUTES)}:
            continue

        description = metadata_value(parser, "description", document, site_directory)
        if description == GENERIC_SITE_DESCRIPTION or description in descriptions:
            fail(f"Generated document description must be page-specific: {document.relative_to(site_directory)}")
        descriptions.add(description)
        if metadata_value(parser, "og:description", document, site_directory) != description:
            fail(f"Generated document Open Graph description differs from its description: {document.relative_to(site_directory)}")
        metadata_value(parser, "og:title", document, site_directory)
        if metadata_value(parser, "og:image", document, site_directory) != SOCIAL_IMAGE:
            fail(f"Generated document must use the canonical Fight Common social card: {document.relative_to(site_directory)}")
        if metadata_value(parser, "twitter:card", document, site_directory) != "summary_large_image":
            fail(f"Generated document must use a large-image Twitter card: {document.relative_to(site_directory)}")
        if metadata_value(parser, "twitter:image", document, site_directory) != SOCIAL_IMAGE:
            fail(f"Generated document must use the canonical Fight Common social card: {document.relative_to(site_directory)}")

    error_parser = parse_html(site_directory / "404.html", parsed_documents)
    if PRODUCTION_ROOT not in error_parser.return_links:
        fail("Generated 404 page is missing a return link to the production docs root")
    if not has_identity_image(
        error_parser,
        site_directory,
        site_directory / "404.html",
        "assets/identity/fight-mark-dark.svg",
        "atlas-not-found__mark",
    ):
        fail("Generated 404 page is missing the dark-surface identity variant")

    homepage = parse_html(site_directory / "index.html", parsed_documents)
    if not has_identity_image(
        homepage,
        site_directory,
        site_directory / "index.html",
        "assets/identity/fight-mark-light.svg",
        "atlas-header__mark--light",
        "header",
    ):
        fail("Generated homepage is missing the light-surface header identity variant")
    if not has_identity_image(
        homepage,
        site_directory,
        site_directory / "index.html",
        "assets/identity/fight-mark-dark.svg",
        "atlas-header__mark--dark",
        "header",
    ):
        fail("Generated homepage is missing the dark-surface header identity variant")
    if not has_identity_image(
        homepage,
        site_directory,
        site_directory / "index.html",
        "assets/identity/fight-mark-light.svg",
        "atlas-header__mark--light",
        "drawer",
    ):
        fail("Generated homepage drawer is missing the light-surface identity variant")
    if not has_identity_image(
        homepage,
        site_directory,
        site_directory / "index.html",
        "assets/identity/fight-mark-dark.svg",
        "atlas-header__mark--dark",
        "drawer",
    ):
        fail("Generated homepage drawer is missing the dark-surface identity variant")
    if not has_identity_image(
        homepage,
        site_directory,
        site_directory / "index.html",
        "assets/identity/fight-mark-dark.svg",
        "atlas-footer__mark",
    ):
        fail("Generated homepage footer is missing the dark-surface identity variant")
    homepage_text = "".join(homepage.text)
    for required_text in HOMEPAGE_TEXT:
        if required_text not in homepage_text:
            fail(f"Generated homepage is missing required content: {required_text}")
    for required_link in HOMEPAGE_LINKS:
        if required_link not in homepage.return_links:
            fail(f"Generated homepage is missing direct component route: {required_link}")
    homepage_html = (site_directory / "index.html").read_text(encoding="utf-8")
    for route, component, ownership in HOMEPAGE_OWNERSHIP_RAILS:
        expected_rail = (
            f'<a href="{route}">{component}</a>\n'
            f'  <span class="atlas-ownership-rail">{ownership}</span>'
        )
        if expected_rail not in homepage_html:
            fail(f"Generated homepage is missing complete ownership rail: {component}")

    mail_article = parse_html(site_directory / "components/mail/index.html", parsed_documents)
    mail_article_text = "".join(mail_article.text)
    for required_text in MAIL_ARTICLE_TEXT:
        if required_text not in mail_article_text:
            fail(f"Generated Mail article is missing required content: {required_text}")
    for required_class in MAIL_ARTICLE_CLASSES:
        if required_class not in mail_article.classes:
            fail(f"Generated Mail article is missing required shell element: {required_class}")
    for anchor in MAIL_ARTICLE_ANCHORS:
        if anchor not in mail_article.ids:
            fail(f"Generated Mail article is missing required article anchor: #{anchor}")
    validate_mail_article_title_order(mail_article)
    validate_mail_article_semantics(mail_article)
    validate_mail_article_skip_navigation(mail_article, site_directory)
    if not has_mail_configuration_controls(mail_article):
        fail("Generated Mail article is missing semantic configuration tab and copy controls")

    quick_start_article = parse_html(site_directory / "quick-start/index.html", parsed_documents)
    validate_quick_start_article(quick_start_article, search_data)


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
