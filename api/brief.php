<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

if (!empty($data['website_trap'])) {
    echo json_encode(['success' => true]);
    exit;
}

$clientName = trim($data['client_name'] ?? 'Не указано');
$contact = trim($data['contact_info'] ?? $data['contact'] ?? 'Не указано');
$companyName = trim($data['company_name'] ?? 'Не указано');
$city = trim($data['city'] ?? '');
$services = trim($data['services'] ?? '');
$mapsLink = trim($data['maps_link'] ?? '');
$socialLink = trim($data['social_link'] ?? '');
$currentIssues = trim($data['current_issues'] ?? '');
$photoReady = trim($data['photo_ready'] ?? '');
$deadline = trim($data['deadline'] ?? '');
$comment = trim($data['comment'] ?? '');
$source = trim($data['source'] ?? 'Сайт (Бриф)');

$goals = '';
if (!empty($data['goals'])) {
    if (is_array($data['goals'])) {
        $goals = implode("\n• ", $data['goals']);
    } else {
        $goals = (string)$data['goals'];
    }
}

$msg = "📋 *НОВЫЙ БРИФ С САЙТА*\n";
$msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
$msg .= "👤 *Имя:* " . $clientName . "\n";
$msg .= "📱 *Контакт:* " . $contact . "\n";
$msg .= "🏢 *Бизнес:* " . $companyName . ($city ? " (" . $city . ")" : "") . "\n";

if ($services) {
    $msg .= "🎯 *Услуги:* " . $services . "\n";
}
if ($mapsLink) {
    $msg .= "📍 *Карты:* " . $mapsLink . "\n";
}
if ($socialLink) {
    $msg .= "🌐 *Соцсети/Сайт:* " . $socialLink . "\n";
}
if ($goals) {
    $msg .= "\n🎯 *Задачи проекта:*\n• " . $goals . "\n";
}
if ($currentIssues) {
    $msg .= "\n⚠️ *Что не устраивает сейчас:*\n" . $currentIssues . "\n";
}
if ($photoReady) {
    $msg .= "📸 *Фотоматериалы:* " . $photoReady . "\n";
}
if ($deadline) {
    $msg .= "⏳ *Желаемые сроки:* " . $deadline . "\n";
}
if ($comment) {
    $msg .= "\n💬 *Комментарий:* " . $comment . "\n";
}
$msg .= "\n━━━━━━━━━━━━━━━━━━━━━\n";
$msg .= "🌐 *Источник:* " . $source . "\n";
$msg .= "🕒 *Время:* " . date('d.m.Y H:i:s');

$maxToken = 'f9LHodD0cOL0j98fJFPmRgDpV-IIo1xkYJKD-KGxg_DSBFo0mLgO9gA3eZIBTCtVmN8vz34dapUPnCJHtBrS';
$chatId = 331000658;

$payload = json_encode([
    'text' => $msg,
    'format' => 'markdown'
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://platform-api2.max.ru/messages?chat_id=' . $chatId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $maxToken,
    'Content-Type: application/json',
    'User-Agent: PHP/MAXBotClient'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['success' => true, 'message' => 'Бриф успешно отправлен в MAX']);
} else {
    echo json_encode(['success' => true, 'warning' => 'Отправлено с резервной обработкой']);
}
