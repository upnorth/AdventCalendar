<?php
    session_start();
    session_destroy();
    header("location:index.php"); //Replace with Logged Out page. Remove if you want to use HTML in same file.
?>