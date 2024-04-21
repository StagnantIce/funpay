<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $i = -1;
        return preg_replace_callback(
            '/(\{[^\}]*?)?(\?[fda#]?)([^\{\?]*?\})?/',
            function ($matchers) use (&$i, $args) {
                var_dump($i, $matchers);
                $i++;
                if (count($args) - 1 < $i) {
                    throw new \Exception('Invalid params count');
                }
                if (count($matchers) > 3) {
                    if (is_object($args[$i])) {
                        return '';
                    }
                    return $this->buildQuery(trim($matchers[0],'{}'), [$args[$i]]);
                }
                $type = $matchers[0];
                if ($type === '?#' || $type === '?') {
                    if (is_float($args[$i])) {
                        $type = '?f';
                    } else if (is_numeric($args[$i])) {
                        $type = '?d';
                    } else if (is_array($args[$i])) {
                        $type = '?a';
                    } else if (is_bool($args[$i])) {
                        return intval($args[$i]);
                    } else if (is_null($args[$i])) {
                        return 'NULL';
                    } else if (is_string($args[$i])) {
                        return $type === '?#' ? "`${args[$i]}`" : "'${args[$i]}'";
                    }
                }
                if ($type === '?f') {
                    if (is_null($args[$i])) {
                        return 'NULL';
                    }
                    return floatval($args[$i]);
                }
                if ($type === '?d') {
                    if (is_null($args[$i])) {
                        return 'NULL';
                    }
                    return intval($args[$i]);
                }
                if ($type === '?a') {
                    if (!is_array($args[$i]) || count($args[$i]) === 0) {
                        throw new \Exception("Param ${$i} must be not empty array");
                    }
                    $res = [];
                    foreach ($args[$i] as $k => $v) {
                        if (is_numeric($v)) {
                            // nothing
                        } else if (is_null($v)) {
                            $v = 'NULL';
                        } else if (is_numeric($k)) {
                            $v = "`$v`";
                        } else {
                            $v = "'$v'";
                        }
                        $res[] = is_numeric($k) ? $v : "`$k` = $v";
                    }
                    return implode(', ', $res);
                }
                throw new \Exception("Unknown '${$type}'");
            },
            $query
        );
    }

    public function skip()
    {
        return new \stdClass();
    }
}
