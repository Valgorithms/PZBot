<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ\Handlers;

use PZ\Bot;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;
use PZ\Interfaces\MessageHandlerInterface;

class MessageHandler extends Handler implements MessageHandlerInterface
{
    protected array $required_permissions;
    protected array $match_methods;
    protected array $descriptions;

    public function __construct(Bot &$pz, array $handlers = [], array $required_permissions = [], array $match_methods = [], array $descriptions = [])
    {
        parent::__construct($pz, $handlers);
        $this->required_permissions = $required_permissions;
        $this->match_methods = $match_methods;
        $this->descriptions = $descriptions;
    }

    public function get(): array
    {
        return [$this->handlers, $this->required_permissions, $this->match_methods, $this->descriptions];
    }

    public function set(array $handlers, array $required_permissions = [], array $match_methods = [], array $descriptions = []): self
    {
        parent::set($handlers);
        $this->required_permissions = $required_permissions;
        $this->match_methods = $match_methods;
        $this->descriptions = $descriptions;
        return $this;
    }

    public function pull(int|string $index, ?callable $defaultCallables = null, ?array $default_required_permissions = null, ?array $default_match_methods = null, ?array $default_descriptions = null): array
    {
        $return = [];
        $return[] = parent::pull($index, $defaultCallables);

        if (isset($this->required_permissions[$index])) {
            $default_required_permissions = $this->required_permissions[$index];
            unset($this->required_permissions[$index]);
        }
        $return[] = $default_required_permissions;

        if (isset($this->match_methods[$index])) {
            $default_match_methods = $this->match_methods[$index];
            unset($this->match_methods[$index]);
        }
        $return[] = $default_match_methods;

        if (isset($this->descriptions[$index])) {
            $default_descriptions = $this->descriptions[$index];
            unset($this->descriptions[$index]);
        }
        $return[] = $default_descriptions;

        return $return;
    }

    public function fill(array $commands, array $handlers, array $required_permissions = [], array $match_methods = [], array $descriptions = []): self
    {
        if (count($commands) !== count($handlers)) {
            throw new \Exception('Commands and Handlers must be the same length.');
            return $this;
        }
        foreach($commands as $command) {
            parent::pushHandler(array_shift($handlers), $command);
            $this->pushPermission(array_shift($required_permissions), $command);
            $this->pushMethod(array_shift($match_methods), $command);
            $this->pushDescription(array_shift($descriptions), $command);
        }
        return $this;
    }
    
    public function pushPermission(array $required_permissions, int|string|null $command = null): ?self
    {
        if ($command) $this->required_permissions[$command] = $required_permissions;
        else $this->required_permissions[] = $required_permissions;
        return $this;
    }

    public function pushMethod(string $method, int|string|null $command = null): ?self
    {
        if ($command) $this->match_methods[$command] = $method;
        else $this->match_methods[] = $method;
        return $this;
    }

    public function pushDescription(string $description, int|string|null $command = null): ?self
    {
        if ($command) $this->descriptions[$command] = $description;
        else $this->descriptions[] = $description;
        return $this;
    }

    public function first(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        return $return;
    }
    
    public function last(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        return $return;
    }

    public function find(callable $callback): array
    {
        foreach ($this->handlers as $index => $handler)
            if ($callback($handler))
                return [$handler, $this->required_permissions[$index] ?? [], $this->match_methods[$index] ?? 'str_starts_with', $this->descriptions[$index] ?? ''];
        return [];
    }

    public function clear(): self
    {
        parent::clear();
        $this->required_permissions = [];
        $this->match_methods = [];
        $this->descriptions = [];
        return $this;
    }
    
    // TODO: Review this method
    public function map(callable $callback): static
    {
        $arr = array_combine(array_keys($this->handlers), array_map($callback, array_values($this->toArray())));
        return new static($this->pz, array_shift($arr) ?? [], array_shift($arr) ?? [], array_shift($arr) ?? [], array_shift($arr) ?? []);
    }

