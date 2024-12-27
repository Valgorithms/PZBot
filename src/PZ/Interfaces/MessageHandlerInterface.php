<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ\Interfaces;

use Discord\Parts\Channel\Message;
use React\Promise\PromiseInterface;


interface MessageHandlerInterface extends HandlerInterface
{
    public function handle(Message $message): ?PromiseInterface;
}