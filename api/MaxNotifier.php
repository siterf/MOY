<?php
/**
 * ============================================================================
 * MAX NOTIFIER — КЛИЕНТ ДЛЯ ОТПРАВКИ УВЕДОМЛЕНИЙ В МЕССЕНДЖЕР MAX
 * ============================================================================
 * Документация API: https://dev.max.ru/docs-api/methods/POST/messages
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

class MaxNotifier
{
    private $botToken;
    private $chatId;
    private $isChat;
    private $apiUrl;

    public function __construct(array $config)
    {
        $this->botToken = $config['max']['bot_token'] ?? '';
        $this->chatId   = $config['max']['chat_id'] ?? '';
        $this->isChat   = $config['max']['is_chat'] ?? true;
        $this->apiUrl   = rtrim($config['max']['api_url'] ?? 'https://platform-api2.max.ru', '/');
    }

    /**
     * Отправка форматированного уведомления о новом брифе
     */
    public function sendBriefNotification(array $data): array
    {
        if (empty($this->botToken) || $this->botToken === 'YOUR_MAX_BOT_TOKEN_HERE') {
            return ['success' => false, 'error' => 'MAX Bot Token не настроен в api/config.php'];
        }

        if (empty($this->chatId) || $this->chatId === 'YOUR_MAX_CHAT_ID_OR_USER_ID_HERE') {
            return ['success' => false, 'error' => 'MAX Chat ID не настроен в api/config.php'];
        }

        $now = date('d.m.Y H:i:s');
        $companyName   = !empty($data['company_name']) ? $data['company_name'] : 'Не указано';
        $city          = !empty($data['city']) ? $data['city'] : 'Не указан';
        $services      = !empty($data['services']) ? $data['services'] : '—';
        $clientName    = !empty($data['client_name']) ? $data['client_name'] : 'Аноним';
        $contact       = !empty($data['contact']) ? $data['contact'] : (!empty($data['contact_info']) ? $data['contact_info'] : 'Не указан');
        $goals         = !empty($data['goals']) ? (is_array($data['goals']) ? implode(', ', $data['goals']) : $data['goals']) : 'Не выбраны';
        $currentIssues = !empty($data['current_issues']) ? $data['current_issues'] : '—';
        $photoReady    = !empty($data['photo_ready']) ? $data['photo_ready'] : '—';
        $deadline      = !empty($data['deadline']) ? $data['deadline'] : '—';
        $comment       = !empty($data['comment']) ? $data['comment'] : '';
        $source        = !empty($data['source']) ? $data['source'] : 'Сайт (Бриф)';

        // Формируем структурированный текст в Markdown
        $text = "📋 **НОВЫЙ БРИФ С САЙТА**\n\n";
        $text .= "🏢 **Бизнес:** {$companyName}\n";
        $text .= "📍 **Город:** {$city}\n";
        $text .= "💆 **Услуги / Ниша:** {$services}\n\n";
        
        $text .= "👤 **Контактное лицо:**\n";
        $text .= "• **Имя:** {$clientName}\n";
        $text .= "• **MAX / Связь:** `{$contact}`\n\n";

        $text .= "🎯 **Задачи сайта:**\n{$goals}\n\n";

        if ($currentIssues !== '—') {
            $text .= "⚠️ **Текущие сложности:**\n_{$currentIssues}_\n\n";
        }

        if ($photoReady !== '—' || $deadline !== '—') {
            $text .= "📦 **Материалы и сроки:**\n";
            if ($photoReady !== '—') $text .= "• Фото: {$photoReady}\n";
            if ($deadline !== '—')   $text .= "• Сроки: {$deadline}\n";
            $text .= "\n";
        }

        if (!empty($comment)) {
            $text .= "💬 **Комментарий:**\n_{$comment}_\n\n";
        }

        $text .= "⏱ _Дата: {$now} | Источник: {$source}_";

        // Формируем кнопки быстрых ссылок
        $buttons = [];
        $row1 = [];

        if (!empty($data['maps_link']) && filter_var($data['maps_link'], FILTER_VALIDATE_URL)) {
            $row1[] = [
                'type' => 'link',
                'text' => '📍 Яндекс Карты / 2ГИС',
                'url'  => $data['maps_link']
            ];
        }

        if (!empty($data['social_link']) && filter_var($data['social_link'], FILTER_VALIDATE_URL)) {
            $row1[] = [
                'type' => 'link',
                'text' => '🌐 VK / Соцсети',
                'url'  => $data['social_link']
            ];
        }

        if (!empty($row1)) {
            $buttons[] = $row1;
        }

        return $this->sendMessage($text, $buttons);
    }

    /**
     * Отправка уведомления о записи на занятие (для расписания)
     */
    public function sendBookingNotification(array $data): array
    {
        $now = date('d.m.Y H:i:s');
        $text = "🔔 **НОВАЯ ЗАПИСЬ НА ЗАНЯТИЕ**\n\n";
        $text .= "👤 **Имя:** " . ($data['name'] ?? '—') . "\n";
        $text .= "📱 **Телефон:** `" . ($data['phone'] ?? '—') . "`\n";
        $text .= "🧘 **Направление:** " . ($data['direction'] ?? '—') . "\n";
        $text .= "📅 **Дата / Время:** " . (!empty($data['date']) ? "{$data['date']} {$data['time']}" : "Общая заявка") . "\n";
        if (!empty($data['goal'])) {
            $text .= "🎯 **Запрос:** _{$data['goal']}_\n";
        }
        $text .= "\n⏱ _Дата: {$now}_";

        return $this->sendMessage($text);
    }

    /**
     * Базовый метод отправки сообщения через MAX Bot API
     */
    public function sendMessage(string $text, array $keyboardButtons = []): array
    {
        $queryParam = $this->isChat ? 'chat_id=' . urlencode($this->chatId) : 'user_id=' . urlencode($this->chatId);
        $url = $this->apiUrl . '/messages?' . $queryParam;

        $payload = [
            'text'   => $text,
            'format' => 'markdown',
            'notify' => true
        ];

        if (!empty($keyboardButtons)) {
            $payload['attachments'] = [
                [
                    'type'    => 'inline_keyboard',
                    'payload' => [
                        'buttons' => $keyboardButtons
                    ]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->botToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'cURL Error: ' . $curlError];
        }

        $result = json_decode($response, true);

        if ($httpCode === 200 || $httpCode === 201) {
            return ['success' => true, 'response' => $result];
        }

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => $result['message'] ?? $result['error'] ?? 'Ошибка отправки в MAX API (HTTP ' . $httpCode . ')',
            'raw'       => $response
        ];
    }
}
