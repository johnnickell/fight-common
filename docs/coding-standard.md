# Coding Standard

Fight Common publishes the optional `FightCommon` PHP_CodeSniffer standard for PHP 8.5 projects. Adopt it from a
consumer-owned ruleset: the package supplies rules, but it never selects consumer files or installs PHPCS plugins.

## Install the tools

Require the standard's tool contracts in the consuming project:

```bash
composer require --dev squizlabs/php_codesniffer slevomat/coding-standard
```

`johnnickell/fight-common` may remain a production dependency. PHPCS and Slevomat are development tools, not
runtime dependencies of Fight Common's Domain, Application, or Adapter layers.

## Add a consumer ruleset

Create `phpcs.xml` in the consumer repository:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ruleset name="Application">
    <description>Application coding standard</description>

    <!-- The consumer owns every scan path and exclusion. -->
    <file>src</file>
    <file>tests</file>
    <exclude-pattern>var/*</exclude-pattern>
    <exclude-pattern>tests/Fixtures/*</exclude-pattern>

    <!-- Load the installed standard explicitly; no Composer plugin is needed. -->
    <rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml">
        <!-- A consumer may exclude an individual public sniff. -->
        <exclude name="Phpcs.Commenting.RequireMethodDocComment" />
    </rule>

    <!-- A consumer may override a documented public property. -->
    <rule ref="Phpcs.Commenting.RequireTypeDocComment">
        <properties>
            <property name="strict" value="false" />
        </properties>
    </rule>
</ruleset>
```

Run the consumer-owned configuration normally:

```bash
vendor/bin/phpcs
```

Removing the `<file>` and `<exclude-pattern>` elements leaves PHPCS with no consumer scan scope. Fight Common
does not provide one implicitly.

## Stage adoption by rule

Select individual public sniffs while retaining the full standard's configuration:

```xml
<arg name="sniffs" value="Phpcs.Files.RequireStrictTypes,Phpcs.Arrays.RequireAlignedArrayArrow" />
<rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml" />
```

The equivalent one-off command is:

```bash
vendor/bin/phpcs --standard=phpcs.xml \
    --sniffs=Phpcs.Files.RequireStrictTypes,Phpcs.Arrays.RequireAlignedArrayArrow
```

Use `vendor/bin/phpcbf --standard=phpcs.xml` only after reviewing which configured rules provide fixers. Inspect
the resulting diff and rerun PHPCS; a fixer does not replace review or the consumer's own submit gate.

## Public identifiers

The standard name is `FightCommon`. These thirteen PHPCS sniff identifiers are stable public names:

| Public sniff identifier | Diagnostic codes |
| --- | --- |
| `Phpcs.Arrays.DisallowTrailingArrayComma` | `DisallowTrailingArrayComma` |
| `Phpcs.Arrays.RequireAlignedArrayArrow` | `ArrowNotAligned` |
| `Phpcs.Classes.NamedClassMemberSpacing` | `IncorrectCountOfBlankLinesBetweenMembers` |
| `Phpcs.Classes.NamedClassStructure` | `IncorrectGroupOrder` |
| `Phpcs.Classes.NamedMethodSpacing` | `IncorrectLinesCountBetweenMethods` |
| `Phpcs.Commenting.RequireMethodDocComment` | `AmbiguousSummary`, `InheritDocWithContent`, `InvalidConstructorSummary`, `MissingBlankLine`, `MissingDocComment`, `TerminalPunctuation`, `UnapprovedVerb`, `WrappedSummary` |
| `Phpcs.Commenting.RequireTypeDocComment` | `IncorrectSummary`, `Missing`, `MissingBlankLine`, `MissingDocComment`, `TerminalPunctuation` |
| `Phpcs.Files.RequireStrictTypes` | `Missing` |
| `Phpcs.Formatting.RequireBlankLineBeforeReturn` | `Missing` |
| `Phpcs.Formatting.RequireVisibilityGroupSpacing` | `MissingBlankLineBetweenVisibilityGroups`, `UnexpectedBlankLineWithinVisibilityGroup` |
| `Phpcs.NamingConventions.RequireUppercaseUnderscoredEnumCase` | `NotUppercaseUnderscored` |
| `SlevomatCodingStandard.Functions.DisallowTrailingCommaInCall` | `DisallowedTrailingComma` |
| `SlevomatCodingStandard.Functions.DisallowTrailingCommaInDeclaration` | `DisallowedTrailingComma` |

PHPCS forms a complete source such as
`Phpcs.Commenting.RequireTypeDocComment.MissingDocComment` by joining the identifier prefix, category, sniff, and
diagnostic code. `DocumentationComment` is a supporting implementation helper, not a selectable PHPCS sniff.

## Supported properties

These custom-sniff properties are also public compatibility contracts:

| Public sniff identifier | Property | Default in `FightCommon` | Meaning |
| --- | --- | --- | --- |
| `Phpcs.Classes.NamedClassStructure` | `groups` | canonical 18-group declaration order | Ordered declaration groups accepted by the inherited Slevomat implementation |
| `Phpcs.Classes.NamedClassMemberSpacing` | `linesCountBetweenMembers` | `1` | Required blank lines between named-class members |
| `Phpcs.Classes.NamedMethodSpacing` | `minLinesCount` | `1` | Minimum blank lines between named-class methods |
| `Phpcs.Classes.NamedMethodSpacing` | `maxLinesCount` | `1` | Maximum blank lines between named-class methods |
| `Phpcs.Commenting.RequireTypeDocComment` | `strict` | `true` | When `false`, ordinary classes may omit type documentation; interfaces, traits, and enums still require it |

Override a property with a `<rule>` and `<properties>` block like the `strict` example above. Exclude a public
sniff by its complete identifier instead of copying or editing the installed ruleset.

## Compatibility and failures

Changing or removing the standard name, a listed sniff or diagnostic, or a documented property follows
[ADR 0004](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0004-coding-standard-compatibility.md).
Consumer ruleset paths and excluded rules remain consumer-owned.

If PHPCS cannot resolve the standard:

1. Confirm `johnnickell/fight-common`, `squizlabs/php_codesniffer`, and `slevomat/coding-standard` are installed in
   the same Composer project.
2. Run from the directory containing the consumer `phpcs.xml`.
3. Verify the relative `vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml` path.
4. Confirm the ruleset defines at least one consumer-owned `<file>` path or pass files on the command line.
5. Run `vendor/bin/phpcs -i` and the targeted command again before changing exclusions.

An unexpected diagnostic is not a reason to edit files under `vendor/`. Narrow the consumer configuration with a
documented property or explicit exclusion, or report a package defect with the complete PHPCS source and a minimal
reproduction.

## Maintainer verification

Fight Common owns behavioral fixtures for the published standard:

Fight Common is the canonical implementation after T-00018 is accepted; the listed fixtures preserve the
accepted standard's behavior without requiring consumers to retain another source repository.

- `MechanicalConventions.*.inc` covers strict types, trailing commas, arrow alignment, blank lines before returns,
  and enum-case naming.
- `MemberLayout.*.inc` covers declaration order, member and method spacing, visibility groups, exclusions, fixes,
  and idempotence.
- `DocumentationGrammar.*.inc` covers strict and lenient type documentation, method grammar, inherited
  documentation, accepted forms, fixes, and idempotence.

When changing the standard itself, verify the affected production behavior and then run the repository's complete
`./bin/build` gate. Consumers need only the installation and ruleset workflow above; package maintenance evidence
does not add runtime dependencies or require a second source repository.

## Related routes

- Follow [Contributing](../contributing/index.md) when changing Fight Common itself.
- Read [Architecture](../../architecture/index.md) before changing ownership or dependency rules.
- Use the [Quick Start](../../quick-start/index.md) for consumer installation and application composition.
