<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ\Handlers;

use PZ\Interfaces\MessageHandlerCallbackInterface;
use Discord\Parts\Channel\Message;
use React\Promise\PromiseInterface;

class MessageHandlerCallback implements MessageHandlerCallbackInterface
{
    private \Closure $callback;

    /**
     * Class constructor.
     *
     * @param callable $callback The callback function to be executed.
     * @throws \InvalidArgumentException If the callback does not have the expected number of parameters or if any parameter does not have a type hint or is of the wrong type.
     */
    public function __construct(callable $callback)
    {
        $reflection = new \ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        $expectedParameterTypes = [Message::class, 'array', 'string'];
        if (count($parameters) !== $count = count($expectedParameterTypes)) throw new \InvalidArgumentException("The callback must take exactly $count parameters: " . implode(', ', $expectedParameterTypes));

        foreach ($parameters as $index => $parameter) {
            if (! $parameter->hasType()) throw new \InvalidArgumentException("Parameter $index must have a type hint.");
            $type = $parameter->getType(); // This could be done all on one line, but it's easier to read this way and makes the compiler happy
            if ($type !== null && $type instanceof \ReflectionNamedType) $type = $type->getName();
            if ($type !== $expectedParameterTypes[$index]) throw new \InvalidArgumentException("Parameter $index must be of type {$expectedParameterTypes[$index]}.");
        }

        $this->callback = $callback;
    }

    public function __invoke(Message $message, array $message_filtered = [], string $command = ''): ?PromiseInterface
    {
        return call_user_func($this->callback, $message, $message_filtered, $command);
    }
}