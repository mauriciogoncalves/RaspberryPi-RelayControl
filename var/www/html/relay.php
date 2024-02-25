<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
error_reporting(0);
session_start();

/**
 * This script can becalled as domain.com/relay.php?r1=2&r2=3&r3=3&r4=3&r5=3&r6=3&r7=3&r8=3
 * And r1=2 means
 * r1 ... means relay 1
 * param value 0 means turn relay off
 * param value 1 means turn relay on
 * param value 2 means switch, if is on become of and if is off become
 * param value 3 means keep current state
 * Class RelayCommand
 */
class RelayCommand
{
    /** @var bool Enable echo messages */
    private bool $debug = false;

    /**
     * In this file will be a json saved from Python script with the last state of the relays
     * File content will be like
     * {"1":false,"2":false,"3":false,"4":false,"5":false,"6":false,"7":false,"8":false}
     *
     * @var  string file absolute path example: /var/www/html/firstRun
     */
    private string $lastStatesCacheFile = '/Users/mauricio/Mau/Platin/workspacePhp/wmau/firstRun';

    /** @var string command line to execute python script that controls the relay */
    private string $defaultCmd = '["/home/pi/lightControl.py", "-1", "-2", "-3", "-4", "-5", "-6", "-7", "-8"]';

    /** @var array */
    private array $lastStates = [];

    /** @var array */
    private array $logRows = [];

    /**
     * RelayCommand constructor.
     */
    public function __construct()
    {
        if (!empty($_REQUEST)) {
            $this->debug = isset($_REQUEST['showDebug']) && in_array($_REQUEST['showDebug'], [1, '1', true, 'true', 'in']);
        } elseif (isset($_SESSION['relay_debug_available'])) {
            $this->debug = $_SESSION['relay_debug_available'] === true;
        }
        $this->lastStates = $this->getLastStates();
    }

    /**
     * Show message in php error log file and do echo on browser html
     * @param $msg
     */
    private function log($msg): void
    {
        if ($this->debug) {
            error_log($msg);
            $this->logRows[] = $msg . PHP_EOL;
        }
    }

    /**
     * @return bool
     */
    public function isDebugVisible()
    {
        return $this->debug;
    }

    /**
     * get the log rows to echo in html
     * @return array
     */
    public function getLogRows(): array
    {
        return $this->logRows;
    }

    /**
     * @return array
     */
    private function getLastStates(): array
    {
        if (!is_file($this->lastStatesCacheFile)) {
            $this->log('Save State file not exists! Creating at: ' . $this->lastStatesCacheFile);
            try {
                $created = file_put_contents($this->lastStatesCacheFile, $this->defaultCmd);
            } catch (Throwable $e) {
                $created = $e->getMessage();
            }
            if ($created !== true) {
                shell_exec("touch " . $this->lastStatesCacheFile);
            }
            if (is_file($this->lastStatesCacheFile)) {
                $this->debug = true;
                $this->log('Could not crate File as:' . $this->lastStatesCacheFile . '. Error! ' . $created);
                $this->log('Consider change file folder to: ' . shell_exec("pwd "));
                $this->log('Consider change file/folder permission');
                return [];
            }
        }
        $result = json_decode(file_get_contents($this->lastStatesCacheFile));
        $result = empty($result) || !is_array($result) ? $this->defaultCmd : $result;
        $this->log('Last State file content is now:' . PHP_EOL . json_encode($result, JSON_UNESCAPED_SLASHES));
        $states = [];
        for ($i = 1; $i < 9; $i++) {
            $states[$i] = $this->parseParam($i, intval($result[$i]) > 0 ? 1 : 0);
        }

        return $this->lastStates = $states;
    }

    public function getNextStates(?array $params = null): array
    {
        $params = $this->normalizeParams($params);
        $states = [];
        for ($i = 1; $i < 9; $i++) {
            $states[$i] = $this->parseParam($i, $params[$i]);
        }

        return $states;
    }

