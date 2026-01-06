<?php

function normalize_number($input)
{
  $n = preg_replace('/[^0-9+]/', '', $input);

  if (strpos($n, '+62') === 0) {
    $n = substr($n, 1);
  }

  if (strpos($n, '0') === 0) {
    $n = '62' . substr($n, 1);
  }

  return ltrim($n, '+');
}

function phone_cache_key(string $normalized)
{
    return "num:lookup:" . $normalized;
}
