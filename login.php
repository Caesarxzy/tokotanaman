<?php
session_start();
include "koneksi.php";

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND password='$password'");
    $data = mysqli_fetch_assoc($query);

    if($data){
        $_SESSION['user'] = $data;

        if($data['role'] == 'owner'){
            header("Location: owner/dashboard.php");
            exit;
        } else {
            header("Location: customer/dashboard.php");
            exit;
        }
    } else {
        echo "<script>alert('Login gagal!');</script>";
    }
}
?>