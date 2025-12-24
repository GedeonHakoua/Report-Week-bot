<?php

// =======================
// CONFIG
// =======================
$BOT_TOKEN = getenv('BOT_TOKEN');
$AUTHORIZED_CHAT_ID = getenv('AUTHORIZED_CHAT_ID');

// =======================
// RÉCUPÉRATION UPDATE
// =======================
$update = json_decode(file_get_contents("php://input"), true);
if (!isset($update['message'])) exit;

$chat_id = $update['message']['chat']['id'];
$text = trim($update['message']['text'] ?? '');

// =======================
// SÉCURITÉ
// =======================
if ($chat_id != $AUTHORIZED_CHAT_ID) {
    sendMessage($chat_id, "⛔ Accès refusé.");
    exit;
}

if ($text === '') {
    sendMessage($chat_id, "⚠️ Message vide ignoré.");
    exit;
}

// =======================
// CONNEXION DB (RAILWAY)
// =======================
$pdo = new PDO(
    getenv("DATABASE_URL"),
    null,
    null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// =======================
// SEMAINE COURANTE
// =======================
$week = date('W');
$year = date('Y');

// =======================
// SAUVEGARDE
// =======================
$stmt = $pdo->prepare("
    SELECT id FROM weekly_reports 
    WHERE week_number = ? AND year = ?
");
$stmt->execute([$week, $year]);

if ($stmt->rowCount() > 0) {
    $pdo->prepare("
        UPDATE weekly_reports 
        SET bible_text = ?, updated_at = NOW()
        WHERE week_number = ? AND year = ?
    ")->execute([$text, $week, $year]);

    sendMessage($chat_id, "✅ Rapport mis à jour pour la semaine $week.");
} else {
    $pdo->prepare("
        INSERT INTO weekly_reports (week_number, year, bible_text)
        VALUES (?, ?, ?)
    ")->execute([$week, $year, $text]);

    sendMessage($chat_id, "📖 Rapport enregistré pour la semaine $week.");
}

// =======================
// FONCTION MESSAGE
// =======================
function sendMessage($chat_id, $message)
{
    global $BOT_TOKEN;
    file_get_contents(
        "https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" .
        http_build_query([
            'chat_id' => $chat_id,
            'text' => $message
        ])
    );
}
