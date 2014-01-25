<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TorrentsWatcher</title>
    <link rel="stylesheet" href="styles.css"/>
</head>
<body class="today">
<h1>Torrents<span>Watcher</span></h1>
<?php
require_once 'vendor/autoload.php';
require_once 'functions.php';
require_once 'config.php';

$pdo = get_pdo();

foreach ($pdo->query("SELECT * FROM items ORDER BY added DESC, rating DESC LIMIT 100") as $row):?>

    <a target="_blank" href="<?= $row['link'] ?>" class="item <?= date_class($row['added']) ?>"
       style="background-image:url(<?= $row['poster'] ?>);">
        <span class="outline">
        <span class="year"><?= $row['year'] ?></span>
        <span class="title"><?= $row['title'] ?></span>
        <span class="genres"><?= $row['genres'] ?></span>
        <span class="rating"><?= $row['rating'] ?></span>
        </span>
    </a>

<?php endforeach; ?>

<div id="filter">
    <a data-days="today" href="#" class="active">Today</a>
    <a data-days="yesterday" href="#">Yesterday</a>
    <a data-days="week" href="#">Week</a>
    <a data-days="all" href="#">All</a>
</div>

<script src="scripts.js"></script>
</body>
</html>