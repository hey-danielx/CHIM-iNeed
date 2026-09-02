CREATE TABLE IF NOT EXISTS plugins.chim_ineed_actor_state (
    actor_name TEXT PRIMARY KEY,
    hungry BOOLEAN NOT NULL DEFAULT FALSE,
    thirsty BOOLEAN NOT NULL DEFAULT FALSE,
    hungry_no_supplies BOOLEAN NOT NULL DEFAULT FALSE,
    thirsty_no_supplies BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO plugins.chim_ineed_settings (setting_key, setting_value)
VALUES ('talk_mention_chance', '40')
ON CONFLICT (setting_key) DO NOTHING;
