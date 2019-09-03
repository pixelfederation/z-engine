<?php
/**
 * Z-Engine framework
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 */
declare(strict_types=1);

namespace ZEngine;

use FFI;
use FFI\CData;

/*
 * Stack Frame Layout (the whole stack frame is allocated at once)
 * ==================
 *
 *                             +========================================+
 * EG(current_execute_data) -> | zend_execute_data                      |
 *                             +----------------------------------------+
 *     EX_VAR_NUM(0) --------> | VAR[0] = ARG[1]                        |
 *                             | ...                                    |
 *                             | VAR[op_array->num_args-1] = ARG[N]     |
 *                             | ...                                    |
 *                             | VAR[op_array->last_var-1]              |
 *                             | VAR[op_array->last_var] = TMP[0]       |
 *                             | ...                                    |
 *                             | VAR[op_array->last_var+op_array->T-1]  |
 *                             | ARG[N+1] (extra_args)                  |
 *                             | ...                                    |
 *                             +----------------------------------------+
 */

/* zend_copy_extra_args is used when the actually passed number of arguments
 * (EX_NUM_ARGS) is greater than what the function defined (op_array->num_args).
 *
 * The extra arguments will be copied into the call frame after all the compiled variables.
 *
 * If there are extra arguments copied, a flag "ZEND_CALL_FREE_EXTRA_ARGS" will be set
 * on the zend_execute_data, and when the executor leaves the function, the
 * args will be freed in zend_leave_helper.
 */

/**
 * ExecutionDataEntry provides an access to general information from executor
 */
class ExecutionDataEntry
{
    private CData $pointer;

    public function __construct(CData $pointer)
    {
        $this->pointer = $pointer;
    }

    /**
     * Returns the currently executed opline
     */
    public function getOpline(): OpCodeLine
    {
        return new OpCodeLine($this->pointer->opline);
    }

    /**
     * Returns the "return value"
     */
    public function getReturnValue(): ValueEntry
    {
        return new ValueEntry($this->pointer->return_value);
    }

    /**
     * Returns the current function entry
     */
    public function getFunction(): FunctionEntry
    {
        if ($this->pointer->func === null) {
            throw new \InvalidArgumentException('Function entry is not available in the current context');
        }

        return new FunctionEntry($this->pointer->func);
    }

    /**
     * Returns the current object scope
     *
     * This contains following: this + call_info + num_args
     */
    public function getThis(): ValueEntry
    {
        return new ValueEntry(FFI::addr($this->pointer->This));
    }

    /**
     * Returns the number of function/method arguments
     */
    public function getNumberOfArguments(): int
    {
        return $this->pointer->This->u2->num_args;
    }

    /**
     * Returns the argument by it's index
     *
     * Argument index is starting from 0.
     */
    public function getArgument(int $argumentIndex): ValueEntry
    {
        if ($argumentIndex >= $this->pointer->This->u2->num_args) {
            throw new \OutOfBoundsException("Argument index is greater than available arguments");
        }

        $valuePointer = Core::cast('zval *', $this->pointer) + self::getCallFrameSlot() + $argumentIndex;
        $valueEntry   = new ValueEntry($valuePointer);

        return $valueEntry;
    }

    /**
     * Returns execution arguments as array of values
     *
     * @return ValueEntry[]
     */
    public function getArguments(): array
    {
        $arguments = [];
        for ($index = 0; $index < $this->pointer->This->u2->num_args; $index++) {
            $arguments[] = $this->getArgument($index);
        }

        return $arguments;
    }

    /**
     * Checks if there is a previous execution entry (aka stack)
     */
    public function hasPrevious(): bool
    {
        return $this->pointer->prev_execute_data !== null;
    }

    /**
     * Returns the previous execution data entry (aka stack)
     */
    public function getPrevious(): ExecutionDataEntry
    {
        if ($this->pointer->prev_execute_data === null) {
            throw new \LogicException('There is no previous execution data. Top of the stack?');
        }
        return new ExecutionDataEntry($this->pointer->prev_execute_data);
    }

    public function getSymbolTable(): HashTable
    {
        return new HashTable($this->pointer->symbol_table);
    }

    /**
     * Calculates the call frame slot size
     *
     * @see ZEND_CALL_FRAME_SLOT
     */
    private static function getCallFrameSlot(): int
    {
        static $slotSize;
        if ($slotSize === null) {
            $alignedSizeOfExecuteData = Core::getAlignedSize(FFI::sizeof(Core::type('zend_execute_data')));
            $alignedSizeOfZval        = Core::getAlignedSize(FFI::sizeof(Core::type('zval')));

            $slotSize = intdiv(($alignedSizeOfExecuteData + $alignedSizeOfZval) - 1, $alignedSizeOfZval);
        }

        return $slotSize;
    }
}