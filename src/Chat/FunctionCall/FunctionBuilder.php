<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class FunctionBuilder
{
    /**
     * Build FunctionInfo from a class method using reflection
     *
     * @param object $class
     * @param string $method
     * @return FunctionInfo
     */
    public static function fromMethod(object $class, string $method): FunctionInfo
    {
        $ref = new \ReflectionMethod($class, $method);

        $name = $method;
        $description = self::extractDescription($ref);
        $parameters = self::extractParameters($ref);

        $handler = function (array $args) use ($class, $method) {
            return call_user_func_array([$class, $method], $args);
        };

        return new FunctionInfo($name, $description, $parameters, $handler);
    }

    /**
     * Build multiple FunctionInfo from all public methods of a class
     *
     * @param object $class
     * @return FunctionInfo[]
     */
    public static function fromClass(object $class): array
    {
        $ref = new \ReflectionClass($class);
        $result = [];

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }
            if (strpos($method->getName(), '__') === 0) {
                continue;
            }
            $result[] = self::fromMethod($class, $method->getName());
        }

        return $result;
    }

    /**
     * @param \ReflectionMethod $ref
     * @return string
     */
    private static function extractDescription(\ReflectionMethod $ref): string
    {
        $doc = $ref->getDocComment();
        if (!$doc) {
            return $ref->getName();
        }

        $lines = explode("\n", $doc);
        $description = '';
        $inDescription = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '/**' || $line === '*/') {
                continue;
            }
            if (strpos($line, '* @') === 0) {
                break;
            }
            $line = ltrim($line, '* ');
            if ($inDescription || $line !== '') {
                $inDescription = true;
                $description .= $line . ' ';
            }
        }

        return trim($description) ?: $ref->getName();
    }

    /**
     * @param \ReflectionMethod $ref
     * @return Parameter[]
     */
    private static function extractParameters(\ReflectionMethod $ref): array
    {
        $result = [];
        $doc = $ref->getDocComment();
        $paramDocs = [];

        if ($doc) {
            preg_match_all('/@param\s+(\S+)\s+\$(\w+)\s*(.*)/', $doc, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $paramDocs[$match[2]] = [
                    'type' => self::normalizeType($match[1]),
                    'description' => trim($match[3]),
                ];
            }
        }

        foreach ($ref->getParameters() as $param) {
            $paramName = $param->getName();
            $type = 'string';
            $description = '';
            $required = true;

            if (isset($paramDocs[$paramName])) {
                $type = $paramDocs[$paramName]['type'];
                $description = $paramDocs[$paramName]['description'];
            }

            if ($param->isOptional()) {
                $required = false;
            }

            $result[] = new Parameter($paramName, $type, $description, [], $required ? [$paramName] : []);
        }

        return $result;
    }

    /**
     * @param string $type
     * @return string
     */
    private static function normalizeType(string $type): string
    {
        $map = [
            'int' => 'integer',
            'integer' => 'integer',
            'float' => 'number',
            'double' => 'number',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            'mixed' => 'string',
        ];

        return $map[$type] ?? 'string';
    }
}