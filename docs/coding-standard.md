# FightCommon Coding Standard

Fight Common publishes an optional PHP_CodeSniffer standard for PHP 8.5 projects. The package remains a
normal Composer library: it does not use the PHP_CodeSniffer Composer installer plugin, and it does not
automatically select any files in a consuming repository.

## Install the development tools

Require the standard's tool contracts in the consuming project:

```bash
composer require --dev squizlabs/php_codesniffer slevomat/coding-standard
```

The `johnnickell/fight-common` package may remain a production dependency. The PHPCS and Slevomat packages
are development tools and are not runtime requirements of Fight Common's Domain, Application, or Adapter
layers.

## Copy-ready consumer ruleset

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

The published `FightCommon` ruleset contains rules only. Removing the `<file>` and `<exclude-pattern>`
elements above leaves PHPCS with no consumer scan scope; the package never supplies one.

To run only individual sniffs while retaining the full standard's configuration, use their stable public
identifiers in the consumer configuration:

```xml
<arg name="sniffs" value="Phpcs.Files.RequireStrictTypes,Phpcs.Arrays.RequireAlignedArrayArrow" />
<rule ref="./vendor/johnnickell/fight-common/src/Standards/Phpcs/ruleset.xml" />
```

The equivalent one-off command is:

```bash
vendor/bin/phpcs --standard=phpcs.xml \
    --sniffs=Phpcs.Files.RequireStrictTypes,Phpcs.Arrays.RequireAlignedArrayArrow
```

## Public compatibility reference

The standard name is `FightCommon`. These ten PHPCS sniff identifiers are public, stable names. The
eleventh custom production unit, `DocumentationComment`, is a supporting helper and is not a PHPCS sniff.

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

PHPCS reports a complete source such as
`Phpcs.Commenting.RequireTypeDocComment.MissingDocComment` by joining the identifier prefix, category, sniff,
and diagnostic code.

These custom-sniff properties are also public compatibility contracts:

| Public sniff identifier | Property | Default in `FightCommon` | Meaning |
| --- | --- | --- | --- |
| `Phpcs.Classes.NamedClassStructure` | `groups` | canonical 18-group declaration order | Ordered declaration groups accepted by the inherited Slevomat implementation |
| `Phpcs.Classes.NamedClassMemberSpacing` | `linesCountBetweenMembers` | `1` | Required blank lines between named-class members |
| `Phpcs.Classes.NamedMethodSpacing` | `minLinesCount` | `1` | Minimum blank lines between named-class methods |
| `Phpcs.Classes.NamedMethodSpacing` | `maxLinesCount` | `1` | Maximum blank lines between named-class methods |
| `Phpcs.Commenting.RequireTypeDocComment` | `strict` | `true` | When `false`, ordinary classes may omit type documentation; interfaces, traits, and enums still require it |

Consumers may override these properties with a `<rule>` and `<properties>` block like the `strict` example.
Changing or removing the standard name, a listed sniff or diagnostic, or one of these properties follows
the coding-standard compatibility policy in ADR 0004.

## Parity and ownership evidence

The initial package port was compared against the accepted Omphalos behavior at the PHPCS CLI seam. The
durable package-owned fixtures preserve that evidence without requiring an Omphalos checkout:

- `MechanicalConventions.*.inc` covers strict types, array commas and arrow alignment, and blank lines before
  return statements.
- `MemberLayout.*.inc` covers declaration order, member and method spacing, visibility groups, exclusions,
  fixes, and idempotence.
- `DocumentationGrammar.*.inc` covers strict and lenient type documentation, method grammar, inherited
  documentation, accepted forms, fixes, and idempotence.

Fight Common is the canonical implementation after T-00018 is accepted. The Omphalos copy is temporary;
adopting and removing it is separately planned in that repository and is not required to run this package.
