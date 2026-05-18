<?php
require_once '../session_check.php';
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID required']);
    exit();
}

$id = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM table_form WHERE reqid = $id");

if ($row = mysqli_fetch_assoc($result)) {
    // Format dates
    $row['tdate_time'] = date('F d, Y h:i A', strtotime($row['tdate_time']));
    $row['created'] = date('F d, Y h:i A', strtotime($row['created']));
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Booking not found']);
}
?>
