<?php

/**
 * بوت روميو - بوت تيليجرام لإدارة المجموعات
 * Romeo Bot - Telegram Group Management Bot
 */

define('BOT_TOKEN', '8328961419:AAEwYLLK55Rbzj7qmfEgd3XkkY8Lq44YPfk');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DATA_FILE', __DIR__ . '/bot_data.json');

// ===========================
// DATA MANAGEMENT
// ===========================

function loadData(): array {
    if (!file_exists(DATA_FILE)) {
        return [
            'custom_replies' => [],
            'group_settings' => [],
            'user_ranks'     => [],
            'spam_tracker'   => [],
        ];
    }
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true) ?? [];
}

function saveData(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getGroupSettings(array &$data, int $chatId): array {
    $id = (string)$chatId;
    if (!isset($data['group_settings'][$id])) {
        $data['group_settings'][$id] = [
            'lock_swear'      => false,
            'lock_links'      => false,
            'lock_forward'    => false,
            'lock_clutter'    => false,
            'lock_english'    => false,
            'lock_chinese'    => false,
            'lock_russian'    => false,
            'lock_photos'     => false,
            'lock_videos'     => false,
            'lock_media_edit' => false,
            'lock_audio'      => false,
            'lock_music'      => false,
            'lock_repeat'     => false,
            'lock_mention'    => false,
            'lock_numbers'    => false,
            'lock_stickers'   => false,
            'lock_animated'   => false,
            'lock_chat'       => false,
            'lock_join'       => false,
            'disable_id'      => false,
            'disable_service' => false,
            'disable_fun'     => false,
            'disable_welcome' => false,
            'disable_link'    => false,
        ];
    }
    return $data['group_settings'][$id];
}

function getUserRank(array &$data, int $chatId, int $userId): string {
    $cid = (string)$chatId;
    $uid = (string)$userId;
    return $data['user_ranks'][$cid][$uid] ?? 'عضو';
}

function setUserRank(array &$data, int $chatId, int $userId, string $rank): void {
    $cid = (string)$chatId;
    $uid = (string)$userId;
    $data['user_ranks'][$cid][$uid] = $rank;
}

// ===========================
// TELEGRAM API HELPERS
// ===========================

function apiRequest(string $method, array $params = []): ?array {
    $url = API_URL . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $result  = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($result, true);
    return $decoded['ok'] ? $decoded['result'] : null;
}

function sendMessage(int $chatId, string $text, array $extra = []): ?array {
    return apiRequest('sendMessage', array_merge([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ], $extra));
}

function sendPhoto(int $chatId, string $photo, string $caption = '', array $extra = []): ?array {
    return apiRequest('sendPhoto', array_merge([
        'chat_id'    => $chatId,
        'photo'      => $photo,
        'caption'    => $caption,
        'parse_mode' => 'HTML',
    ], $extra));
}

function deleteMessage(int $chatId, int $messageId): void {
    apiRequest('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
}

function getChatMember(int $chatId, int $userId): ?array {
    return apiRequest('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}

function getChat(int $chatId): ?array {
    return apiRequest('getChat', ['chat_id' => $chatId]);
}

function restrictChatMember(int $chatId, int $userId, array $permissions, ?int $until = null): void {
    $params = [
        'chat_id'     => $chatId,
        'user_id'     => $userId,
        'permissions' => $permissions,
    ];
    if ($until !== null) $params['until_date'] = $until;
    apiRequest('restrictChatMember', $params);
}

function banChatMember(int $chatId, int $userId): void {
    apiRequest('banChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}

function unbanChatMember(int $chatId, int $userId): void {
    apiRequest('unbanChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}

function answerCallbackQuery(string $callbackId, string $text = ''): void {
    apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

function editMessageText(int $chatId, int $messageId, string $text, array $replyMarkup = []): void {
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];
    if (!empty($replyMarkup)) $params['reply_markup'] = $replyMarkup;
    apiRequest('editMessageText', $params);
}

function getUserName(array $from): string {
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    return $name ?: ($from['username'] ?? 'مجهول');
}

function getUserMention(array $from): string {
    $name = getUserName($from);
    $id   = $from['id'];
    return "<a href=\"tg://user?id={$id}\">{$name}</a>";
}

// ===========================
// RANK / PERMISSION HELPERS
// ===========================

$RANK_ORDER = ['عضو' => 0, 'مميز' => 1, 'أدمن' => 2, 'ادمن' => 2, 'مدير' => 3, 'مالك' => 4, 'مالك أساسي' => 5];

function rankLevel(string $rank): int {
    global $RANK_ORDER;
    return $RANK_ORDER[$rank] ?? 0;
}

function isTelegramAdmin(int $chatId, int $userId): bool {
    $member = getChatMember($chatId, $userId);
    if (!$member) return false;
    return in_array($member['status'], ['administrator', 'creator']);
}

function isAdminOrAbove(array &$data, int $chatId, int $userId): bool {
    if (isTelegramAdmin($chatId, $userId)) return true;
    $rank = getUserRank($data, $chatId, $userId);
    return rankLevel($rank) >= rankLevel('ادمن');
}

function isOwnerOrAbove(array &$data, int $chatId, int $userId): bool {
    $member = getChatMember($chatId, $userId);
    if ($member && $member['status'] === 'creator') return true;
    $rank = getUserRank($data, $chatId, $userId);
    return rankLevel($rank) >= rankLevel('مالك');
}

function isMaster(array &$data, int $chatId, int $userId): bool {
    $member = getChatMember($chatId, $userId);
    if ($member && $member['status'] === 'creator') return true;
    $rank = getUserRank($data, $chatId, $userId);
    return rankLevel($rank) >= rankLevel('مالك أساسي');
}

// ===========================
// CONVERSATION STATE
// ===========================

$STATE_FILE = __DIR__ . '/bot_state.json';

function loadState(): array {
    global $STATE_FILE;
    if (!file_exists($STATE_FILE)) return [];
    return json_decode(file_get_contents($STATE_FILE), true) ?? [];
}

function saveState(array $state): void {
    global $STATE_FILE;
    file_put_contents($STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE));
}

function getState(array $state, int $chatId, int $userId): ?array {
    return $state[(string)$chatId][(string)$userId] ?? null;
}

function setState(array &$state, int $chatId, int $userId, ?array $value): void {
    if ($value === null) unset($state[(string)$chatId][(string)$userId]);
    else $state[(string)$chatId][(string)$userId] = $value;
}

// ===========================
// MAIN BOT LOGIC
// ===========================

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) { echo 'OK'; exit; }

$data  = loadData();
$state = loadState();

if (isset($update['callback_query'])) {
    handleCallback($update['callback_query'], $data, $state);
    saveData($data); saveState($state);
    echo 'OK'; exit;
}

if (isset($update['message']['new_chat_members'])) {
    handleNewMembers($update['message'], $data);
    saveData($data); echo 'OK'; exit;
}

if (isset($update['message'])) {
    handleMessage($update['message'], $data, $state);
    saveData($data); saveState($state);
}

echo 'OK'; exit;

// ===========================
// HANDLE NEW MEMBERS
// ===========================

function handleNewMembers(array $message, array &$data): void {
    $chatId   = $message['chat']['id'];
    $settings = getGroupSettings($data, $chatId);

    if ($settings['lock_join']) {
        foreach ($message['new_chat_members'] as $member) {
            banChatMember($chatId, $member['id']);
            unbanChatMember($chatId, $member['id']);
        }
        return;
    }

    if ($settings['disable_welcome']) return;

    $chatInfo = getChat($chatId);
    $chatName = $chatInfo['title'] ?? 'المجموعة';

    foreach ($message['new_chat_members'] as $member) {
        $mention = getUserMention($member);
        sendMessage($chatId, "🌹 أهلا بك يا {$mention}\nنورت في مجموعة <b>{$chatName}</b>\nأنا بوت روميو 🤖");
    }
}

// ===========================
// HANDLE CALLBACKS
// ===========================

function handleCallback(array $cb, array &$data, array &$state): void {
    $chatId    = $cb['message']['chat']['id'];
    $messageId = $cb['message']['message_id'];
    $cbId      = $cb['id'];
    $cbData    = $cb['data'];

    answerCallbackQuery($cbId);

    switch ($cbData) {
        case 'menu_service':
            editMessageText($chatId, $messageId, getServiceCommandsText()); break;
        case 'menu_fun':
            editMessageText($chatId, $messageId, getFunCommandsText()); break;
        case 'menu_locks':
            editMessageText($chatId, $messageId, getLocksCommandsText()); break;
        case 'menu_settings':
            editMessageText($chatId, $messageId, getSettingsCommandsText()); break;
    }
}

function getServiceCommandsText(): string {
    return "🔧 <b>أوامر الخدمية:</b>\n\n" .
        "• <b>بايو</b> - يرسل البوت بايو كاتب الكلمة\n" .
        "• <b>اسمي</b> - يرسل اسمك\n" .
        "• <b>اسمه</b> (رد على رسالة) - يرسل اسم الشخص\n" .
        "• <b>يوزري</b> - يرسل يوزرك\n" .
        "• <b>يوزره</b> (رد على رسالة) - يرسل يوزر الشخص\n" .
        "• <b>المالك</b> - يذكر مالك المجموعة\n" .
        "• <b>ايدي</b> - يرسل معرفك\n" .
        "• <b>الرابط</b> - يرسل رابط المجموعة\n" .
        "• <b>رتبة / رتبته</b> - رتبتك أو رتبة الشخص\n\n" .
        "🔴 تعطيل الخدمية | تفعيل الخدمية";
}

function getFunCommandsText(): string {
    return "🎉 <b>أوامر التسليه:</b>\n\n" .
        "• <b>رفع [كلمة]</b> (رد على رسالة) - يرفع لقب للشخص\n\n" .
        "🔴 تعطيل التسليه | تفعيل التسليه";
}

function getLocksCommandsText(): string {
    return "🔒 <b>أوامر القفل والفتح:</b>\n\n" .
        "قفل السب | قفل التكرار | قفل الروابط\n" .
        "قفل التوجيه | قفل الكلايش | قفل الانجليزية\n" .
        "قفل الصينية | قفل الروسية | قفل الصور\n" .
        "قفل الفيديوهات | قفل تعديل الميديا\n" .
        "قفل الصوتيات | قفل الاغاني | قفل التحويل\n" .
        "قفل الدخول | قفل التاك | قفل الارقام\n" .
        "قفل الملصقات | قفل المتحركة | قفل الشات\n\n" .
        "(استبدل قفل بـ فتح لإلغاء)\n\n" .
        "🔴 تعطيل الايدي | تعطيل الخدمية\n" .
        "تعطيل التسليه | تعطيل الترحيب | تعطيل الرابط";
}

function getSettingsCommandsText(): string {
    return "⚙️ <b>أوامر الإعدادات (رد على رسالة شخص):</b>\n\n" .
        "• رفع مالك أساسي / تنزيل مالك أساسي\n" .
        "• رفع مالك / تنزيل مالك\n" .
        "• رفع مدير / تنزيل مدير\n" .
        "• رفع ادمن / تنزيل ادمن\n" .
        "• رفع مميز / تنزيل مميز\n\n" .
        "🔴 <b>الإدارة:</b>\n" .
        "• كتم | تقييد | طرد | رفع القيود | مسح";
}

// ===========================
// HANDLE MESSAGES
// ===========================

function handleMessage(array $message, array &$data, array &$state): void {
    if (!isset($message['text']) && !isset($message['caption'])) {
        handleMediaMessage($message, $data); return;
    }

    $chatId   = $message['chat']['id'];
    $msgId    = $message['message_id'];
    $from     = $message['from'];
    $userId   = $from['id'];
    $text     = trim($message['text'] ?? $message['caption'] ?? '');
    $settings = getGroupSettings($data, $chatId);

    if ($settings['lock_chat'] && !isTelegramAdmin($chatId, $userId) && !isMaster($data, $chatId, $userId)) {
        deleteMessage($chatId, $msgId); return;
    }

    $userState = getState($state, $chatId, $userId);
    if ($userState) {
        handleStateFlow($message, $data, $state, $userState, $text); return;
    }

    if (checkContentModeration($message, $data, $settings)) return;

    processCommand($message, $data, $state, $text, $settings);
}

function handleMediaMessage(array $message, array &$data): void {
    $chatId   = $message['chat']['id'];
    $msgId    = $message['message_id'];
    $from     = $message['from'];
    $userId   = $from['id'];
    $settings = getGroupSettings($data, $chatId);
    $mention  = getUserMention($from);

    if ($settings['lock_chat'] && !isTelegramAdmin($chatId, $userId) && !isMaster($data, $chatId, $userId)) {
        deleteMessage($chatId, $msgId); return;
    }

    if ((isset($message['forward_from']) || isset($message['forward_from_chat']) || isset($message['forward_sender_name'])) && $settings['lock_forward'] && !isTelegramAdmin($chatId, $userId)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع التوجيه والتحويل هنا"); return;
    }

    if (isset($message['photo']) && $settings['lock_photos'] && !isTelegramAdmin($chatId, $userId)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ عذرا عزيزي {$mention}\nممنوع ارسال الصور هنا"); return;
    }
    if (isset($message['video']) && $settings['lock_videos'] && !isTelegramAdmin($chatId, $userId)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ عذرا عزيزي {$mention}\nممنوع ارسال الفيديوهات هنا"); return;
    }
    if (isset($message['sticker']) && $settings['lock_stickers'] && !isTelegramAdmin($chatId, $userId)) {
        if (($message['sticker']['is_animated'] ?? false) && $settings['lock_animated']) {
            deleteMessage($chatId, $msgId);
            sendMessage($chatId, "⚠️ {$mention} ممنوع الملصقات المتحركة هنا"); return;
        } elseif (!($message['sticker']['is_animated'] ?? false)) {
            deleteMessage($chatId, $msgId);
            sendMessage($chatId, "⚠️ {$mention} ممنوع الملصقات هنا"); return;
        }
    }
    if (isset($message['voice']) && $settings['lock_audio'] && !isTelegramAdmin($chatId, $userId)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention} ممنوع ارسال الرسائل الصوتية هنا"); return;
    }
    if (isset($message['audio']) && $settings['lock_music'] && !isTelegramAdmin($chatId, $userId)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention} ممنوع ارسال الاغاني هنا"); return;
    }
}

function checkContentModeration(array $message, array &$data, array $settings): bool {
    $chatId  = $message['chat']['id'];
    $msgId   = $message['message_id'];
    $from    = $message['from'];
    $userId  = $from['id'];
    $text    = $message['text'] ?? $message['caption'] ?? '';
    $mention = getUserMention($from);

    if (isTelegramAdmin($chatId, $userId)) return false;

    if ((isset($message['forward_from']) || isset($message['forward_from_chat']) || isset($message['forward_sender_name'])) && $settings['lock_forward']) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع التوجيه والتحويل هنا"); return true;
    }

    if ($settings['lock_swear']) {
        $swearWords = ['انيجك','انيج امك','كسمك','عير بابوك','عير بامك','قحبه','كحبه','شرموط','شرموطه','زبفيك','عيرك','كسي',' كس ','زبي','عيري',' زب '];
        foreach ($swearWords as $word) {
            if (mb_stripos($text, $word) !== false) {
                deleteMessage($chatId, $msgId);
                sendMessage($chatId, "⚠️ {$mention}\nالسب ممنوع هنا ❌"); return true;
            }
        }
    }

    if ($settings['lock_links'] && preg_match('/(https?:\/\/|t\.me\/|@\w+|www\.)/iu', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع ارسال الروابط هنا"); return true;
    }

    if ($settings['lock_mention'] && preg_match('/@\w+/', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع التاك هنا"); return true;
    }

    if ($settings['lock_numbers'] && preg_match('/(\+?\d{9,12})/', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع ارسال الارقام هنا"); return true;
    }

    if ($settings['lock_clutter'] && mb_strlen($text) > 1000) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع ارسال الرسائل الطويلة هنا"); return true;
    }

    if ($settings['lock_english'] && preg_match('/[a-zA-Z]/', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع الكتابة بالانجليزية هنا"); return true;
    }

    if ($settings['lock_chinese'] && preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع اللغة الصينية هنا"); return true;
    }

    if ($settings['lock_russian'] && preg_match('/[\x{0400}-\x{04FF}]/u', $text)) {
        deleteMessage($chatId, $msgId);
        sendMessage($chatId, "⚠️ {$mention}\nممنوع اللغة الروسية هنا"); return true;
    }

    return false;
}

// ===========================
// COMMAND PROCESSOR
// ===========================

function processCommand(array $message, array &$data, array &$state, string $text, array $settings): void {
    $chatId  = $message['chat']['id'];
    $msgId   = $message['message_id'];
    $from    = $message['from'];
    $userId  = $from['id'];
    $mention = getUserMention($from);
    $cid     = (string)$chatId;

    // ---- KEYWORD TRIGGERS ----
    if (preg_match('/^بوت\b/u', $text) || $text === 'بوت') {
        $replies = ['وش تبي 😒','اهلا 👋','شكد مزعج 😤','عندي اسم ترا 🌹'];
        sendMessage($chatId, $replies[array_rand($replies)], ['reply_to_message_id' => $msgId]); return;
    }

    if (preg_match('/^روميو\b/u', $text) || $text === 'روميو') {
        $replies = ['قول وش تبي 😊','هلا 👋','تفضل 🌹','لا تلح 😑'];
        sendMessage($chatId, $replies[array_rand($replies)], ['reply_to_message_id' => $msgId]); return;
    }

    if (mb_strpos($text, 'صباح الخير') !== false) {
        sendMessage($chatId, '☀️ صباح النور', ['reply_to_message_id' => $msgId]); return;
    }

    if (mb_strpos($text, 'سلام عليكم') !== false || mb_strpos($text, 'السلام عليكم') !== false) {
        $replies = ['وعليكم السلام والرحمة 🌹','وعليكم السلام 👋','وعليكم السلام ورحمة الله وبركاته 🤲'];
        sendMessage($chatId, $replies[array_rand($replies)], ['reply_to_message_id' => $msgId]); return;
    }

    // ---- SERVICE COMMANDS ----
    if (!$settings['disable_service']) {
        if ($text === 'بايو') {
            $member = getChatMember($chatId, $userId);
            $bio    = $member['user']['bio'] ?? null;
            sendMessage($chatId, $bio ? "📋 بايو {$mention}:\n{$bio}" : "😕 {$mention} ما عندك بايو", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'اسمي') {
            sendMessage($chatId, "👤 اسمك: <b>" . getUserName($from) . "</b>", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'اسمه' || $text === 'اسمها') {
            if (isset($message['reply_to_message'])) {
                $name = getUserName($message['reply_to_message']['from']);
                sendMessage($chatId, "👤 اسم الشخص: <b>{$name}</b>", ['reply_to_message_id' => $msgId]);
            } else {
                sendMessage($chatId, "⚠️ رد على رسالة شخص لمعرفة اسمه", ['reply_to_message_id' => $msgId]);
            }
            return;
        }

        if ($text === 'يوزري') {
            $username = $from['username'] ?? null;
            sendMessage($chatId, $username ? "🔗 يوزرك: @{$username}" : "😕 ما عندك يوزر", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'يوزره') {
            if (isset($message['reply_to_message'])) {
                $username = $message['reply_to_message']['from']['username'] ?? null;
                sendMessage($chatId, $username ? "🔗 يوزره: @{$username}" : "😕 ما عنده يوزر", ['reply_to_message_id' => $msgId]);
            } else {
                sendMessage($chatId, "⚠️ رد على رسالة شخص لمعرفة يوزره", ['reply_to_message_id' => $msgId]);
            }
            return;
        }

        if ($text === 'ايدي' && !$settings['disable_id']) {
            if (isset($message['reply_to_message'])) {
                $tf = $message['reply_to_message']['from'];
                sendMessage($chatId, "🆔 ايدي " . getUserName($tf) . ": <code>{$tf['id']}</code>", ['reply_to_message_id' => $msgId]);
            } else {
                sendMessage($chatId, "🆔 ايدك: <code>{$userId}</code>", ['reply_to_message_id' => $msgId]);
            }
            return;
        }

        if ($text === 'الرابط' && !$settings['disable_link']) {
            $link = apiRequest('exportChatInviteLink', ['chat_id' => $chatId]);
            sendMessage($chatId, $link ? "🔗 رابط المجموعة:\n{$link}" : "⚠️ تعذر الحصول على الرابط", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'المالك') {
            $admins = apiRequest('getChatAdministrators', ['chat_id' => $chatId]);
            if ($admins) {
                foreach ($admins as $admin) {
                    if ($admin['status'] === 'creator') {
                        $ownerFrom    = $admin['user'];
                        $ownerMention = getUserMention($ownerFrom);
                        $bio          = $ownerFrom['bio'] ?? 'لا يوجد بايو';
                        $photos       = apiRequest('getUserProfilePhotos', ['user_id' => $ownerFrom['id'], 'limit' => 1]);
                        $photo        = ($photos && $photos['total_count'] > 0) ? ($photos['photos'][0][0]['file_id'] ?? null) : null;
                        if ($photo) sendPhoto($chatId, $photo, "👑 مالك المجموعة: {$ownerMention}\n📋 البايو: {$bio}");
                        else sendMessage($chatId, "👑 مالك المجموعة: {$ownerMention}\n📋 البايو: {$bio}");
                        return;
                    }
                }
            }
            sendMessage($chatId, "⚠️ لم أجد مالك المجموعة", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'رتبة' || $text === 'رتبتي') {
            $rank = getUserRank($data, $chatId, $userId);
            sendMessage($chatId, "🏅 رتبتك: <b>{$rank}</b>", ['reply_to_message_id' => $msgId]); return;
        }

        if ($text === 'رتبته' || $text === 'رتبتها') {
            if (isset($message['reply_to_message'])) {
                $tf   = $message['reply_to_message']['from'];
                $rank = getUserRank($data, $chatId, $tf['id']);
                $m2   = getChatMember($chatId, $tf['id']);
                if ($m2 && $m2['status'] === 'creator') $rank = 'مالك المجموعة';
                elseif ($m2 && $m2['status'] === 'administrator' && $rank === 'عضو') $rank = 'مشرف';
                sendMessage($chatId, "🏅 رتبة " . getUserName($tf) . ": <b>{$rank}</b>", ['reply_to_message_id' => $msgId]);
            } else {
                sendMessage($chatId, "⚠️ رد على رسالة شخص لمعرفة رتبته", ['reply_to_message_id' => $msgId]);
            }
            return;
        }
    }

    // ---- FUN COMMANDS ----
    if (!$settings['disable_fun'] && isAdminOrAbove($data, $chatId, $userId)) {
        if (preg_match('/^رفع\s+(.+)$/u', $text, $m) && isset($message['reply_to_message'])) {
            $tMention = getUserMention($message['reply_to_message']['from']);
            sendMessage($chatId, "✅ تم رفع {$tMention} " . trim($m[1]) . " للتسلية 😜", ['reply_to_message_id' => $msgId]); return;
        }
    }

    // ---- COMMANDS MENU ----
    if (($text === 'الاوامر' || $text === 'اوامر') && isAdminOrAbove($data, $chatId, $userId)) {
        $keyboard = ['inline_keyboard' => [[
            ['text' => '① خدمية',  'callback_data' => 'menu_service'],
            ['text' => '② تسليه',  'callback_data' => 'menu_fun'],
            ['text' => '③ قفل/فتح','callback_data' => 'menu_locks'],
            ['text' => '④ إعدادات','callback_data' => 'menu_settings'],
        ]]];
        sendMessage($chatId, "🤖 <b>قائمة الأوامر</b>\n\n- أوامر ① الخدمية\n- أوامر ② التسليه\n- أوامر ③ القفل والفتح\n- أوامر ④ الإعدادات", ['reply_markup' => $keyboard]); return;
    }

    // ---- ADD / DELETE CUSTOM REPLY ----
    if ($text === 'اضف رد' && isAdminOrAbove($data, $chatId, $userId)) {
        setState($state, $chatId, $userId, ['step' => 'await_reply_name']);
        sendMessage($chatId, "📝 أرسل اسم الرد:", ['reply_to_message_id' => $msgId]); return;
    }

    if ($text === 'مسح رد' && isAdminOrAbove($data, $chatId, $userId)) {
        setState($state, $chatId, $userId, ['step' => 'await_delete_reply_name']);
        sendMessage($chatId, "🗑️ أرسل اسم الرد الذي تريد حذفه:", ['reply_to_message_id' => $msgId]); return;
    }

    // ---- CHECK CUSTOM REPLIES ----
    $cReplies = $data['custom_replies'][$cid] ?? [];
    if (isset($cReplies[$text])) {
        $rd = $cReplies[$text];
        if ($rd['type'] === 'text') sendMessage($chatId, $rd['content'], ['reply_to_message_id' => $msgId]);
        elseif ($rd['type'] === 'photo') sendPhoto($chatId, $rd['file_id'], $rd['caption'] ?? '');
        elseif ($rd['type'] === 'video') apiRequest('sendVideo', ['chat_id' => $chatId, 'video' => $rd['file_id'], 'caption' => $rd['caption'] ?? '', 'parse_mode' => 'HTML']);
        return;
    }

    // ---- LOCK / UNLOCK COMMANDS ----
    if (isAdminOrAbove($data, $chatId, $userId)) {
        $lockMap = [
            'قفل السب'         => 'lock_swear',   'فتح السب'         => 'lock_swear',
            'قفل التكرار'      => 'lock_repeat',  'فتح التكرار'      => 'lock_repeat',
            'قفل الروابط'      => 'lock_links',   'فتح الروابط'      => 'lock_links',
            'قفل التوجيه'      => 'lock_forward', 'فتح التوجيه'      => 'lock_forward',
            'قفل التحويل'      => 'lock_forward', 'فتح التحويل'      => 'lock_forward',
            'قفل الكلايش'      => 'lock_clutter', 'فتح الكلايش'      => 'lock_clutter',
            'قفل الانجليزيه'   => 'lock_english', 'فتح الانجليزيه'   => 'lock_english',
            'قفل الانجليزية'   => 'lock_english', 'فتح الانجليزية'   => 'lock_english',
            'قفل الصينيه'      => 'lock_chinese', 'فتح الصينيه'      => 'lock_chinese',
            'قفل الصينية'      => 'lock_chinese', 'فتح الصينية'      => 'lock_chinese',
            'قفل الروسيه'      => 'lock_russian', 'فتح الروسيه'      => 'lock_russian',
            'قفل الروسية'      => 'lock_russian', 'فتح الروسية'      => 'lock_russian',
            'قفل الصور'        => 'lock_photos',  'فتح الصور'        => 'lock_photos',
            'قفل الفيديوهات'   => 'lock_videos',  'فتح الفيديوهات'   => 'lock_videos',
            'قفل تعديل الميديا'=> 'lock_media_edit','فتح تعديل الميديا'=> 'lock_media_edit',
            'قفل الصوتيات'     => 'lock_audio',   'فتح الصوتيات'     => 'lock_audio',
            'قفل الاغاني'      => 'lock_music',   'فتح الاغاني'      => 'lock_music',
            'قفل الدخول'       => 'lock_join',    'فتح الدخول'       => 'lock_join',
            'قفل التاك'        => 'lock_mention', 'فتح التاك'        => 'lock_mention',
            'قفل الارقام'      => 'lock_numbers', 'فتح الارقام'      => 'lock_numbers',
            'قفل الملصقات'     => 'lock_stickers','فتح الملصقات'     => 'lock_stickers',
            'قفل المتحركه'     => 'lock_animated','فتح المتحركه'     => 'lock_animated',
            'قفل المتحركة'     => 'lock_animated','فتح المتحركة'     => 'lock_animated',
            'قفل الشات'        => 'lock_chat',    'فتح الشات'        => 'lock_chat',
        ];

        if (isset($lockMap[$text])) {
            $key    = $lockMap[$text];
            $isLock = mb_strpos($text, 'قفل') !== false;
            $data['group_settings'][$cid][$key] = $isLock;
            sendMessage($chatId, ($isLock ? '🔒 تم القفل: ' : '🔓 تم الفتح: ') . "<b>{$text}</b>", ['reply_to_message_id' => $msgId]); return;
        }

        $disableMap = [
            'تعطيل الايدي'  => ['disable_id', true],      'تفعيل الايدي'  => ['disable_id', false],
            'تعطيل الخدميه' => ['disable_service', true],  'تفعيل الخدميه' => ['disable_service', false],
            'تعطيل الخدمية' => ['disable_service', true],  'تفعيل الخدمية' => ['disable_service', false],
            'تعطيل التسليه' => ['disable_fun', true],      'تفعيل التسليه' => ['disable_fun', false],
            'تعطيل التسلية' => ['disable_fun', true],      'تفعيل التسلية' => ['disable_fun', false],
            'تعطيل الترحيب' => ['disable_welcome', true],  'تفعيل الترحيب' => ['disable_welcome', false],
            'تعطيل الرابط'  => ['disable_link', true],     'تفعيل الرابط'  => ['disable_link', false],
        ];

        if (isset($disableMap[$text])) {
            [$key, $value] = $disableMap[$text];
            $data['group_settings'][$cid][$key] = $value;
            sendMessage($chatId, ($value ? '🔴 تم التعطيل: ' : '🟢 تم التفعيل: ') . "<b>{$text}</b>", ['reply_to_message_id' => $msgId]); return;
        }
    }

    // ---- RANK MANAGEMENT ----
    $rankCommands = [
        'رفع مالك أساسي'   => 'مالك أساسي', 'تنزيل مالك أساسي' => 'عضو',
        'رفع مالك'         => 'مالك',        'تنزيل مالك'       => 'عضو',
        'رفع مدير'         => 'مدير',        'تنزيل مدير'       => 'عضو',
        'رفع ادمن'         => 'ادمن',        'تنزيل ادمن'       => 'عضو',
        'رفع مميز'         => 'مميز',        'تنزيل مميز'       => 'عضو',
    ];

    if (isset($rankCommands[$text])) {
        if (!isset($message['reply_to_message'])) {
            sendMessage($chatId, "⚠️ رد على رسالة الشخص الذي تريد تغيير رتبته", ['reply_to_message_id' => $msgId]); return;
        }
        if (!isOwnerOrAbove($data, $chatId, $userId)) {
            sendMessage($chatId, "⛔ ليس لديك صلاحية هذا الأمر", ['reply_to_message_id' => $msgId]); return;
        }
        $tf      = $message['reply_to_message']['from'];
        $newRank = $rankCommands[$text];
        setUserRank($data, $chatId, $tf['id'], $newRank);
        $tMention = getUserMention($tf);
        if (mb_strpos($text, 'رفع') !== false)
            sendMessage($chatId, "✅ تم رفع {$tMention} إلى رتبة <b>{$newRank}</b>\nبواسطة {$mention}");
        else
            sendMessage($chatId, "✅ تم تنزيل {$tMention}\nبواسطة {$mention}");
        return;
    }

    // ---- MODERATION ----
    if (isAdminOrAbove($data, $chatId, $userId)) {
        if ($text === 'كتم') {
            if (!isset($message['reply_to_message'])) { sendMessage($chatId, "⚠️ رد على رسالة الشخص", ['reply_to_message_id' => $msgId]); return; }
            $tf = $message['reply_to_message']['from'];
            restrictChatMember($chatId, $tf['id'], ['can_send_messages' => false]);
            sendMessage($chatId, "🔇 تم كتم " . getUserMention($tf) . "\nبواسطة {$mention}"); return;
        }

        if ($text === 'تقييد') {
            if (!isset($message['reply_to_message'])) { sendMessage($chatId, "⚠️ رد على رسالة الشخص", ['reply_to_message_id' => $msgId]); return; }
            $tf = $message['reply_to_message']['from'];
            restrictChatMember($chatId, $tf['id'], ['can_send_messages' => false, 'can_send_media_messages' => false, 'can_send_polls' => false, 'can_send_other_messages' => false, 'can_add_web_page_previews' => false, 'can_change_info' => false, 'can_invite_users' => false, 'can_pin_messages' => false]);
            sendMessage($chatId, "🚫 تم تقييد " . getUserMention($tf) . "\nبواسطة {$mention}"); return;
        }

        if ($text === 'رفع القيود' || $text === 'الغاء الكتم' || $text === 'الغاء التقييد') {
            if (!isset($message['reply_to_message'])) { sendMessage($chatId, "⚠️ رد على رسالة الشخص", ['reply_to_message_id' => $msgId]); return; }
            $tf = $message['reply_to_message']['from'];
            restrictChatMember($chatId, $tf['id'], ['can_send_messages' => true, 'can_send_media_messages' => true, 'can_send_polls' => true, 'can_send_other_messages' => true, 'can_add_web_page_previews' => true]);
            sendMessage($chatId, "✅ تم رفع القيود عن " . getUserMention($tf) . "\nبواسطة {$mention}"); return;
        }

        if ($text === 'طرد') {
            if (!isset($message['reply_to_message'])) { sendMessage($chatId, "⚠️ رد على رسالة الشخص", ['reply_to_message_id' => $msgId]); return; }
            $tf = $message['reply_to_message']['from'];
            banChatMember($chatId, $tf['id']);
            unbanChatMember($chatId, $tf['id']);
            sendMessage($chatId, "👢 تم طرد " . getUserMention($tf) . "\nبواسطة {$mention}"); return;
        }

        if ($text === 'مسح') {
            if (!isset($message['reply_to_message'])) { sendMessage($chatId, "⚠️ رد على الرسالة المراد مسحها", ['reply_to_message_id' => $msgId]); return; }
            deleteMessage($chatId, $message['reply_to_message']['message_id']);
            deleteMessage($chatId, $msgId);
            sendMessage($chatId, "🗑️ تم مسح الرسالة"); return;
        }
    }
}

// ===========================
// STATE FLOW HANDLER
// ===========================

function handleStateFlow(array $message, array &$data, array &$state, array $userState, string $text): void {
    $chatId = $message['chat']['id'];
    $msgId  = $message['message_id'];
    $userId = $message['from']['id'];
    $cid    = (string)$chatId;

    switch ($userState['step']) {
        case 'await_reply_name':
            if (empty($text)) { sendMessage($chatId, "⚠️ أرسل اسم الرد:", ['reply_to_message_id' => $msgId]); return; }
            setState($state, $chatId, $userId, ['step' => 'await_reply_content', 'name' => $text]);
            sendMessage($chatId, "📦 أرسل محتوى الرد (نص، صورة، فيديو...):", ['reply_to_message_id' => $msgId]);
            break;

        case 'await_reply_content':
            $name = $userState['name'];
            if (!isset($data['custom_replies'][$cid])) $data['custom_replies'][$cid] = [];
            if (isset($message['photo'])) {
                $photos = $message['photo'];
                $data['custom_replies'][$cid][$name] = ['type' => 'photo', 'file_id' => end($photos)['file_id'], 'caption' => $message['caption'] ?? ''];
            } elseif (isset($message['video'])) {
                $data['custom_replies'][$cid][$name] = ['type' => 'video', 'file_id' => $message['video']['file_id'], 'caption' => $message['caption'] ?? ''];
            } elseif (!empty($text)) {
                $data['custom_replies'][$cid][$name] = ['type' => 'text', 'content' => $text];
            } else {
                sendMessage($chatId, "⚠️ نوع المحتوى غير مدعوم. أرسل نص أو صورة أو فيديو", ['reply_to_message_id' => $msgId]); return;
            }
            setState($state, $chatId, $userId, null);
            sendMessage($chatId, "✅ تم إضافة الرد <b>{$name}</b> بنجاح 🌹", ['reply_to_message_id' => $msgId]);
            break;

        case 'await_delete_reply_name':
            if (empty($text)) { sendMessage($chatId, "⚠️ أرسل اسم الرد:", ['reply_to_message_id' => $msgId]); return; }
            if (isset($data['custom_replies'][$cid][$text])) {
                unset($data['custom_replies'][$cid][$text]);
                sendMessage($chatId, "✅ تم حذف الرد <b>{$text}</b>", ['reply_to_message_id' => $msgId]);
            } else {
                sendMessage($chatId, "⚠️ لم أجد ردًا باسم <b>{$text}</b>", ['reply_to_message_id' => $msgId]);
            }
            setState($state, $chatId, $userId, null);
            break;
    }
}