    /**
     * @throws Exception if toArray property does not exist
     */
    public function merge(object $handler): self
    {
        if (! property_exists($handler, 'toArray')) {
            throw new \Exception('Handler::merge() expects parameter 1 to be an object with a method named "toArray", ' . gettype($handler) . ' given');
            return $this;
        }
        $toArray = $handler->toArray();
        $this->handlers = array_merge($this->handlers, array_shift($toArray) ?? []);
        $this->required_permissions = array_merge($this->required_permissions, array_shift($toArray) ?? []);
        $this->match_methods = array_merge($this->match_methods, array_shift($toArray) ?? []);
        $this->descriptions = array_merge($this->descriptions, array_shift($toArray) ?? []);
        return $this;
    }

    public function toArray(): array
    {
        $toArray = parent::toArray();
        $toArray[] = $this->required_permissions ?? [];
        $toArray[] = $this->match_methods ?? [];
        $toArray[] = $this->descriptions ?? [];
        return $toArray;
    }

    public function offsetGet(int|string $offset): array
    {
        $return = parent::offsetGet($offset);
        $return[] = $this->required_permissions[$offset] ?? null;
        $return[] = $this->match_methods[$offset] ?? null;
        $return[] = $this->descriptions[$offset] ?? null;
        return $return;
    }
    
    public function offsetSet(int|string $offset, callable $callback, ?array $required_permissions = [], ?string $method = 'str_starts_with', ?string $description = ''): self
    {
        parent::offsetSet($offset, $callback);
        $this->required_permissions[$offset] = $required_permissions;
        $this->match_methods[$offset] = $method;
        $this->descriptions[$offset] = $description;
        return $this;
    }
    
    public function setOffset(int|string $newOffset, callable $callback, ?array $required_permissions = [], ?string $method = 'str_starts_with', ?string $description = ''): self
    {
        parent::setOffset($newOffset, $callback);
        if ($offset = $this->getOffset($callback) === false) $offset = $newOffset;
        unset($this->required_permissions[$offset]);
        unset($this->match_methods[$offset]);
        unset($this->descriptions[$offset]);
        $this->required_permissions[$newOffset] = $required_permissions;
        $this->match_methods[$newOffset] = $method;
        $this->descriptions[$newOffset] = $description;
        return $this;
    }

    public function __debugInfo(): array
    {
        return ['pz' => isset($this->pz) ? $this->pz instanceof BOT : false, 'handlers' => array_keys($this->handlers)];
    }

    //Unique to MessageHandler
    
