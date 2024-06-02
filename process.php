<?php
// Include the config file
include 'config.php';

// Function to send a file to Telegram
function sendFile($apiUrl, $chat_id, $file_path, $caption, $is_document) {
    $url = $apiUrl . ($is_document ? "sendDocument" : "sendPhoto");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $post_fields = [
        'chat_id' => $chat_id,
        $is_document ? 'document' : 'photo' => new CURLFile(realpath($file_path))
    ];

    if ($caption) {
        $post_fields['caption'] = $caption;
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);
    return $response;
}

// Function to send a text message to Telegram
function sendMessage($apiUrl, $chat_id, $message) {
    $postData = [
        'chat_id' => $chat_id,
        'text' => $message
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . "sendMessage",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);
    return $response;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $group_chat_id = "-1002220564841"; // Replace with your Telegram group chat ID

    // Handle file upload if there's a file
    $file_path = '';
    $is_document = false;
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_path = $upload_dir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Determine if the file is a document (PDF) or an image
            $is_document = !in_array(mime_content_type($file_path), ['image/jpeg', 'image/png', 'image/gif']);
        } else {
            echo "Error moving the uploaded file.";
            exit;
        }
    }

    $description = $_POST['description'] . "\n\nFr. Francis Sales Library";

    if ($file_path) {
        $response = sendFile($apiUrl, $group_chat_id, $file_path, $description, $is_document);
        echo "File sent successfully: " . $response . "<br>";
    } else {
        $response = sendMessage($apiUrl, $group_chat_id, $description);
        echo "Message sent successfully: " . $response . "<br>";
    }

    echo '<a href="index.html">Back to Home</a>';
}
?>
