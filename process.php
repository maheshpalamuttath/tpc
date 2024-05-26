<?php
// Include the config file
include 'config.php';

function sendFile($apiUrl, $chat_id, $file_path, $caption, $is_document) {
    $url = $apiUrl . ($is_document ? "sendDocument" : "sendPhoto");

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Prepare the data
    $post_fields = [
        'chat_id' => $chat_id,
        $is_document ? 'document' : 'photo' => new CURLFile(realpath($file_path))
    ];

    if ($caption) {
        $post_fields['caption'] = $caption;
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

    // Execute the cURL session
    $response = curl_exec($ch);

    // Close the cURL session
    curl_close($ch);

    return $response;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process the form data

    // Get the selected branch code
    $selected_branch_code = $_POST['branch_code']; // Assuming branch code is submitted via the form

    // SQL query to get chat IDs with expiry date check for selected branch
    $sql = "SELECT b.sort2 AS 'telegram_chat_id', CONCAT(b.title, ' ', b.surname) AS 'name', b.cardnumber, br.branchname, b.dateexpiry
            FROM borrowers b
            LEFT JOIN branches br ON b.branchcode = br.branchcode
            WHERE b.sort2 IS NOT NULL
                AND TRIM(b.sort2) <> ''
                AND CURDATE() <= b.dateexpiry
                AND b.branchcode = '$selected_branch_code'";
    $result = $conn->query($sql);

    // Initialize multi-cURL
    $multiCurl = curl_multi_init();

    // Initialize an array to store individual cURL handles
    $curlHandles = [];

    // Check if there are rows in the result
    if ($result && $result->num_rows > 0) {
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

        while ($row = $result->fetch_assoc()) {
            // Prepare data for cURL
            $message = "Hi, {$row['name']} ({$row['cardnumber']})\n\n{$_POST['description']}\n\n{$row['branchname']}";
            $chatId = $row['telegram_chat_id'];

            if ($file_path) {
                // Initialize cURL handle for this request
                $response = sendFile($apiUrl, $chatId, $file_path, $message, $is_document);
                echo "File sent successfully: " . $response . "<br>";
            } else {
                // Prepare data for cURL
                $postData = array(
                    'chat_id' => $chatId,
                    'text' => $message
                );

                // Initialize cURL handle for this request
                $curlHandle = curl_init();

                // Set cURL options
                curl_setopt_array($curlHandle, array(
                    CURLOPT_URL => $apiUrl . "sendMessage",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($postData),
                ));

                // Add the cURL handle to the array
                $curlHandles[] = $curlHandle;

                // Add the cURL handle to multi-cURL
                curl_multi_add_handle($multiCurl, $curlHandle);
            }
        }

        // Execute all cURL handles simultaneously
        if (empty($file_path)) {
            $running = null;
            do {
                curl_multi_exec($multiCurl, $running);
            } while ($running > 0);

            // Close all cURL handles
            foreach ($curlHandles as $curlHandle) {
                curl_multi_remove_handle($multiCurl, $curlHandle);
                curl_close($curlHandle);
            }

            // Close multi-cURL
            curl_multi_close($multiCurl);
        }

        // Output a success message
        echo "Telegram messages sent successfully to users of branch with code $selected_branch_code!<br><br>";

        // Output a "Back to Home" button
        echo '<a href="index.html">Back to Home</a>';

    } else {
        echo "No chat IDs found for users of branch with code $selected_branch_code.";
    }

    // Close MySQL connection
    $conn->close();
}
?>
