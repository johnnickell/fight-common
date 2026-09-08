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

    def test_that_quick_start_contract_rejects_missing_structure_symbols_and_next_paths(self) -> None:
        parser = validator.DocumentParser()
        parser.feed(
            '<h1 id="framework-neutral-quick-start">Framework-Neutral Quick Start</h1>'
            '<p>composer require johnnickell/fight-common '
            'Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested. '
            'InMemoryCommandRouter RoutingCommandBus SimpleEventDispatcher TransactionalUnitOfWork '
            'EventDispatchFailed PaymentNotSuccessful FulfillmentRequester Only succeeded requests fulfillment. '
            'Both pending and failed redelivery or retry of FulfillOrder Production transaction boundary '
            'no queue, saga, or durable outbox</p>'
            '<h2 id="prerequisites">Prerequisites</h2>'
            '<h2 id="process-an-order">Process an order</h2>'
            '<h2 id="complete-executable-example">Complete executable example</h2>'
            '<h2 id="ownership-and-flow">Ownership and flow</h2>'
            '<h2 id="payment-guard-and-retries">Payment guard and retries</h2>'
            '<h2 id="continue">Continue</h2>'
            '<a href="../architecture/">Architecture</a>'
            '<a href="../components/messaging/">Messaging</a>'
            '<a href="../components/repositories/">Repositories</a>'
            '<a href="../frameworks/framework-support/">Framework Support</a>'
            '<div id="quick-start-composition" class="highlight"><pre><code>final <span class="k">class</span> OrderProcessingExample CustomerId::fromString(<span class="s1">\'CUSTOMER-42\'</span>) require $_SERVER[\'FIGHT_AUTOLOAD\'] ?? __DIR__.\'/vendor/autoload.php\';</code></pre></div><button class="md-code__button" data-md-type="copy" data-clipboard-target="#quick-start-composition code"></button>'
            '<div id="quick-start-process-order" class="highlight"><pre><code>final readonly class CustomerId final readonly class ProcessOrder implements Command</code></pre></div>'
            '<div id="quick-start-complete-example" class="highlight"><pre><code>final readonly class FulfillOrder implements Command provider-token-for-customer-42 OrderProcessingExample::process().PHP_EOL</code></pre></div>'
            '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
        )

        validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser.ids.remove("payment-guard-and-retries")
        with self.assertRaisesRegex(ValueError, "#payment-guard-and-retries"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.text = [text.replace("SimpleEventDispatcher", "EventDispatcher") for text in parser.text]
        with self.assertRaisesRegex(ValueError, "SimpleEventDispatcher"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.return_links.remove("../frameworks/framework-support/")
        with self.assertRaisesRegex(ValueError, "../frameworks/framework-support/"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        with self.assertRaisesRegex(ValueError, "missing the Quick Start entry"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "architecture/"}]})

        parser = self.quick_start_parser()
        parser.text = [text.replace("final readonly class CustomerId", "final readonly class Customer") for text in parser.text]
        with self.assertRaisesRegex(ValueError, "missing executable region"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.headings[3] = (2, "complete-executable-example", "Executable reference")
        with self.assertRaisesRegex(ValueError, "exact H1/H2 hierarchy"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.classes.remove("k")
        with self.assertRaisesRegex(ValueError, "PHP syntax token class: k"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

        parser = self.quick_start_parser()
        parser.text = [text.replace("content.code.copy", "content.code.annotate") for text in parser.text]
        with self.assertRaisesRegex(ValueError, "runtime code-copy controls"):
            validator.validate_quick_start_article(parser, {"docs": [{"location": "quick-start/"}]})

    @staticmethod
    def quick_start_parser() -> object:
        parser = validator.DocumentParser()
        parser.feed(
            '<h1 id="framework-neutral-quick-start">Framework-Neutral Quick Start</h1>'
            '<p>composer require johnnickell/fight-common '
            'Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested. '
            'InMemoryCommandRouter RoutingCommandBus SimpleEventDispatcher TransactionalUnitOfWork '
            'EventDispatchFailed PaymentNotSuccessful FulfillmentRequester Only succeeded requests fulfillment. '
            'Both pending and failed redelivery or retry of FulfillOrder Production transaction boundary '
            'no queue, saga, or durable outbox</p>'
            '<h2 id="prerequisites">Prerequisites</h2>'
            '<h2 id="process-an-order">Process an order</h2>'
            '<h2 id="complete-executable-example">Complete executable example</h2>'
            '<h2 id="ownership-and-flow">Ownership and flow</h2>'
            '<h2 id="payment-guard-and-retries">Payment guard and retries</h2>'
            '<h2 id="continue">Continue</h2>'
            '<a href="../architecture/">Architecture</a>'
            '<a href="../components/messaging/">Messaging</a>'
            '<a href="../components/repositories/">Repositories</a>'
            '<a href="../frameworks/framework-support/">Framework Support</a>'
            '<div id="quick-start-composition" class="highlight"><pre><code>final <span class="k">class</span> OrderProcessingExample CustomerId::fromString(<span class="s1">\'CUSTOMER-42\'</span>) require $_SERVER[\'FIGHT_AUTOLOAD\'] ?? __DIR__.\'/vendor/autoload.php\';</code></pre></div><button class="md-code__button" data-md-type="copy" data-clipboard-target="#quick-start-composition code"></button>'
            '<div id="quick-start-process-order" class="highlight"><pre><code>final readonly class CustomerId final readonly class ProcessOrder implements Command</code></pre></div>'
            '<div id="quick-start-complete-example" class="highlight"><pre><code>final readonly class FulfillOrder implements Command provider-token-for-customer-42 OrderProcessingExample::process().PHP_EOL</code></pre></div>'
            '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
        )

        return parser


if __name__ == "__main__":
    unittest.main()
