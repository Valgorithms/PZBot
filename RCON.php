<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ;

class RCON
{
    public string $serverIp;
    public int $rconPort;

    private readonly string $rconPassword;
    //private string $mcrconDir = '';
    private string $mcrconPath = '';

    public bool $initialized = false;
    public array $previousPlayers = [];
    public array $currentPlayers = [];

    public function __construct(string $serverIp, int $rconPort, string $rconPassword, string $mcrconDir, ?string $mcronPath = 'mcrcon.exe')
    {
        if (! filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_RES_RANGE)) 
            throw new \InvalidArgumentException('Invalid server IP address');
        $this->serverIp = $serverIp;
        if ($rconPort < 1 || $rconPort > 65535)
            throw new \InvalidArgumentException('Invalid RCON port number');
        $this->rconPort = $rconPort;

        $this->rconPassword = $rconPassword;

        if (! is_dir($mcrconDir))
            throw new \InvalidArgumentException('Invalid directory for mcrcon: ' . $mcrconDir);
        //$this->mcrconDir = $mcrconDir;
        
        if (! file_exists($mcrconPath = $mcrconDir . '\\' . $mcronPath))
            throw new \InvalidArgumentException('Invalid file for mcrcon:' . $mcrconPath);
        $this->mcrconPath = $mcrconPath;

        $this->__populatePlayers();
        $this->initialized = true;
    }

    public function getPlayerCount($populate = false): int
    {
        if ($populate) $this->__populatePlayers();
        return count($this->currentPlayers);
    }
    
    public function getPlayersWhoJoined($populate = false): array
    {        
        if ($populate) $this->__populatePlayers();
        return array_diff($this->currentPlayers, $this->previousPlayers);
    }

    public function getPlayersWhoLeft($populate = false): array
    {
        if ($populate) $this->__populatePlayers();
        return array_diff($this->previousPlayers, $this->currentPlayers);
    }
    
    public function getPlayers($populate = false): array
    {
        if ($populate) $this->__populatePlayers();
        return $this->currentPlayers;
    }
    
    private function __populatePlayers(): array
    {
        $this->previousPlayers = $this->currentPlayers;
        
        $playersString = $this->__getPlayersString();
        $playersString = is_string($playersString) ? str_replace("Error: Failed to execute mcrcon command.", "", $playersString) : '';
        return $this->currentPlayers = $playersString ? explode(PHP_EOL, trim($playersString)) : [];
    }

    private function __getPlayersString(): string|false
    { // Needs to be tested
        if (empty($this->serverIp)) {
            echo "Error: Failed to resolve server IP address." . PHP_EOL;
            return false;
        }

        // "a" (or any other input) is needed to flush the output buffer
        $command = sprintf('%s -H %s -P %s -p %s "players" "a"', escapeshellcmd($this->mcrconPath), escapeshellarg($this->serverIp), escapeshellarg($this->rconPort), escapeshellarg($this->rconPassword));
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            echo "Error: Failed to execute mcrcon command." . PHP_EOL;
            return false;
        }

        $playerLines = array_map(function($line) {
            return substr($line, 1);
        }, array_slice($output, 1));

        return implode(PHP_EOL, $playerLines);
    }

    private function __(): string|false
    { // Needs to be tested
        $socket = fsockopen($this->serverIp, $this->rconPort, $errno, $errstr, 30);
        if (!$socket) {
            echo "Error: $errstr ($errno)" . PHP_EOL;
            return false;
        }

        $authCommand = sprintf("auth %s" . PHP_EOL, $this->rconPassword);
        fwrite($socket, $authCommand);
        $authResponse = fgets($socket);
        if (strpos($authResponse, 'failed') !== false) {
            echo "Error: Authentication failed." . PHP_EOL;
            fclose($socket);
            return false;
        }

        $command = "players" . PHP_EOL;
        fwrite($socket, $command);
        $output = '';
        while (!feof($socket)) {
            $output .= fgets($socket, 128);
        }
        fclose($socket);

        if (empty($output)) {
            echo "Error: Failed to retrieve player list." . PHP_EOL;
            return false;
        }

        $playerLines = array_filter(array_map('trim', explode(PHP_EOL, $output)));
        return implode(PHP_EOL, $playerLines);
    }
}