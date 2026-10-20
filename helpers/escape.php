<?php
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}