<?php
# MADE BY:
#  __    __                                          __        __  __  __                     
# /  |  /  |                                        /  |      /  |/  |/  |                    
# $$ |  $$ |  ______   _______    ______    ______  $$ |____  $$/ $$ |$$/   _______  __    __ 
# $$  \/$$/  /      \ /       \  /      \  /      \ $$      \ /  |$$ |/  | /       |/  |  /  |
#  $$  $$<  /$$$$$$  |$$$$$$$  |/$$$$$$  |/$$$$$$  |$$$$$$$  |$$ |$$ |$$ |/$$$$$$$/ $$ |  $$ |
#   $$$$  \ $$    $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |$$ |$$ |      $$ |  $$ |
#  $$ /$$  |$$$$$$$$/ $$ |  $$ |$$ \__$$ |$$ |__$$ |$$ |  $$ |$$ |$$ |$$ |$$ \_____ $$ \__$$ |
# $$ |  $$ |$$       |$$ |  $$ |$$    $$/ $$    $$/ $$ |  $$ |$$ |$$ |$$ |$$       |$$    $$ |
# $$/   $$/  $$$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$/  $$/   $$/ $$/ $$/ $$/  $$$$$$$/  $$$$$$$ |
#                                         $$ |                                      /  \__$$ |
#                                         $$ |                                      $$    $$/ 
#                                         $$/                                        $$$$$$/

namespace Xenophilicy\NaviCompass;

use pocketmine\event\player\{PlayerJoinEvent,PlayerInteractEvent,PlayerQuitEvent,PlayerDropItemEvent};
use pocketmine\command\{Command,CommandSender,ConsoleCommandSender};
use pocketmine\item\enchantment\{Enchantment,EnchantmentInstance};
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\utils\{Config,TextFormat as TF};
use pocketmine\item\Item;

use Xenophilicy\NaviCompass\libs\jojoe77777\FormAPI\SimpleForm;

class NaviCompass extends PluginBase implements Listener {

