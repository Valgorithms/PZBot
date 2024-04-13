# PZ BOT for DiscordPHP

This is a PZ BOT class for DiscordPHP. It provides functionalities for interacting with Discord using PHP.

## Installation

Before you start, make sure you have PHP installed on your machine. This BOT class requires PHP CLI to run.

1. Clone this repository to your local machine.
2. Navigate to the project directory.
3. Install the required dependencies. This project requires the `Monolog` library for logging and the `BigInt` extension for big integer operations. If you're using Composer, you can install these dependencies by running `composer install`.

## Usage

To use the PZ BOT class, you need to create an instance of the class and pass an options array to the constructor. The options array should include the following keys:

- `discordToken`: Your Discord bot token.
- `clientId`: Your Discord client ID.
- `clientSecret`: Your Discord client secret.
- `guild_id`: The ID of the guild (server) that the bot is connected to.
- `channel_ids`: An associative array mapping channel names to their IDs.
- `role_ids`: An associative array mapping role names to their IDs.
- `loop`: An instance of the ReactPHP event loop.
- `discord`: An instance of the DiscordPHP client.
- `rcon`: An instance of the RCON client.

Here's an example of how to use the PZ BOT class:

```php
$options = [
    'discordToken' => 'your_discord_token',
    'clientId' => 'your_client_id',
    'clientSecret' => 'your_client_secret',
    'guild_id' => 'your_guild_id',
    'channel_ids' => [
        'pz-players' => 'channel_id', // Channel name will automatically update with the # of players
        // Add more channels as needed
    ],
    'role_ids' => [
        'role_name' => 'role_id',
        // Add more roles as needed
    ],
    'loop' => $loop,
    'discord' => $discord,
    'rcon' => $rcon,
];

$bot = new PZ\BOT($options);
$bot->run();
```
Replace 'your_discord_token', 'your_client_id', 'your_client_secret', 'your_guild_id', 'channel_name', 'channel_id', 'role_name', 'role_id', $loop, $discord, and $rcon with your actual values. Also, you might need to adjust the installation and usage instructions based on your actual project setup and requirements.

## Contributing

Contributions are welcome! Please submit a pull request with any enhancements, bug fixes, or other contributions.

## License

This project is licensed under the MIT License. See the LICENSE file for more details.