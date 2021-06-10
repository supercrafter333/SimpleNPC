<?php

declare(strict_types=1);

namespace brokiem\snpc\task\async;

use brokiem\snpc\SimpleNPC;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Internet;

class CheckUpdateTask extends AsyncTask {

    private const POGGIT_URL = "https://poggit.pmmp.io/releases.json?name=";
    private string $version;
    private string $name;
    private bool $retry;

    public function __construct(SimpleNPC $plugin, bool $retry) {
        $this->retry = $retry;
        $this->name = $plugin->getDescription()->getName();
        $this->version = $plugin->getDescription()->getVersion();
        $this->storeLocal("snpc_checkupdate", [$plugin]);
    }

    public function onRun(): void {
        $poggitData = Internet::getURL(self::POGGIT_URL . $this->name);

        var_dump($poggitData);
        if ($poggitData === null) {
            return;
        }

        $poggit = json_decode($poggitData->getBody(), true);

        if (!is_array($poggit)) {
            return;
        }

        $version = "";
        $date = "";
        $updateUrl = "";

        foreach ($poggit as $pog) {
            if (version_compare($this->version, str_replace("-beta", "", $pog["version"]), ">=")) {
                continue;
            }

            $version = $pog["version"]; $date = $pog["last_state_change_date"]; $updateUrl = $pog["html_url"];
        }

        $this->setResult([$version, $date, $updateUrl]);
    }

    public function onCompletion(): void {
        /** @var SimpleNPC $plugin */
        [$plugin] = $this->fetchLocal("snpc_checkupdate");

        if ($this->getResult() === null) {
            $plugin->getLogger()->debug("Async update check failed!");

            if (!$this->retry) {
                $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($plugin): void {
                    $plugin->checkUpdate(true);
                }), 30);
            }

            return;
        }

        [$latestVersion, $updateDateUnix, $updateUrl] = $this->getResult();

        if ($latestVersion != "" || $updateDateUnix != null || $updateUrl !== "") {
            $updateDate = date("j F Y", (int)$updateDateUnix);

            if ($this->version !== $latestVersion) {
                $plugin->getLogger()->notice("SimpleNPC v$latestVersion has been released on $updateDate. Download the new update at $updateUrl");
                $plugin->cachedUpdate = [$latestVersion, $updateDate, $updateUrl];
            }
        }
    }
}