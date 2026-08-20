<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'chim_ineed.php';

if (function_exists('chimINeedRegisterPromptHooks')) {
    chimINeedRegisterPromptHooks();
}
