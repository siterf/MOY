<?php
/**
 * ============================================================================
 * YANDEX DISK REST API КЛИЕНТ
 * ============================================================================
 * Позволяет скачивать, проверять и перезаписывать файлы таблиц (.xlsx) на Яндекс Диске.
 * Документация API: https://yandex.ru/dev/disk/api/reference/upload.html
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

class YandexDisk
{
    private $oauthToken;
    private $baseUrl = 'https://cloud-api.yandex.net/v1/disk';

    public function __construct(array $config)
    {
        $this->oauthToken = $config['yandex']['oauth_token'] ?? '';
    }

    /**
     * Проверка готовности токена
     */
    public function isConfigured(): bool
    {
        return !empty($this->oauthToken) && $this->oauthToken !== 'YOUR_YANDEX_OAUTH_TOKEN_HERE';
    }

    /**
     * Проверка существования файла на Яндекс Диске
     */
    public function fileExists(string $path): bool
    {
        if (!$this->isConfigured()) return false;

        $url = $this->baseUrl . '/resources?path=' . urlencode($path);
        $res = $this->request('GET', $url);

        return isset($res['http_code']) && $res['http_code'] === 200;
    }

    /**
     * Скачивание файла с Яндекс Диска во временный локальный файл
     */
    public function downloadFile(string $remotePath, string $localSavePath): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Яндекс OAuth-токен не настроен в config.php'];
        }

        // 1. Получаем ссылку на скачивание
        $metaUrl = $this->baseUrl . '/resources/download?path=' . urlencode($remotePath);
        $meta = $this->request('GET', $metaUrl);

        if (!isset($meta['http_code']) || $meta['http_code'] !== 200 || empty($meta['data']['href'])) {
            return [
                'success' => false,
                'error'   => 'Не удалось получить ссылку на скачивание файла: ' . ($meta['data']['message'] ?? 'Ошибка Яндекс API')
            ];
        }

        $downloadUrl = $meta['data']['href'];

        // 2. Скачиваем бинарный файл
        $dir = dirname($localSavePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fp = @fopen($localSavePath, 'w+');
        if (!$fp) {
            return ['success' => false, 'error' => 'Не удалось открыть локальный файл для записи: ' . $localSavePath];
        }

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $exec = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curlError || $httpCode >= 400 || !$exec) {
            @unlink($localSavePath);
            return [
                'success' => false,
                'error'   => 'Ошибка при скачивании файла: ' . ($curlError ?: 'HTTP ' . $httpCode)
            ];
        }

        return ['success' => true, 'path' => $localSavePath];
    }

    /**
     * Загрузка (перезапись) локального файла на Яндекс Диск
     */
    public function uploadFile(string $localPath, string $remotePath): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Яндекс OAuth-токен не настроен в config.php'];
        }

        if (!file_exists($localPath)) {
            return ['success' => false, 'error' => 'Локальный файл для загрузки не найден: ' . $localPath];
        }

        // 1. Получаем ссылку для загрузки (с параметром overwrite=true)
        $metaUrl = $this->baseUrl . '/resources/upload?path=' . urlencode($remotePath) . '&overwrite=true';
        $meta = $this->request('GET', $metaUrl);

        if (!isset($meta['http_code']) || $meta['http_code'] !== 200 || empty($meta['data']['href'])) {
            return [
                'success' => false,
                'error'   => 'Не удалось получить ссылку для загрузки файла: ' . ($meta['data']['message'] ?? 'Ошибка Яндекс API')
            ];
        }

        $uploadUrl = $meta['data']['href'];

        // 2. Отправляем бинарные данные через PUT
        $fileSize = filesize($localPath);
        $fp = fopen($localPath, 'r');

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $fileSize,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curlError) {
            return ['success' => false, 'error' => 'cURL Error при загрузке: ' . $curlError];
        }

        if ($httpCode === 201 || $httpCode === 200 || $httpCode === 202) {
            return ['success' => true];
        }

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => 'Ошибка загрузки файла на Яндекс Диск (HTTP ' . $httpCode . '): ' . $response
        ];
    }

    /**
     * Вспомогательный запрос к Яндекс Диск REST API
     */
    private function request(string $method, string $url, array $params = []): array
    {
        $ch = curl_init();
        $headers = [
            'Authorization: OAuth ' . $this->oauthToken,
            'Accept: application/json'
        ];

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if (!empty($params)) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($params);
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
        } elseif ($method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true) ?: [];

        return [
            'http_code' => $httpCode,
            'error'     => $curlError,
            'data'      => $data
        ];
    }
}
