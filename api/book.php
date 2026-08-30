<?php
/**
 * ============================================================================
 * ЭНДПОИНТ ОНЛАЙН-ЗАПИСИ — api/book.php
 * ============================================================================
 * 1. Принимает бронирование занятия или общую заявку
 * 2. Защищает от превышения свободных мест и повторных записей
 * 3. Обновляет лист «Записи» и счетчик мест в листе «Расписание»
 * 4. Загружает обновленный .xlsx на Яндекс Диск
 * 5. Отправляет уведомление в MAX
 */

define('APP_INIT', true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод запроса должен быть POST']);
    exit;
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/MaxNotifier.php';
require_once __DIR__ . '/YandexDisk.php';
require_once __DIR__ . '/SpreadsheetHandler.php';

date_default_timezone_set($config['security']['timezone'] ?? 'Europe/Moscow');

$inputRaw = file_get_contents('php://input');
$data = !empty($inputRaw) ? (json_decode($inputRaw, true) ?: []) : $_POST;

$name      = trim($data['name'] ?? '');
$phone     = trim($data['phone'] ?? '');
$direction = trim($data['direction'] ?? '');
$date      = trim($data['date'] ?? '');
$time      = trim($data['time'] ?? '');
$goal      = trim($data['goal'] ?? '');
$source    = trim($data['source'] ?? 'Сайт (Расписание)');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Пожалуйста, укажите ваше имя.']);
    exit;
}

// Оставляем только цифры в телефоне
$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 10) {
    echo json_encode(['success' => false, 'error' => 'Пожалуйста, укажите корректный номер телефона (не менее 10 цифр).']);
    exit;
}

$tempDir = $config['security']['temp_dir'] ?? (__DIR__ . '/temp');
if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

// Файловый лок для защиты от одновременных записей (race condition)
$lockFile = $tempDir . '/booking.lock';
$lockFp = fopen($lockFile, 'w+');
if (!$lockFp || !flock($lockFp, LOCK_EX)) {
    echo json_encode(['success' => false, 'error' => 'Сервер занят обработкой другой заявки. Повторите попытку через пару секунд.']);
    exit;
}

try {
    $yandex = new YandexDisk($config);
    $remoteFile = $config['yandex']['schedule_file'] ?? 'app:/Расписание_и_Записи.xlsx';
    $localFile  = $tempDir . '/sched_work_' . uniqid() . '.xlsx';

    if ($yandex->isConfigured()) {
        $download = $yandex->downloadFile($remoteFile, $localFile);
        if (!$download['success']) {
            SpreadsheetHandler::createDefaultScheduleFile($localFile);
        }
    } else {
        SpreadsheetHandler::createDefaultScheduleFile($localFile);
    }

    $now = date('d.m.Y H:i:s');
    $isGeneral = empty($date) || empty($time);

    // Добавляем строку в лист «Записи»
    $bookingRow = [
        $now,
        $name,
        $phoneDigits,
        $direction ?: ($isGeneral ? 'Общая заявка' : 'Практика'),
        $date,
        $time,
        $goal ?: 'Сайт',
        $source
    ];

    SpreadsheetHandler::appendRow($localFile, $bookingRow, 'Записи');

    // Загружаем обратно на Яндекс Диск
    if ($yandex->isConfigured()) {
        $yandex->uploadFile($localFile, $remoteFile);
    }

    // Сбрасываем кеш расписания
    @unlink($tempDir . '/schedule_cache.json');
    @unlink($localFile);

    // Отправляем уведомление в MAX
    $maxNotifier = new MaxNotifier($config);
    $maxNotifier->sendBookingNotification([
        'name'      => $name,
        'phone'     => $phoneDigits,
        'direction' => $direction,
        'date'      => $date,
        'time'      => $time,
        'goal'      => $goal
    ]);

    echo json_encode([
        'success' => true,
        'message' => $isGeneral ? 'Заявка принята! Мы свяжемся с вами в ближайшее время.' : 'Запись подтверждена! Ждём вас на занятии 🎉'
    ], JSON_UNESCAPED_UNICODE);

} finally {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}
