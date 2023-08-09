<?php

declare(strict_types=1);

namespace Rector\Privatization\Guard;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\BetterReflection\Reflection\ReflectionClass;
use PHPStan\Reflection\ClassReflection;
use Rector\Core\Enum\ObjectReference;
use Rector\Core\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\Core\NodeManipulator\PropertyManipulator;
use Rector\Core\PhpParser\AstResolver;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\Core\Util\Reflection\PrivatesAccessor;
use Rector\NodeNameResolver\NodeNameResolver;

final class ParentPropertyLookupGuard
{
    public function __construct(
        private readonly BetterNodeFinder $betterNodeFinder,
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly PropertyFetchAnalyzer $propertyFetchAnalyzer,
        private readonly AstResolver $astResolver,
        private readonly PropertyManipulator $propertyManipulator,
        private readonly PrivatesAccessor $privatesAccessor
    ) {
    }

    public function isLegal(Property $property, ?ClassReflection $classReflection): bool
    {
        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        if ($classReflection->isAnonymous()) {
            return false;
        }

        $propertyName = $this->nodeNameResolver->getName($property);
        if ($this->propertyManipulator->isUsedByTrait($classReflection, $propertyName)) {
            return false;
        }

        // XXX rework this hack, after https://github.com/phpstan/phpstan-src/pull/2563 landed
        $nativeReflection = $classReflection->getNativeReflection();
        $betterReflectionClass = $this->privatesAccessor->getPrivateProperty(
            $nativeReflection,
            'betterReflectionClass'
        );
        /** @var ReflectionClass $betterReflectionClass */
        $parentClassName = $betterReflectionClass->getParentClassName();

        if ($parentClassName === null) {
            return true;
        }

        $className = $classReflection->getName();
        $parentClassReflections = $classReflection->getParents();

        // parent class not autoloaded
        if ($parentClassReflections === []) {
            return false;
        }

        return $this->isGuardedByParents($parentClassReflections, $propertyName, $className);
    }

    private function isFoundInParentClassMethods(
        ClassReflection $parentClassReflection,
        string $propertyName,
        string $className
    ): bool {
        $classLike = $this->astResolver->resolveClassFromClassReflection($parentClassReflection);
        if (! $classLike instanceof Class_) {
            return false;
        }

        $methods = $classLike->getMethods();
        foreach ($methods as $method) {
            $isFound = $this->isFoundInMethodStmts((array) $method->stmts, $propertyName, $className);
            if ($isFound) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Stmt[] $stmts
     */
    private function isFoundInMethodStmts(array $stmts, string $propertyName, string $className): bool
    {
        return (bool) $this->betterNodeFinder->findFirst($stmts, function (Node $subNode) use (
            $propertyName,
            $className
        ): bool {
            if (! $this->propertyFetchAnalyzer->isPropertyFetch($subNode)) {
                return false;
            }

            /** @var PropertyFetch|StaticPropertyFetch $subNode */
            if ($subNode instanceof PropertyFetch) {
                if (! $subNode->var instanceof Variable) {
                    return false;
                }

                if (! $this->nodeNameResolver->isName($subNode->var, 'this')) {
                    return false;
                }

                return $this->nodeNameResolver->isName($subNode, $propertyName);
            }

            if (! $this->nodeNameResolver->isNames(
                $subNode->class,
                [ObjectReference::SELF, ObjectReference::STATIC, $className]
            )) {
                return false;
            }

            return $this->nodeNameResolver->isName($subNode->name, $propertyName);
        });
    }

    /**
     * @param ClassReflection[] $parentClassReflections
     */
    private function isGuardedByParents(array $parentClassReflections, string $propertyName, string $className): bool
    {
        foreach ($parentClassReflections as $parentClassReflection) {
            if ($parentClassReflection->hasProperty($propertyName)) {
                return false;
            }

            if ($this->isFoundInParentClassMethods($parentClassReflection, $propertyName, $className)) {
                return false;
            }
        }

        return true;
    }
}
