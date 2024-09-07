<?php

namespace AntiAFKMine;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class Main extends PluginBase implements Listener {

    private array $afkPlayers = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private $plugin;
            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }
            public function onRun(): void {
                $this->plugin->checkAFKPlayers();
            }
        }, 20); // Vérifie toutes les secondes
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Si le joueur bouge, on le retire de la liste des AFK
        if (isset($this->afkPlayers[$name])) {
            unset($this->afkPlayers[$name]);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Si le joueur casse un bloc, il est potentiellement AFK Mine
        if (!isset($this->afkPlayers[$name])) {
            $this->afkPlayers[$name] = ['startTime' => time(), 'warned' => false];
        }
    }

    public function checkAFKPlayers(): void {
        foreach ($this->afkPlayers as $name => $data) {
            $player = $this->getServer()->getPlayerByPrefix($name);
            if ($player instanceof Player) {
                $afkTime = time() - $data['startTime'];

                // Avertir après 5 minutes (300 secondes)
                if ($afkTime >= 300 && !$data['warned']) {
                    $player->sendMessage("§cVous êtes AFK mine. Bougez dans les 30 secondes ou vous serez kické !");
                    $this->afkPlayers[$name]['warned'] = true;
                    $this->afkPlayers[$name]['warnTime'] = time(); // Enregistre le temps d'avertissement
                }

                // Vérifier si le joueur doit être kické après l'avertissement
                if ($data['warned'] && isset($data['warnTime'])) {
                    $warnElapsed = time() - $data['warnTime'];
                    if ($warnElapsed >= 30) { // Kick après 30 secondes d'avertissement
                        $player->kick("AFK Mine détecté. Vous avez été kické du serveur.", false);
                        unset($this->afkPlayers[$name]);
                    }
                }
            } else {
                unset($this->afkPlayers[$name]); // Si le joueur n'est plus en ligne
            }
        }
    }
}
