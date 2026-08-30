<?php
/**
 * ============================================================================
 * ТЕСТ ОТПРАВКИ УВЕДОМЛЕНИЯ В МЕССЕНДЖЕР MAX — api/test_max.php
 * ============================================================================
 * Запустите этот скрипт в браузере (например, yourdomain.com/api/test_max.php)
 * для проверки работы токена и доставки тестового сообщения в MAX.
 */

define('APP_INIT', true);

header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/MaxNotifier.php';

echo "<h2>🧪 Тестирование отправки в мессенджер MAX</h2>";

$max = new MaxNotifier($config);

$testData = [
    'company_name'   => 'Тестовая Студия «Баланс»',
    'client_name'    => 'Алексей (Тест)',
    'contact'        => '+7 (999) 000-00-00 / @test_user',
    'city'           => 'Москва',
    'services'       => 'Персональные тренировки, йога, спа',
    'goals'          => ['Повысить доверие и статус', 'Упростить запись гостей'],
    'current_issues' => 'Тестовое сообщение для проверки интеграции бэкенда с мессенджером MAX.',
    'photo_ready'    => 'Да, есть качественные фото',
    'deadline'       => '2 недели',
    'maps_link'      => 'https://yandex.ru/maps',
    'social_link'    => 'https://max.ru',
    'comment'        => 'Проверка кнопок и форматирования Markdown.',
    'source'         => 'Тест бэкенда'
];

$result = $max->sendBriefNotification($testData);

if ($result['success']) {
    echo "<p style='color:green;'>✅ <b>Успех!</b> Тестовое уведомление успешно отправлено в MAX. Проверьте ваш чат в мессенджере.</p>";
    echo "<pre>" . htmlspecialchars(json_encode($result['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
} else {
    echo "<p style='color:red;'>❌ <b>Ошибка отправки:</b> " . htmlspecialchars($result['error'] ?? 'Неизвестная ошибка') . "</p>";
    if (!empty($result['raw'])) {
        echo "<pre>Ответ сервера: " . htmlspecialchars($result['raw']) . "</pre>";
    }
}
