<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\Attributes\AlgorithmAttribute;
use Aurynx\HttpCompression\Attributes\OutputFormatAttribute;
use Aurynx\HttpCompression\CompressionException;
use LogicException;
use ReflectionClass;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * Centralized attribute cache for enum and class attributes.
 *
 * Uses simple static array cache keyed by FQCN or FQCN::caseName.
 * Efficient for enum cases and immutable value objects in PHP 8.4+.
 */
final class AttributeCache
{
    /** @var array<string, object|null> */
    private static array $cache = [];

    /**
     * @throws CompressionException
     * @throws LogicException
     */
    public static function algorithmForEnumCase(string $enumFqcn, string $caseName): object
    {
        $key = "{$enumFqcn}::{$caseName}";

        return self::$cache[$key] ??= (static function () use ($enumFqcn, $caseName, $key): object {
            if (!is_a($enumFqcn, UnitEnum::class, true)) {
                throw new LogicException("{$enumFqcn} is not an enum");
            }

            if (!defined($enumFqcn.'::'.$caseName)) {
                throw new CompressionException("Enum case {$enumFqcn}::{$caseName} not found");
            }

            $classRef = new ReflectionEnumUnitCase($enumFqcn, $caseName);
            $attrs = $classRef->getAttributes(AlgorithmAttribute::class);
            $count = count($attrs);

            if ($count !== 1) {
                throw new CompressionException("Expected exactly one AlgorithmAttribute on {$key}, got {$count}");
            }

            return $attrs[0]->newInstance();
        })();
    }

    public static function warmUp(string $enumFqcn): void
    {
        assert(is_a($enumFqcn, UnitEnum::class, true));

        foreach ($enumFqcn::cases() as $case) {
            self::algorithmForEnumCase($enumFqcn, $case->name);
        }
    }

    /**
     * Get first attribute of given type for a class.
     *
     * @template T of object
     * @param class-string $className
     * @param class-string<T> $attributeClass
     * @return T|null
     * @throws LogicException
     */
    public static function forClass(string $className, string $attributeClass): ?object
    {
        $key = "{$className}@{$attributeClass}";

        if (array_key_exists($key, self::$cache)) {
            /** @var T|null */
            return self::$cache[$key];
        }

        if (!class_exists($className)) {
            throw new LogicException("Class {$className} not found");
        }

        if (!class_exists($attributeClass)) {
            throw new LogicException("Attribute class {$attributeClass} not found");
        }

        $classRef = new ReflectionClass($className);
        $attrs = $classRef->getAttributes($attributeClass);
        $count = count($attrs);

        if ($count === 0) {
            self::$cache[$key] = null;
            return null;
        }

        if ($count > 1) {
            throw new LogicException("Expected at most one {$attributeClass} on {$className}, got {$count}");
        }

        /** @var T $attribute */
        $attribute = $attrs[0]->newInstance();
        self::$cache[$key] = $attribute;

        return $attribute;
    }

    /**
     * Get OutputFormatAttribute for a class (specialized wrapper).
     *
     * @param class-string $className
     * @throws LogicException
     */
    public static function forClassOutputFormat(string $className): ?OutputFormatAttribute
    {
        /** @var OutputFormatAttribute|null */
        return self::forClass($className, OutputFormatAttribute::class);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}
