<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ\Interfaces;

use Discord\Parts\Channel\Message;
use React\Promise\PromiseInterface;

interface MessageHandlerCallbackInterface
{
    public function __invoke(Message $message, array $message_filtered, string $command): ?PromiseInterface;
}