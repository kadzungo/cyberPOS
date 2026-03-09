<?php

function require_admin() {

    if (!isset($_SESSION["role"])) {
        header("Location: login.php");
        exit;
    }

    if ($_SESSION["role"] !== "admin") {
        die("Access denied. Admin only.");
    }

}

function require_staff_or_admin() {

    if (!isset($_SESSION["role"])) {
        header("Location: login.php");
        exit;
    }

    if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "staff") {
        die("Access denied.");
    }

}