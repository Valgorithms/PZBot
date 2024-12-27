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
use Discord\Repository\Guild\GuildCommandRepository;
use Discord\Repository\Interaction\GlobalCommandRepository;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Promise\PromiseInterface;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use PZ\Handlers\MessageHandler;
use PZ\Handlers\MessageHandlerCallback;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Bot
{
    public StreamSelectLoop $loop;
    public Logger $logger;
    protected readonly array $options;

    public Discord $discord;
    public Slash $slash;
    public RCON $rcon;
    public bool $ready = false;

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

        $this->resolveOptions($options);

        $this->afterConstruct($options/*, $server_options*/);
    }

    public function afterConstruct()
    {
        //$this->httpHandler = new HttpHandler($this, [], $options['http_whitelist'] ?? [], $options['http_key'] ?? '');
        $this->messageHandler = new MessageHandler($this);
    }

    public function resolveOptions(array $options = [])
    {
        $resolver = new OptionsResolver();
        
        $resolver->setDefaults([
            'loop' => Loop::get(),
            'logger' => new Logger(
                self::class,
                [(new StreamHandler('php://stdout', Level::Info))->setFormatter(new LineFormatter(null, null, true, true))]
            ),
            'channel_ids' => [],
            'role_ids' => [],
            'discord' => null,
            'discord_options' => [],
            'rcon' => null,
            'guild_id' => null,
            'command_symbol' => ';',
        ]);

        $resolver->setAllowedTypes('loop', [LoopInterface::class]);
        $resolver->setAllowedTypes('logger', [Logger::class]);
        $resolver->setAllowedTypes('channel_ids', 'array');
        $resolver->setAllowedTypes('role_ids', 'array');
        $resolver->setAllowedTypes('discord', ['null', Discord::class]);
        $resolver->setAllowedTypes('discord_options', ['null', 'array']);
        $resolver->setAllowedTypes('rcon', RCON::class);
        $resolver->setAllowedTypes('guild_id', 'string');
        $resolver->setAllowedTypes('command_symbol', 'string');

        $options = $resolver->resolve($options);

        $this->loop = $options['loop'];
        $this->logger = $options['logger'];
        $this->onFulfilledDefault = fn($result) => $this->logger->debug('Promise resolved with type of: `' . gettype($result) . '`');
        $this->onRejectedDefault = function ($reason): void
        {
            $this->logger->error("Promise rejected with reason: `$reason'`");
        };
        if (!$options['discord'] && empty($options['discord_options'])) {
            throw new \InvalidArgumentException('Either discord or discord_options must be set.');
        }
        $this->discord = $options['discord'] ?? new Discord($options['discord_options']);

        foreach ($options['channel_ids'] as $key => $id) $this->channel_ids[$key] = $id;
        foreach ($options['role_ids'] as $key => $id) $this->role_ids[$key] = $id;

        $this->slash = new Slash($this);

        $this->rcon = $options['rcon'];
        $this->guild_id = $options['guild_id'];
        $this->command_symbol = $options['command_symbol'];

        return $this->options = $options;
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

            $this->then(
                $this->discord->application->commands->freshen(),
                fn (GlobalCommandRepository $commands) => $this->slash->updateGlobalCommands($commands)
            );
            foreach ($this->discord->guilds as $guild) {
                $this->then(
                    $guild->commands->freshen(),
                    fn (GuildCommandRepository $commands) => $this->slash->updateGuildCommands($commands)
                );
            }

            $this->discord->on('message', fn($message) => $this->messageHandler->handle($message));
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
        $this->messageHandler->offsetSet(
            'ping',
            new MessageHandlerCallback(fn(Message $message, array $message_filtered, string $command): PromiseInterface =>
                $this->messageHandler->reply($message, 'Pong!'))
        );

        $help = new MessageHandlerCallback(fn(Message $message, array $message_filtered, string $command): PromiseInterface =>
            $this->messageHandler->reply($message, $this->messageHandler->generateHelp($message->member->roles), 'help.txt', true)
        );
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
        //if (! $channel->created) return null;
        
        [$channelName, $oldPlayerCount] = explode('-', $channel->name);
        $newPlayerCount = $this->rcon->getPlayerCount(true);
        if (is_numeric($oldPlayerCount) && (intval($oldPlayerCount) === $newPlayerCount)) return null;
        
        $channel->name = "{$channelName}-{$newPlayerCount}";
        return $channel->guild->channels->save($channel, 'Player count update');
    }

    protected function __startUpdatePlayerCountTimer(): void
    {
        if (! isset($this->timers['updatePlayerCountTimer'])) {
            $this->timers['updatePlayerCountTimer'] = $this->loop->addPeriodicTimer(
                30,
                function(): void
                {
                    if (! $channel = $this->discord->getChannel($this->channel_ids['pz-players'])) return;
                    ($promise = ($this->timerCounter !== 0 && $this->timerCounter % 6 === 0) ? $this->__updatePlayerCountChannel($populate = true) : null)
                        ? $this->then(
                            $promise,
                            fn() => $this->timerCounter = 0,
                            function ($reason) use ($channel): ?PromiseInterface
                            {
                                $this->logger->error($err = "Failed to update player count channel: $reason");
                                return $this->messageHandler->sendMessage($channel, $err);
                            }
                        ) : $this->timerCounter++;
                    
                    if ($msg = implode(PHP_EOL, array_filter([
                        ($connected = trim(implode(', ', $this->rcon->getPlayersWhoJoined(true)))) ? "Connected: $connected" : '',
                        ($disconnected = trim(implode(', ', $this->rcon->getPlayersWhoLeft()))) ? "Disconnected: $disconnected" : ''
                    ]))) $this->messageHandler->sendMessage($channel, $msg);
                }
            );
        }
    }
}