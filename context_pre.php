<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'chim_ineed.php';

if (!chimINeedIsEnabled()) {
    return;
}

$instructions = chimINeedPromptInstructions();
if ($instructions === '') {
    return;
}

if (function_exists('chimRegisterPromptInjection')) {
    chimRegisterPromptInjection('prompt_bottom', 'chim_ineed.needs_instructions', $instructions, 80);
    return;
}

if (!isset($GLOBALS['HERIKA_PERS'])) {
    $GLOBALS['HERIKA_PERS'] = '';
}
$GLOBALS['HERIKA_PERS'] .= "\n" . $instructions;
