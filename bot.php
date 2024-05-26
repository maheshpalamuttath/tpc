<?php
// bot.php
require 'config.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = $message['text'];

if ($text == "/start") {
    sendMessage($chatId, "Welcome to the Library Bot. Please enter your card number.");
} else {
    $cardNumber = $text;
    $stmt = $conn->prepare("SELECT borrowernumber, surname FROM borrowers WHERE cardnumber = ?");
    $stmt->bind_param("s", $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $surname = $row['surname'];
        $stmtUpdate = $conn->prepare("UPDATE borrowers SET sort2 = ? WHERE cardnumber = ?");
        $stmtUpdate->bind_param("ss", $chatId, $cardNumber);
        $stmtUpdate->execute();
        sendMessage($chatId, "Hi, $surname, your telegram chat ID has been successfully added to the library database.");
    } else {
        sendMessage($chatId, "You are not a registered user, please meet the librarian.");
    }
}

function sendMessage($chatId, $message) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message);
    file_get_contents($url);
}
?>
