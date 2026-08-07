<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;

class Calculator implements ToolInterface
{
    public function getName(): string
    {
        return 'calculator';
    }

    public function getDescription(): string
    {
        return '安全的数学计算器。支持基本运算（加减乘除）、括号、幂运算和三角函数。不使用eval()。';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => '数学表达式，如 "2 + 3 * 4" 或 "sin(45) ^ 2"',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    public function execute(array $parameters)
    {
        $expression = $parameters['expression'] ?? '';
        if (empty($expression)) {
            return '错误：必须提供表达式';
        }

        try {
            $result = $this->evaluate($expression);
            return (string)$result;
        } catch (\Throwable $e) {
            return '计算错误: ' . $e->getMessage();
        }
    }

    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters(),
            ],
        ];
    }

    /**
     * @param string $expression
     * @return float|int
     */
    private function evaluate(string $expression)
    {
        $tokens = $this->tokenize($expression);
        $pos = 0;
        $result = $this->parseExpression($tokens, $pos);

        if ($pos < count($tokens)) {
            throw new \RuntimeException('表达式解析错误');
        }

        return $result;
    }

    /**
     * @param string $expression
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $len = strlen($expression);
        $i = 0;

        while ($i < $len) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (is_numeric($char) || $char === '.') {
                $num = '';
                while ($i < $len && (is_numeric($expression[$i]) || $expression[$i] === '.')) {
                    $num .= $expression[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'number', 'value' => $num];
                continue;
            }

            if (ctype_alpha($char)) {
                $func = '';
                while ($i < $len && ctype_alpha($expression[$i])) {
                    $func .= $expression[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'function', 'value' => strtolower($func)];
                continue;
            }

            if (in_array($char, ['+', '-', '*', '/', '(', ')', '^', '%'], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;
                continue;
            }

            throw new \RuntimeException("无效字符: {$char}");
        }

        return $tokens;
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     * @param int $pos
     * @return float|int
     */
    private function parseExpression(array &$tokens, int &$pos)
    {
        $result = $this->parseTerm($tokens, $pos);

        while ($pos < count($tokens)
            && $tokens[$pos]['type'] === 'operator'
            && in_array($tokens[$pos]['value'], ['+', '-'], true)) {
            $op = $tokens[$pos]['value'];
            $pos++;
            $right = $this->parseTerm($tokens, $pos);
            $result = $op === '+' ? $result + $right : $result - $right;
        }

        return $result;
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     * @param int $pos
     * @return float|int
     */
    private function parseTerm(array &$tokens, int &$pos)
    {
        $result = $this->parseFactor($tokens, $pos);

        while ($pos < count($tokens)
            && $tokens[$pos]['type'] === 'operator'
            && in_array($tokens[$pos]['value'], ['*', '/', '%'], true)) {
            $op = $tokens[$pos]['value'];
            $pos++;
            $right = $this->parseFactor($tokens, $pos);
            if ($op === '*') {
                $result = $result * $right;
            } elseif ($op === '/') {
                if ($right == 0) {
                    throw new \RuntimeException('除数不能为零');
                }
                $result = $result / $right;
            } else {
                $result = fmod($result, $right);
            }
        }

        return $result;
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     * @param int $pos
     * @return float|int
     */
    private function parseFactor(array &$tokens, int &$pos)
    {
        $base = $this->parseUnary($tokens, $pos);

        if ($pos < count($tokens)
            && $tokens[$pos]['type'] === 'operator'
            && $tokens[$pos]['value'] === '^') {
            $pos++;
            $exponent = $this->parseFactor($tokens, $pos);
            return pow($base, $exponent);
        }

        return $base;
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     * @param int $pos
     * @return float|int
     */
    private function parseUnary(array &$tokens, int &$pos)
    {
        if ($pos < count($tokens)
            && $tokens[$pos]['type'] === 'operator'
            && in_array($tokens[$pos]['value'], ['+', '-'], true)) {
            $op = $tokens[$pos]['value'];
            $pos++;
            $operand = $this->parseUnary($tokens, $pos);
            return $op === '-' ? -$operand : $operand;
        }

        return $this->parsePrimary($tokens, $pos);
    }

    /**
     * @param array<int, array{type: string, value: string}> $tokens
     * @param int $pos
     * @return float|int
     */
    private function parsePrimary(array &$tokens, int &$pos)
    {
        if ($pos >= count($tokens)) {
            throw new \RuntimeException('表达式不完整');
        }

        $token = $tokens[$pos];

        if ($token['type'] === 'number') {
            $pos++;
            $val = $token['value'];
            return strpos($val, '.') !== false ? (float)$val : (int)$val;
        }

        if ($token['type'] === 'function') {
            $funcName = $token['value'];
            $pos++;

            if ($pos >= count($tokens) || $tokens[$pos]['value'] !== '(') {
                throw new \RuntimeException("函数 {$funcName} 需要括号");
            }
            $pos++;

            $arg = $this->parseExpression($tokens, $pos);

            if ($pos >= count($tokens) || $tokens[$pos]['value'] !== ')') {
                throw new \RuntimeException("缺少函数 {$funcName} 的闭合括号");
            }
            $pos++;

            return $this->callFunction($funcName, $arg);
        }

        if ($token['type'] === 'operator' && $token['value'] === '(') {
            $pos++;
            $result = $this->parseExpression($tokens, $pos);
            if ($pos >= count($tokens) || $tokens[$pos]['value'] !== ')') {
                throw new \RuntimeException('缺少闭合括号');
            }
            $pos++;
            return $result;
        }

        throw new \RuntimeException("意外的令牌: {$token['value']}");
    }

    /**
     * @param string $name
     * @param float|int $arg
     * @return float|int
     */
    private function callFunction(string $name, $arg)
    {
        switch ($name) {
            case 'sin':
                return sin(deg2rad($arg));
            case 'cos':
                return cos(deg2rad($arg));
            case 'tan':
                return tan(deg2rad($arg));
            case 'sqrt':
                return sqrt($arg);
            case 'abs':
                return abs($arg);
            case 'log':
                return log($arg);
            case 'ceil':
                return ceil($arg);
            case 'floor':
                return floor($arg);
            case 'round':
                return round($arg);
            case 'exp':
                return exp($arg);
            case 'pi':
                return M_PI;
            default:
                throw new \RuntimeException("未知函数: {$name}");
        }
    }
}