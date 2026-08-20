<?php

function chimINeedJsonEncode($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) && $json !== '' ? $json : '{}';
}

function chimINeedToBool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 't', 'true', 'yes', 'y', 'on', 'enabled'], true);
}

function chimINeedDbReady(): bool
{
    global $db;
    if (!isset($db)) {
        return false;
    }

    try {
        $row = $db->fetchOne("SELECT to_regclass('plugins.chim_ineed_settings') AS table_name");
        return is_array($row) && !empty($row['table_name']);
    } catch (Throwable $e) {
        return false;
    }
}

function chimINeedDefaultSettings(): array
{
    return [
        'enabled' => true,
        'inject_prompt' => true,
        'allow_eat_action' => true,
        'allow_drink_action' => true,
    ];
}

function chimINeedGetSettings(): array
{
    $settings = chimINeedDefaultSettings();
    if (!chimINeedDbReady()) {
        return $settings;
    }

    global $db;
    try {
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM plugins.chim_ineed_settings");
        if (!is_array($rows)) {
            return $settings;
        }
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key === '' || !array_key_exists($key, $settings)) {
                continue;
            }
            $settings[$key] = chimINeedToBool($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        return $settings;
    }

    return $settings;
}

function chimINeedSaveSettings(array $settings): void
{
    if (!chimINeedDbReady()) {
        return;
    }

    global $db;
    $defaults = chimINeedDefaultSettings();
    foreach ($defaults as $key => $defaultValue) {
        $value = !empty($settings[$key]) ? 'true' : 'false';
        $escapedKey = $db->escape($key);
        $escapedValue = $db->escape($value);
        $db->execQuery("
            INSERT INTO plugins.chim_ineed_settings (setting_key, setting_value, updated_at)
            VALUES ('{$escapedKey}', '{$escapedValue}', CURRENT_TIMESTAMP)
            ON CONFLICT (setting_key) DO UPDATE
            SET setting_value = EXCLUDED.setting_value,
                updated_at = CURRENT_TIMESTAMP
        ");
    }
}

function chimINeedIsEnabled(): bool
{
    $settings = chimINeedGetSettings();
    return !empty($settings['enabled']);
}

function chimINeedPromptInstructions(): string
{
    $settings = chimINeedGetSettings();
    if (empty($settings['enabled']) || empty($settings['inject_prompt'])) {
        return '';
    }

    return trim(<<<'TXT'
This speaker is an NPC. The player is a human and will roleplay their own hunger or thirst; do not invent player need status.

If a marker or recent context says this NPC is hungry or thirsty, they may comment briefly in character, ask for food or water, or use Eat_Food / Drink_Water when those actions are available.
If context says they are no longer hungry or thirsty, drop that topic.
Never mention iNeed, meters, factions, or other game mechanics.
TXT);
}

function chimINeedActorProfileLine($actorName, $actorType = '', array $context = [])
{
    if (!chimINeedIsEnabled()) {
        return '';
    }

    $haystack = '';
    if (isset($context['recent_events']) && is_string($context['recent_events'])) {
        $haystack .= ' ' . $context['recent_events'];
    }
    if (!empty($GLOBALS['gameRequest']) && is_string($GLOBALS['gameRequest'])) {
        $haystack .= ' ' . $GLOBALS['gameRequest'];
    }

    $actorName = trim((string) $actorName);
    if ($actorName === '' || $haystack === '') {
        return '';
    }

    $quoted = preg_quote($actorName, '/');
    if (preg_match('/' . $quoted . ' is (hungry(?: and has no food)?|thirsty(?: and has nothing to drink)?)/i', $haystack, $match)) {
        return 'Needs: ' . strtolower($match[1]);
    }

    return '';
}

function chimINeedRegisterPromptHooks(): void
{
    if (!chimINeedIsEnabled()) {
        return;
    }

    $instructions = chimINeedPromptInstructions();
    if ($instructions !== '' && function_exists('chimRegisterPromptInjection')) {
        chimRegisterPromptInjection('prompt_bottom', 'chim_ineed.needs_instructions', $instructions, 80);
    }

    if (function_exists('chimRegisterActorProfileEnricher')) {
        chimRegisterActorProfileEnricher('chim_ineed.actor_needs', 'chimINeedActorProfileLine', 60);
    }
}
