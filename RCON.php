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
    private string $batDir = '';
    private string $batPath = '';

    public bool $initialized = false;
    public array $previousPlayers = [];
    public array $currentPlayers = [];

    public function __construct(string $serverIp, int $rconPort, string $rconPassword, string $mcrconDir, ?string $mcronPath = 'mcrcon.exe', ?string $batDir = __DIR__, ?string $batFile = 'pz.bat')
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

        if (! is_dir($batDir))
            throw new \InvalidArgumentException('Invalid directory for pz.bat:' . $batDir);
        $this->batDir = $batDir;

        if (! file_exists($batDir . '\\' . $batFile))
            throw new \InvalidArgumentException('Invalid file for pz.bat:' . $batFile);
        $this->batPath = $batDir . '\\' . $batFile;

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
        return $this->currentPlayers = $playersString ? explode("\n", trim($playersString)) : [];
    }
    
    /*
    * Function to execute pz.bat in the current directory
    * Creats a txt file containing the output of the batch file, formatted as a list of players
    * Usage: pz.bat SERVER_IP [RCON_PASSWORD] [RCON_PORT] [MCRCON_PATH]
    */
    private function __getPlayersString(): string|false|null
    {   
        $launchOptions = sprintf('"%s" "%s" "%d" "%s"', $this->serverIp, $this->rconPassword, $this->rconPort, $this->mcrconPath);
        $cmd = "cd /d \"{$this->batDir}\" && \"{$this->batPath}\" $launchOptions";
        return shell_exec($cmd);
    }
}