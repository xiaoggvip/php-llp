<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class FunctionFormatter
{
    /**
     * @param FunctionInfo[] $functions
     * @return array<int, array<string, mixed>>
     */
    public static function formatForApi(array $functions): array
    {
        $result = [];
        foreach ($functions as $function) {
            $result[] = $function->toToolFormat();
        }
        return $result;
    }

    /**
     * @param FunctionInfo $function
     * @return string
     */
    public static function formatDescription(FunctionInfo $function): string
    {
        $lines = [];
        $lines[] = "Function: {$function->getName()}";
        $lines[] = "Description: {$function->getDescription()}";
        $lines[] = "Parameters:";

        foreach ($function->getParameters() as $param) {
            $required = in_array($param->getName(), $param->getRequired(), true) ? ' (required)' : '';
            $lines[] = "  - {$param->getName()} ({$param->getType()}){$required}: {$param->getDescription()}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param FunctionInfo[] $functions
     * @return string
     */
    public static function formatList(array $functions): string
    {
        $lines = [];
        foreach ($functions as $function) {
            $lines[] = self::formatDescription($function);
            $lines[] = '';
        }
        return implode("\n", $lines);
    }
}