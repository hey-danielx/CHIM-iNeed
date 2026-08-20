CREATE SCHEMA IF NOT EXISTS plugins;

CREATE TABLE IF NOT EXISTS plugins.chim_ineed_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO plugins.chim_ineed_settings (setting_key, setting_value)
VALUES
    ('enabled', 'true'),
    ('inject_prompt', 'true'),
    ('allow_eat_action', 'true'),
    ('allow_drink_action', 'true')
ON CONFLICT (setting_key) DO NOTHING;
