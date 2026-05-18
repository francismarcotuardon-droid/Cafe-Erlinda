<?php

include("db_cafe_connect.php"); 

$tfull_name = $_POST['tfull_name'];
$temail = $_POST['temail'];
$tdate_time = date("Y-m-d H:i:s", strtotime($_POST['tdate_time']));
$tno_people = $_POST['tno_people'];
$ttable = $_POST['ttable'];
$tmessage = $_POST['tmessage'];

$query=mysqli_query($conn,"INSERT INTO table_form
(tfull_name,temail,tdate_time,tno_people,ttable,tmessage)VALUES(
'$tfull_name','$temail','$tdate_time','$tno_people','$ttable','$tmessage')");
?>

<script>
window.alert("Successfully Submitted!");
window.location="index.html"
</script>
<?php

mysqli_close($conn); 
?>
