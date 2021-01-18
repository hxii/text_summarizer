<?php

$stopwords = explode(PHP_EOL,file_get_contents('stopwords.txt'));

function get_keywords(string $data) {
  global $stopwords;
  $pattern = "/(\b[a-zA-Z'\-]+\b)/"; /* find all words not including numbers or punctuation */
  preg_match_all($pattern, strtolower($data), $keywords);
  $keywords = array_diff($keywords[0], $stopwords); /* remove any stopwords */
  $keywords = array_map('strip_suffix', $keywords); /* remove suffixes */
  $keywords = merge_similar($keywords); /* merge similar keywords */ 
  return $keywords;
}

function strip_suffix(string $data) {
  $suffixes = ['\'s']; /* hurricane's -> hurricane */
  return str_replace($suffixes, '', $data);
}

function score_sentence(string $sentence, array $keywords) {
  $score = 0;
  foreach ($keywords as $keyword=>$frequency) {
    $offset = 0;
    $positions = [];
    while(($pos = stripos($sentence, $keyword, $offset)) !== FALSE) { /* find all positions of keyword in sentence */
      $positions[] = $pos; /* add position to list */
      $offset = $pos + 1; /* advance offset */
    }
    foreach ($positions as $pos) {
      $score += (strlen($sentence) - $pos) * $frequency; /* add score of keyword position */
    }
  }
  return $score;
}


function remove_singular($item) {
  return $item > 2; /* remove any keywords with single occurrence */
}

function merge_similar(array $keywords) {
  foreach ($keywords as $i=>$k) {
      foreach ($keywords as $j=>$k2) {
        /* check similary between keywords */
        similar_text($k, $k2, $perc);
        similar_text($k2, $k, $perc2);
          if ( $j > $i && (max($perc, $perc2) >= 85) ) { /* if the keywords are at least 85% similar, merge them */
              $keywords[$i] = $keywords[$j]; /* by setting the keyword we're checking with the one we found */
          }
      }
  }
  return $keywords;
}

function get_paragraphs(string $data) {
  return explode(PHP_EOL.PHP_EOL, $data); /* get paragraphs which we find by searching for two consecutive line endings */
}

function get_sentences(string $paragraph) {
  $pattern = "/[.?!\n]/"; /* find sentences by punctuation marks or line endings */
  return preg_split($pattern, $paragraph);
}

function get_frequency(array $keywords) {
  $frequency = array_count_values($keywords); /* count all keywords */
  $frequency = array_filter($frequency, 'remove_singular'); /* remove all keywords with single occurrence */
  arsort($frequency); /* sort by frequency descending */
  return $frequency;
}

function summarize($data, $sentence_count = 5) {
  $keywords = get_frequency(get_keywords($data)); /* get array of: 'keyword' => frequency */
  $paragraphs = get_paragraphs($data); /* get text paragraphs */
  $scored_sentences = [];
  foreach ($paragraphs as $i=>$paragraph) {
    $sentences = get_sentences($paragraph); /* get paragraph sentences */
    foreach ($sentences as $j=>$sentence) {
      $sentence = str_replace(PHP_EOL,'', $sentence); /* remove line endings from sentence */
      $score = score_sentence($sentence, $keywords); /* get sentence score based on keywords */
      $scored_sentences[$score*(1/($i+$j+1))] = $sentence; /* adjust sentence score based on distance */
    }
  }
  krsort($scored_sentences); /* sort sentence array based on score descending */
  $out = (array_slice($scored_sentences,0,$sentence_count)); /* get X (5) first sentences */
  return ['summary'=>$out, 'keywords'=>$keywords]; /* return sentences and keywords for text */
}

function show_summary(string $data) {
  $out = summarize($data, 5);
  foreach ($out['summary'] as $item) {
    echo '<p>'.$item.'.</p>';
  }
  echo '<div class="keywords"><strong>The top 10 keywords for this text are: </strong>' .
  implode(',', array_keys(array_slice($out['keywords'], 0, 10))) .
  '</div>';
}

function show_form() {
  ?>
  <form action="" method="POST">
    <textarea name="input" id="input" rows="10" placeholder="Input your text here"></textarea>
    <button>TL;DR This</button>
  </form>
  <?php
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <style>
html {
  font: 22px/1.6 serif;
  color: #333;
}

.container {
  max-width: 82ch;
  margin: 0 auto;
}

.keywords {
  color: #aaa;
}

textarea {
  font: inherit;
  color: inherit;
  border: none;
  width: 100%;
  height: 90vh;
  resize: none;
}

button {
  font: inherit;
  border: none;
  text-decoration: underline;
  cursor: pointer;
}
  </style>
</head>
<body>
<div class="container">
  <?php
    if (isset($_POST['input']) && !empty($_POST['input'])) {
      show_summary($_POST['input']);
    } else {
      show_form();
    }
  ?>
</div>
</body>
</html>
