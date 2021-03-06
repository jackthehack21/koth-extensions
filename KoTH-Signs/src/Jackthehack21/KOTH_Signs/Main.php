<?php
/*
 *   KOTH-Signs
 *   Copyright (C) 2019 Jackthehack21 (Jack Honour/Jackthehaxk21/JaxkDev)
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *   Twitter :: @JaxkDev
 *   Discord :: Jackthehaxk21#8860
 *   Email   :: gangnam253@gmail.com
 */

declare(strict_types=1);
namespace Jackthehack21\KOTH_Signs;

use Jackthehack21\KOTH\Arena;
use Jackthehack21\KOTH\Main as KOTH;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

    public const SIGN_TYPE_STATS = 0;
    public const SIGN_TYPE_JOIN = 1;
    public const SIGN_TYPE_LEAVE = 2;

    /** @var Config */
    private $configC;
    /** @var Config */
    private $dataC; //todo remove and change to sqlite3.

    private $config;
    private $data;

    /** @var KOTH */
    public $koth;

    /** @var EventHandler */
    private $EventHandler;

    /** @var TaskHandler */
    private $updateTask;

    public function onEnable()
    {
        $this->koth = $this->getServer()->getPluginManager()->getPlugin("KOTH");
        $this->EventHandler = new EventHandler($this);
        // VERIFY koth is a specific version so no API conflicts and api errors.
        if($this->koth->getDescription()->getVersion() !== "1.0.0-Beta3"){
            $this->getLogger()->error("KoTH v".$this->koth->getDescription()->getVersion()." is not supported, plugin is now disabled.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->getServer()->getPluginManager()->registerEvents($this->EventHandler, $this);
        $this->init();
    }

    public function onDisable()
    {
        $this->save();
        $this->updateTask->cancel();
    }

    private function init(): void{
        $this->saveResource("config.yml");
        $this->configC = new Config($this->getDataFolder() . "config.yml");
        $this->config = $this->configC->getAll();

        $this->dataC = new Config($this->getDataFolder() . "data.yml", CONFIG::YAML, ["version"=>0, "signs"=>[]]);
        $this->data = $this->dataC->getAll();

        $this->updateTask = $this->getScheduler()->scheduleRepeatingTask(new UpdateTask($this),$this->config["update_interval"]*20);
    }

    public function save(): void{
        $this->dataC->setAll($this->data);
        $this->dataC->save();
    }


    /**
     * @param string $world
     * @param int|Vector3 $x
     * @param int $y
     * @param int $z
     *
     * @return null|object
     */
    public function getSign($world, $x, $y = 0, $z = 0){
        if($x instanceof Vector3){
            $z = $x->getZ();
            $y = $x->getY();
            $x = $x->getX();
        }
        $pos = $x.".".$y.".".$z;
        foreach($this->data["signs"] as $sign){
            if($sign["world"] === $world && $sign["position"] === $pos){
                return $sign;
            }
        }
        return null;
    }

    /**
     * @param object $sign
     *
     * @return bool
     */
    public function remSign($sign): bool{
        for($i = 0; $i < count($this->data["signs"]); $i++){
            if($this->data["signs"][$i] === $sign){
                unset($this->data["signs"][$i]);
                $this->data["signs"] = array_values($this->data["signs"]); //reset index's
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * @param object $sign
     */
    public function addSign($sign): void{
        $this->data["signs"][] = $sign;
        $this->save();
    }

    /**
     * @return mixed
     */
    public function getSigns(){
        return $this->data["signs"];
    }

    /**
     * @param object $sign
     *
     * @return null|Arena
     */
    public function getArena($sign){
        return $this->koth->getArenaByName($sign["arena"]);
    }

    /**
     * @param int $type
     *
     * @return mixed|null
     */
    public function getFormat(int $type){
        switch ($type) {
            case $this::SIGN_TYPE_STATS:
                return $this->config["sign_stats_format"];
            case $this::SIGN_TYPE_JOIN:
                return $this->config["sign_join_format"];
            case $this::SIGN_TYPE_LEAVE:
                return $this->config["sign_leave_format"];
            default:
                return null;
        }
    }

    /**
     * @param string $text
     * @param Arena $arena
     *
     * @return string
     */
    public function format(string $text, Arena $arena): string{
        return $this->koth->utils->colourise(str_replace(
            ["{ARENA}", "{PLAYERS}", "{PLAYERS_LIMIT}", "{STATUS}", "{WORLD}"],
            [$arena->getName(), count($arena->getPlayers()), $arena->maxPlayers, $arena->getFriendlyStatus(), $arena->world],
            $text
        ));
    }
}