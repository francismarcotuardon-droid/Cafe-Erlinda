<?php
include("db_cafe_connect.php");

$cname = $_POST['cname'];
$cemail = $_POST['cemail'];
$cevent_type = $_POST['cevent_type'];
$cpeople = $_POST['cpeople'];
$cevent_date = $_POST['cevent_date'];
$cspecial_request = $_POST['cspecial_request'];

$cbirthday_name = $_POST['cbirthday_name'] ?? '';
$cage = $_POST['cage'] ?? '';
$ctheme = $_POST['ctheme'] ?? '';

$ccouple_name = $_POST['ccouple_name'] ?? '';
$cyears = $_POST['cyears'] ?? '';

$cgroup_name = $_POST['cgroup_name'] ?? '';
$cyear_grad = $_POST['cyear_grad'] ?? '';

$sql = "INSERT INTO reservations 
(cname, cemail, cevent_type, cpeople, cevent_date, cspecial_request, cbirthday_name, cage, ctheme, ccouple_name, cyears, cgroup_name, cyear_grad)
VALUES 
('$cname', '$cemail', '$cevent_type', '$cpeople', '$cevent_date', '$cspecial_request', '$cbirthday_name', '$cage', '$ctheme', '$ccouple_name', '$cyears', '$cgroup_name', '$cyear_grad')";

if (mysqli_query($conn, $sql)) {
    echo "<script>alert('Success'); window.location.href='success_res.html';</script>";
} else {
    echo "<script>alert('Error: " . mysqli_error($conn) . "'); window.history.back();</script>";
}

?>