<?php
namespace ImagicalGamer\SurvivalGames;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

use pocketmine\Server;
use pocketmine\Player;

use pocketmine\level\Level;

use pocketmine\utils\TextFormat as C;

use ImagicalGamer\SurvivalGames\Commands\SurvivalGamesCommand;
use ImagicalGamer\SurvivalGames\Commands\StatsCommand;
use ImagicalGamer\SurvivalGames\Tasks\RefreshSigns;
use ImagicalGamer\SurvivalGames\Tasks\GameSender;
use ImagicalGamer\SurvivalGames\Tasks\Updater\UpdateCheckTask;

use pocketmine\level\Position;
use pocketmine\utils\Config;

use pocketmine\tile\Chest;

/* Copyright (C) ImagicalGamer - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Jake C <imagicalgamer@outlook.com>, August 2016
 */

class Main extends PluginBase implements Listener{

  public $mode = 0;
  public $prefix;
  public $format;
  public $current_lev = "";
  public $joinText = C::AQUA . "[JOIN]";
  public $runningText = C::RED . "[Full]";
  public $arenas = array();

    public function onEnable(){
    chmod("/arenas.json",0777);
    $this->getServer()->getPluginManager()->registerEvents($this ,$this);
    $this->saveResource("/config.yml");
    $this->saveResource("/arenas.json");
    chmod("/arenas.json",0777);
    @mkdir($this->getDataFolder());
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON, $default = ["Arenas" => []]);
    chmod("/arenas.json",0777);
    $cfg2 = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    $itm = array(array(261,0,1),array(262,0,2),array(262,0,3),array(267,0,1),array(268,0,1),array(272,0,1),array(276,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1),array(283,0,1));
    if(empty($cfg2->get("Items"))){
      $cfg2->set("Items", $itm);
      $cfg2->save();
    }
    $cfg->save();
    $this->prefix = $cfg2->get('Prefix') . " ";
    $this->format = $cfg2->get('Prefix') . " ";
    $this->refreshArenas();
    $this->loadArenas();
    if(!is_writeable($this->getDataFolder() . "/arenas.json")){
      $this->getLogger()->error("Cannot write to file 'arenas.json' please insure that the files permission level is '1777'... Disabling plugin");
      return;
    }
    $this->getServer()->getCommandMap()->register("sg", new SurvivalGamesCommand("sg", $this));
    $this->getServer()->getCommandMap()->register("sg", new StatsCommand("sg", $this));
    $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    $this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
    $this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 25);
    $this->getServer()->getScheduler()->scheduleAsyncTask($task = new UpdateCheckTask($this->getVersion()));
    $this->getLogger()->info(C::GREEN . "Enabled!");
  }

  public function onDisable(){
    $this->refreshArenas();
    chmod("/arenas.json",0777);
    $this->saveData();
    chmod("/arenas.json",0777);
  }

  public function newArena(Player $player, String $lv, Integer $num){
    if($this->isArena($lv)){
      $player->sendMessage(C::RED . "Theres already an arena in level " . $lv . "!");
      return false;
    }
    if(!$this->getServer()->getLevelByName($lv)){
      $player->sendMessage(C::RED . "Level not found");
      return false;
    }
    $this->getServer()->loadLevel($lv);
    $lev = $this->getServer()->getLevelByName($lv);
    $player->teleport($this->getServer()->getLevelByName($lv)->getSafeSpawn(),0,0);
    $this->current_lev = $lv;
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $cfg->set($lv. "MaxPlayer", $num);
    $player->setGamemode(1);
    $player->sendMessage($this->prefix . "Your about to register an arena! Tap a block to set a spawn!");
    $this->mode = 1;
    return false;
  }

  public function minPlayer(){
    return 2;
  }

  public function maxPlayer(String $arena){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $maxPlayer = $cfg->get($arena . "MaxPlayer");
    return $maxPlayer;
  }

  public function isArena(String $arena){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    if(in_array($arena, $this->arenas)){
      return true;
    }
    else{
      return false;
    }
  }

  public function addArena(String $arena){
    array_push($this->arenas, $arena);
    $this->refreshArenas();
  }

  public function refresh(String $arena){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $cfg->set($arena . "StartTime", 30);
    $cfg->set($arena . "PlayTime", 780);
    $cfg->save();
  }

  public function saveData(){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $cfg->set("Arenas",$this->arenas);
    $cfg->save();
  }

  public function setVersion(int $version){
    $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    $cfg->set("Version", $version);
    $cfg->save();
    $cfg->reload();
  }

  public function loadArenas(){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    foreach($cfg->get("Arenas") as $lev)
    {
      array_push($this->arenas, $lev);
      $this->getServer()->loadLevel($lev);
    }

  }

  public function refreshArenas(){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    foreach($this->arenas as $arena)
    {
      $cfg->set($arena . "PlayTime", 780);
      $cfg->set($arena . "StartTime", 30);
    }
    $cfg->save();
  }

  public function refreshArena(String $arena){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $cfg->set($arena . "PlayTime", 780);
    $cfg->set($arena . "StartTime", 30);
    $cfg->save();
  }

  public function getRank(Player $p){
    return;
    }

  public function getDefaultLevel(){
    $cfg = new Config($this->getDataFolder() . "/arenas.json", Config::JSON);
    $lev = $cfg->get("DefaultWorld");
    if($this->getServer()->getLevelByName($lev) instanceof Level){
      return $this->getServer()->getLevelByName($lev);
    }
    return $this->getServer()->getDefaultLevel();
  }

  public function worldChat(){
    $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    return $cfg->get("WorldChat");
  }

  public function refillChests(Level $level){ 
    $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    $tiles = $level->getTiles();
    foreach($tiles as $t) {
      if($t instanceof Chest) 
      {
        $chest = $t;
        $chest->getInventory()->clearAll();
        if($chest->getInventory() instanceof ChestInventory)
        {
          for($i=0;$i<=26;$i++)
          {
            $rand = rand(1,3);
            if($rand==1)
            {
              $k = array_rand($cfg->get("Items"));
              $v = $cfg->get("Items")[$k];
              $chest->getInventory()->setItem($i, Item::get($v[0],$v[1],$v[2]));
            }
          }
        }
      }
    }
  }

  public function getVersion(){
    $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    return (int) $cfg->get("Version");
  }

  public function hasUpdate(){
    return;
  }

  public function getPlayerStats(String $player){
    $player = strtolower($player);
    $stats = new Config($this->getDataFolder() . "/stats.json", Config::JSON);
    $stats->reload();
    if($stats->exists($player)){
      return "You have " . $stats->get($player) . " SurvivalGames wins!";
    }
    return "You dont have any stats!";
  }

  public function addPlayerWin(String $player){
    $player = strtolower($player);
    $stats = new Config($this->getDataFolder() . "/stats.json", Config::JSON);
    $stats->reload();
    $current = $stats->get($player);
    $stats->set($player, $current + 1);
    $stats->save();
    $stats->reload();
  }

  public function getAllStats(){
    $stats = new Config($this->getDataFolder() . "/stats.json", Config::JSON);
    $stats->reload();
    return $stats->getAll();
  }
}
