<?php

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
if (file_exists($enginePath . 'conf.php')) {
    require_once $enginePath . 'conf.php';
}
if (file_exists($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap.php')) {
    require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap.php';
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'chim_ineed.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chimINeedSaveSettings([
        'enabled' => isset($_POST['enabled']),
        'inject_prompt' => isset($_POST['inject_prompt']),
        'allow_eat_action' => isset($_POST['allow_eat_action']),
        'allow_drink_action' => isset($_POST['allow_drink_action']),
    ]);
    $message = 'Settings saved.';
}

$settings = chimINeedGetSettings();

function chimINeedChecked($value): string
{
    return !empty($value) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CHIM-iNeed</title>
    <style>
        body { font-family: Segoe UI, sans-serif; background: #101418; color: #eee; margin: 2rem; max-width: 720px; }
        a { color: #8ec8ff; }
        .card { background: #1b2229; border: 1px solid #2c3640; border-radius: 8px; padding: 1.25rem 1.5rem; }
        label { display: block; margin: 0.75rem 0; }
        button { background: #2f6fed; color: #fff; border: 0; border-radius: 6px; padding: 0.55rem 1rem; cursor: pointer; }
        .ok { color: #9fd89f; }
        code { background: #0d1116; padding: 0.1rem 0.35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>CHIM-iNeed</h1>
    <p>NPC-only. iNeed sets a hunger/thirst marker on followers and can trigger a spoken comment. The player is human and roleplays their own needs.</p>
    <?php if ($message !== ''): ?>
        <p class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post" class="card">
        <label>
            <input type="checkbox" name="enabled" value="1" <?php echo chimINeedChecked($settings['enabled']); ?>>
            Enable iNeed prompt support
        </label>
        <label>
            <input type="checkbox" name="inject_prompt" value="1" <?php echo chimINeedChecked($settings['inject_prompt']); ?>>
            Inject hunger/thirst instructions into CHIM prompts
        </label>
        <label>
            <input type="checkbox" name="allow_eat_action" value="1" <?php echo chimINeedChecked($settings['allow_eat_action']); ?>>
            Keep Eat_Food available for followers
        </label>
        <label>
            <input type="checkbox" name="allow_drink_action" value="1" <?php echo chimINeedChecked($settings['allow_drink_action']); ?>>
            Keep Drink_Water available for followers
        </label>
        <p>
            <button type="submit">Save</button>
        </p>
    </form>
    <div class="card" style="margin-top:1rem;">
        <h2>Game side</h2>
        <p>The Papyrus bridge in iNeed sends a CHIM <code>infoaction</code> plus a <code>chat</code> request when a follower becomes hungry or thirsty. Follower Needs must be enabled in iNeed.</p>
        <p>Optional actions <code>Eat_Food</code> and <code>Drink_Water</code> are shipped as <code>CHIM/ineed_actions.csv</code>.</p>
    </div>
</body>
</html>
