#!/usr/bin/env python3

"""Focused regression checks for documentation artifact parser caching."""

import importlib.util
import tempfile
import unittest
from pathlib import Path


VALIDATOR = Path(__file__).parents[2] / "scripts" / "validate_docs_artifact.py"
SPEC = importlib.util.spec_from_file_location("validate_docs_artifact", VALIDATOR)
assert SPEC is not None and SPEC.loader is not None
validator = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(validator)


class DocsArtifactValidatorTest(unittest.TestCase):
    def test_that_parsed_documents_are_reused_from_the_cache(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            document = Path(directory) / "index.html"
            document.write_text('<h1 id="start">Start</h1>', encoding="utf-8")
            cache = {}

            first = validator.parse_html(document, cache)
            second = validator.parse_html(document, cache)

            self.assertIs(first, second)

    def test_that_quick_start_contract_rejects_missing_structure_and_next_paths(self) -> None:
        parser = self.quick_start_parser()

        validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser.ids.remove("the-event")
        with self.assertRaisesRegex(ValueError, "#the-event"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.return_links.remove("../frameworks/framework-support/")
        with self.assertRaisesRegex(ValueError, "../frameworks/framework-support/"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        with self.assertRaisesRegex(ValueError, "missing the Quick Start entry"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "architecture/"}]})

        parser = self.quick_start_parser()
        parser.elements = [
            (tag, {**attributes, "class": "code-surface"})
            if attributes.get("id") == "quick-start-composition"
            else (tag, attributes)
            for tag, attributes in parser.elements
        ]
        with self.assertRaisesRegex(ValueError, "syntax highlighting for executable surface: #quick-start-composition"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.code_blocks_by_anchor["quick-start-order-processed-event"] = [""]
        with self.assertRaisesRegex(ValueError, "nonempty executable PHP code surface: #quick-start-order-processed-event"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser("<p>content.code.copy</p>")
        with self.assertRaisesRegex(ValueError, "missing Material runtime configuration"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser(
            '<p>content.code.copy</p><script id="__config" type="application/json">{</script>',
        )
        with self.assertRaisesRegex(ValueError, "malformed Material runtime configuration"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser(
            '<script id="__config" type="application/json">{"features":[]}</script>',
        )
        with self.assertRaisesRegex(ValueError, "runtime code-copy controls"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

    def test_that_quick_start_contract_accepts_accurate_rewording(self) -> None:
        parser = self.quick_start_parser()
        parser.headings = [
            (level, identifier, f"Reworded {identifier}")
            for level, identifier, _ in parser.headings
        ]
        parser.text = [
            text.replace("Framework-Neutral Quick Start", "Portable guided introduction")
            .replace("InMemoryCommandRouter", "Command router")
            .replace("RoutingCommandBus", "Command bus")
            .replace("SimpleEventDispatcher", "Event dispatcher")
            .replace("TransactionalUnitOfWork", "Unit of work")
            .replace("EventDispatchFailed", "dispatch failure")
            .replace("PaymentNotSuccessful", "unsuccessful payment")
            .replace("FulfillmentRequester", "fulfillment port")
            .replace("Only succeeded requests fulfillment.", "Fulfillment follows a successful payment.")
            .replace("Both pending and failed", "Pending and failed requests")
            .replace("redelivery or retry of FulfillOrder", "a delivery retry")
            .replace("Production transaction boundary", "Production transaction guidance")
            .replace("no queue, saga, or durable outbox", "without prescribing infrastructure")
            for text in parser.text
        ]

        validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

    def quick_start_parser(
        self,
        runtime_configuration: str = '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
    ) -> object:
        parser = validator.DocumentParser()
        parser.feed(
            '<h1 id="framework-neutral-quick-start">Framework-Neutral Quick Start</h1>'
            '<p>composer require johnnickell/fight-common '
            'Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested. '
            'The starter repositories are still being prepared for that release. '
            'InMemoryCommandRouter RoutingCommandBus SimpleEventDispatcher TransactionalUnitOfWork '
            'EventDispatchFailed PaymentNotSuccessful FulfillmentRequester Only succeeded requests fulfillment. '
            'Both pending and failed redelivery or retry of FulfillOrder Production transaction boundary '
            'no queue, saga, or durable outbox</p>'
            '<h2 id="pick-your-framework">Pick your framework</h2>'
            '<h2 id="the-supporting-domain-type">The supporting domain type</h2>'
            '<h2 id="the-command">The command</h2>'
            '<h2 id="the-event">The event</h2>'
            '<h2 id="the-command-handler">The command handler</h2>'
            '<h2 id="the-follow-up-command">The follow-up command</h2>'
            '<h2 id="the-event-subscriber">The event subscriber</h2>'
            '<h2 id="the-fulfillment-handler">The fulfillment handler</h2>'
            '<h2 id="wire-the-application">Wire the application</h2>'
            '<h2 id="dispatch-the-command">Dispatch the command</h2>'
            '<a href="https://github.com/johnnickell/project-symfony">Symfony</a>'
            '<a href="https://github.com/johnnickell/project-laravel">Laravel</a>'
            '<a href="https://github.com/johnnickell/project-yii">Yii</a>'
            '<a href="https://github.com/johnnickell/project-codeigniter">CodeIgniter</a>'
            '<a href="https://github.com/johnnickell/project-slim">Slim</a>'
            '<a href="../architecture/">Architecture</a>'
            '<a href="../components/messaging/">Messaging</a>'
            '<a href="../components/repositories/">Repositories</a>'
            '<a href="../frameworks/framework-support/">Framework Support</a>'
            '<div id="quick-start-order-id" class="highlight"><pre><code>final readonly class OrderId</code></pre></div>'
            '<div id="quick-start-process-order-command" class="highlight"><pre><code>final readonly class ProcessOrder implements Command</code></pre></div>'
            '<div id="quick-start-order-processed-event" class="highlight"><pre><code>final readonly class OrderProcessed implements Event</code></pre></div>'
            '<div id="quick-start-process-order-handler" class="highlight"><pre><code>final readonly class ProcessOrderHandler implements CommandHandler</code></pre></div>'
            '<div id="quick-start-fulfill-order-command" class="highlight"><pre><code>final readonly class FulfillOrder implements Command</code></pre></div>'
            '<div id="quick-start-order-processed-subscriber" class="highlight"><pre><code>final readonly class OrderProcessedSubscriber implements EventSubscriber</code></pre></div>'
            '<div id="quick-start-fulfill-order-handler" class="highlight"><pre><code>final readonly class FulfillOrderHandler implements CommandHandler</code></pre></div>'
            '<div id="quick-start-composition" class="highlight"><pre><code>final <span class="k">class</span> OrderProcessingExample CustomerId::fromString(<span class="s1">\'CUSTOMER-42\'</span>) require $_SERVER[\'FIGHT_AUTOLOAD\'] ?? __DIR__.\'/vendor/autoload.php\';</code></pre></div><button class="md-code__button" data-md-type="copy" data-clipboard-target="#quick-start-composition code"></button>'
            '<div id="quick-start-dispatch" class="highlight"><pre><code>OrderProcessingExample::process().PHP_EOL</code></pre></div>'
            + runtime_configuration,
        )

        return parser


if __name__ == "__main__":
    unittest.main()
