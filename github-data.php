<?php
require_once __DIR__ . '/config.php';

function github_data_request($method, $path, $body = null) {
    $url = 'https://api.github.com/repos/' . rawurlencode(GITHUB_OWNER) . '/' . rawurlencode(GITHUB_REPOSITORY) . '/contents/' . implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    if ($method === 'GET') $url .= '?ref=' . rawurlencode(GITHUB_BRANCH);
    $headers = ['Accept: application/vnd.github+json', 'Authorization: Bearer ' . GITHUB_TOKEN, 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: ConfigBox-Admin'];
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 60]);
    if ($body !== null) { $headers[] = 'Content-Type: application/json'; curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    $response = curl_exec($curl); $error = curl_error($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    if ($response === false || $error) throw new Exception('Falha de conexão com o GitHub: ' . $error);
    $json = json_decode($response, true);
    if ($status < 200 || $status >= 300) throw new Exception(is_array($json) && isset($json['message']) ? $json['message'] : 'GitHub HTTP ' . $status);
    return $json;
}

function github_devices_read() {
    try {
        $file = github_data_request('GET', GITHUB_DATA_FOLDER . '/' . GITHUB_DEVICES_FILE);
        $content = base64_decode(str_replace(["\n", "\r"], '', $file['content'] ?? ''), true);
        $value = $content === false ? [] : json_decode($content, true);
        return ['items' => is_array($value) ? $value : [], 'sha' => $file['sha'] ?? ''];
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Not Found') !== false || strpos($e->getMessage(), 'HTTP 404') !== false) return ['items' => [], 'sha' => null];
        throw $e;
    }
}

function github_devices_write(array $items, $sha = null) {
    $body = ['message' => 'Atualizar cadastro de aparelhos', 'content' => base64_encode(json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'branch' => GITHUB_BRANCH];
    if ($sha) $body['sha'] = $sha;
    return github_data_request('PUT', GITHUB_DATA_FOLDER . '/' . GITHUB_DEVICES_FILE, $body);
}

function github_devices_upsert(array $record) {
    $data = github_devices_read(); $items = $data['items']; $found = false;
    foreach ($items as &$item) if (($item['device_id'] ?? '') === $record['device_id']) { $record['blocked'] = !empty($item['blocked']); $record['message'] = (string)($item['message'] ?? ''); $item = array_merge($item, $record); $found = true; break; }
    unset($item);
    if (!$found) { $record['blocked'] = false; $record['message'] = ''; $items[] = $record; }
    github_devices_write($items, $data['sha']);
    return $items;
}
