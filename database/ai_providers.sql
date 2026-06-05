-- ============================================================
-- AI Providers schema (separate from the main settings table)
-- Import this AFTER schema.sql.
--
-- Stores API keys, model names and config for every supported
-- AI provider. The admin panel (admin/ai/) manages these rows;
-- the call_ai() helper in includes/functions.php reads from here.
-- ============================================================

CREATE TABLE IF NOT EXISTS ai_providers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    provider_key VARCHAR(50) UNIQUE NOT NULL,   -- machine name: gemini, openai, claude...
    name         VARCHAR(100) NOT NULL,         -- display name: ChatGPT (OpenAI)
    api_type     ENUM('gemini','openai','anthropic') NOT NULL DEFAULT 'openai',
    api_key      TEXT,                           -- secret key (stored in DB, not config)
    model        VARCHAR(100),                   -- default model id for this provider
    endpoint     VARCHAR(255),                   -- base endpoint (OpenAI-compatible providers)
    enabled      TINYINT(1) NOT NULL DEFAULT 0,  -- show/use this provider
    is_default   TINYINT(1) NOT NULL DEFAULT 0,  -- the provider used by default
    last_test    TEXT,                           -- JSON: {ok, at, note} from last test
    sort_order   INT NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the supported providers. api_key stays empty until the admin fills it in.
-- api_type tells call_ai() which request format to use:
--   gemini    -> Google Generative Language API
--   openai    -> OpenAI Chat Completions format (also DeepSeek, OpenRouter, Groq, etc.)
--   anthropic -> Anthropic Messages API (Claude)
INSERT INTO ai_providers
    (provider_key, name, api_type, model, endpoint, enabled, is_default, sort_order)
VALUES
    ('gemini',     'Google Gemini',      'gemini',    'gemini-3.5-flash',          'https://generativelanguage.googleapis.com/v1beta', 1, 1, 1),
    ('openai',     'ChatGPT (OpenAI)',   'openai',    'gpt-4o-mini',               'https://api.openai.com/v1',                         0, 0, 2),
    ('claude',     'Claude (Anthropic)', 'anthropic', 'claude-3-5-sonnet-latest',  'https://api.anthropic.com/v1',                      0, 0, 3),
    ('deepseek',   'DeepSeek',           'openai',    'deepseek-chat',             'https://api.deepseek.com/v1',                       0, 0, 4),
    ('grok',       'Grok (xAI)',         'openai',    'grok-2-latest',             'https://api.x.ai/v1',                               0, 0, 5),
    ('openrouter', 'OpenRouter',         'openai',    'openai/gpt-4o-mini',        'https://openrouter.ai/api/v1',                      0, 0, 6)
ON DUPLICATE KEY UPDATE
    name = VALUES(name);
