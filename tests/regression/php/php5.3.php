<?php
function getAdder($x) {
    return function() use ($x) {
        return $x + $y;
    };
}

function getAdder($x) {
    return function($x) use ($x) {
        return $x + $y;
    };
}
