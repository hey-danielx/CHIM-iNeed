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

function chimINeedTableExists(string $tableName): bool
{
    global $db;
    if (!isset($db)) {
        return false;
    }

    try {
        $escaped = $db->escape($tableName);
        $row = $db->fetchOne("SELECT to_regclass('{$escaped}') AS table_name");
        return is_array($row) && !empty($row['table_name']);
    } catch (Throwable $e) {
        return false;
    }
}

function chimINeedDbReady(): bool
{
    return chimINeedTableExists('plugins.chim_ineed_settings');
}

function chimINeedStateDbReady(): bool
{
    return chimINeedTableExists('plugins.chim_ineed_actor_state');
}

function chimINeedDefaultSettings(): array
{
    return [
        'enabled' => true,
        'inject_prompt' => true,
        'allow_eat_action' => true,
        'allow_drink_action' => true,
        'talk_mention_chance' => 40,
    ];
}

function chimINeedCoerceSetting(string $key, $value)
{
    if ($key === 'talk_mention_chance') {
        return max(0, min(100, intval($value)));
    }
    return chimINeedToBool($value);
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
            $settings[$key] = chimINeedCoerceSetting($key, $row['setting_value'] ?? '');
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
        if ($key === 'talk_mention_chance') {
            $value = (string) chimINeedCoerceSetting($key, $settings[$key] ?? $defaultValue);
        } else {
            $value = !empty($settings[$key]) ? 'true' : 'false';
        }
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

function chimINeedRequestType(): string
{
    $gameRequest = $GLOBALS['gameRequest'] ?? null;
    if (is_array($gameRequest)) {
        return strtolower(trim((string) ($gameRequest[0] ?? '')));
    }
    if (is_string($gameRequest) && $gameRequest !== '') {
        $parts = explode('|', $gameRequest, 2);
        return strtolower(trim($parts[0]));
    }
    return '';
}

function chimINeedRequestData(): string
{
    $gameRequest = $GLOBALS['gameRequest'] ?? null;
    if (is_array($gameRequest)) {
        return trim((string) ($gameRequest[3] ?? ''));
    }
    return is_string($gameRequest) ? $gameRequest : '';
}

function chimINeedCurrentNpcName(): string
{
    $name = trim((string) ($GLOBALS['HERIKA_NAME'] ?? ''));
    if ($name !== '' && strcasecmp($name, 'The Narrator') !== 0) {
        return $name;
    }
    return '';
}

function chimINeedIsPlayerTalkRequest(string $type): bool
{
    if ($type === '') {
        return false;
    }
    if (strpos($type, 'inputtext') === 0) {
        return true;
    }
    return in_array($type, ['talk', 'dialogue', 'playerinput', 'chatinput'], true);
}

function chimINeedIsBoredRequest(string $type): bool
{
    return $type === 'bored' || strpos($type, 'bored') === 0;
}

function chimINeedNormalizeNeed(string $need): string
{
    $need = strtolower(trim($need));
    if (in_array($need, ['thirsty', 'thirst', 'drink'], true)) {
        return 'thirsty';
    }
    if (in_array($need, ['hungry', 'hunger', 'food'], true)) {
        return 'hungry';
    }
    return '';
}

// Copy persisted state to an existing NPC; the plugin table remains the live cache.
function chimINeedStoreNpcPluginState(string $actorName): void
{
    global $db;
    static $supported = null;
    try {
        if ($supported === null) {
            $supported = false;
            $enginePath = (string) ($GLOBALS['ENGINE_PATH'] ?? '');
            $classFile = rtrim($enginePath, '/\\') . '/lib/core/npc_master.class.php';
            if (!class_exists('NpcMaster', false) && $enginePath !== '' && is_file($classFile)) {
                require_once $classFile;
            }
            if (method_exists('NpcMaster', 'setPluginData')) {
                $column = $db->fetchOne("SELECT 1 AS supported FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'core_npc_master'
                    AND column_name = 'plugin_extended_data'");
                $supported = !empty($column['supported']);
            }
        }
        if (!$supported) {
            return;
        }
        // Events carry names, not FormIDs. Never choose between ambiguous profiles.
        $match = $db->fetchOne("SELECT min(id) AS id, count(*) AS matches FROM
            (SELECT id FROM core_npc_master WHERE npc_name = $1 LIMIT 2) candidates", [$actorName]);
        if ((int) ($match['matches'] ?? 0) !== 1) {
            return;
        }
        $row = $db->fetchOne("SELECT to_jsonb(state) - 'actor_name' - 'updated_at' AS data
            FROM plugins.chim_ineed_actor_state state WHERE actor_name = $1", [$actorName]);
        if (empty($row['data'])) {
            return;
        }
        $state = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);

        if (!(new NpcMaster())->setPluginData((int) $match['id'], 'chim_ineed', [
            'actor_name' => $actorName, 'state' => (object) $state, 'updated_at' => gmdate('c'),
        ])) {
            throw new RuntimeException('npc_plugin_state_not_saved');
        }
    } catch (Throwable $e) {
        error_log('[CHIM-iNeed] Could not store NPC plugin state; live cache retained.');
    }
}

function chimINeedUpsertActorNeed(string $actorName, string $need, $noSupplies, bool $clear): void
{
    if (!chimINeedStateDbReady()) {
        return;
    }

    $actorName = trim($actorName);
    $need = chimINeedNormalizeNeed($need);
    if ($actorName === '' || $need === '') {
        return;
    }

    global $db;
    $nameSql = $db->escape($actorName);
    if ($need === 'thirsty') {
        $flagCol = 'thirsty';
        $supplyCol = 'thirsty_no_supplies';
    } else {
        $flagCol = 'hungry';
        $supplyCol = 'hungry_no_supplies';
    }

    $flagSql = $clear ? 'FALSE' : 'TRUE';
    $supplySql = (!$clear && $noSupplies) ? 'TRUE' : 'FALSE';

    $db->execQuery("
        INSERT INTO plugins.chim_ineed_actor_state (
            actor_name, hungry, thirsty, hungry_no_supplies, thirsty_no_supplies, updated_at
        ) VALUES (
            '{$nameSql}', FALSE, FALSE, FALSE, FALSE, CURRENT_TIMESTAMP
        )
        ON CONFLICT (actor_name) DO NOTHING
    ");

    $db->execQuery("
        UPDATE plugins.chim_ineed_actor_state
        SET {$flagCol} = {$flagSql},
            {$supplyCol} = {$supplySql},
            updated_at = CURRENT_TIMESTAMP
        WHERE actor_name = '{$nameSql}'
    ");
    chimINeedStoreNpcPluginState($actorName);
}

function chimINeedGetActorState(string $actorName): array
{
    $empty = [
        'hungry' => false,
        'thirsty' => false,
        'hungry_no_supplies' => false,
        'thirsty_no_supplies' => false,
    ];
    $actorName = trim($actorName);
    if ($actorName === '' || !chimINeedStateDbReady()) {
        return $empty;
    }

    global $db;
    try {
        $nameSql = $db->escape($actorName);
        $row = $db->fetchOne("SELECT hungry, thirsty, hungry_no_supplies, thirsty_no_supplies FROM plugins.chim_ineed_actor_state WHERE actor_name = '{$nameSql}'");
        if (!is_array($row)) {
            return $empty;
        }
        return [
            'hungry' => chimINeedToBool($row['hungry'] ?? false),
            'thirsty' => chimINeedToBool($row['thirsty'] ?? false),
            'hungry_no_supplies' => chimINeedToBool($row['hungry_no_supplies'] ?? false),
            'thirsty_no_supplies' => chimINeedToBool($row['thirsty_no_supplies'] ?? false),
        ];
    } catch (Throwable $e) {
        return $empty;
    }
}

function chimINeedActorHasNeed(array $state): bool
{
    return !empty($state['hungry']) || !empty($state['thirsty']);
}

function chimINeedDescribeState(array $state): string
{
    $parts = [];
    if (!empty($state['hungry'])) {
        $parts[] = !empty($state['hungry_no_supplies']) ? 'hungry and has no food' : 'hungry';
    }
    if (!empty($state['thirsty'])) {
        $parts[] = !empty($state['thirsty_no_supplies']) ? 'thirsty and has nothing to drink' : 'thirsty';
    }
    return implode('; ', $parts);
}

function chimINeedParseNeedPayload(string $data, string $fallbackName): void
{
    $data = trim($data);
    if ($data === '') {
        return;
    }

    if (preg_match('/ineed_state@([^@]+)@(hungry|thirsty)@(0|1|clear)/i', $data, $match)) {
        $clear = strtolower($match[3]) === 'clear';
        chimINeedUpsertActorNeed($match[1], $match[2], $match[3] === '1', $clear);
        return;
    }

    if (preg_match('/^(.+?) is no longer (hungry|thirsty)\.?$/i', $data, $match)) {
        chimINeedUpsertActorNeed($match[1], $match[2], false, true);
        return;
    }

    if (preg_match('/^(.+?) is (hungry)(?: and has no food)?\.?$/i', $data, $match)) {
        $noSupplies = stripos($data, 'no food') !== false;
        chimINeedUpsertActorNeed($match[1], 'hungry', $noSupplies, false);
        return;
    }

    if (preg_match('/^(.+?) is (thirsty)(?: and has nothing to drink)?\.?$/i', $data, $match)) {
        $noSupplies = stripos($data, 'nothing to drink') !== false;
        chimINeedUpsertActorNeed($match[1], 'thirsty', $noSupplies, false);
        return;
    }

    if ($fallbackName !== '' && preg_match('/\bis (hungry|thirsty)\b/i', $data, $match)) {
        $noSupplies = (stripos($data, 'no food') !== false) || (stripos($data, 'nothing to drink') !== false);
        $clear = stripos($data, 'no longer') !== false;
        chimINeedUpsertActorNeed($fallbackName, $match[1], $noSupplies, $clear);
    }
}

function chimINeedIngestCurrentRequest(): void
{
    if (!chimINeedIsEnabled()) {
        return;
    }

    $type = chimINeedRequestType();
    $data = chimINeedRequestData();
    $npc = chimINeedCurrentNpcName();

    if ($type === 'infoaction' || strpos($data, 'ineed_state@') !== false || preg_match('/\bis (?:no longer )?(?:hungry|thirsty)\b/i', $data)) {
        chimINeedParseNeedPayload($data, $npc);
    }
}

function chimINeedPromptInstructions(): string
{
    $settings = chimINeedGetSettings();
    if (empty($settings['enabled']) || empty($settings['inject_prompt'])) {
        return '';
    }

    return trim(<<<'TXT'
This speaker is an NPC. The player is a human and will roleplay their own hunger or thirst; do not invent player need status.

If this NPC's profile or plugin state says they are hungry or thirsty, treat that as current until it is cleared.
They may comment briefly in character, ask for food or water, or use Eat_Food / Drink_Water when those actions are available.
If they are no longer hungry or thirsty, drop that topic.
Never mention iNeed, meters, factions, or other game mechanics.
TXT);
}

function chimINeedActorProfileLine($actorName, $actorType = '', array $context = [])
{
    if (!chimINeedIsEnabled()) {
        return '';
    }

    $actorName = trim((string) $actorName);
    if ($actorName === '') {
        return '';
    }

    $state = chimINeedGetActorState($actorName);
    $description = chimINeedDescribeState($state);
    if ($description !== '') {
        return 'Needs: ' . $description;
    }

    $haystack = '';
    if (isset($context['recent_events']) && is_string($context['recent_events'])) {
        $haystack .= ' ' . $context['recent_events'];
    }
    if (!empty($GLOBALS['gameRequest']) && is_string($GLOBALS['gameRequest'])) {
        $haystack .= ' ' . $GLOBALS['gameRequest'];
    }
    $quoted = preg_quote($actorName, '/');
    if ($haystack !== '' && preg_match('/' . $quoted . ' is (hungry(?: and has no food)?|thirsty(?: and has nothing to drink)?)/i', $haystack, $match)) {
        return 'Needs: ' . strtolower($match[1]);
    }

    return '';
}

function chimINeedTurnInstruction(): string
{
    if (!chimINeedIsEnabled()) {
        return '';
    }

    $npc = chimINeedCurrentNpcName();
    if ($npc === '') {
        return '';
    }

    $state = chimINeedGetActorState($npc);
    if (!chimINeedActorHasNeed($state)) {
        return '';
    }

    $description = chimINeedDescribeState($state);
    $type = chimINeedRequestType();
    $settings = chimINeedGetSettings();
    $chance = (int) ($settings['talk_mention_chance'] ?? 40);

    if (chimINeedIsBoredRequest($type)) {
        return "This is a quiet/bored moment. You are currently {$description}. Make this idle line a short in-character comment about that need. Do not mention mods, meters, or game menus.";
    }

    if (chimINeedIsPlayerTalkRequest($type)) {
        if ($chance <= 0) {
            return '';
        }
        if ($chance < 100 && random_int(1, 100) > $chance) {
            return '';
        }
        return "You are currently {$description}. In this reply, briefly bring that up in character (ask for food or water, or use Eat_Food / Drink_Water if you have it). Keep it short. Do not mention mods, meters, or game menus.";
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
