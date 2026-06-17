<?php
include "db.php";

$result = mysqli_query($conn, "SELECT * FROM users");

if ($result) {
    echo "Users table connected successfully!";
} else {
    echo "Error connecting to table";
}
?>