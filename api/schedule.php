<?php
/**
 * ============================================================================
 * ЭНДПОИНТ РАСПИСАНИЯ — api/schedule.php
 * ============================================================================
 * Читает лист «Расписание» из файла .xlsx на Яндекс Диске,
 * группирует по дням («Сегодня», «Завтра», дни недели) и отдает JSON.
 */

define('APP_INIT', true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/YandexDisk.php';
require_once __DIR__ . '/SpreadsheetHandler.php';

date_default_timezone_set($config['security']['timezone'] ?? 'Europe/Moscow');

$cacheTtl = $config['yandex']['cache_ttl'] ?? 120;
$tempDir  = $config['security']['temp_dir'] ?? (__DIR__ . '/temp');
if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

$cacheFile = $tempDir . '/schedule_cache.json';
$cachedXlsx = $tempDir . '/schedule_latest.xlsx';

// 1. Проверяем кеш JSON
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cachedData = file_get_contents($cacheFile);
    if (!empty($cachedData)) {
        echo $cachedData;
        exit;
    }
}

// 2. Скачиваем свежий файл с Яндекс Диска
$yandex = new YandexDisk($config);
$remoteFile = $config['yandex']['schedule_file'] ?? 'app:/Расписание_и_Записи.xlsx';

$download = $yandex->downloadFile($remoteFile, $cachedXlsx);
if (!$download['success'] && !file_exists($cachedXlsx)) {
    // Если файла еще нет на Диске, генерируем базовый
    SpreadsheetHandler::createDefaultScheduleFile($cachedXlsx);
}

// 3. Парсим строки расписания
$rows = SpreadsheetHandler::readSheetRows($cachedXlsx, 'xl/worksheets/sheet1.xml');
$schedule = [];
$today = strtotime('today');
$daysOfWeek = [
    0 => 'Вс', 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'
];

foreach ($rows as $rNum => $row) {
    if ($rNum === 1) continue; // Заголовки

    $direction = trim($row['A'] ?? '');
    $dateStr   = trim($row['B'] ?? '');
    $timeStr   = trim($row['C'] ?? '');
    $trainer   = trim($row['D'] ?? '');
    $total     = (int)($row['E'] ?? 0);
    $booked    = (int)($row['F'] ?? 0);
    $type      = trim($row['H'] ?? '');

    if (empty($direction) || empty($dateStr) || empty($timeStr)) continue;

    $classTimestamp = strtotime($dateStr);
    if (!$classTimestamp || $classTimestamp < $today) continue;

    $diffDays = (int)round(($classTimestamp - $today) / 86400);
    if ($diffDays > 14) continue; // На 14 дней вперед

    $dayKey = $diffDays === 0 ? 'Сегодня'
            : ($diffDays === 1 ? 'Завтра'
            : ($daysOfWeek[(int)date('w', $classTimestamp)] ?? date('d.m', $classTimestamp)));

    $avail = max(0, $total - $booked);

    if (!isset($schedule[$dayKey])) {
        $schedule[$dayKey] = [];
    }

    $schedule[$dayKey][] = [
        'title'     => $direction,
        'time'      => $timeStr,
        'trainer'   => $trainer,
        'total'     => $total,
        'booked'    => $booked,
        'available' => $avail,
        'date'      => date('d.m.Y', $classTimestamp),
        'type'      => $type
    ];
}

// Сортировка занятий по времени внутри дня
foreach ($schedule as $day => &$items) {
    usort($items, function ($a, $b) {
        return strcmp($a['time'], $b['time']);
    });
}

// Генерируем токен безопасности для формы
$token = bin2hex(random_bytes(16));
$response = [
    'success'  => true,
    'schedule' => $schedule,
    '_token'   => $token
];

$jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $jsonOutput, LOCK_EX);

echo $jsonOutput;
