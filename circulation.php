<?php
// circulation.php
require 'config.php';

// Function to create file if not exists
function createFileIfNotExists($filePath) {
    if (!file_exists($filePath) && !touch($filePath)) {
        die("Failed to create file: $filePath");
    }
}

// Function to send message to Telegram
function sendMessageToTelegram($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $message];
    $options = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data)]];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// Function to read the last processed item number from a file
function readLastProcessedItem($filePath) {
    if (is_readable($filePath)) {
        $content = file_get_contents($filePath);
        return trim($content);
    } else {
        die("File not readable: $filePath");
    }
}

// Function to get the latest transaction from the database
function getLatestTransaction($conn, $type) {
    $queries = [
        'issue' => "
            SELECT
                DATE_FORMAT(c.issuedate, '%d-%m-%Y %h:%i:%p') AS datetime,
                'issue' AS transaction,
                i.itemnumber,
                i.barcode,
                CONCAT(bi.title, COALESCE(CONCAT(' ', bi.subtitle), '')) AS title,
                bi.author,
                b.cardnumber,
                CONCAT(b.title, ' ', b.surname) AS 'name',
                b.sort2 AS telegram_chat_id,
                i.itype,
                br.branchname,
                DATE_FORMAT(c.date_due, '%d %b %Y') AS date_due
            FROM
                statistics s
            JOIN
                borrowers b ON s.borrowernumber = b.borrowernumber
            LEFT JOIN
                items i ON s.itemnumber = i.itemnumber
            LEFT JOIN
                biblio bi ON i.biblionumber = bi.biblionumber
            LEFT JOIN
                issues c ON c.itemnumber = s.itemnumber
            LEFT JOIN
                branches br ON i.homebranch = br.branchcode
            WHERE
                s.datetime >= CURRENT_DATE()
                AND s.type = 'issue'
            ORDER BY s.datetime DESC
            LIMIT 1
        ",
        'return' => "
            SELECT
                DATE_FORMAT(oi.returndate, '%d-%m-%Y %h:%i:%p') AS datetime,
                'return' AS transaction,
                i.itemnumber,
                i.barcode,
                CONCAT(bi.title, COALESCE(CONCAT(' ', bi.subtitle), '')) AS title,
                bi.author,
                b.cardnumber,
                CONCAT(b.title, ' ', b.surname) AS 'name',
                b.sort2 AS telegram_chat_id,
                i.itype,
                br.branchname
            FROM
                statistics s
            JOIN
                borrowers b ON s.borrowernumber = b.borrowernumber
            LEFT JOIN
                items i ON s.itemnumber = i.itemnumber
            LEFT JOIN
                biblio bi ON i.biblionumber = bi.biblionumber
            LEFT JOIN
                old_issues oi ON oi.itemnumber = s.itemnumber
            LEFT JOIN
                branches br ON i.homebranch = br.branchcode
            WHERE
                s.datetime >= CURRENT_DATE()
                AND s.type = 'return'
            ORDER BY s.datetime DESC
            LIMIT 1
        ",
        'renew' => "
            SELECT
                DATE_FORMAT(s.datetime, '%d-%m-%Y %h:%i %p') AS datetime,
                'renew' AS transaction,
                i.itemnumber,
                i.barcode,
                CONCAT(bi.title, COALESCE(CONCAT(' ', bi.subtitle), '')) AS title,
                bi.author,
                b.cardnumber,
                CONCAT(b.title, ' ', b.surname) AS 'name',
                b.sort2 AS telegram_chat_id,
                i.itype,
                br.branchname
            FROM
                statistics s
            JOIN
                borrowers b ON s.borrowernumber = b.borrowernumber
            LEFT JOIN
                items i ON s.itemnumber = i.itemnumber
            LEFT JOIN
                biblio bi ON i.biblionumber = bi.biblionumber
            LEFT JOIN
                branches br ON i.homebranch = br.branchcode
            WHERE
                s.datetime >= CURRENT_DATE()
                AND s.type = 'renew'
            ORDER BY s.datetime DESC
            LIMIT 1
        "
    ];

    return $conn->query($queries[$type]);
}

// Main script logic
$logFiles = [
    'issue' => "/usr/share/koha/opac/htdocs/tpc/checkout_notification_log.txt",
    'return' => "/usr/share/koha/opac/htdocs/tpc/checkin_notification_log.txt",
    'renew' => "/usr/share/koha/opac/htdocs/tpc/renewal_notification_log.txt"
];
$lastProcessedFiles = [
    'issue' => "/usr/share/koha/opac/htdocs/tpc/last_checkout.txt",
    'return' => "/usr/share/koha/opac/htdocs/tpc/last_checkin.txt",
    'renew' => "/usr/share/koha/opac/htdocs/tpc/last_renewal.txt"
];

foreach ($logFiles as $type => $logFile) {
    createFileIfNotExists($logFile);
}
foreach ($lastProcessedFiles as $type => $lastFile) {
    createFileIfNotExists($lastFile);
}

foreach (['issue', 'return', 'renew'] as $type) {
    $lastProcessedItem = readLastProcessedItem($lastProcessedFiles[$type]);

    $result = getLatestTransaction($conn, $type);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row['itemnumber'] !== $lastProcessedItem) {
                $message = ucfirst($type) . " Notification\n\n";
                $message .= "Dear {$row['name']} ({$row['cardnumber']}),\n\n";

                if ($type == 'issue') {
                    $message .= "Thank you for checking out the following library item:\n\n";
                } elseif ($type == 'return') {
                    $message .= "Thank you for returning the following library item:\n\n";
                } elseif ($type == 'renew') {
                    $message .= "Thank you for renewing the following library item:\n\n";
                }

                $message .= "- Title: {$row['title']}\n";
                $message .= "- Author: {$row['author']}\n";
                $message .= "- Barcode: {$row['barcode']}\n";
                $message .= "- Date: {$row['datetime']}\n";
                if (isset($row['date_due'])) {
                    $message .= "- Due Date: {$row['date_due']}\n";
                }
                $message .= "- Item Type: {$row['itype']}\n\n";
                $message .= "Thank you for using our library services!\n\n";
                $message .= $row['branchname'];

                $chatId = $row['telegram_chat_id'];

                sendMessageToTelegram($botToken, $chatId, $message);

                // Replace the content of the log file and last processed file with the new item number
                file_put_contents($lastProcessedFiles[$type], $row['itemnumber']);
                file_put_contents($logFiles[$type], $row['itemnumber']);
            } else {
                echo "Item already notified.";
            }
        }
    } else {
        echo "No results found for $type.";
    }
}

$conn->close();
echo "Messages sent to Telegram";
?>
