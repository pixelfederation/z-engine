<?php
/**
 * Z-Engine framework
 *
 * @copyright Copyright 2020, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 */
declare(strict_types=1);

namespace ZEngine\ClassExtension\Hook;

use FFI\CData;
use ZEngine\Core;
use ZEngine\Hook\AbstractHook;
use ZEngine\Reflection\ReflectionValue;

/**
 * Receiving hook for performing operation on object
 */
class DoOperationHook extends AbstractHook
{
    protected const HOOK_FIELD = 'do_operation';

    /**
     * Operation opcode
     */
    protected int $opCode;

    /**
     * Holds a return value
     */
    protected CData $returnValue;

    /**
     * First operand
     */
    protected CData $op1;

    /**
     * Second operand
     */
    protected CData $op2;

    /**
     * typedef int (*zend_object_do_operation_t)(zend_uchar opcode, zval *result, zval *op1, zval *op2);
     *
     * @inheritDoc
     */
    public function handle(...$rawArguments): int
    {
        [$this->opCode, $this->returnValue, $this->op1, $this->op2] = $rawArguments;

        $result = ($this->userHandler)($this);
        ReflectionValue::fromValueEntry($this->returnValue)->setNativeValue($result);

        return Core::SUCCESS;
    }

    /**
     * Returns an opcode
     */
    public function getOpcode(): int
    {
        return $this->opCode;
    }

    /**
     * Returns first operand
     */
    public function getFirst()
    {
        ReflectionValue::fromValueEntry($this->op1)->getNativeValue($value);

        return $value;
    }

    /**
     * Returns second operand
     */
    public function getSecond()
    {
        ReflectionValue::fromValueEntry($this->op2)->getNativeValue($value);

        return $value;
    }

    /**
     * Returns result of casting (eg from call to proceed)
     */
    public function getResult()
    {
        ReflectionValue::fromValueEntry($this->returnValue)->getNativeValue($result);

        return $result;
    }

    /**
     * Proceeds with object custom operation
     */
    public function proceed()
    {
        if (!$this->hasOriginalHandler()) {
            throw new \LogicException('Original handler is not available');
        }
        $result = ($this->originalHandler)($this->opCode, $this->returnValue, $this->op1, $this->op2);

        return $result;
    }
}