    public function handle(Message $message): ?PromiseInterface
    {
        // if (! $message->member) return $message->reply('Unable to get Discord Member class. Commands are only available in guilds.');
        $message_filtered = $this->filterMessage($message);
        foreach ($this->handlers as $command => $callback) {
            switch ($this->match_methods[$command]) {
                case 'exact':
                $method_func = function () use ($callback, $message_filtered, $command): ?callable
                {
                    if ($message_filtered['message_content_lower'] == $command)
                        return $callback; // This is where the magic happens
                    return null;
                };
                break;
                case 'str_contains':
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_contains($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
                    break;
                case 'str_ends_with':
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_ends_with($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
                    break;
                case 'str_starts_with':
                default:
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_starts_with($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
            }
            if (! $message->member) return null;
            if ($callback = $method_func()) { // Command triggered
                $required_permissions = $this->required_permissions[$command] ?? [];
                if ($lowest_rank = array_pop($required_permissions)) {
                    if (! isset($this->pz->role_ids[$lowest_rank])) {
                        $this->pz->logger->warning("Unable to find role ID for rank `$lowest_rank`");
                        throw new \Exception("Unable to find role ID for rank `$lowest_rank`");
                    } elseif (! $this->checkRank($message->member->roles, $this->required_permissions[$command] ?? [])) return $this->reply($message, 'Rejected! You need to have at least the <@&' . $this->pz->role_ids[$lowest_rank] . '> rank.');
                }
                return $callback($message, $message_filtered, $command);
            }
        }
        if (empty($this->handlers)) $this->pz->logger->info('No message handlers found!');
        return null;
    }

    // Don't forget to use ->setAllowedMentions(['parse'=>[]]) on the MessageBuilder object to prevent all roles being pinged
    public function generateHelp(?Collection $roles = null): string
    {
        $ranks = array_keys($this->pz->role_ids);
        $ranks[] = 'everyone';
        
        $array = [];
        foreach (array_keys($this->handlers) as $command) {
            $required_permissions = $this->required_permissions[$command] ?? [];
            $lowest_rank = array_pop($required_permissions) ?? 'everyone';
            if (! $roles) $array[$lowest_rank][] = $command;
            elseif ($lowest_rank == 'everyone' || $this->checkRank($roles, $this->required_permissions[$command])) $array[$lowest_rank][] = $command;
        }
        $string = '';
        foreach ($ranks as $rank) {
            if (! isset($array[$rank]) || ! $array[$rank]) continue;
            if (is_numeric($rank)) $string .= '<@&' . $this->pz->role_ids[$rank] . '>: `';
            else $string .= '@' . $rank . ': `'; // everyone
            asort($array[$rank]);
            $string .= implode('`, `', $array[$rank]);
            $string .= '`' . PHP_EOL;
        }
        return $string;
    }

    public function sendMessage($channel, string $content, string $file_name = 'message.txt', $prevent_mentions = false): ?PromiseInterface
    {
        // $this->logger->debug("Sending message to {$channel->name} ({$channel->id}): {$message}");
        if (is_string($channel)) $channel = $this->pz->discord->getChannel($channel);
        if (! $channel) {
            $this->pz->logger->error("Channel not found: {$channel}");
            return null;
        }
        $builder = MessageBuilder::new();
        if ($prevent_mentions) $builder->setAllowedMentions(['parse'=>[]]);
        if (strlen($content)<=2000) return $channel->sendMessage($builder->setContent($content));
        if (strlen($content)<=4096) {
            $embed = new Embed($this->pz->discord);
            $embed->setDescription($content);
            $builder->addEmbed($embed);
            return $channel->sendMessage($builder);
        }
        return $channel->sendMessage($builder->addFileFromContent($file_name, $content));
    }

    public function reply(Message $message, string $content, string $file_name = 'message.txt', bool $prevent_mentions = false): ?PromiseInterface
    {
        // $this->logger->debug("Sending message to {$channel->name} ({$channel->id}): {$message}");
        $builder = MessageBuilder::new();
        if ($prevent_mentions) $builder->setAllowedMentions(['parse'=>[]]);
        if (strlen($content)<=2000) return $message->reply($builder->setContent($content));
        if (strlen($content)<=4096) {
            $embed = new Embed($this->pz->discord);
            $embed->setDescription($content);
            $builder->addEmbed($embed);
            return $message->reply($builder);
        }
        return $message->reply($builder->addFileFromContent($file_name, $content));
    }
    
    public function filterMessage(Message $message): array
    {
        if (! $message->guild || $message->guild->owner_id != $this->pz->owner_id)  return ['message_content' => '', 'message_content_lower' => '', 'called' => false]; // Only process commands from a guild that Taislin owns
        $message_content = '';
        $prefix = $this->pz->command_symbol;
        $called = false;
        if (str_starts_with($message->content, $call = $prefix . ' ')) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        elseif (str_starts_with($message->content, $call = "<@!{$this->pz->discord->id}>")) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        elseif (str_starts_with($message->content, $call = "<@{$this->pz->discord->id}>")) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        return ['message_content' => $message_content, 'message_content_lower' => strtolower($message_content), 'called' => $called];
    }
    
    // Don't forget to use ->setAllowedMentions(['parse'=>[]]) on the MessageBuilder object to prevent all roles being pinged
    public function __toString(): string
    {
        return $this->generateHelp();
    }
}