    /**
     * Parse saved value or posted value to get an array showing each realy is turned on or off
     * @param $relayNum 1 to 8 as the relays in eletric board
     * @param mixed $param value might be 1 or 0 , true or false or string on off change keep
     * @return bool
     */
    function parseParam($relayNum, $param = null): bool
    {
        $param = empty($param) ? 'false' : $param;
        $param = $param === true ? 'true' : $param;
        $param = '' . $param;
        if (in_array($param, ['0', '-1', 'false', 'off'])) {
            $result = false;
        } elseif (in_array($param, ['1', 'true', 'on'])) {
            $result = true;
        } elseif (in_array($param, ['2', 'change'])) {
            $result = intval($this->getRelayState($relayNum)) > 0;
        } elseif (in_array($param, ['3', 'keep'])) {
            $result = intval($this->getRelayState($relayNum)) * -1 > 0;
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * @param $i
     * @return int -1 means Off , 1 means On
     */
    private function getRelayState(int $i): int
    {
        $this->lastStates = empty($this->lastStates) || !is_array($this->lastStates) ? [0 => ''] : $this->lastStates;

        if (intval($this->lastStates[$i]) > 0) {
            return intval($i) * -1;
        } else {
            return intval($i);
        }
    }

    /**
     * @param array|null $params
     * @return array
     */
    private function normalizeParams(?array $params): array
    {
        $params = empty($params) ? ($_REQUEST ?? []) : $params;
        $params = empty($params) ? $this->lastStates : $params;
        $result = $this->lastStates;
        foreach ($this->lastStates as $key => $value) {
            $i = intval(str_ireplace('r', '', $key));
            $param = isset($params['r' . $key]) ? $params['r' . $key] : false;
            $result[$key] = $key !== 0 ? $this->parseParam($i, $param) : $value;
        }
        return $result;
    }

    /**
     * @param null|array $params
     * @return array
     */
    public function work(?array $params = null): array
    {
        $items = empty($params) ? $this->lastStates : $this->getNextStates($params);
        $bashCmd = "sudo /bin/relay_python ";
        for ($i = 1; $i < 9; $i++) {
            $bashCmd .= "  " . ($items[$i] ? $i : -$i) . " ";
        }
        if (count($params) > 0) {
            $this->log('Command to run on bash: ' . PHP_EOL . $bashCmd);

            @exec($bashCmd);
            $previousState = json_encode($this->lastStates, JSON_UNESCAPED_SLASHES);
            $currentState = json_encode($this->getLastStates(), JSON_UNESCAPED_SLASHES);
            if ($previousState == $currentState) {
                $this->log("Looks like Python script is not running properly 😢");
            } else {
                $this->log("It works! 😎");
            }

        }
        return $this->lastStates;
    }


}

$relayCmd = new RelayCommand();
$relayItems = $relayCmd->work($_REQUEST);
?>
<html lang="en">
<head>
    <title>Relay Control</title>
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23000000' class='icon' viewBox='0 0 32 32' version='1.1'%3E%3Cpath d='M16.13 1.781c-5.371 0-8.19 4.236-8.19 8.090 0 4.324 4.024 7.043 4.024 14.114h4.075c0 0 3.827 0.018 3.943 0.018 0.116-7.123 4.078-9.925 4.078-14.166 0.001-3.619-2.559-8.056-7.93-8.056zM19.474 25.031h-7c-0.276 0-0.5 0.224-0.5 0.5s0.224 0.5 0.5 0.5h7c0.275 0 0.5-0.224 0.5-0.5s-0.225-0.5-0.5-0.5zM19.474 27.031h-7c-0.276 0-0.5 0.224-0.5 0.5s0.224 0.5 0.5 0.5h7c0.275 0 0.5-0.224 0.5-0.5s-0.225-0.5-0.5-0.5zM12.974 29.031c0 0 0 0.447 0 1 0 0.552 0.447 1 1 1h4.014c0.553 0 1-0.448 1-1 0-0.553 0-1 0-1h-6.014z'/%3E%3C/svg%3E" />
    <style type="text/css">
        body {
            background: #FFF;
            display: flex;
            font-family: "JetBrains Mono", monospace;
        }
        .noPointerEvents {
            pointer-events: none;
        }
        .itemRow {
            margin-bottom: 10px;
        }
        form {
            display: flex;
            flex-direction: row;
            margin: auto;
            justify-content: space-evenly;
            gap: 30px;
        }
        .button-30 {
            align-items: center;
            appearance: none;
            background-color: #FCFCFD;
            border-radius: 4px;
            border-width: 0;
            box-shadow: rgba(45, 35, 66, 0.4) 0 2px 4px, rgba(45, 35, 66, 0.3) 0 7px 13px -3px, #D6D6E7 0 -3px 0 inset;
            box-sizing: border-box;
            color: #36395A;
            cursor: pointer;
            display: inline-flex;
            height: 48px;
            justify-content: flex-start;
            line-height: 1;
            list-style: none;
            overflow: hidden;
            padding-left: 16px;
            padding-right: 16px;
            position: relative;
            text-align: left;
            text-decoration: none;
            transition: box-shadow .15s, transform .15s;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
            white-space: nowrap;
            will-change: box-shadow, transform;
            font-size: 18px;
        }
        .button-30:focus {
            box-shadow: #D6D6E7 0 0 0 1px inset, rgba(45, 35, 66, 0.4) 0 2px 4px, rgba(45, 35, 66, 0.3) 0 7px 13px -3px, #D6D6E7 0 -3px 0 inset;
        }
        .button-30:hover {
            box-shadow: rgba(45, 35, 66, 0.4) 0 4px 8px, rgba(45, 35, 66, 0.3) 0 7px 13px -3px, #D6D6E7 0 -3px 0 inset;
            transform: translateY(-2px);
        }
        .button-30:active {
            box-shadow: #D6D6E7 0 3px 7px inset;
            transform: translateY(2px);
        }
        textarea.button-30:hover,
        textarea.button-30:active {
            transform: none;
        }
        textarea.button-30 {
            white-space: nowrap;
            height: 100%;
            width: 100%;
            overflow: auto;
            padding-top: 14px;
            margin-bottom: 10px;
            min-width: 750px;
            font-size: 1em;
        }
        .flexCol {
            display: flex;
            flex-direction: column;
        }
        .icon {
            width: 14px;
            height: 14px;
            position: relative;
        }
    </style>
    <script type="application/javascript">
        function relayRowClick(i) {
            const item = document.getElementById('r' + i);
            item.checked = !item.checked;
            document.getElementById('form').submit();
        }

        function showDebug() {
            console.log(123);
            const item = document.getElementById('showDebug');
            item.checked = !item.checked;
            document.getElementById('debugBox').style.display = item.checked ? 'flex' : 'none';
        }

    </script>
</head>
<body>
<?php
$icons = [];

$icons[0] = <<<HTML
<svg xmlns="http://www.w3.org/2000/svg" fill="#000000" class="icon" viewBox="0 0 32 32" version="1.1">
    <path d="M16.13 1.781c-5.371 0-8.19 4.236-8.19 8.090 0 4.324 4.024 7.043 4.024 14.114h4.075c0 0 3.827 0.018 3.943 0.018 0.116-7.123 4.078-9.925 4.078-14.166 0.001-3.619-2.559-8.056-7.93-8.056zM19.474 25.031h-7c-0.276 0-0.5 0.224-0.5 0.5s0.224 0.5 0.5 0.5h7c0.275 0 0.5-0.224 0.5-0.5s-0.225-0.5-0.5-0.5zM19.474 27.031h-7c-0.276 0-0.5 0.224-0.5 0.5s0.224 0.5 0.5 0.5h7c0.275 0 0.5-0.224 0.5-0.5s-0.225-0.5-0.5-0.5zM12.974 29.031c0 0 0 0.447 0 1 0 0.552 0.447 1 1 1h4.014c0.553 0 1-0.448 1-1 0-0.553 0-1 0-1h-6.014z"/>
</svg>
HTML;

$icons[1] = <<<HTML
<svg xmlns="http://www.w3.org/2000/svg" fill="#000000" class="icon" viewBox="0 0 461.977 461.977" version="1.1">
<g>
	<path d="M398.47,248.268L346.376,18.543C344.136,8.665,333.287,0,323.158,0H138.821c-10.129,0-20.979,8.665-23.219,18.543   L63.507,248.268c-0.902,3.979-0.271,7.582,1.775,10.145c2.047,2.564,5.421,3.975,9.501,3.975h51.822v39.108   c-6.551,3.555-11,10.493-11,18.47c0,11.598,9.402,21,21,21c11.598,0,21-9.402,21-21c0-7.978-4.449-14.916-11-18.47v-39.108h240.587   c4.079,0,7.454-1.412,9.501-3.975C398.742,255.849,399.372,252.247,398.47,248.268z"/>
	<path d="M318.735,441.977h-77.747V282.388h-20v159.588h-77.747c-5.523,0-10,4.477-10,10c0,5.523,4.477,10,10,10h175.494   c5.522,0,10-4.477,10-10C328.735,446.454,324.257,441.977,318.735,441.977z"/>
</g>
</svg>
HTML;


?>
<form method="post" id="form">
    <div class="flexCol">
        <label for="r0">&nbsp;</label>
        <input type="hidden" name="r0"/>
        <?php for ($i = 1; $i < 9; $i++) : ?>
            <div class="itemRow button-30" onClick="relayRowClick(<?= '' . $i; ?>)">
                <div class="itemRelay noPointerEvents">
                    <input class="noPointerEvents"
                           name="r<?= '' . $i; ?>" id="r<?= '' . $i; ?>"
                           type="checkbox" <?= $relayItems[$i] ? 'checked="true"' : '' ?> />
                    <label for="r<?= '' . $i; ?>">Relay <?= '' . $i; ?>
                        <?= isset($icons[$i]) ? $icons[$i] : $icons[0]; ?>
                    </label>
                </div>
            </div>
        <?php endfor ?>
        <div class="itemRow button-30" onClick="showDebug()">
            <div class="itemRelay noPointerEvents">
                <input class="noPointerEvents"
                       id="showDebug" name="showDebug"
                       type="checkbox" <?= $relayCmd->isDebugVisible() ? 'checked="true"' : '' ?> />
                <label for="showDebug">Debug Box</label>
            </div>
        </div>
    </div>
    <div class="flexCol" id="debugBox" style="<?= $relayCmd->isDebugVisible() ? '' : 'display:none' ?>">
        <label for="showDebugText">Debug Messages</label>
        <textarea class="button-30" name="showDebugText"><?php
            foreach ($relayCmd->getLogRows() as $log) {
                echo $log . PHP_EOL;
            }
            ?></textarea>
    </div>
</form>
</body>
</html>
