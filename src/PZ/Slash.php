<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ;

use Discord\Builders\MessageBuilder;
use React\Promise\PromiseInterface;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Interactions\Command\Command;
use Discord\Repository\Guild\GuildCommandRepository;
use Discord\Repository\Interaction\GlobalCommandRepository;

class Slash
{
    public BOT $pz;

    public function __construct(BOT &$pz) {
        $this->pz = $pz;
        $this->afterConstruct();
    }

    /*
    * This function is called after the constructor is finished.
    * It is used to load the files, start the timers, and start handling events.
    */
    protected function afterConstruct()
    {
        // 
    }
    public function updateGlobalCommands(GlobalCommandRepository $commands): void
    {
        // if ($command = $commands->get('name', 'ping')) $commands->delete($command->id);
        if (! $commands->get('name', 'ping')) $commands->save(new Command($this->pz->discord, [
            'name'        => 'ping',
            'description' => 'Replies with Pong!',
        ]));

        // if ($command = $commands->get('name', 'ping')) $commands->delete($command->id);
        if (! $commands->get('name', 'help')) $commands->save(new Command($this->pz->discord, [
            'name'          => 'help',
            'description'   => 'View a list of available commands',
            'dm_permission' => false,
        ]));
        // if ($command = $commands->get('name', 'players')) $commands->delete($command->id);
        if (! $commands->get('name', 'players')) $commands->save(new Command($this->pz->discord, [
            'name'        => 'players',
            'description' => 'Show Project Zomboid server information'
        ]));

        $this->declareListeners();
    }
    public function updateGuildCommands(GuildCommandRepository $commands): void
    {
        //
    }
    public function declareListeners(): void
    {
        $this->pz->discord->listenCommand('ping', fn(Interaction $interaction): PromiseInterface =>
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Pong!'))
        );

        $this->pz->discord->listenCommand('help', fn(Interaction $interaction): PromiseInterface =>
            $interaction->respondWithMessage(MessageBuilder::new()->setContent($this->pz->messageHandler->generateHelp($interaction->member->roles)), true)
        );

        $this->pz->discord->listenCommand('players', fn(Interaction $interaction): PromiseInterface =>
            $interaction->respondWithMessage(MessageBuilder::new()->setContent(
                ($players = $this->pz->rcon->getPlayers())
                    ? "Players (" . count($players) . "):" . PHP_EOL . implode(', ', $players)
                    : 'No players found!'
            ), true)
        );
    }
}