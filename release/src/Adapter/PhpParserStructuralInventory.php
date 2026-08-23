<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Class PhpParserStructuralInventory
 */
final readonly class PhpParserStructuralInventory implements StructuralInventoryPort
{
    /**
     * Constructs PhpParserStructuralInventory
     */
    public function __construct(private CompatibilityInputPort $input)
    {
    }

    /**
     * Returns runtime declarations without assigning compatibility policy
     *
     * @return array<string, mixed>
     */
    public function structuralInventory(string $sourceRoot, string $sourceOid): array
    {
        $declarations = [];

        foreach (['Domain', 'Application', 'Adapter'] as $layer) {
            $files = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot.'/src/'.$layer)),
                '/\.php$/D'
            );

            foreach ($files as $file) {
                assert($file instanceof SplFileInfo);

                if ($file->getFilename() === 'functions.php') {
                    continue;
                }

                $declarations[] = $this->declarationInventory(
                    $this->input->read($file->getPathname()),
                    $this->repositoryRelativeSource($sourceRoot, $file->getPathname())
                );
            }
        }

        $functionPath = $sourceRoot.'/src/Domain/functions.php';
        $functionSource = $this->input->read($functionPath);
        $functionInventory = $this->functionInventory(
            $functionSource,
            $this->repositoryRelativeSource($sourceRoot, $functionPath)
        );
        usort($declarations, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        usort($functionInventory, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => $sourceOid,
            'declarations'   => $declarations,
            'functions'      => $functionInventory
        ];
    }

    /**
     * Returns one top-level declaration and its consumer operation shapes
     *
     * @return array<string, mixed>
     */
    private function declarationInventory(string $source, string $sourcePath): array
    {
        $nodes = $this->resolvedNodes($source);
        $declaration = new NodeFinder()->findFirst(
            $nodes,
            static fn (Node $node): bool => $node instanceof ClassLike && $node->namespacedName instanceof Name
        );
        $declaration instanceof ClassLike
            || throw new UnexpectedValueException('A structural declaration is unreadable.');
        assert(
            $declaration instanceof Class_
            || $declaration instanceof Interface_
            || $declaration instanceof Trait_
            || $declaration instanceof Enum_
        );

        $name = $declaration->namespacedName->toString();
        $kind = match (true) {
            $declaration instanceof Class_     => 'class',
            $declaration instanceof Interface_ => 'interface',
            $declaration instanceof Trait_     => 'trait',
            $declaration instanceof Enum_      => 'enum'
        };
        $callable = [];
        $constructible = [];
        $extensible = [];
        $implementable = [];
        $members = [];
        $extensionPoint = ($declaration instanceof Class_ && !$declaration->isFinal())
            || $declaration instanceof Trait_;

        if ($declaration instanceof Class_) {
            $parent = $declaration->extends instanceof Name ? $this->type($declaration->extends) : 'none';
            $callable[] = 'parent '.$parent;
            if ($declaration->isAbstract()) {
                $extensible[] = 'abstract class';
            }

            if (!$declaration->getMethod('__construct') instanceof ClassMethod) {
                $constructible[] = $parent === 'none' ? 'implicit __construct()' : 'inherits constructor '.$parent;
            }
        }

        foreach ($declaration->getMethods() as $method) {
            $methodName = strtolower($method->name->toString());
            if ($method->isPublic() && !in_array($methodName, ['__construct', '__destruct'], true)) {
                $callable[] = $this->methodSignature($method);
            }

            if (
                ($methodName === '__construct' && $method->isPublic())
                || ($method->isPublic() && $method->isStatic() && $this->returnsDeclaration($method, $name))
            ) {
                $constructible[] = $this->methodSignature($method);
            }

            if (
                $extensionPoint
                && ($method->isPublic() || $method->isProtected())
                && !$method->isFinal()
                && $methodName !== '__construct'
            ) {
                $visibility = $method->isProtected() ? 'protected ' : 'public ';
                $extensible[] = $visibility.$this->methodSignature($method);
            }

            if ($declaration instanceof Interface_) {
                $implementable[] = $this->methodSignature($method);
            }

            if ($extensionPoint && $methodName === '__construct') {
                foreach ($method->getParams() as $parameter) {
                    if ($parameter->isProtected()) {
                        $parameter->var instanceof Variable && is_string($parameter->var->name)
                            || throw new UnexpectedValueException('A promoted structural property is unreadable.');
                        $extensible[] = $this->protectedPropertySignature(
                            $parameter->type,
                            $parameter->var->name,
                            $parameter->default,
                            $parameter->flags
                        );
                    }
                }
            }
        }

        if ($extensionPoint) {
            foreach ($declaration->getProperties() as $property) {
                if (!$property->isProtected()) {
                    continue;
                }

                foreach ($property->props as $item) {
                    $extensible[] = $this->protectedPropertySignature(
                        $property->type,
                        $item->name->toString(),
                        $item->default,
                        $property->flags
                    );
                }
            }

            foreach ($declaration->getConstants() as $constant) {
                if (!$constant->isProtected()) {
                    continue;
                }

                foreach ($constant->consts as $item) {
                    $extensible[] = $this->protectedConstantSignature($constant, $item);
                }
            }
        }

        foreach ($declaration->getConstants() as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }

            foreach ($constant->consts as $item) {
                $members[] = [
                    'name'      => 'constant '.$item->name->toString(),
                    'signature' => $this->publicConstantSignature($constant, $item)
                ];
            }
        }

        if ($declaration instanceof Enum_) {
            foreach ($declaration->stmts as $statement) {
                if (!$statement instanceof EnumCase) {
                    continue;
                }

                $signature = 'case '.$statement->name->toString();
                if ($statement->expr instanceof Expr) {
                    $signature .= ' = '.new Standard()->prettyPrintExpr($statement->expr);
                }

                $members[] = ['name' => 'case '.$statement->name->toString(), 'signature' => $signature];
            }
        }

        usort($members, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        if ($declaration instanceof Class_ && $declaration->extends instanceof Name && !$declaration->isFinal()) {
            $extensible[] = 'extends '.$this->type($declaration->extends);
        }

        if ($declaration instanceof Interface_) {
            foreach ($declaration->extends as $extendedInterface) {
                $implementable[] = 'extends '.$this->type($extendedInterface);
            }
        }

        return [
            'name'       => $name,
            'source'     => $sourcePath,
            'kind'       => $kind,
            'members'    => $members,
            'operations' => $this->operationShapes($callable, $constructible, $extensible, $implementable)
        ];
    }

    /**
     * Returns top-level functions as public callable operations
     *
     * @return list<array<string, mixed>>
     */
    private function functionInventory(string $source, string $sourcePath): array
    {
        $functions = new NodeFinder()->findInstanceOf($this->resolvedNodes($source), Function_::class);

        return array_map(
            fn (Function_ $function): array => [
                'name'       => $function->namespacedName->toString(),
                'source'     => $sourcePath,
                'kind'       => 'function',
                'members'    => [],
                'operations' => $this->operationShapes([$this->functionSignature($function)], [], [], [])
            ],
            $functions
        );
    }

    /**
     * Returns one canonical repository-relative source location derived from an actual scanned file
     */
    private function repositoryRelativeSource(string $sourceRoot, string $sourcePath): string
    {
        $prefix = rtrim($sourceRoot, '/').'/';
        str_starts_with($sourcePath, $prefix)
            || throw new UnexpectedValueException('A structural source location is outside its source root.');
        $relative = substr($sourcePath, strlen($prefix));
        preg_match(
            '/\Asrc\/(?:Domain|Application|Adapter)\/(?:[A-Za-z0-9_.-]+\/)*[A-Za-z0-9_.-]+\.php\z/D',
            $relative
        ) === 1
            || throw new UnexpectedValueException('A structural source location is not canonical.');

        return $relative;
    }

    /**
     * Returns parsed source with names resolved for canonical signatures
     *
     * @return list<Node\Stmt>
     */
    private function resolvedNodes(string $source): array
    {
        $nodes = new ParserFactory()->createForNewestSupportedVersion()->parse($source);
        is_array($nodes) || throw new UnexpectedValueException('Structural source is unreadable.');
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        /** @var list<Node\Stmt> $resolved */
        $resolved = array_values($traverser->traverse($nodes));

        return $resolved;
    }

    /**
     * Returns one canonical method signature
     */
    private function methodSignature(ClassMethod $method): string
    {
        $prefix = $method->isAbstract() ? 'abstract ' : '';
        $prefix .= $method->isStatic() ? 'static ' : '';

        return $prefix.$this->functionLikeSignature($method, $method->name->toString());
    }

    /**
     * Returns one canonical function signature
     */
    private function functionSignature(Function_ $function): string
    {
        return $this->functionLikeSignature($function, $function->namespacedName->toString());
    }

    /**
     * Returns the signature fields relevant to a PHP consumer operation
     */
    private function functionLikeSignature(FunctionLike $function, string $name): string
    {
        $parameters = array_map(function (Param $parameter): string {
            $signature = $parameter->type instanceof Node ? $this->type($parameter->type).' ' : '';
            $signature .= $parameter->byRef ? '&' : '';
            $signature .= $parameter->variadic ? '...' : '';
            $parameter->var instanceof Variable && is_string($parameter->var->name)
                || throw new UnexpectedValueException('A structural parameter is unreadable.');
            $signature .= '$'.$parameter->var->name;
            if ($parameter->default instanceof Expr) {
                $signature .= ' = '.new Standard()->prettyPrintExpr($parameter->default);
            }

            return $signature;
        }, $function->getParams());
        $return = $function->getReturnType();

        $reference = $function->returnsByRef() ? '&' : '';
        $signature = $name.'('.implode(', ', $parameters).')';
        $returnType = $return === null ? '' : ': '.$this->type($return);

        return $reference.$signature.$returnType;
    }

    /**
     * Returns one canonical protected property extension signature
     */
    private function protectedPropertySignature(
        ?Node $type,
        string $name,
        ?Expr $default,
        int $flags
    ): string {
        $modifiers = ['protected'];
        foreach (
            [
                Modifiers::PUBLIC_SET    => 'public(set)',
                Modifiers::PROTECTED_SET => 'protected(set)',
                Modifiers::PRIVATE_SET   => 'private(set)',
                Modifiers::STATIC        => 'static',
                Modifiers::READONLY      => 'readonly',
                Modifiers::FINAL         => 'final',
                Modifiers::ABSTRACT      => 'abstract'
            ] as $modifier => $label
        ) {
            if (($flags & $modifier) !== 0) {
                $modifiers[] = $label;
            }
        }

        $signature = implode(' ', $modifiers).' property ';
        $signature .= $type instanceof Node ? $this->type($type).' ' : '';
        $signature .= '$'.$name;
        if ($default instanceof Expr) {
            $signature .= ' = '.new Standard()->prettyPrintExpr($default);
        }

        return $signature;
    }

    /**
     * Returns one canonical protected constant extension signature
     */
    private function protectedConstantSignature(ClassConst $constant, Const_ $item): string
    {
        $signature = 'protected ';
        $signature .= $constant->isFinal() ? 'final ' : '';
        $signature .= 'const ';
        $signature .= $constant->type instanceof Node ? $this->type($constant->type).' ' : '';
        $signature .= $item->name->toString().' = '.new Standard()->prettyPrintExpr($item->value);

        return $signature;
    }

    /**
     * Returns one canonical public constant declaration signature
     */
    private function publicConstantSignature(ClassConst $constant, Const_ $item): string
    {
        $signature = 'public ';
        $signature .= $constant->isFinal() ? 'final ' : '';
        $signature .= 'const ';
        $signature .= $constant->type instanceof Node ? $this->type($constant->type).' ' : '';
        $signature .= $item->name->toString().' = '.new Standard()->prettyPrintExpr($item->value);

        return $signature;
    }

    /**
     * Returns one canonical resolved type
     */
    private function type(Node $type): string
    {
        return match (true) {
            $type instanceof Identifier => $type->toString(),
            $type instanceof Name       => $this->resolvedName($type),
            $type instanceof NullableType => '?'.$this->type($type->type),
            $type instanceof UnionType => implode('|', array_map($this->type(...), $type->types)),
            $type instanceof IntersectionType => implode('&', array_map($this->type(...), $type->types)),
            default => throw new UnexpectedValueException('A structural type is unreadable.')
        };
    }

    /**
     * Returns one canonical resolved name
     */
    private function resolvedName(Name $name): string
    {
        return ($name instanceof FullyQualified ? '\\' : '').$name->toString();
    }

    /**
     * Reports whether a public static method returns this declaration
     */
    private function returnsDeclaration(ClassMethod $method, string $declaration): bool
    {
        if (!$method->returnType instanceof Node) {
            return false;
        }

        return in_array($this->type($method->returnType), ['self', 'static', '\\'.$declaration], true);
    }

    /**
     * Returns deterministically ordered generated operation shapes
     *
     * @param array $callable      Public callable signatures.
     * @param array $constructible Public construction signatures.
     * @param array $extensible    Published extension signatures.
     * @param array $implementable Interface implementation signatures.
     *
     * @phpstan-param list<string> $callable
     * @phpstan-param list<string> $constructible
     * @phpstan-param list<string> $extensible
     * @phpstan-param list<string> $implementable
     *
     * @return array<string, list<string>>
     */
    private function operationShapes(
        array $callable,
        array $constructible,
        array $extensible,
        array $implementable
    ): array {
        sort($callable, SORT_STRING);
        sort($constructible, SORT_STRING);
        sort($extensible, SORT_STRING);
        sort($implementable, SORT_STRING);

        return [
            'callable'      => $callable,
            'constructible' => $constructible,
            'extensible'    => $extensible,
            'implementable' => $implementable
        ];
    }
}
