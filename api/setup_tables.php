<?php
/**
 * ============================================================================
 * УТИЛИТА ПЕРВОНАЧАЛЬНОЙ НАСТРОЙКИ ТАБЛИЦ НА ЯНДЕКС ДИСКЕ — api/setup_tables.php
 * ============================================================================
 * Создает правильные файлы .xlsx с форматированными заголовками и загружает
 * их на ваш Яндекс Диск. Запустите один раз в браузере или через CLI.
 */

define('APP_INIT', true);

header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/YandexDisk.php';
require_once __DIR__ . '/SpreadsheetHandler.php';

$yandex = new YandexDisk($config);
$tempDir = $config['security']['temp_dir'] ?? (__DIR__ . '/temp');
if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

echo "<h2>⚙️ Настройка таблиц на Яндекс Диске</h2>";

if (!$yandex->isConfigured()) {
    echo "<p style='color:red;'>❌ Ошибка: В файле <code>api/config.php</code> не указан <code>oauth_token</code> для Яндекс Диска.</p>";
    exit;
}

// 1. Таблица брифов
$briefsRemote = $config['yandex']['briefs_file'] ?? 'app:/Брифы_Клиентов.xlsx';
$briefsLocal  = $tempDir . '/setup_briefs.xlsx';

SpreadsheetHandler::createDefaultBriefsFile($briefsLocal);
$upload1 = $yandex->uploadFile($briefsLocal, $briefsRemote);
@unlink($briefsLocal);

if ($upload1['success']) {
    echo "<p style='color:green;'>✅ Таблица <b>«Брифы Клиентов»</b> успешно создана и загружена на Яндекс Диск ({$briefsRemote})!</p>";
} else {
    echo "<p style='color:red;'>❌ Ошибка загрузки таблицы брифов: " . htmlspecialchars($upload1['error'] ?? '') . "</p>";
}

// 2. Таблица расписания
$schedRemote = $config['yandex']['schedule_file'] ?? 'app:/Расписание_и_Записи.xlsx';
$schedLocal  = $tempDir . '/setup_schedule.xlsx';

SpreadsheetHandler::createDefaultScheduleFile($schedLocal);
$upload2 = $yandex->uploadFile($schedLocal, $schedRemote);
@unlink($schedLocal);

if ($upload2['success']) {
    echo "<p style='color:green;'>✅ Таблица <b>«Расписание и Записи»</b> успешно создана и загружена на Яндекс Диск ({$schedRemote})!</p>";
} else {
    echo "<p style='color:red;'>❌ Ошибка загрузки таблицы расписания: " . htmlspecialchars($upload2['error'] ?? '') . "</p>";
}

echo "<hr><p>Теперь вы можете открыть эти таблицы прямо в веб-интерфейсе <b>Яндекс Документов</b> на вашем Яндекс Диске.</p>";