    private $config;
    private $cmdMode;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->config->getAll();
        $this->getLogger()->info("NaviCompass has been enabled!");
        $version = $this->config->get("VERSION");
        if($version != "1.0.4"){
            $this->getLogger()->warning("You have updated NaviCompass but have an old config! Please delete your old config for new features to be enabled!");
        }
        $selectorEnable = $this->config->getNested("Selector.Enabled");
        if ($selectorEnable == true) {
            $this->selectorSupport = true;
        }
        else{
            $this->selectorSupport = false;
            $this->getLogger()->info("Selector item disabled in config...");
        }
        $transferType = $this->config->get("Transfer-Type");
        switch(strtolower($transferType)){
            case "external":
                $this->externalLimit = false;
                break;
            case "hybrid":
            case "internal":
                if($this->config->get("World-CMD") == null || $this->config->get("World-CMD") == ""){
                    $this->getLogger()->critical("Null world command string found, limiting to external use!");
                    $this->externalLimit = true;
                }
                else{
                    $mode = $this->config->get("World-CMD-Mode");
                    if(strtolower($mode) == "player"){
                        $this->externalLimit = false;
                        $this->cmdMode = 0;
                    }
                    elseif(strtolower($mode) == "console"){
                        $this->externalLimit = false;
                        $this->cmdMode = 1;
                    }
                    else{
                        $this->getLogger()->critical("Null world command mode found, limiting to external use!");
                        $this->externalLimit = true;
                    }
                }
                break;
            case false:
            case null:
                $this->getLogger()->critical("Null transfer type found, disabling plugin!");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            default:
                $this->getLogger()->critical("Invalid transfer type! Input type: ".$transferType." not supported, disabling plugin!");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
        }
        $this->list = $this->config->get("List");
        foreach ($this->list as $target) {
            unset($search);
            $value = explode(":", $target);
            if(strtolower($value[0]) === "ext"){
                if(isset($value[4])){
                    $search = $value[4];
                }
            }
            elseif(strtolower($value[0]) === "int"){
                if(isset($value[3])){
                    $search = $value[3];
                    if(!isset($value[4])){
                        $this->getLogger()->warning("Null path/URL! Input: ".$value[1]);
                    }
                }
            }
            if(isset($search)){
                switch(strtolower($search)){
                    case'url':
                        break;
                    case'path':
                        break;
                    default:
                        $this->getLogger()->warning("Invalid image type! Input: ".$value[1].TF::RESET.TF::YELLOW." Image type: ".$search.TF::RESET.TF::YELLOW." not supported. ");
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($command->getName() == "servers"){
            if ($sender instanceof Player) {
                $this->serverList($sender);
            }
            else{
                $sender->sendMessage(" ".$this->config->getNested("UI.Title"));
                foreach ($this->list as $target){
                    $value = explode(":", $target);
                    $value = str_replace("&", "§", $value);
                    if(strtolower($value[0]) == "ext"){
                        $sender->sendMessage(TF::YELLOW."Name: ".$value[1].TF::RESET.TF::YELLOW." | IP: ".$value[2]." | Port: ".$value[3]);
                    }
                    else{
                        $sender->sendMessage(TF::YELLOW."Name: ".$value[1].TF::RESET.TF::YELLOW." | Alias: ".$value[2]);
                    }
                }
            }
        }
        return true;
    }

    public function serverList($player){
        $form = new SimpleForm(function (Player $player, $data){
            if ($data === null){
                return;
            }
            else{
                $value = explode(":", $this->list[$data]);
                $value = str_replace("&", "§", $value);
                if(strtolower($value[0]) == "ext"){
                    $player->transfer($value[2],$value[3]);
                }
                else{
                    $cmdStr = $this->config->get("World-CMD");
                    $cmdStr = str_replace("{player}", $player, $cmdStr);
                    $cmdStr = str_replace("{world}", $value[2], $cmdStr);
                    if ($this->cmdMode == 0){
                        $this->getServer()->getCommandMap()->dispatch($player, $cmdStr);
                    }
                    else{
                        $this->getServer()->getCommandMap()->dispatch(new ConsoleCommandSender(), $cmdStr);
                    }
                }
            }
            return true;
        });
        $form->setTitle($this->config->get("UI.Title"));
        $form->setContent($this->config->get("UI.Message"));
        $subtext = $this->config->getNested("UI.Button-Subtext");
        foreach ($this->list as $target) {
            $value = explode(":", $target);
            $value = str_replace("&", "§", $value);
            unset($search);
            if(strtolower($value[0]) == "ext"){
                if(isset($value[4])){
                    $search = $value[4];
                    $file = $value[5];
                }
            }
            else{
                if(isset($value[3])){
                    $search = $value[3];
                    $file = $value[4];
                }
            }
            if(isset($search)){
                if($search == "url"){
                    $form->addButton($value[1]."\n".$subtext, 1, "http://".$file);
                }
                if($search == "path"){
                    $form->addButton($value[1]."\n".$subtext, 0, $file);
                }
            }
            else{
                $form->addButton($value[1]."\n".$subtext);
            }
        }
        $form->sendToPlayer($player);
    }

    public function onJoin(PlayerJoinEvent $event){
        if ($this->selectorSupport == true) {
            $player = $event->getPlayer();
            $selectorText = $this->config->getNested("Selector.Name");
            $selectorText = str_replace("&", "§", $selectorText);
            $enchantment = Enchantment::getEnchantment(0);
            $enchInstance = new EnchantmentInstance($enchantment, 1);
            $itemType = $this->config->getNested("Selector.Item");
            $item = Item::get($itemType);
            $item->setCustomName(TF::ITALIC."$selectorText");
            $item->addEnchantment($enchInstance);
            $slot = $itemType = $this->config->getNested("Selector.Slot");
            $player->getInventory()->setItem($slot,$item,true);
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $items = $player->getInventory()->getContents();
        $selectorText = $this->config->getNested("Selector.Name");
        $selectorText = str_replace("&", "§", $selectorText);
        foreach ($items as $target) {
            if ($target->getCustomName() == TF::ITALIC."$selectorText") {
                $player->getInventory()->remove($target);
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event){
        if ($this->selectorSupport == true) {
            $player = $event->getPlayer();
            $selectorText = $this->config->getNested("Selector.Name");
            $selectorText = str_replace("&", "§", $selectorText);
            $itemType = $this->config->getNested("Selector.Item");
            $item = $player->getInventory()->getItemInHand();
            if ($item->getCustomName() == TF::ITALIC."$selectorText" && $item->getId() == $itemType){
                $this->serverList($player);
            }
        }
    }

    public function onDrop(PlayerDropItemEvent $event){
        if ($this->selectorSupport == true) {
            $player = $event->getPlayer();
            $selectorText = $this->config->getNested("Selector.Name");
            $selectorText = str_replace("&", "§", $selectorText);
            $itemType = $this->config->getNested("Selector.Item");
            $item = $player->getInventory()->getItemInHand();
            if ($item->getCustomName() == TF::ITALIC."$selectorText" && $item->getId() == $itemType){
                $event->setCancelled();
            }
        }
    }
}
