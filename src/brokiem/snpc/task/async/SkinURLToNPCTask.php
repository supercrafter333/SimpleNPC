<?php

declare(strict_types=1);

namespace brokiem\snpc\task\async;

use brokiem\snpc\manager\NPCManager;
use brokiem\snpc\SimpleNPC;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class SkinURLToNPCTask extends AsyncTask {
    private ?string $skinUrl;
    private ?string $nametag;
    private bool $canWalk;
    private string $username;
    private string $dataPath;

    public function __construct(?string $nametag, string $username, string $dataPath, bool $canWalk = false, ?string $skinUrl = null, CompoundTag $command = null, Skin $skin = null, Location $customPos = null) {
        $this->username = $username;
        $this->nametag = $nametag;
        $this->canWalk = $canWalk;
        $this->skinUrl = $skinUrl;
        $this->dataPath = $dataPath;

        $this->storeLocal("snpc_skinurltonpc", [$command, $skin, $customPos]);
    }

    public function onRun(): void {
        if ($this->skinUrl !== null) {
            $uniqId = uniqid($this->nametag ?? "", true);
            $parse = parse_url($this->skinUrl, PHP_URL_PATH);

            if ($parse === null || $parse === false) {
                return;
            }

            $extension = pathinfo($parse, PATHINFO_EXTENSION);
            $data = Internet::getURL($this->skinUrl);

            if ($data === null || $extension !== "png") {
                return;
            }

            file_put_contents($this->dataPath . $uniqId . ".$extension", $data->getBody());
            $file = $this->dataPath . $uniqId . ".$extension";

            $img = @imagecreatefrompng($file);

            if (!$img) {
                if (is_file($file)) {
                    unlink($file);
                }
                return;
            }

            $bytes = '';
            for ($y = 0; $y < imagesy($img); $y++) {
                for ($x = 0; $x < imagesx($img); $x++) {
                    $rgba = @imagecolorat($img, $x, $y);
                    $a = ((~($rgba >> 24)) << 1) & 0xff;
                    $r = ($rgba >> 16) & 0xff;
                    $g = ($rgba >> 8) & 0xff;
                    $b = $rgba & 0xff;
                    $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
                }
            }

            imagedestroy($img);
            $this->setResult($bytes);

            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function onCompletion(): void {
        $player = Server::getInstance()->getPlayerExact($this->username);
        [$commands, $skin, $customPos] = $this->fetchLocal("snpc_skinurltonpc");

        if ($player === null) {
            return;
        }

        $player->saveNBT();

        $position = $customPos instanceof Location ? $customPos : null;
        $skinData = $skin instanceof Skin ? $skin->getSkinData() : $this->getResult();

        NPCManager::getInstance()->spawnNPC(SimpleNPC::ENTITY_HUMAN, $player, $this->nametag, $commands, $position, $skinData, $this->canWalk);
    }
}