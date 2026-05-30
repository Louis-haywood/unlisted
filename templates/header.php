<?php
// Expects: $page_title (string), $tenant (array)
$page_title = $page_title ?? 'LouVentory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> — LouVentory</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-layout">
