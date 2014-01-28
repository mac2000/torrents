<?php

function date_class($added)
{
    if (is_string($added) || is_numeric($added)) {
        $added = new DateTime($added);
    }

    $diff = $added->diff(new DateTime('NOW'));

    return 'days-' . $diff->days;
}

function filter($text, $stopwords)
{
    foreach ($stopwords as $stopword) {
        if (stripos($text, $stopword) !== false) {
            return true;
        }
        //if (stripos(urlencode($text), urlencode($stopword)) !== false) {
        //    return true;
        //}
    }
    return false;
}

function read_non_empty_lines($filname)
{
    return file($filname, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

function get_stopwords()
{
    return read_non_empty_lines('stopwords.txt');
}

function get_feeds()
{
    return read_non_empty_lines('feeds.txt');
}

function get_imdb_id($description)
{
    return preg_match('/imdb.*?\/(tt\d+)/si', $description, $matches) ? $matches[1] : null;
}

function get_imdb_info($description)
{
    $imdb_id = get_imdb_id($description);

    if (!$imdb_id) {
        return array();
    }

    $imdb = json_decode(file_get_contents('http://omdbapi.com/?i=' . $imdb_id), true);

    return array(
        'imdb' => isset($imdb['imdbID']) ? $imdb['imdbID'] : null,
        'title' => isset($imdb['Title']) ? $imdb['Title'] : null,
        'year' => isset($imdb['Year']) ? $imdb['Year'] : null,
        'poster' => isset($imdb['Poster']) ? $imdb['Poster'] : null,
        'rating' => isset($imdb['imdbRating']) ? $imdb['imdbRating'] : null,
        'genres' => isset($imdb['Genre']) ? $imdb['Genre'] : null
    );
}

function get_pdo()
{
    $pdo = new PDO('mysql:host=' . MYSQL_HOSTNAME . ';dbname=' . MYSQL_DATABASE, MYSQL_USERNAME, MYSQL_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function is_cli()
{
    return php_sapi_name() === 'cli';
}

function gnl()
{
    return is_cli() ? PHP_EOL : '<br>';
}

function enl()
{
    echo gnl();
}

function image_path($imdb_id, $poster)
{
    return '/images/' . date('Y/m/d') . '/' . $imdb_id . '.' . array_pop(explode('.', $poster));
}

function is_item_already_checked(PDO $pdo, $link)
{
    $stmt = $pdo->prepare("SELECT link FROM items WHERE link = :link");
    $stmt->execute(array('link' => $link));
    return $stmt->rowCount() > 0;
}

function save_item(PDO $pdo, $data)
{
    if (isset($data['description'])) {
        unset($data['description']);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO items
            (imdb, title, year, poster, rating, link, genres, added)
            VALUES(:imdb, :title, :year, :poster, :rating, :link, :genres, :added)"
    );

    $stmt->execute($data);
}

function save_poster($imdb_id, $poster)
{
    $image_path = image_path($imdb_id, $poster);
    if (!file_exists(dirname(__DIR__ . $image_path))) {
        mkdir(dirname(__DIR__ . $image_path), 0775, true);
    }
    if (!file_exists(__DIR__ . $image_path)) {
        file_put_contents(__DIR__ . $image_path, file_get_contents($poster));
    }
    return $image_path;
}

function check_item(SimplePie_Item $item, PDO $pdo, $stopwords = array())
{
    $data = array(
        'link' => $item->get_link(),
        'title' => $item->get_title(),
        'description' => $item->get_description(),
        'added' => $item->get_date('Y-m-d H:i:s')
    );

    $data['added'] = $data['added'] ? $data['added'] : date('Y-m-d H:i:s', time());

    if (is_item_already_checked($pdo, $data['link'])) {
        return;
    }

    if (filter($data['title'], $stopwords)) {
        return;
    }

    if (empty($data['description'])) {
        $data['description'] = @file_get_contents($data['link']);
        //if (stripos($data['description'], 'windows-1251') !== false) {
        //    $data['description'] = @iconv('windows-1251', 'UTF-8', $data['description']);
        //}
    }

    if (filter($data['description'], $stopwords)) {
        return;
    }

    $data = array_merge($data, get_imdb_info($data['description']));

    if (!isset($data['imdb'])) {
        return;
    }

    if ($data['rating'] < 5) {
        return;
    }

    if (!$data['poster'] || $data['poster'] == 'N/A') {
        return;
    }

    $data['poster'] = save_poster($data['imdb'], $data['poster']);

    save_item($pdo, $data);
}

function check_feed($url)
{
    $stopwords = get_stopwords();
    $pdo = get_pdo();
    $feed = new SimplePie();
    $feed->enable_cache(false);

    $feed->set_feed_url($url);
    if ($feed->init()) {
        $feed->handle_content_type();

        foreach ($feed->get_items() as $item) {
            try {
                check_item($item, $pdo, $stopwords);
            } catch (Exception $ex) {
                echo $ex->getMessage() . gnl();
            }
        }
    }
}
