<?php 
session_start();
session_destroy();
header ("Locatiom: admin-login.php");
exit();
?>