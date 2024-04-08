<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ;

use Discord\Discord;
//use Discord\Builders\MessageBuilder;
use Discord\Helpers\BigInt;
//use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Repository\Interaction\GlobalCommandRepository;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class BOT
{
    public StreamSelectLoop $loop;
    public Logger $logger;

    public Discord $discord;
    public Slash $slash;
    public RCON $rcon;
    public bool $ready = false;
    private readonly array $options;

    public MessageHandler $messageHandler;
    
    public readonly string $owner_id;
    public readonly string $command_symbol;
    public readonly string $guild_id; // This bot currently only supports one guild at a time
    public array $channel_ids = [];
    public array $role_ids = [];

    public array $timers = [];
    public int $timerCounter = 0;

    public \Closure $onFulfilledDefault;
    public \Closure $onRejectedDefault;
    
    public function __construct(array $options)
    {
        if (php_sapi_name() !== 'cli') trigger_error('DiscordPHP will not run on a webserver. Please use PHP CLI to run a DiscordPHP bot.', E_USER_ERROR);

        // x86 need gmp extension for big integer operation
        if (PHP_INT_SIZE === 4 && ! BigInt::init()) trigger_error('ext-gmp is not loaded. Permissions will NOT work correctly!', E_USER_WARNING);

        $this->options = $this->resolveOptions($options);

        $this->afterConstruct($options/*, $server_options*/);
    }

    public function afterConstruct()
    {
        //$this->httpHandler = new HttpHandler($this, [], $options['http_whitelist'] ?? [], $options['http_key'] ?? '');
        $this->messageHandler = new MessageHandler($this);
    }

    public function resolveOptions($options)
    {
        if (! isset($options['loop']) || ! ($options['loop'] instanceof LoopInterface)) $options['loop'] = Loop::get();
        if (! isset($options['logger']) || ! ($options['logger'] instanceof Logger)) {
            $streamHandler = new StreamHandler('php://stdout', Level::Info);
            $streamHandler->setFormatter(new LineFormatter(null, null, true, true));
            $options['logger'] = new Logger(self::class, [$streamHandler]);
        }
        $this->loop = $options['loop'];
        $this->logger = $options['logger'];
        $this->onFulfilledDefault = function ($result): void
        {
            $this->logger->debug('Promise resolved with type of: `' . gettype($result) . '`');
        };
        $this->onRejectedDefault = function ($reason): void
        {
            $this->logger->error("Promise rejected with reason: `$reason'`");
        };

        if (isset($options['channel_ids'])) foreach ($options['channel_ids'] as $key => $id) $this->channel_ids[$key] = $id;
        else $this->logger->warning('No channel_ids passed in options!');
        if (isset($options['role_ids'])) foreach ($options['role_ids'] as $key => $id) $this->role_ids[$key] = $id;
        else $this->logger->warning('No role_ids passed in options!');

        if (isset($options['discord']) && ($options['discord'] instanceof Discord)) $this->discord = $options['discord'];
        elseif (isset($options['discord_options']) && is_array($options['discord_options'])) $this->discord = new Discord($options['discord_options']);
        else $this->logger->error('No Discord instance or options passed in options!');
        if (isset($options['discordToken'])) unset($options['discordToken']);

        require 'slash.php';
        $this->slash = new Slash($this);

        if (! isset($options['rcon']) || ! ($options['rcon'] instanceof RCON)) throw new \InvalidArgumentException('Invalid RCON object!');
        $this->rcon = $options['rcon'];
        
        if (isset($options['guild_id']) && is_string($options['guild_id'])) $this->guild_id = $options['guild_id'];
        else throw new \InvalidArgumentException('Invalid Discord guild_id!');

        if (isset($options['command_symbol']) && is_string($options['command_symbol'])) $this->command_symbol = $options['command_symbol'];
        else $options['command_symbol'] = ';';
        $this->command_symbol = $options['command_symbol'];

        return $options;
    }

    public function then(PromiseInterface $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        return $promise->then($onFulfilled ?? $this->onFulfilledDefault, $onRejected ?? $this->onRejectedDefault);
    }

    public function run()
    {
        $this->discord->on('ready', function ($discord) {
            if ($guild = $this->discord->guilds->get('id', $this->guild_id)) $this->owner_id = $guild->owner_id;
            else $this->logger->warning('Discord Guild not found!');
            $this->generateGlobalFunctions();
            $this->logger->debug('[CHAT COMMAND LIST] ' . PHP_EOL . $this->messageHandler->generateHelp());

            $this->logger->info("logged in as {$this->discord->user->displayname} ({$this->discord->id})");
            $this->logger->info('------');
            $this->ready = true;

            $this->rcon->getPlayers(true);
            $this->__startUpdatePlayerCountTimer();

            $this->then($this->discord->application->commands->freshen(), function (GlobalCommandRepository $commands): void
            {
                $this->slash->updateCommands($commands);
            });

            $this->discord->on('message', function ($message) {
                $this->messageHandler->handle($message);
            });
        });

        

        $this->discord->run();
    }

    /*
     * The generated functions include `ping` and `help`.
     * The `ping` function replies with "Pong!" when called.
     * The `help` function generates a list of available commands based on the user's roles.
     * And more! (see the code for more details)
     */
    protected function generateGlobalFunctions(): void
    { // TODO: add infantry and veteran roles to all non-staff command parameters except for `approveme`
        // messageHandler
        $this->messageHandler->offsetSet('ping', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $this->messageHandler->reply($message, 'Pong!');
        }));

        $help = new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $this->messageHandler->reply($message, $this->messageHandler->generateHelp($message->member->roles), 'help.txt', true);
        });
        $this->messageHandler->offsetSet('help', $help);
        $this->messageHandler->offsetSet('commands', $help);

        $players = new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            if (! $players = $this->rcon->getPlayers()) return $this->messageHandler->reply($message, 'No players found!');

            $playerCount = count($players);
            $playerList = implode(', ', $players);

            return $this->messageHandler->reply($message, "Players ($playerCount):" . PHP_EOL . $playerList);
        });
        $this->messageHandler->offsetSet('players', $players);
    }
    
    protected function __updatePlayerCountChannel(): ?PromiseInterface
    {
        if (! $channel = $this->discord->getChannel($this->channel_ids['pz-players'])) return null;
        
        [$channelName, $oldPlayerCount] = explode('-', $channel->name);
        $newPlayerCount = $this->rcon->getPlayerCount(true);
        if (is_numeric($oldPlayerCount) && (intval($oldPlayerCount) === $newPlayerCount)) return null;
        
        $channel->name = "{$channelName}-{$newPlayerCount}";
        return $channel->guild->channels->save($channel, 'Player count update');
    }

    protected function __startUpdatePlayerCountTimer(): void
    {

        if (! isset($this->timers['updatePlayerCountTimer'])) {
            $periodic = function(): void
            {
                if (! $channel = $this->discord->getChannel($this->channel_ids['pz-players'])) return;

                $promise = null;
                $populate = false;
                if (($this->timerCounter !== 0) && ($this->timerCounter % 6 === 0)) {
                    $promise = $this->__updatePlayerCountChannel($populate = true);
                }

                $channel_id = $channel->id;
                $sendTransitMessage = function () use ($populate, $channel_id): void
                {
                    if (! $channel = $this->discord->getChannel($channel_id)) return;
                    $msg = '';
                    if ($playersWhoJoined = $this->rcon->getPlayersWhoJoined($populate ? false : true)) $msg .= 'Connected: ' . implode(', ', $playersWhoJoined) . PHP_EOL;
                    if ($playersWhoLeft = $this->rcon->getPlayersWhoLeft()) $msg .= 'Disconnected: ' . implode(', ', $playersWhoLeft) . PHP_EOL;
                    if ($msg) $this->messageHandler->sendMessage($channel, $msg);
                };
                $onFulfilled = function ($new_channel) use ($sendTransitMessage): TimerInterface
                {
                    $this->timerCounter = 0;
                    return $this->loop->addTimer(2, $sendTransitMessage);
                };
                $onRejected = function ($reason) use ($channel): ?PromiseInterface
                {
                    $this->logger->error('Failed to update player count channel: ' . $reason);
                    return $this->messageHandler->sendMessage($channel, 'Failed to update player count channel: ' . $reason);
                };
                if ($promise) $this->then($promise, $onFulfilled , $onRejected);
                else {
                    $sendTransitMessage($populate, $channel->id);
                    $this->timerCounter++;
                }
            };
            $this->timers['updatePlayerCountTimer'] = $this->loop->addPeriodicTimer(30, $periodic);
        }
    }
}