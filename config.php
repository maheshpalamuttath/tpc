<?php
// Replace with your bot token
$botToken = "7130803239:AAH9ufIclF1vBJ4QcYg04hF4w8J0EnNRfug";

// API URL for Telegram bot
$apiUrl = "https://api.telegram.org/bot$botToken/";

// Database connection parameters
$servername = "localhost";
$username = "koha_library";
$password = "!|I-pXV#=mN74GS#";
$dbname = "koha_library";

// Group invite link
$groupInviteLink = 'https://t.me/+Lxybu-nsf8g4MjE1';

// Telegram group chat ID
$group_chat_id = "-1002220564841";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
