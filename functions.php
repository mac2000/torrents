<?php
require_once 'config.php';

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
    }
    return false;
}

function read_non_empty_lines($filname)
{
    return preg_split('/(\r\n|\n)+/', file_get_contents(__DIR__ . '/' . $filname), -1, PREG_SPLIT_NO_EMPTY);
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

                /* @var $item SimplePie_Item */
                $data = array(
                    'link' => $item->get_link(),
                    'title' => $item->get_title(),
                    'description' => $item->get_description(),
                    'added' => $item->get_date('Y-m-d H:i:s')
                );

                $data['added'] = $data['added'] ? $data['added'] : date('Y-m-d H:i:s', time());

                $stmt = $pdo->prepare("SELECT link FROM items WHERE link = :link");
                $stmt->execute(array('link' => $data['link']));
                if ($stmt->rowCount()) {
                    continue;
                }

                if (filter($data['title'], $stopwords)) {
                    continue;
                }

                if (empty($data['description'])) {
                    $data['description'] = file_get_contents($data['link']);
                }

                if (filter($data['description'], $stopwords)) {
                    continue;
                }

                $data = array_merge($data, get_imdb_info($data['description']));

                if (!isset($data['imdb'])) {
                    continue;
                }

                if ($data['rating'] < 5) {
                    continue;
                }

                if (!$data['poster'] || $data['poster'] == 'N/A') {
                    continue;
                }

                if(!file_exists(__DIR__ . '/images/' . $data['imdb'] . '.' . array_pop(explode('.', $data['poster']))))
                {
                    file_put_contents(__DIR__ . '/images/' . $data['imdb'] . '.' . array_pop(explode('.', $data['poster'])), file_get_contents($data['poster']));
                }
                $data['poster'] = '/images/' . $data['imdb'] . '.' . array_pop(explode('.', $data['poster']));

                unset($data['description']);
                $stmt = $pdo->prepare(
                    "INSERT INTO items
                        (imdb, title, year, poster, rating, link, genres, added)
                        VALUES(:imdb, :title, :year, :poster, :rating, :link, :genres, :added)"
                );
                $stmt->execute($data);

            } catch (Exception $ex) {
                echo $ex->getMessage() . gnl();
            }
        }
    }
}