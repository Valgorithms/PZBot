<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); // Unlimited memory usage
define('MAIN_INCLUDED', 1); // Token and SQL credential files may be protected locally and require this to be defined to access
@include getcwd() . '/vendor/autoload.php';

// This is required for the example script to work, but you should use Composer's autoloader instead
require 'secret.php';
require 'RCON.php';
require 'PZ.php';
require 'Handler.php';
require 'MessageHandler.php';

const MCRCON_DIR = __DIR__;
const MCRCON_FILE = 'mcrcon.exe';
const RCON_PORT = 27015;
$discordToken = getenv('discord_token');
$clientId = getenv('dwa_client_id');
$clientSecret = getenv('dwa_client_secret');
$rconPassword = getenv('rcon_password');
$globalIp = gethostbyname('www.valzargaming.com');

$rcon = new PZ\RCON($globalIp, RCON_PORT, $rconPassword, MCRCON_DIR, MCRCON_FILE);

use Discord\Discord;
use React\EventLoop\Loop;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Discord\WebSockets\Intents;

$loop = Loop::get();
$streamHandler = new StreamHandler('php://stdout', Level::Info);
$streamHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger = new Logger('PZ', [$streamHandler]);
$discord = new Discord([
    'loop' => $loop,
    'logger' => $logger,
    /* // Disabled for debugging
    'cache' => new CacheConfig(
        $interface = new RedisCache(
            (new Redis($loop))->createLazyClient('127.0.0.1:6379'),
            'dphp:cache:
        '),
        $compress = true, // Enable compression if desired
        $sweep = false // Disable automatic cache sweeping if desired
    ), 
    */
    'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
    ],
    'token' => $discordToken,
    'loadAllMembers' => true,
    'storeMessages' => true, // Because why not?
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT,
]);

$options = array(
    'discordToken' => $discordToken,
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'guild_id' => '1077144430588469349',
    'channel_ids' => array(
        'pz-players' => '1226732689525313638'
    ),
    'role_ids' => array(
        // Discord ranks
        'Developer' => '798026051304554565',
        'Administrator' => '798026051304554564'
    )
);

$hidden_options = [
    'loop' => $loop,
    'discord' => $discord,
    'rcon' => $rcon
];

$options = array_merge($options, $hidden_options);

$bot = new PZ\BOT($options);
$bot->run();