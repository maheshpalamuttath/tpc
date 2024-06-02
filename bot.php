<?php
// bot.php
require 'config.php';  // Load configuration (database connection, API URL, etc.)

$content = file_get_contents("php://input");  // Get incoming update from Telegram
$update = json_decode($content, true);  // Decode JSON update to associative array

if (!$update) {
    exit;  // Exit if no update is received
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = $message['text'];
$chatType = $message['chat']['type'];  // Get the chat type

try {
    // Check if the message is from a private chat
    if ($chatType == 'private') {
        handleUserMessage($chatId, $text);
    }
} catch (Exception $e) {
    sendMessage($chatId, "An error occurred. Please try again later.");
    error_log($e->getMessage());
}

function handleUserMessage($chatId, $text) {
    global $conn, $groupInviteLink;

    if ($text == "/start") {
        sendMessage($chatId, "Welcome to the Library Bot. Please enter your card number.");
    } else {
        $cardNumber = $text;

        $user = getUserByCardNumber($cardNumber);
        if ($user) {
            $name = $user['name'];
            $existingChatId = $user['sort2'];

            if (!empty($existingChatId)) {
                sendMessage($chatId, "Hi, $name, your telegram chat ID has already been added. If you need to update it, please meet the librarian.");
            } else {
                updateChatId($cardNumber, $chatId);
                sendMessage($chatId, "Hi, $name, your telegram chat ID has been successfully added to the library database. Join our group using this link: " . $groupInviteLink);
            }
        } else {
            sendMessage($chatId, "You are not a registered user, please meet the librarian.");
        }
    }
}

function getUserByCardNumber($cardNumber) {
    global $conn;

    $stmt = $conn->prepare("SELECT borrowernumber, CONCAT(title, ' ', surname) AS name, sort2 FROM borrowers WHERE cardnumber = ?");
    $stmt->bind_param("s", $cardNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function updateChatId($cardNumber, $chatId) {
    global $conn;

    $stmt = $conn->prepare("UPDATE borrowers SET sort2 = ? WHERE cardnumber = ?");
    $stmt->bind_param("ss", $chatId, $cardNumber);
    $stmt->execute();
}

function sendMessage($chatId, $message) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message);
    file_get_contents($url);
}
?>
