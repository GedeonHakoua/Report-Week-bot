<?php

// =======================
// CONFIG
// =======================
$BOT_TOKEN = getenv('BOT_TOKEN');
$AUTHORIZED_CHAT_ID = getenv('AUTHORIZED_CHAT_ID');
$DATABASE_URL = getenv('DATABASE_URL');

if (!$BOT_TOKEN || !$AUTHORIZED_CHAT_ID || !$DATABASE_URL) {
    http_response_code(500);
    exit("Missing environment variables");
}

// =======================
// CONNEXION DB
// =======================
$pdo = new PDO(
    $DATABASE_URL,
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// =======================
// INIT DB (AUTO)
// =======================
initTables($pdo);

function initTables(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS weeks (
            id SERIAL PRIMARY KEY,
            week_start DATE NOT NULL,
            week_end DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS days (
            id SERIAL PRIMARY KEY,
            week_id INT REFERENCES weeks(id) ON DELETE CASCADE,
            day_name VARCHAR(10),
            meditation TEXT,
            verse TEXT,
            chapters TEXT,
            prayer_time TEXT,
            fasting TEXT,
            saturday_prayer TEXT,
            sunday_teaching TEXT
        );
    ");
}

// =======================
// UPDATE TELEGRAM
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

// =======================
// DATE / SEMAINE
// =======================
$today = new DateTime();
$dayName = strtolower($today->format('l'));

$mapDays = [
    'monday' => 'lundi',
    'tuesday' => 'mardi',
    'wednesday' => 'mercredi',
    'thursday' => 'jeudi',
    'friday' => 'vendredi',
    'saturday' => 'samedi',
    'sunday' => 'dimanche'
];

$day = $mapDays[$dayName];
$weekStart = (clone $today)->modify('monday this week')->format('Y-m-d');
$weekEnd   = (clone $today)->modify('sunday this week')->format('Y-m-d');

// =======================
// RÉCUPÉRER / CRÉER SEMAINE
// =======================
$stmt = $pdo->prepare("
    SELECT id FROM weeks WHERE week_start = ? AND week_end = ?
");
$stmt->execute([$weekStart, $weekEnd]);
$week = $stmt->fetch();

if (!$week) {
    $pdo->prepare("
        INSERT INTO weeks (week_start, week_end) VALUES (?, ?)
    ")->execute([$weekStart, $weekEnd]);
    $weekId = $pdo->lastInsertId();
} else {
    $weekId = $week['id'];
}

// =======================
// COMMANDE /RAPPORT
// =======================
if ($text === '/rapport') {

    $stmt = $pdo->prepare("
        SELECT * FROM days WHERE week_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$weekId]);
    $days = $stmt->fetchAll();

    if (!$days) {
        sendMessage($chat_id, "⚠️ Aucun rapport pour cette semaine.");
        exit;
    }

    $message = "📖 *RAPPORT HEBDOMADAIRE*\n";
    $message .= "_Semaine du $weekStart au $weekEnd_\n\n";

    foreach ($days as $d) {
        $message .= "🗓 *" . strtoupper($d['day_name']) . "*\n";
        if ($d['meditation']) $message .= "1️⃣ Méditation: {$d['meditation']}\n";
        if ($d['verse']) $message .= "2️⃣ Verset: {$d['verse']}\n";
        if ($d['chapters']) $message .= "3️⃣ Chapitres: {$d['chapters']}\n";
        if ($d['prayer_time']) $message .= "4️⃣ Prière: {$d['prayer_time']}\n";
        if ($d['fasting']) $message .= "5️⃣ Jeûne: {$d['fasting']}\n";
        if ($d['saturday_prayer']) $message .= "6️⃣ Prière spéciale: {$d['saturday_prayer']}\n";
        if ($d['sunday_teaching']) $message .= "7️⃣ Enseignement: {$d['sunday_teaching']}\n";
        $message .= "\n";
    }

    sendMessage($chat_id, $message);
    exit;
}

// =======================
// PARSING MESSAGE JOURNALIER
// =======================
$data = [
    'meditation' => null,
    'verse' => null,
    'chapters' => null,
    'prayer_time' => null,
    'fasting' => null,
    'saturday_prayer' => null,
    'sunday_teaching' => null
];

foreach (explode("\n", $text) as $line) {
    if (preg_match('/^1\.\s*(.+)$/', $line, $m)) $data['meditation'] = $m[1];
    if (preg_match('/^2\.\s*(.+)$/', $line, $m)) $data['verse'] = $m[1];
    if (preg_match('/^3\.\s*(.+)$/', $line, $m)) $data['chapters'] = $m[1];
    if (preg_match('/^4\.\s*(.+)$/', $line, $m)) $data['prayer_time'] = $m[1];
    if ($day === 'jeudi' && preg_match('/^5\.\s*(.+)$/', $line, $m)) $data['fasting'] = $m[1];
    if ($day === 'samedi' && preg_match('/^6\.\s*(.+)$/', $line, $m)) $data['saturday_prayer'] = $m[1];
    if ($day === 'dimanche' && preg_match('/^7\.\s*(.+)$/', $line, $m)) $data['sunday_teaching'] = $m[1];
}

// =======================
// SAUVEGARDE
// =======================
$stmt = $pdo->prepare("
    SELECT id FROM days WHERE week_id = ? AND day_name = ?
");
$stmt->execute([$weekId, $day]);

if ($stmt->fetch()) {
    $pdo->prepare("
        UPDATE days SET
            meditation=:meditation, verse=:verse, chapters=:chapters,
            prayer_time=:prayer_time, fasting=:fasting,
            saturday_prayer=:saturday_prayer, sunday_teaching=:sunday_teaching
        WHERE week_id=:week_id AND day_name=:day_name
    ")->execute(array_merge($data, [
        'week_id' => $weekId,
        'day_name' => $day
    ]));
} else {
    $pdo->prepare("
        INSERT INTO days
        (week_id, day_name, meditation, verse, chapters, prayer_time, fasting, saturday_prayer, sunday_teaching)
        VALUES
        (:week_id, :day_name, :meditation, :verse, :chapters, :prayer_time, :fasting, :saturday_prayer, :sunday_teaching)
    ")->execute(array_merge($data, [
        'week_id' => $weekId,
        'day_name' => $day
    ]));
}

sendMessage($chat_id, "✅ Rapport du *$day* enregistré.");

// =======================
// ENVOI TELEGRAM
// =======================
function sendMessage($chat_id, $message)
{
    global $BOT_TOKEN;
    file_get_contents(
        "https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" .
        http_build_query([
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ])
    );
}
